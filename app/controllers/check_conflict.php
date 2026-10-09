<?php

declare(strict_types=1);

session_start();
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

require '../../includes/database_include.php';

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
    $parsed = DateTime::createFromFormat('!H:i', $time);
    return $parsed !== false && $parsed->format('H:i') === $time;
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

    /* Preserve the existing overlap logic for one-time reservations. */
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
        /* Check both recurring weekly schedules AND upcoming one-time reservations on this weekday */
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
                     AND schedule_day >= CURDATE()
                     AND LOWER(DAYNAME(schedule_day)) = ?
                     AND schedule_start < ?
                     AND schedule_end > ?)
               )
             ORDER BY schedule_start ASC
             LIMIT 1'
        );
        $stmt->execute([$roomId, $dow, $end, $start, $dow, $end, $start]);
    }

    $conflict = $stmt->fetch();

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
