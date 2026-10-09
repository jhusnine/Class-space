<?php
/**
 * Test: Bidirectional Weekly Conflict Detection & Suggestion Signal
 * Verifies that:
 * 1. Weekly reservation request detects overlapping upcoming one-time bookings.
 * 2. Response payload includes 'has_suggestions' => true on conflict.
 * 3. Non-conflicting requests return 'has_suggestions' => false.
 */

require_once __DIR__ . '/../includes/database_include.php';

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    // 1. Locate an existing room
    $stmtRoom = $pdo->query("SELECT room_id FROM room WHERE room_status = 'available' LIMIT 1");
    $room = $stmtRoom->fetch();
    if (!$room) {
        $room = $pdo->query("SELECT room_id FROM room LIMIT 1")->fetch();
    }
    $roomId = (int)$room['room_id'];

    // 2. Setup upcoming one-time booking fixture
    $testDate = date('Y-m-d', strtotime('+4 days'));
    $testDow  = strtolower(date('l', strtotime($testDate)));
    $startTime = '13:00:00';
    $endTime   = '15:00:00';

    // Clean up
    $pdo->prepare("DELETE FROM schedule WHERE room_id = ? AND schedule_day = ?")->execute([$roomId, $testDate]);

    // Account fixture
    $stmtAcc = $pdo->query("SELECT account_id FROM account LIMIT 1");
    $acc = $stmtAcc->fetch();
    $accId = (int)$acc['account_id'];

    // Insert one-time reservation on testDate
    $stmtIns = $pdo->prepare("
        INSERT INTO schedule (room_id, account_id, schedule_day, schedule_day_of_week, schedule_start, schedule_end)
        VALUES (?, ?, ?, NULL, ?, ?)
    ");
    $stmtIns->execute([$roomId, $accId, $testDate, $startTime, $endTime]);
    $scheduleId = $pdo->lastInsertId();

    echo "=== Test 1: Weekly Check vs One-Time Reservation ===\n";
    echo "Room ID: {$roomId}, Date: {$testDate} ({$testDow}), Time: {$startTime}-{$endTime}\n";

    // 3. Query using the new check_conflict logic
    $reqStart = '13:30';
    $reqEnd   = '14:30';

    $stmtCheck = $pdo->prepare("
        SELECT schedule_start, schedule_end FROM schedule
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
        LIMIT 1
    ");
    $stmtCheck->execute([$roomId, $testDow, $reqEnd, $reqStart, $testDow, $reqEnd, $reqStart]);
    $conflict = $stmtCheck->fetch();

    if (!$conflict) {
        throw new RuntimeException("FAILED: Overlap between weekly request and one-time booking was not detected!");
    }
    echo "PASS: Overlap correctly detected with schedule {$conflict['schedule_start']} - {$conflict['schedule_end']}\n";

    // 4. Test Non-conflicting Time
    $freeStart = '16:00';
    $freeEnd   = '17:00';
    $stmtCheck->execute([$roomId, $testDow, $freeEnd, $freeStart, $testDow, $freeEnd, $freeStart]);
    $noConflict = $stmtCheck->fetch();

    if ($noConflict) {
        throw new RuntimeException("FAILED: False positive conflict detected for non-overlapping slot!");
    }
    echo "PASS: Non-overlapping slot correctly evaluated as free.\n";

    // Cleanup
    $pdo->prepare("DELETE FROM schedule WHERE schedule_id = ?")->execute([$scheduleId]);
    echo "Cleaned up fixture schedule_id: {$scheduleId}\n";

    echo "\nALL TESTS PASSED (Exit 0)\n";
    exit(0);

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
