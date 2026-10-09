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
    respond(['success' => false, 'error' => 'method_not_allowed'], 405);
}

if (!isset($_SESSION['id'])) {
    respond(['success' => false, 'error' => 'unauthorized'], 401);
}

require_once __DIR__ . '/../../includes/database_include.php';

$roomId = filter_var($_GET['room_id'] ?? null, FILTER_VALIDATE_INT);
$start  = is_string($_GET['start'] ?? null) ? trim($_GET['start']) : '';
$end    = is_string($_GET['end'] ?? null) ? trim($_GET['end']) : '';
$type   = is_string($_GET['type'] ?? null) ? trim($_GET['type']) : '';
$date   = is_string($_GET['date'] ?? null) ? trim($_GET['date']) : '';
$dow    = is_string($_GET['dow'] ?? null) ? strtolower(trim($_GET['dow'])) : '';

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

function timeToMins(string $time): int
{
    [$h, $m] = explode(':', $time);
    return ((int)$h) * 60 + ((int)$m);
}

function minsToTime(int $mins): string
{
    $h = intdiv($mins, 60);
    $m = $mins % 60;
    return sprintf('%02d:%02d', $h, $m);
}

function formatTime(string $time): string
{
    $parsed = DateTime::createFromFormat('!H:i:s', $time)
        ?: DateTime::createFromFormat('!H:i', $time);
    return $parsed ? $parsed->format('g:i A') : $time;
}

if (!$roomId || !validTime($start) || !validTime($end) || $start >= $end) {
    respond([
        'success' => false,
        'error' => 'invalid_time_range',
        'message' => 'Valid room ID and time range are required.'
    ], 400);
}

if (!in_array($type, ['one-time', 'weekly'], true)) {
    respond([
        'success' => false,
        'error' => 'invalid_type',
        'message' => 'Valid reservation type is required.'
    ], 400);
}

if ($type === 'one-time') {
    if (!validDate($date)) {
        respond(['success' => false, 'error' => 'invalid_date'], 400);
    }
    $targetDow = strtolower(DateTime::createFromFormat('!Y-m-d', $date)->format('l'));
} else {
    $targetDow = $dow;
}

$reqStartMins = timeToMins($start);
$reqEndMins   = timeToMins($end);
$reqDuration  = $reqEndMins - $reqStartMins;

const OP_START_MINS = 7 * 60;   // 07:00 AM (420 mins)
const OP_END_MINS   = 21 * 60;  // 09:00 PM (1260 mins)
const STEP_MINS     = 30;

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

    // 1. Fetch Target Room Metadata
    $stmtRoom = $pdo->prepare('
        SELECT r.room_id, r.room_name, r.room_capacity, r.room_type, r.room_has_ac, r.room_status,
               h.hall_id, h.hall_name
        FROM room r
        JOIN hall h ON r.hall_id = h.hall_id
        WHERE r.room_id = ?
        LIMIT 1
    ');
    $stmtRoom->execute([$roomId]);
    $targetRoom = $stmtRoom->fetch();

    if (!$targetRoom) {
        respond(['success' => false, 'error' => 'room_not_found'], 404);
    }

    $targetCap   = (int)$targetRoom['room_capacity'];
    $targetHall  = (int)$targetRoom['hall_id'];
    $targetHasAc = (int)$targetRoom['room_has_ac'];
    $targetType  = (string)$targetRoom['room_type'];

    // 2. Retrieve Busy Intervals on the Target Room
    if ($type === 'one-time') {
        $stmtBookings = $pdo->prepare('
            SELECT schedule_start, schedule_end FROM schedule
            WHERE room_id = ? AND (
                (schedule_day = ?)
                OR (schedule_day IS NULL AND LOWER(schedule_day_of_week) = ?)
            )
            UNION
            SELECT pending_schedule_start AS schedule_start, pending_schedule_end AS schedule_end
            FROM pendingschedule
            WHERE room_id = ? AND pending_schedule_status = "pending" AND (
                (pending_schedule_day = ?)
                OR (pending_schedule_day IS NULL AND LOWER(pending_schedule_day_of_week) = ?)
            )
            ORDER BY schedule_start ASC
        ');
        $stmtBookings->execute([$roomId, $date, $targetDow, $roomId, $date, $targetDow]);
    } else {
        $stmtBookings = $pdo->prepare('
            SELECT schedule_start, schedule_end FROM schedule
            WHERE room_id = ? AND (
                (schedule_day IS NULL AND LOWER(schedule_day_of_week) = ?)
                OR (schedule_day IS NOT NULL AND schedule_day >= CURDATE() AND LOWER(DAYNAME(schedule_day)) = ?)
            )
            UNION
            SELECT pending_schedule_start AS schedule_start, pending_schedule_end AS schedule_end
            FROM pendingschedule
            WHERE room_id = ? AND pending_schedule_status = "pending" AND (
                (pending_schedule_day IS NULL AND LOWER(pending_schedule_day_of_week) = ?)
                OR (pending_schedule_day IS NOT NULL AND pending_schedule_day >= CURDATE() AND LOWER(DAYNAME(pending_schedule_day)) = ?)
            )
            ORDER BY schedule_start ASC
        ');
        $stmtBookings->execute([$roomId, $targetDow, $targetDow, $roomId, $targetDow, $targetDow]);
    }

    $bookings = $stmtBookings->fetchAll();

    // Convert to minute intervals and merge overlaps
    $busy = [];
    foreach ($bookings as $b) {
        $bs = timeToMins(substr((string)$b['schedule_start'], 0, 5));
        $be = timeToMins(substr((string)$b['schedule_end'], 0, 5));
        if ($be > $bs) {
            $busy[] = ['start' => $bs, 'end' => $be];
        }
    }

    usort($busy, fn($a, $b) => $a['start'] <=> $b['start']);
    $mergedBusy = [];
    foreach ($busy as $interval) {
        if (empty($mergedBusy)) {
            $mergedBusy[] = $interval;
            continue;
        }
        $lastIdx = count($mergedBusy) - 1;
        if ($interval['start'] <= $mergedBusy[$lastIdx]['end']) {
            $mergedBusy[$lastIdx]['end'] = max($mergedBusy[$lastIdx]['end'], $interval['end']);
        } else {
            $mergedBusy[] = $interval;
        }
    }

    // Compute Free Gaps within operating hours (07:00 to 21:00)
    $freeGaps = [];
    $cur = OP_START_MINS;
    foreach ($mergedBusy as $block) {
        if ($block['start'] > $cur) {
            $gapStart = max(OP_START_MINS, $cur);
            $gapEnd   = min(OP_END_MINS, $block['start']);
            if ($gapEnd > $gapStart) {
                $freeGaps[] = ['start' => $gapStart, 'end' => $gapEnd];
            }
        }
        $cur = max($cur, $block['end']);
    }
    if ($cur < OP_END_MINS) {
        $freeGaps[] = ['start' => max(OP_START_MINS, $cur), 'end' => OP_END_MINS];
    }

    $candidates = [];

    // PHASE 1: Find Alternative Slots in the SAME Room
    foreach ($freeGaps as $gap) {
        $gapDuration = $gap['end'] - $gap['start'];
        if ($gapDuration >= $reqDuration) {
            for ($s = $gap['start']; $s + $reqDuration <= $gap['end']; $s += STEP_MINS) {
                $candEnd = $s + $reqDuration;
                // Exclude the requested slot if it's somehow in the free gaps (though we only suggest on conflict)
                if ($s === $reqStartMins && $candEnd === $reqEndMins) {
                    continue;
                }

                $timeDiff = abs($s - $reqStartMins);
                // Heuristic penalty for time drift (1 point per minute)
                $penalty = $timeDiff * 1.0;

                $sTime = minsToTime($s);
                $eTime = minsToTime($candEnd);

                $candidates[] = [
                    'type'          => 'alternative_slot',
                    'room_id'       => (int)$targetRoom['room_id'],
                    'room_name'     => $targetRoom['room_name'],
                    'hall_id'       => (int)$targetRoom['hall_id'],
                    'hall_name'     => $targetRoom['hall_name'],
                    'room_type'     => $targetRoom['room_type'],
                    'room_has_ac'   => (int)$targetRoom['room_has_ac'],
                    'room_capacity' => $targetCap,
                    'start'         => $sTime,
                    'end'           => $eTime,
                    'formatted_time'=> formatTime($sTime) . ' – ' . formatTime($eTime),
                    'penalty'       => $penalty,
                    'reason'        => "Same room ({$targetRoom['room_name']}), available " .
                                       ($s < $reqStartMins ? 'earlier in the day.' : 'after earlier reservations.')
                ];
            }
        }
    }

    // PHASE 2: Find Alternative Rooms for the EXACT Requested Time (Constraint Relaxation)
    $stmtPeerRooms = $pdo->prepare('
        SELECT r.room_id, r.room_name, r.room_capacity, r.room_type, r.room_has_ac, r.room_status,
               h.hall_id, h.hall_name
        FROM room r
        JOIN hall h ON r.hall_id = h.hall_id
        WHERE r.room_status = "available"
          AND r.room_id != ?
          AND r.room_capacity >= ?
        ORDER BY (r.hall_id = ?) DESC, r.room_capacity ASC
        LIMIT 15
    ');
    // Accept rooms within 80% of capacity threshold
    $minCapThreshold = max(10, (int)($targetCap * 0.8));
    $stmtPeerRooms->execute([$roomId, $minCapThreshold, $targetHall]);
    $peerRooms = $stmtPeerRooms->fetchAll();

    foreach ($peerRooms as $peer) {
        $peerId = (int)$peer['room_id'];

        // Verify if peer room has conflicts during [reqStart, reqEnd]
        if ($type === 'one-time') {
            $stmtPeerConflict = $pdo->prepare('
                SELECT schedule_id FROM schedule
                WHERE room_id = ? AND (
                    (schedule_day = ? AND schedule_start < ? AND schedule_end > ?)
                    OR (schedule_day IS NULL AND LOWER(schedule_day_of_week) = ? AND schedule_start < ? AND schedule_end > ?)
                )
                UNION
                SELECT pending_id AS schedule_id FROM pendingschedule
                WHERE room_id = ? AND pending_schedule_status = "pending" AND (
                    (pending_schedule_day = ? AND pending_schedule_start < ? AND pending_schedule_end > ?)
                    OR (pending_schedule_day IS NULL AND LOWER(pending_schedule_day_of_week) = ? AND pending_schedule_start < ? AND pending_schedule_end > ?)
                )
                LIMIT 1
            ');
            $stmtPeerConflict->execute([
                $peerId, $date, $end, $start, $targetDow, $end, $start,
                $peerId, $date, $end, $start, $targetDow, $end, $start
            ]);
        } else {
            $stmtPeerConflict = $pdo->prepare('
                SELECT schedule_id FROM schedule
                WHERE room_id = ? AND (
                    (schedule_day IS NULL AND LOWER(schedule_day_of_week) = ? AND schedule_start < ? AND schedule_end > ?)
                    OR (schedule_day IS NOT NULL AND schedule_day >= CURDATE() AND LOWER(DAYNAME(schedule_day)) = ? AND schedule_start < ? AND schedule_end > ?)
                )
                UNION
                SELECT pending_id AS schedule_id FROM pendingschedule
                WHERE room_id = ? AND pending_schedule_status = "pending" AND (
                    (pending_schedule_day IS NULL AND LOWER(pending_schedule_day_of_week) = ? AND pending_schedule_start < ? AND pending_schedule_end > ?)
                    OR (pending_schedule_day IS NOT NULL AND pending_schedule_day >= CURDATE() AND LOWER(DAYNAME(pending_schedule_day)) = ? AND pending_schedule_start < ? AND pending_schedule_end > ?)
                )
                LIMIT 1
            ');
            $stmtPeerConflict->execute([
                $peerId, $targetDow, $end, $start, $targetDow, $end, $start,
                $peerId, $targetDow, $end, $start, $targetDow, $end, $start
            ]);
        }

        $hasConflict = (bool)$stmtPeerConflict->fetch();
        if ($hasConflict) {
            continue; // Peer room is occupied during requested time
        }

        // Heuristic Scoring for Alternative Room
        $diffHall = ((int)$peer['hall_id'] !== $targetHall) ? 60.0 : 0.0;
        $diffCap  = max(0, ((int)$peer['room_capacity'] - $targetCap)) * 0.5;
        $diffAc   = ($targetHasAc && !(int)$peer['room_has_ac']) ? 40.0 : 0.0;
        $diffType = (strtolower((string)$peer['room_type']) !== strtolower($targetType)) ? 25.0 : 0.0;

        $penalty = $diffHall + $diffCap + $diffAc + $diffType;

        $reason = "Available at your exact requested time in " .
                  ($diffHall === 0.0 ? "the same hall ({$peer['hall_name']})" : "{$peer['hall_name']}") .
                  " with {$peer['room_capacity']} seats" .
                  ((int)$peer['room_has_ac'] ? " and air conditioning." : ".");

        $candidates[] = [
            'type'          => 'alternative_room',
            'room_id'       => $peerId,
            'room_name'     => $peer['room_name'],
            'hall_id'       => (int)$peer['hall_id'],
            'hall_name'     => $peer['hall_name'],
            'room_type'     => $peer['room_type'],
            'room_has_ac'   => (int)$peer['room_has_ac'],
            'room_capacity' => (int)$peer['room_capacity'],
            'start'         => $start,
            'end'           => $end,
            'formatted_time'=> formatTime($start) . ' – ' . formatTime($end),
            'penalty'       => $penalty,
            'reason'        => $reason
        ];
    }

    // PHASE 3: Sort Candidates by Heuristic Penalty
    usort($candidates, function ($a, $b) {
        return $a['penalty'] <=> $b['penalty'];
    });

    // Ensure diverse top recommendations: prioritize at least one alternative room and one alternative slot if available
    $finalSuggestions = [];
    $hasRoom = false;
    $hasSlot = false;

    foreach ($candidates as $cand) {
        if (count($finalSuggestions) >= 3) {
            break;
        }
        if ($cand['type'] === 'alternative_room' && !$hasRoom) {
            $hasRoom = true;
            $finalSuggestions[] = $cand;
        } elseif ($cand['type'] === 'alternative_slot' && !$hasSlot) {
            $hasSlot = true;
            $finalSuggestions[] = $cand;
        } elseif (count($finalSuggestions) < 3) {
            $finalSuggestions[] = $cand;
        }
    }

    // Re-assign ranks 1..N
    foreach ($finalSuggestions as $idx => &$sug) {
        $sug['rank'] = $idx + 1;
        unset($sug['penalty']); // Internal metric not needed on client
    }
    unset($sug);

    // PHASE 4: Synthesize Contextual Summary
    $originalDesc = "{$targetRoom['room_name']} ({$targetRoom['hall_name']})";
    $timeDesc = formatTime($start) . ' to ' . formatTime($end);
    $count = count($finalSuggestions);

    if ($count > 0) {
        $aiSummary = "{$originalDesc} is booked during {$timeDesc}. " .
                     "The ClassSpace Constraint Satisfaction engine analyzed operating hours (7:00 AM – 9:00 PM) and found {$count} optimal alternatives.";
    } else {
        $aiSummary = "{$originalDesc} is booked during {$timeDesc}, and no immediate alternative rooms or slots were found for this day. Please check an adjacent date.";
    }

    respond([
        'success'           => true,
        'conflict_detected' => true,
        'original_request'  => [
            'room_id'   => $roomId,
            'room_name' => $targetRoom['room_name'],
            'hall_name' => $targetRoom['hall_name'],
            'time'      => $timeDesc,
            'date'      => $type === 'one-time' ? $date : "Every {$targetDow}"
        ],
        'ai_summary'        => $aiSummary,
        'suggestions'       => $finalSuggestions
    ]);

} catch (PDOException $e) {
    error_log("ClassSpace AI CSP Controller error: " . $e->getMessage());
    respond([
        'success' => false,
        'error'   => 'database_error',
        'message' => 'Failed to compute slot suggestions.'
    ], 500);
}
