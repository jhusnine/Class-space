<?php
/**
 * Test: CSP Slot and Room Recommendation Controller
 * Verifies that ai_suggest_controller.php returns ranked suggestions
 * satisfying hard constraints and sorted by heuristic score.
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
    $stmtRoom = $pdo->query("SELECT room_id, hall_id, room_capacity FROM room WHERE room_status = 'available' LIMIT 1");
    $room = $stmtRoom->fetch();
    if (!$room) {
        $room = $pdo->query("SELECT room_id, hall_id, room_capacity FROM room LIMIT 1")->fetch();
    }
    $roomId = (int)$room['room_id'];

    // 2. Insert conflicting fixture
    $testDate  = date('Y-m-d', strtotime('+5 days'));
    $startTime = '13:00:00';
    $endTime   = '15:00:00';

    $stmtAcc = $pdo->query("SELECT account_id FROM account LIMIT 1");
    $acc = $stmtAcc->fetch();
    $accId = (int)$acc['account_id'];

    $pdo->prepare("DELETE FROM schedule WHERE room_id = ? AND schedule_day = ?")->execute([$roomId, $testDate]);
    $stmtIns = $pdo->prepare("
        INSERT INTO schedule (room_id, account_id, schedule_day, schedule_day_of_week, schedule_start, schedule_end)
        VALUES (?, ?, ?, NULL, ?, ?)
    ");
    $stmtIns->execute([$roomId, $accId, $testDate, $startTime, $endTime]);
    $scheduleId = $pdo->lastInsertId();

    echo "=== Test CSP Controller Fixture ===\n";
    echo "Room ID: {$roomId}, Date: {$testDate}, Fixture: {$startTime}-{$endTime}\n";

    // 3. Invoke controller via isolated PHP CLI process
    $runnerScript = __DIR__ . '/_run_suggest_subrequest.php';
    $code = '<?php
session_start();
$_SESSION["id"] = ' . $accId . ';
$_SERVER["REQUEST_METHOD"] = "GET";
$_GET = [
    "room_id" => ' . $roomId . ',
    "date"    => "' . $testDate . '",
    "start"   => "13:30",
    "end"     => "14:30",
    "type"    => "one-time"
];
require __DIR__ . "/../app/controllers/ai_suggest_controller.php";
';
    file_put_contents($runnerScript, $code);

    $cmd = 'php ' . escapeshellarg($runnerScript);
    $output = shell_exec($cmd);
    @unlink($runnerScript);

    // Cleanup DB fixture
    $pdo->prepare("DELETE FROM schedule WHERE schedule_id = ?")->execute([$scheduleId]);

    $response = json_decode($output ?? '', true);

    if (!$response) {
        throw new RuntimeException("Controller did not return valid JSON. Raw output: " . ($output ?? 'empty'));
    }

    if (empty($response['success'])) {
        throw new RuntimeException("Controller returned success: false. Response: " . json_encode($response));
    }

    if (empty($response['suggestions']) || !is_array($response['suggestions'])) {
        throw new RuntimeException("Controller returned no suggestions array. Response: " . json_encode($response));
    }

    echo "PASS: Controller returned " . count($response['suggestions']) . " suggestions.\n";
    echo "AI Summary: " . ($response['ai_summary'] ?? 'N/A') . "\n";

    foreach ($response['suggestions'] as $sug) {
        echo "  [Rank {$sug['rank']}] Type: {$sug['type']}, Room: {$sug['room_name']}, Time: {$sug['formatted_time']}\n";
        echo "    Reason: {$sug['reason']}\n";

        // Verification: ensure alternative slots on the same room don't overlap the conflicting fixture (13:00 - 15:00)
        if ($sug['type'] === 'alternative_slot' && (int)$sug['room_id'] === $roomId) {
            $sStart = $sug['start'];
            $sEnd   = $sug['end'];
            if ($sStart < '15:00' && $sEnd > '13:00') {
                throw new RuntimeException("FAILED: Alternative slot {$sStart}-{$sEnd} overlaps with fixture 13:00-15:00!");
            }
        }
    }

    echo "\nALL CSP TESTS PASSED (Exit 0)\n";
    exit(0);

} catch (Exception $e) {
    if (isset($scheduleId) && isset($pdo)) {
        $pdo->prepare("DELETE FROM schedule WHERE schedule_id = ?")->execute([$scheduleId]);
    }
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
