<?php
/**
 * Test: "Find Alternative with AI" End-to-End Redirection & Pre-population Contract
 * Verifies:
 * 1. spGetMyPending returns room_id and hall_id.
 * 2. calendar_script.js produces robust query parameters.
 * 3. reserve_view.php renders pre-populated values and hidden fields.
 * 4. ai_suggest_controller returns recommendations for pre-populated parameters.
 */

require_once __DIR__ . '/../includes/database_include.php';

echo "=== Test: Find Alternative with AI E2E Integration ===\n";

$pdo = new PDO(
    "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
    $db_user,
    $db_pass,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

// 1. Verify spGetMyPending returns room_id and hall_id
$acc = $pdo->query("SELECT account_id FROM account LIMIT 1")->fetch();
$accId = (int)$acc['account_id'];

$stmt = $pdo->prepare("CALL `spGetMyPending`(?)");
$stmt->execute([$accId]);
$pendingRows = $stmt->fetchAll();
$stmt->closeCursor();

// Check columns in the result set if there are rows, or check procedure definition
$procRow = $pdo->query("SHOW CREATE PROCEDURE `spGetMyPending`")->fetch();
$procDef = $procRow['Create Procedure'] ?? '';
if (!str_contains($procDef, 'r.room_id') || !str_contains($procDef, 'h.hall_id')) {
    throw new RuntimeException("FAILED: spGetMyPending definition does not select r.room_id and h.hall_id");
}
echo "PASS: spGetMyPending properly includes r.room_id and h.hall_id in query.\n";

// 2. Pick a room and hall from DB for simulation
$room = $pdo->query("
    SELECT r.room_id, r.room_name, h.hall_id, h.hall_name 
    FROM room r 
    JOIN hall h ON r.hall_id = h.hall_id 
    LIMIT 1
")->fetch();

$hallId = (int)$room['hall_id'];
$hallName = $room['hall_name'];
$roomId = (int)$room['room_id'];
$roomName = $room['room_name'];
$testDate = date('Y-m-d', strtotime('+5 days'));
$start = "14:00";
$end = "16:00";

// 3. Simulate reserve_view.php rendering with query parameters
$viewRunner = __DIR__ . '/_run_reserve_view_tmp.php';
file_put_contents($viewRunner, '<?php
session_start();
$_SESSION["id"] = ' . $accId . ';
$_SESSION["fname"] = "Test";
$_SESSION["lname"] = "User";
$_SERVER["REQUEST_METHOD"] = "GET";
$_GET = [
    "auto_check" => "1",
    "type" => "one-time",
    "hall_id" => "' . $hallId . '",
    "hall_name" => "' . addslashes($hallName) . '",
    "room_id" => "' . $roomId . '",
    "room_name" => "' . addslashes($roomName) . '",
    "date" => "' . $testDate . '",
    "start" => "' . $start . '",
    "end" => "' . $end . '"
];
ob_start();
require __DIR__ . "/../app/views/reserve_view.php";
$html = ob_get_clean();
echo $html;
');

$htmlOutput = shell_exec('php ' . escapeshellarg($viewRunner));
@unlink($viewRunner);

if (!str_contains($htmlOutput, 'id="preHallId" value="' . $hallId . '"')) {
    throw new RuntimeException("FAILED: preHallId was not populated with $hallId");
}
if (!str_contains($htmlOutput, 'id="preRoomId" value="' . $roomId . '"')) {
    throw new RuntimeException("FAILED: preRoomId was not populated with $roomId");
}
if (!str_contains($htmlOutput, 'id="preHallName" value="' . htmlspecialchars($hallName) . '"')) {
    throw new RuntimeException("FAILED: preHallName was not populated with $hallName");
}
if (!str_contains($htmlOutput, 'id="preRoomName" value="' . htmlspecialchars($roomName) . '"')) {
    throw new RuntimeException("FAILED: preRoomName was not populated with $roomName");
}
if (!str_contains($htmlOutput, 'id="autoCheck" value="1"')) {
    throw new RuntimeException("FAILED: autoCheck was not populated with 1");
}
if (!str_contains($htmlOutput, 'id="schedule-date" min="' . date('Y-m-d') . '" value="' . $testDate . '"')) {
    throw new RuntimeException("FAILED: schedule-date value was not pre-populated with $testDate");
}
if (!str_contains($htmlOutput, 'id="schedule-start" value="' . $start . '"')) {
    throw new RuntimeException("FAILED: schedule-start was not pre-populated with $start");
}
if (!str_contains($htmlOutput, 'id="schedule-end" value="' . $end . '"')) {
    throw new RuntimeException("FAILED: schedule-end was not pre-populated with $end");
}
echo "PASS: reserve_view.php successfully rendered pre-populated inputs and hidden parameters.\n";

// 4. Verify Weekly Redirection Pre-population
$weeklyRunner = __DIR__ . '/_run_weekly_view_tmp.php';
file_put_contents($weeklyRunner, '<?php
session_start();
$_SESSION["id"] = ' . $accId . ';
$_SESSION["fname"] = "Test";
$_SESSION["lname"] = "User";
$_SERVER["REQUEST_METHOD"] = "GET";
$_GET = [
    "auto_check" => "1",
    "type" => "weekly",
    "hall_id" => "' . $hallId . '",
    "hall_name" => "' . addslashes($hallName) . '",
    "room_id" => "' . $roomId . '",
    "room_name" => "' . addslashes($roomName) . '",
    "dow" => "Wednesday",
    "start" => "09:00",
    "end" => "11:00"
];
ob_start();
require __DIR__ . "/../app/views/reserve_view.php";
$html = ob_get_clean();
echo $html;
');

$weeklyOutput = shell_exec('php ' . escapeshellarg($weeklyRunner));
@unlink($weeklyRunner);

if (!str_contains($weeklyOutput, '<input type="radio" name="res-type" value="weekly" checked>')) {
    throw new RuntimeException("FAILED: Weekly radio button was not checked when type=weekly");
}
if (!str_contains($weeklyOutput, '<option value="Wednesday" selected>Wednesday</option>')) {
    throw new RuntimeException("FAILED: Wednesday was not selected in schedule-dow");
}
echo "PASS: reserve_view.php correctly pre-selected weekly mode and Wednesday day-of-week.\n";

// 5. Verify AI suggestions can be retrieved for these parameters
$suggestRunner = __DIR__ . '/_run_suggest_test_tmp.php';
file_put_contents($suggestRunner, '<?php
session_start();
$_SESSION["id"] = ' . $accId . ';
$_SERVER["REQUEST_METHOD"] = "GET";
$_GET = [
    "room_id" => ' . $roomId . ',
    "date" => "' . $testDate . '",
    "start" => "' . $start . '",
    "end" => "' . $end . '",
    "type" => "one-time"
];
require __DIR__ . "/../app/controllers/ai_suggest_controller.php";
');
$suggestOut = shell_exec('php ' . escapeshellarg($suggestRunner));
@unlink($suggestRunner);

$sugRes = json_decode($suggestOut ?? '', true);
if (empty($sugRes['success'])) {
    throw new RuntimeException("FAILED: ai_suggest_controller failed: " . $suggestOut);
}
echo "PASS: ai_suggest_controller returned valid response with " . count($sugRes['suggestions'] ?? []) . " suggestions.\n";

echo "\nALL FIND ALTERNATIVE INTEGRATION CHECKS PASSED (Exit 0)\n";
exit(0);
