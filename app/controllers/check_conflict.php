<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');

function respond(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond([
        'conflict' => false,
        'available' => false,
        'error' => 'method_not_allowed'
    ], 405);
}

if (!isset($_SESSION['id'])) {
    respond([
        'conflict' => false,
        'available' => false,
        'error' => 'unauthorized'
    ], 401);
}

require_once __DIR__ . '/../../includes/database_include.php';

date_default_timezone_set('Asia/Manila');
$todayDate = date('Y-m-d');

$roomId = filter_var($_GET['room_id'] ?? null, FILTER_VALIDATE_INT);
$start  = is_string($_GET['start'] ?? null) ? trim($_GET['start']) : '';
$end    = is_string($_GET['end'] ?? null) ? trim($_GET['end']) : '';
$type   = is_string($_GET['type'] ?? null) ? trim($_GET['type']) : '';
$date   = is_string($_GET['date'] ?? null) ? trim($_GET['date']) : '';
$dow    = is_string($_GET['dow'] ?? null) ? strtolower(trim($_GET['dow'])) : '';

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

function validDate(string $date): bool
{
    $parsed = DateTime::createFromFormat('!Y-m-d', $date);
    return $parsed !== false && $parsed->format('Y-m-d') === $date;
}

function formatTime(string $time): string
{
    $parsed = DateTime::createFromFormat('!H:i:s', $time)
        ?: DateTime::createFromFormat('!H:i', $time);

    return $parsed ? $parsed->format('g:i A') : $time;
}

if (validTime($start) && validTime($end)) {
    $sParts = explode(':', $start);
    $eParts = explode(':', $end);
    $start = sprintf('%02d:%02d', (int)$sParts[0], (int)$sParts[1]);
    $end   = sprintf('%02d:%02d', (int)$eParts[0], (int)$eParts[1]);
}

if (!$roomId || !validTime($start) || !validTime($end) || $start >= $end) {
    respond([
        'conflict' => false,
        'available' => false,
        'error' => 'invalid_time_range',
        'message' => 'A valid start and end time are required.'
    ], 400);
}

if (!in_array($type, ['one-time', 'weekly'], true)) {
    respond([
        'conflict' => false,
        'available' => false,
        'error' => 'invalid_type',
        'message' => 'A valid reservation type is required.'
    ], 400);
}

if ($type === 'one-time' && !validDate($date)) {
    respond([
        'conflict' => false,
        'available' => false,
        'error' => 'invalid_date',
        'message' => 'A valid reservation date is required.'
    ], 400);
}

if ($type === 'weekly' && !in_array($dow, $allowedDays, true)) {
    respond([
        'conflict' => false,
        'available' => false,
        'error' => 'invalid_day',
        'message' => 'A valid day of the week is required.'
    ], 400);
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

    $roomStmt = $pdo->prepare('SELECT room_status, room_type, room_name FROM room WHERE room_id = ? LIMIT 1');
    $roomStmt->execute([$roomId]);
    $room = $roomStmt->fetch();
    $roomStatus = strtolower(trim((string) ($room ? ($room['room_status'] ?? '') : '')));
    $roomType = strtolower(trim((string) ($room ? ($room['room_type'] ?? '') : '')));
    $roomName = strtolower(trim((string) ($room ? ($room['room_name'] ?? '') : '')));
    if (!$room || $roomStatus !== 'available' || strpos($roomType, 'storage') !== false || strpos($roomName, 'storage') !== false) {
        respond([
            'conflict' => false,
            'available' => false,
            'has_suggestions' => false,
            'error' => 'room_unavailable',
            'message' => (strpos($roomType, 'storage') !== false || strpos($roomName, 'storage') !== false)
                ? 'Storage rooms cannot be reserved.'
                : 'This room is unavailable for reservation.'
        ], 409);
    }


    if ($type === 'one-time') {
        $dowOfDate = strtolower(
            DateTime::createFromFormat('!Y-m-d', $date)->format('l')
        );

        $stmt = $pdo->prepare(
            'SELECT schedule_start, schedule_end FROM schedule
             WHERE room_id = ?
               AND (
                    (schedule_day = ?
                     AND schedule_start < ?
                     AND schedule_end > ?)
                    OR
                    (schedule_day IS NULL
                     AND LOWER(schedule_day_of_week) = ?
                     AND schedule_start < ?
                     AND schedule_end > ?)
               )
             LIMIT 1'
        );
        $stmt->execute([
            $roomId,
            $date,
            $end,
            $start,
            $dowOfDate,
            $end,
            $start
        ]);
    } else {

        $stmt = $pdo->prepare(
            'SELECT schedule_start, schedule_end FROM schedule
             WHERE room_id = ?
               AND (
                    (schedule_day IS NULL
                     AND LOWER(schedule_day_of_week) = ?
                     AND schedule_start < ?
                     AND schedule_end > ?)
                    OR
                    (schedule_day IS NOT NULL
                     AND schedule_day >= ?
                     AND LOWER(DAYNAME(schedule_day)) = ?
                     AND schedule_start < ?
                     AND schedule_end > ?)
               )
             ORDER BY schedule_start ASC
             LIMIT 1'
        );
        $stmt->execute([$roomId, $dow, $end, $start, $todayDate, $dow, $end, $start]);
    }

    $conflict = $stmt->fetch();

    if (!$conflict) {
        $pendingId = filter_var($_GET['pending_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
        if ($type === 'one-time') {
            $pendingStmt = $pdo->prepare(
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
                         AND pending_schedule_start < ?
                         AND pending_schedule_end > ?)
                   )
                 LIMIT 1'
            );
            $pendingStmt->execute([$roomId, $pendingId, $date, $end, $start, $dowOfDate, $end, $start]);
        } else {
            $pendingStmt = $pdo->prepare(
                'SELECT pending_schedule_start AS schedule_start, pending_schedule_end AS schedule_end
                 FROM pendingschedule
                 WHERE room_id = ?
                   AND pending_schedule_status = "pending"
                   AND pending_id <> ?
                   AND (
                        (pending_schedule_day IS NULL
                         AND LOWER(pending_schedule_day_of_week) = ?
                         AND pending_schedule_start < ?
                         AND pending_schedule_end > ?)
                        OR
                        (pending_schedule_day IS NOT NULL
                         AND LOWER(DAYNAME(pending_schedule_day)) = ?
                         AND pending_schedule_start < ?
                         AND pending_schedule_end > ?)
                   )
                 LIMIT 1'
            );
            $pendingStmt->execute([$roomId, $pendingId, $dow, $end, $start, $dow, $end, $start]);
        }
        $conflict = $pendingStmt->fetch();
    }

    if ($conflict) {
        respond([
            'conflict' => true,
            'available' => false,
            'has_suggestions' => true,
            'error' => 'conflict',
            'message' => 'This room is already booked from ' .
                formatTime((string) $conflict['schedule_start']) .
                ' to ' .
                formatTime((string) $conflict['schedule_end']) .
                ' on that time.'
        ]);
    }

    respond([
        'conflict' => false,
        'available' => true,
        'has_suggestions' => false,
        'message' => 'The room is available for the selected time.'
    ]);
} catch (PDOException $exception) {
    error_log(
        'ClassSpace conflict API database error [' . $exception->getCode() . ']: ' .
        $exception->getMessage()
    );

    respond([
        'conflict' => false,
        'available' => false,
        'error' => 'database_error',
        'message' => 'Availability could not be checked right now.'
    ], 500);
}
