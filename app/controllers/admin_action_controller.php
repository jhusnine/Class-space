<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION["id"])) {
    echo json_encode(["success" => false, "error" => "unauthorized"]);
    exit;
}
require "../../includes/database_include.php";

$body   = json_decode(file_get_contents("php://input"), true);
$pendId = intval($body["pending_id"] ?? 0);
$action = $body["action"] ?? '';

if (!$pendId || !in_array($action, ["approve", "reject"], true)) {
    echo json_encode(["success" => false, "error" => "invalid_params"]);
    exit;
}

function fmtTime($t) {
    if (!$t) return '';
    [$h, $m] = explode(':', $t);
    $h = intval($h);
    return ($h % 12 ?: 12) . ':' . $m . ' ' . ($h >= 12 ? 'PM' : 'AM');
}

function normalizeWeekday($value) {
    return strtolower(trim((string)$value));
}

function pendingRequestConflictsWithApproval(array $request, array $approved): bool {
    $requestStart = (string)($request['pending_schedule_start'] ?? '');
    $requestEnd = (string)($request['pending_schedule_end'] ?? '');
    $approvedStart = (string)($approved['pending_schedule_start'] ?? '');
    $approvedEnd = (string)($approved['pending_schedule_end'] ?? '');

    if ($requestStart === '' || $requestEnd === '' || $approvedStart === '' || $approvedEnd === '') {
        return false;
    }
    if (!($requestStart < $approvedEnd && $requestEnd > $approvedStart)) {
        return false;
    }

    $approvedDate = trim((string)($approved['pending_schedule_day'] ?? ''));
    $requestDate = trim((string)($request['pending_schedule_day'] ?? ''));

    if ($approvedDate !== '') {
        $approvedWeekday = normalizeWeekday(date('l', strtotime($approvedDate)));
        if ($requestDate !== '') {
            return $requestDate === $approvedDate;
        }
        return normalizeWeekday($request['pending_schedule_day_of_week'] ?? '') === $approvedWeekday;
    }

    $approvedWeekday = normalizeWeekday($approved['pending_schedule_day_of_week'] ?? '');
    if ($requestDate !== '') {
        return $requestDate >= date('Y-m-d')
            && normalizeWeekday(date('l', strtotime($requestDate))) === $approvedWeekday;
    }
    return normalizeWeekday($request['pending_schedule_day_of_week'] ?? '') === $approvedWeekday;
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Verify the acting user is an admin.
    $stmtAdmin = $pdo->prepare("SELECT account_is_admin FROM account WHERE account_id = ? LIMIT 1");
    $stmtAdmin->execute([intval($_SESSION["id"])]);
    $admin = $stmtAdmin->fetch(PDO::FETCH_ASSOC);
    if (!$admin || !$admin["account_is_admin"]) {
        http_response_code(403);
        echo json_encode(["success" => false, "error" => "not_admin"]);
        exit;
    }

    // Lock the request while checking conflicts and changing reservation state.
    $pdo->beginTransaction();
    $stmtRoomId = $pdo->prepare("SELECT room_id FROM pendingschedule WHERE pending_id = ? LIMIT 1");
    $stmtRoomId->execute([$pendId]);
    $lockedRoomId = $stmtRoomId->fetchColumn();
    if ($lockedRoomId === false) {
        $pdo->rollBack();
        echo json_encode(["success" => false, "error" => "not_found"]);
        exit;
    }
    $stmtRoomLock = $pdo->prepare("SELECT room_id FROM room WHERE room_id = ? FOR UPDATE");
    $stmtRoomLock->execute([$lockedRoomId]);
    $stmtRoomLock->fetchColumn();

    $stmtPend = $pdo->prepare("
        SELECT pend.*, r.room_name, h.hall_name
        FROM pendingschedule pend
        JOIN room r ON pend.room_id = r.room_id
        JOIN hall h ON r.hall_id = h.hall_id
        WHERE pend.pending_id = ?
        LIMIT 1
        FOR UPDATE
    ");
    $stmtPend->execute([$pendId]);
    $pend = $stmtPend->fetch(PDO::FETCH_ASSOC);

    if (!$pend) {
        $pdo->rollBack();
        echo json_encode(["success" => false, "error" => "not_found"]);
        exit;
    }
    if (strtolower((string)($pend['pending_schedule_status'] ?? 'pending')) !== 'pending') {
        $pdo->rollBack();
        echo json_encode(["success" => false, "error" => "not_pending"]);
        exit;
    }

    $roomHall = "{$pend['room_name']} ({$pend['hall_name']})";
    $dayLabel = $pend['pending_schedule_day']
        ? date('M j, Y', strtotime($pend['pending_schedule_day']))
        : "every {$pend['pending_schedule_day_of_week']}";
    $timeRange = fmtTime($pend['pending_schedule_start']) . ' – ' . fmtTime($pend['pending_schedule_end']);

    if ($action === "approve") {
        // Re-check the selected time against already approved schedules.
        if ($pend['pending_schedule_day']) {
            $dowOfDate = strtolower(date('l', strtotime($pend['pending_schedule_day'])));
            $stmtConflict = $pdo->prepare("
                SELECT schedule_start, schedule_end FROM schedule
                WHERE room_id = ?
                  AND (
                    (schedule_day = ? AND schedule_start < ? AND schedule_end > ?)
                    OR
                    (schedule_day IS NULL AND LOWER(schedule_day_of_week) = ? AND schedule_start < ? AND schedule_end > ?)
                  )
                LIMIT 1
            ");
            $stmtConflict->execute([
                $pend['room_id'], $pend['pending_schedule_day'],
                $pend['pending_schedule_end'], $pend['pending_schedule_start'],
                $dowOfDate, $pend['pending_schedule_end'], $pend['pending_schedule_start']
            ]);
        } else {
            $stmtConflict = $pdo->prepare("
                SELECT schedule_start, schedule_end FROM schedule
                WHERE room_id = ?
                  AND schedule_day IS NULL
                  AND LOWER(schedule_day_of_week) = ?
                  AND schedule_start < ? AND schedule_end > ?
                LIMIT 1
            ");
            $stmtConflict->execute([
                $pend['room_id'], strtolower($pend['pending_schedule_day_of_week']),
                $pend['pending_schedule_end'], $pend['pending_schedule_start']
            ]);
        }
        $conflictRow = $stmtConflict->fetch(PDO::FETCH_ASSOC);

        if ($conflictRow) {
            $pdo->rollBack();
            echo json_encode([
                "success" => false,
                "error" => "conflict",
                "message" => "Cannot approve: {$roomHall} is already booked from " .
                    fmtTime($conflictRow['schedule_start']) . " to " .
                    fmtTime($conflictRow['schedule_end']) . " on that day. Please reject this request or ask the user to modify it."
            ]);
            exit;
        }

        // Create approved reservation for the selected request.
        $pdo->prepare("
            INSERT INTO schedule
                (room_id, account_id, schedule_day_of_week, schedule_start, schedule_end, schedule_day)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([
            $pend["room_id"], $pend["account_id"], $pend["pending_schedule_day_of_week"],
            $pend["pending_schedule_start"], $pend["pending_schedule_end"], $pend["pending_schedule_day"]
        ]);

        $pdo->prepare("DELETE FROM pendingschedule WHERE pending_id = ?")->execute([$pendId]);

        $pdo->prepare("
            INSERT INTO notification (account_id, notif_type, notif_title, notif_message)
            VALUES (?, 'approved', 'Reservation Approved', ?)
        ")->execute([
            $pend["account_id"],
            "Your reservation for {$roomHall} on {$dayLabel} ({$timeRange}) has been approved."
        ]);

        // Other users' overlapping pending requests are no longer actionable.
        // Keep their database rows as rejected history and tell each affected user to reserve another slot.
        $stmtCandidates = $pdo->prepare("
            SELECT pending_id, account_id, pending_schedule_day_of_week,
                   pending_schedule_start, pending_schedule_end, pending_schedule_day
            FROM pendingschedule
            WHERE room_id = ?
              AND pending_schedule_status = 'pending'
              AND pending_id <> ?
              AND account_id <> ?
            FOR UPDATE
        ");
        $stmtCandidates->execute([$pend['room_id'], $pendId, $pend['account_id']]);
        $affectedByAccount = [];
        $rejectOther = $pdo->prepare("
            UPDATE pendingschedule
            SET pending_schedule_status = 'rejected'
            WHERE pending_id = ? AND pending_schedule_status = 'pending'
        ");
        while ($candidate = $stmtCandidates->fetch(PDO::FETCH_ASSOC)) {
            if (!pendingRequestConflictsWithApproval($candidate, $pend)) {
                continue;
            }
            $rejectOther->execute([$candidate['pending_id']]);
            if ($rejectOther->rowCount() > 0) {
                $affectedByAccount[(string)$candidate['account_id']] = true;
            }
        }

        $notifyAffected = $pdo->prepare("
            INSERT INTO notification (account_id, notif_type, notif_title, notif_message)
            VALUES (?, 'declined', 'Room No Longer Available', ?)
        ");
        $message = "The reservation for {$roomHall} on {$dayLabel} ({$timeRange}) was approved for another user. Your overlapping pending request is no longer available and has been declined. Please choose another room or time and submit a new reservation.";
        foreach (array_keys($affectedByAccount) as $affectedAccountId) {
            $notifyAffected->execute([(int)$affectedAccountId, $message]);
        }

        $pdo->commit();
        echo json_encode([
            "success" => true,
            "message" => "Reservation approved. Conflicting pending requests were declined and their users were notified.",
            "affected_users" => count($affectedByAccount)
        ]);
    } else {
        $pdo->prepare("
            UPDATE pendingschedule SET pending_schedule_status = 'rejected' WHERE pending_id = ?
        ")->execute([$pendId]);

        $pdo->prepare("
            INSERT INTO notification (account_id, notif_type, notif_title, notif_message)
            VALUES (?, 'declined', 'Reservation Declined', ?)
        ")->execute([
            $pend["account_id"],
            "Your reservation request for {$roomHall} on {$dayLabel} ({$timeRange}) has been declined."
        ]);

        $pdo->commit();
        echo json_encode(["success" => true, "message" => "Reservation rejected."]);
    }
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(["success" => false, "error" => "db_error", "message" => $e->getMessage()]);
}
?>
