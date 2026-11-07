<?php

declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

function respond(array $payload): never
{
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);
}

if (!isset($_SESSION['id'])) {
    respond(['error' => 'unauthorized']);
}

require '../../includes/database_include.php';
require_once __DIR__ . '/../../includes/semester_helper.php';

$rawBody = file_get_contents('php://input');
if (empty($rawBody) && php_sapi_name() === 'cli') {
    $rawBody = file_get_contents('php://stdin');
}
$body = json_decode($rawBody, true);

if (!is_array($body)) {
    respond([
        'success' => false,
        'message' => 'Invalid request data.'
    ]);
}

$action    = is_string($body['action'] ?? null) ? trim($body['action']) : '';
$pendingId = filter_var($body['pending_id'] ?? null, FILTER_VALIDATE_INT);


if ($action === 'cancel') {
    if (!$pendingId) {
        respond(['success' => false, 'message' => 'Invalid reservation ID to cancel.']);
    }

    try {
        $pdo = new PDO("mysql:host={$host};dbname={$dbname};charset=utf8mb4", $db_user, $db_pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);

        $stmtDel = $pdo->prepare('DELETE FROM pendingschedule WHERE pending_id = ? AND account_id = ? AND pending_schedule_status = "pending"');
        $stmtDel->execute([$pendingId, $_SESSION['id']]);

        if ($stmtDel->rowCount() > 0) {
            $stmtNotif = $pdo->prepare('INSERT INTO notification (account_id, notif_type, notif_title, notif_message) VALUES (?, "cancelled", "Reservation Withdrawn", ?)');
            $stmtNotif->execute([$_SESSION['id'], "You have withdrawn your pending reservation request #{$pendingId}."]);
            respond(['success' => true, 'action' => 'cancelled', 'message' => 'Pending reservation request withdrawn.']);
        } else {
            respond(['success' => false, 'message' => 'Reservation not found or already processed.']);
        }
    } catch (PDOException $e) {
        respond(['success' => false, 'message' => 'Failed to cancel reservation.']);
    }
}

$roomId = filter_var($body['room_id'] ?? null, FILTER_VALIDATE_INT);
$subject = is_string($body['subject'] ?? null) ? trim($body['subject']) : '';
$start  = is_string($body['start'] ?? null) ? trim($body['start']) : '';
$end    = is_string($body['end'] ?? null) ? trim($body['end']) : '';
$type   = is_string($body['type'] ?? null) ? trim($body['type']) : '';
$date   = is_string($body['date'] ?? null) ? trim($body['date']) : null;
$dow    = is_string($body['dow'] ?? null) ? trim($body['dow']) : null;

$allowedDays = [
    'monday',
    'tuesday',
    'wednesday',
    'thursday',
    'friday',
    'saturday',
    'sunday'
];

function validTime(string $time): bool
{
    $parts = explode(':', $time);
    if (count($parts) >= 2) {
        $h = (int)$parts[0];
        $m = (int)$parts[1];
        return $h >= 0 && $h <= 23 && $m >= 0 && $m <= 59;
    }
    return false;
}

function validDate(?string $date): bool
{
    if ($date === null || $date === '') {
        return false;
    }

    $parsed = DateTime::createFromFormat('!Y-m-d', $date);
    return $parsed !== false && $parsed->format('Y-m-d') === $date;
}

function fmtTime(string $time): string
{
    $parsed = DateTime::createFromFormat('!H:i', $time);

    if ($parsed === false) {
        return '';
    }

    return $parsed->format('g:i A');
}

function isExcludedDeanOffice($room, $hallName = '')
{
    $hall = strtolower(trim((string) ($hallName !== '' ? $hallName : ($room['hall_name'] ?? ($room['name'] ?? '')))));
    $roomLabel = strtolower(trim((string) ($room['room_name'] ?? '')) . ' ' . trim((string) ($room['room_type'] ?? '')));
    $normalizedLabel = preg_replace('/[^a-z0-9]+/', ' ', $roomLabel);
    $isDeanProgramHeadOffice = strpos($normalizedLabel, 'dean') !== false
        && preg_match('/\bprogram head\b/', $normalizedLabel) === 1;
    $isPlaridelDeanRoom = strpos($hall, 'plaridel') !== false
        && strpos($normalizedLabel, 'dean') !== false
        && (preg_match('/\bdean (?:s )?(?:room|office)\b/', $normalizedLabel) === 1
            || preg_match('/\b(?:room|office) dean\b/', $normalizedLabel) === 1);

    return $isDeanProgramHeadOffice || $isPlaridelDeanRoom;
}

if (!$roomId || $start === '' || $end === '' || $type === '') {
    respond([
        'success' => false,
        'message' => 'Missing required fields.'
    ]);
}

if ($subject === '' || mb_strlen($subject) > 150) {
    respond([
        'success' => false,
        'message' => 'Please provide a reservation subject of 150 characters or fewer.'
    ]);
}

if (!in_array($type, ['one-time', 'weekly'], true)) {
    respond([
        'success' => false,
        'message' => 'Invalid reservation type.'
    ]);
}

if (validTime($start) && validTime($end)) {
    $sParts = explode(':', $start);
    $eParts = explode(':', $end);
    $start = sprintf('%02d:%02d', (int)$sParts[0], (int)$sParts[1]);
    $end   = sprintf('%02d:%02d', (int)$eParts[0], (int)$eParts[1]);
}

if (!validTime($start) || !validTime($end) || $start >= $end) {
    respond([
        'success' => false,
        'message' => 'Please provide a valid time range.'
    ]);
}

if ($type === 'one-time') {
    if (!validDate($date)) {
        respond([
            'success' => false,
            'message' => 'Please provide a valid reservation date.'
        ]);
    }

    $dow = null;
} else {
    $normalizedDow = strtolower((string) $dow);

    if (!in_array($normalizedDow, $allowedDays, true)) {
        respond([
            'success' => false,
            'message' => 'Please provide a valid day of the week.'
        ]);
    }

    $dow = ucfirst($normalizedDow);
    $date = null;
}

$untilDate = trim((string) ($data['until'] ?? ($data['until_date'] ?? '')));
if ($type === 'weekly') {
    if (empty($untilDate) || !validDate($untilDate)) {
        $untilDate = getSemesterEndDate();
    }
    $scheduledEndDate = $untilDate;
} else {
    $scheduledEndDate = $date;
}

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    $stmtAcct = $pdo->prepare(
        'SELECT account_id FROM account WHERE account_id = ? LIMIT 1'
    );
    $stmtAcct->execute([$_SESSION['id']]);
    $account = $stmtAcct->fetch();

    if (!$account) {
        respond([
            'success' => false,
            'message' => 'Account not found.'
        ]);
    }

    $accountId = $account['account_id'];

    $stmtRoom = $pdo->prepare(
        'SELECT r.room_name, r.room_type, r.room_status, h.hall_name
         FROM room r
         JOIN hall h ON r.hall_id = h.hall_id
         WHERE r.room_id = ?
         LIMIT 1'
    );
    $stmtRoom->execute([$roomId]);
    $roomRow = $stmtRoom->fetch();

    if (!$roomRow) {
        respond([
            'success' => false,
            'message' => 'Room not found.'
        ]);
    }

    $roomStatus = strtolower(trim((string) ($roomRow['room_status'] ?? '')));
    $roomType = strtolower(trim((string) ($roomRow['room_type'] ?? '')));
    $roomName = strtolower(trim((string) ($roomRow['room_name'] ?? '')));
    if ($roomStatus !== 'available' || strpos($roomType, 'storage') !== false || strpos($roomName, 'storage') !== false) {
        respond([
            'success' => false,
            'message' => (strpos($roomType, 'storage') !== false || strpos($roomName, 'storage') !== false)
                ? 'Storage rooms cannot be reserved.'
                : 'This room is unavailable for reservation.'
        ]);
    }

    if (isExcludedDeanOffice($roomRow)) {
        respond([
            'success' => false,
            'message' => 'This room is not available for user reservations.'
        ]);
    }

    $roomHall = $roomRow['room_name'] . ' (' . $roomRow['hall_name'] . ')';

    if ($type === 'one-time') {
        $dateObject = DateTime::createFromFormat('!Y-m-d', $date);
        $dayLabel = $dateObject->format('M j, Y');
    } else {
        $dayLabel = 'every ' . $dow;
    }

    $timeRange = fmtTime($start) . ' – ' . fmtTime($end);


    if ($type === 'one-time') {
        $dowOfDate = strtolower(
            DateTime::createFromFormat('!Y-m-d', $date)->format('l')
        );

        $stmtConflict = $pdo->prepare(
            'SELECT schedule_start, schedule_end FROM schedule
             WHERE room_id = ?
               AND (
                    (schedule_day = ? AND schedule_start < ? AND schedule_end > ?)
                    OR
                    (schedule_day IS NULL
                     AND LOWER(schedule_day_of_week) = ?
                     AND (schedule_end_date IS NULL OR schedule_end_date >= ?)
                     AND schedule_start < ?
                     AND schedule_end > ?)
               )
             LIMIT 1'
        );
        $stmtConflict->execute([
            $roomId,
            $date,
            $end,
            $start,
            $dowOfDate,
            $date,
            $end,
            $start
        ]);
    } else {
        $stmtConflict = $pdo->prepare(
            'SELECT schedule_start, schedule_end FROM schedule
             WHERE room_id = ?
               AND (
                    (schedule_day IS NULL
                     AND LOWER(schedule_day_of_week) = ?
                     AND (schedule_end_date IS NULL OR schedule_end_date >= ?)
                     AND schedule_start < ?
                     AND schedule_end > ?)
                    OR
                    (schedule_day IS NOT NULL
                     AND schedule_day >= ?
                     AND schedule_day <= ?
                     AND LOWER(DAYNAME(schedule_day)) = ?
                     AND schedule_start < ?
                     AND schedule_end > ?)
               )
             LIMIT 1'
        );
        $stmtConflict->execute([
            $roomId,
            strtolower($dow),
            date('Y-m-d'),
            $end,
            $start,
            date('Y-m-d'),
            $untilDate,
            strtolower($dow),
            $end,
            $start
        ]);
    }

    $conflictRow = $stmtConflict->fetch();

    if (!$conflictRow) {
        if ($type === 'one-time') {
            $pendingConflictStmt = $pdo->prepare(
                'SELECT pending_schedule_start AS schedule_start, pending_schedule_end AS schedule_end
                 FROM pendingschedule
                 WHERE room_id = ?
                   AND pending_schedule_status = "pending"
                   AND pending_id <> ?
                   AND (
                        (pending_schedule_day = ? AND pending_schedule_start < ? AND pending_schedule_end > ?)
                        OR
                        (pending_schedule_day IS NULL
                         AND LOWER(pending_schedule_day_of_week) = ?
                         AND (pending_schedule_end_date IS NULL OR pending_schedule_end_date >= ?)
                         AND pending_schedule_start < ?
                         AND pending_schedule_end > ?)
                   )
                 LIMIT 1'
            );
            $pendingConflictStmt->execute([$roomId, $pendingId ?: 0, $date, $end, $start, $dowOfDate, $date, $end, $start]);
        } else {
            $pendingConflictStmt = $pdo->prepare(
                'SELECT pending_schedule_start AS schedule_start, pending_schedule_end AS schedule_end
                 FROM pendingschedule
                 WHERE room_id = ?
                   AND pending_schedule_status = "pending"
                   AND pending_id <> ?
                   AND (
                        (pending_schedule_day IS NULL
                         AND LOWER(pending_schedule_day_of_week) = ?
                         AND (pending_schedule_end_date IS NULL OR pending_schedule_end_date >= ?)
                         AND pending_schedule_start < ?
                         AND pending_schedule_end > ?)
                        OR
                        (pending_schedule_day IS NOT NULL
                         AND pending_schedule_day >= ?
                         AND pending_schedule_day <= ?
                         AND LOWER(DAYNAME(pending_schedule_day)) = ?
                         AND pending_schedule_start < ?
                         AND pending_schedule_end > ?)
                   )
                 LIMIT 1'
            );
            $pendingConflictStmt->execute([$roomId, $pendingId ?: 0, strtolower($dow), date('Y-m-d'), $end, $start, date('Y-m-d'), $untilDate, strtolower($dow), $end, $start]);
        }
        $conflictRow = $pendingConflictStmt->fetch();
    }

    if ($conflictRow) {
        respond([
            'success' => false,
            'error' => 'conflict',
            'message' => 'This room already has a pending or approved reservation from ' .
                fmtTime((string) $conflictRow['schedule_start']) .
                ' to ' .
                fmtTime((string) $conflictRow['schedule_end']) .
                ' on that time.'
        ]);
    }

    $pdo->beginTransaction();

    if ($pendingId) {
        $stmtCheck = $pdo->prepare(
            'SELECT pending_id, reserve_id FROM pendingschedule WHERE pending_id = ? AND account_id = ? AND pending_schedule_status = "pending" LIMIT 1'
        );
        $stmtCheck->execute([$pendingId, $accountId]);
        $pendingRow = $stmtCheck->fetch();
        if (!$pendingRow) {
            $pdo->rollBack();
            respond([
                'success' => false,
                'message' => 'Pending reservation not found or cannot be modified.'
            ]);
        }

        $reserveId = (int)($pendingRow['reserve_id'] ?? 0);
        if ($reserveId > 0) {
            $pdo->prepare(
                'UPDATE reservedetails SET reserve_title = ? WHERE reserve_id = ?'
            )->execute([$subject, $reserveId]);
        } else {
            $pdo->prepare(
                'INSERT INTO reservedetails (reserve_title, reserve_details) VALUES (?, ?)'
            )->execute([$subject, $subject]);
            $reserveId = (int)$pdo->lastInsertId();
        }

        $stmtUpdate = $pdo->prepare(
            'UPDATE pendingschedule
             SET room_id = ?,
                 reserve_id = ?,
                 pending_schedule_day_of_week = ?,
                 pending_schedule_start = ?,
                 pending_schedule_end = ?,
                 pending_schedule_day = ?,
                 pending_schedule_end_date = ?
             WHERE pending_id = ? AND account_id = ?'
        );
        $stmtUpdate->execute([
            $roomId,
            $reserveId,
            $type === 'weekly' ? $dow : null,
            $start,
            $end,
            $type === 'one-time' ? $date : null,
            $scheduledEndDate,
            $pendingId,
            $accountId
        ]);

        $stmtNotification = $pdo->prepare(
            'INSERT INTO notification
                (account_id, notif_type, notif_title, notif_message)
             VALUES (?, \'pending\', \'Reservation Updated\', ?)'
        );
        $stmtNotification->execute([
            $accountId,
            "Your reservation request #{$pendingId} has been moved to {$roomHall} on {$dayLabel} ({$timeRange}) and is awaiting admin approval."
        ]);

        $pdo->commit();
        respond([
            'success' => true,
            'action' => 'updated',
            'message' => "Reservation #{$pendingId} moved to {$roomHall}!"
        ]);
    } else {
        $stmtDetails = $pdo->prepare(
            'INSERT INTO reservedetails (reserve_title, reserve_details) VALUES (?, ?)'
        );
        $stmtDetails->execute([$subject, $subject]);
        $reserveId = (int)$pdo->lastInsertId();

        $stmtPending = $pdo->prepare(
            'INSERT INTO pendingschedule
                (room_id, account_id, pending_schedule_day_of_week,
                 pending_schedule_start, pending_schedule_end,
                 pending_schedule_day, pending_schedule_end_date,
                 pending_schedule_status, reserve_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, \'pending\', ?)'
        );
        $stmtPending->execute([
            $roomId,
            $accountId,
            $type === 'weekly' ? $dow : null,
            $start,
            $end,
            $type === 'one-time' ? $date : null,
            $scheduledEndDate,
            $reserveId
        ]);

        $stmtNotification = $pdo->prepare(
            'INSERT INTO notification
                (account_id, notif_type, notif_title, notif_message)
             VALUES (?, \'pending\', \'Reservation Submitted\', ?)'
        );
        $stmtNotification->execute([
            $accountId,
            "Your reservation request for {$roomHall} on {$dayLabel} ({$timeRange}) has been submitted and is awaiting admin approval."
        ]);

        $pdo->commit();
        respond(['success' => true, 'action' => 'inserted']);
    }
} catch (PDOException $exception) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'ClassSpace reservation database error [' . $exception->getCode() . ']: ' .
        $exception->getMessage()
    );

    respond([
        'success' => false,
        'message' => 'Unable to submit the reservation right now. Please try again.'
    ]);
}
