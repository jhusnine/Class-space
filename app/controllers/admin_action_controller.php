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

if (!$pendId || !in_array($action, ["approve", "reject"])) {
    echo json_encode(["success" => false, "error" => "invalid_params"]);
    exit;
}

function fmtTime($t) {
    if (!$t) return '';
    [$h, $m] = explode(':', $t);
    $h = intval($h);
    return ($h % 12 ?: 12) . ':' . $m . ' ' . ($h >= 12 ? 'PM' : 'AM');
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Verify the acting user is an admin
    $stmtAdmin = $pdo->prepare("SELECT account_is_admin FROM account WHERE account_id = ? LIMIT 1");
    $stmtAdmin->execute([intval($_SESSION["id"])]);
    $admin = $stmtAdmin->fetch(PDO::FETCH_ASSOC);
    if (!$admin || !$admin["account_is_admin"]) {
        echo json_encode(["success" => false, "error" => "not_admin"]);
        exit;
    }

    // Fetch pending row with room + hall name for the notification message
    $stmtPend = $pdo->prepare("
        SELECT pend.*, r.room_name, h.hall_name
        FROM pendingschedule pend
        JOIN room r ON pend.room_id  = r.room_id
        JOIN hall h ON r.hall_id  = h.hall_id
        WHERE pend.pending_id = ?
        LIMIT 1
    ");
    $stmtPend->execute([$pendId]);
    $pend = $stmtPend->fetch(PDO::FETCH_ASSOC);

    if (!$pend) {
        echo json_encode(["success" => false, "error" => "not_found"]);
        exit;
    }

    // Build readable labels
    $roomHall = "{$pend['room_name']} ({$pend['hall_name']})";
    $dayLabel = $pend['pending_schedule_day']
        ? date('M j, Y', strtotime($pend['pending_schedule_day']))
        : "every {$pend['pending_schedule_day_of_week']}";
    $timeRange = fmtTime($pend['pending_schedule_start']) . ' – ' . fmtTime($pend['pending_schedule_end']);

    // ==================== SERVER-SIDE CONFLICT RE-CHECK (NEW, approval only) ====================
    // Re-checks this pending request against the approved `schedule` table
    // right before approval, in case another request for the same
    // room/date/time was approved earlier while this one was still pending.
    if ($action === "approve") {
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
            echo json_encode([
                "success" => false,
                "error"   => "conflict",
                "message" => "Cannot approve: {$roomHall} is already booked from " .
                             fmtTime($conflictRow['schedule_start']) . " to " .
                             fmtTime($conflictRow['schedule_end']) . " on that day. " .
                             "Please reject this request or ask the user to modify it."
            ]);
            exit;
        }
    }
    // ==================== END CONFLICT RE-CHECK ====================

    $pdo->beginTransaction();

    if ($action === "approve") {

        // 1. Copy to schedule table
        $pdo->prepare("
            INSERT INTO schedule
                (room_id, account_id, schedule_day_of_week, schedule_start, schedule_end, schedule_day)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([
            $pend["room_id"],
            $pend["account_id"],
            $pend["pending_schedule_day_of_week"],
            $pend["pending_schedule_start"],
            $pend["pending_schedule_end"],
            $pend["pending_schedule_day"]
        ]);

        // 2. Remove from pending
        $pdo->prepare("DELETE FROM pendingschedule WHERE pending_id = ?")->execute([$pendId]);

        // 3. Notify the user: approved
        $pdo->prepare("
            INSERT INTO notification (account_id, notif_type, notif_title, notif_message)
            VALUES (?, 'approved', 'Reservation Approved', ?)
        ")->execute([
            $pend["account_id"],
            "Your reservation for {$roomHall} on {$dayLabel} ({$timeRange}) has been approved."
        ]);

        $pdo->commit();
        echo json_encode(["success" => true, "message" => "Reservation approved."]);

    } else {

        // 1. Mark as rejected in pending  ✅ FIXED: was "schedET" instead of "SET"
        $pdo->prepare("
            UPDATE pendingschedule SET pending_schedule_status = 'rejected' WHERE pending_id = ?
        ")->execute([$pendId]);

        // 2. Notify the user: declined
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