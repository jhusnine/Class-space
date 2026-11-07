<?php






require_once __DIR__ . '/../includes/database_include.php';

echo "=== Task 3: Reservation View & AI Integration Test ===\n";


$viewHtml = file_get_contents(__DIR__ . '/../app/views/reserve_view.php');
if (!str_contains($viewHtml, 'id="ai-suggestions-container"')) {
    throw new RuntimeException("FAILED: reserve_view.php is missing #ai-suggestions-container");
}
if (!str_contains($viewHtml, 'id="ai-summary-text"')) {
    throw new RuntimeException("FAILED: reserve_view.php is missing #ai-summary-text");
}
if (!str_contains($viewHtml, 'id="ai-cards-list"')) {
    throw new RuntimeException("FAILED: reserve_view.php is missing #ai-cards-list");
}
echo "PASS: View markup verified with #ai-suggestions-container.\n";


$css = file_get_contents(__DIR__ . '/../public/css/reserve_style.css');
if (!str_contains($css, '.ai-suggestions-container')) {
    throw new RuntimeException("FAILED: reserve_style.css is missing .ai-suggestions-container");
}
if (!str_contains($css, '.ai-suggestion-card')) {
    throw new RuntimeException("FAILED: reserve_style.css is missing .ai-suggestion-card");
}
if (!str_contains($css, '.btn-apply-suggestion')) {
    throw new RuntimeException("FAILED: reserve_style.css is missing .btn-apply-suggestion");
}
echo "PASS: CSS styles verified for AI recommendation cards.\n";


$js = file_get_contents(__DIR__ . '/../public/js/reserve_script.js');
if (!str_contains($js, 'fetchAiSuggestions')) {
    throw new RuntimeException("FAILED: reserve_script.js is missing fetchAiSuggestions");
}
if (!str_contains($js, 'applySuggestion')) {
    throw new RuntimeException("FAILED: reserve_script.js is missing applySuggestion");
}
if (!str_contains($js, 'currentSuggestions')) {
    throw new RuntimeException("FAILED: reserve_script.js is missing currentSuggestions array");
}
echo "PASS: JavaScript handlers verified for AI suggestion fetching and 1-click apply.\n";


$pdo = new PDO(
    "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
    $db_user,
    $db_pass,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

$room = $pdo->query("SELECT room_id FROM room WHERE room_status = 'available' LIMIT 1")->fetch();
$roomId = (int)$room['room_id'];
$testDate = date('Y-m-d', strtotime('+3 days'));
$accId = (int)$pdo->query("SELECT account_id FROM account LIMIT 1")->fetchColumn();


$pdo->prepare("DELETE FROM schedule WHERE room_id = ? AND schedule_day = ?")->execute([$roomId, $testDate]);
$pdo->prepare("
    INSERT INTO schedule (room_id, account_id, schedule_day, schedule_day_of_week, schedule_start, schedule_end)
    VALUES (?, ?, ?, NULL, '10:00:00', '12:00:00')
")->execute([$roomId, $accId, $testDate]);
$scheduleId = $pdo->lastInsertId();


$checkRunner = __DIR__ . '/_run_check_tmp.php';
file_put_contents($checkRunner, '<?php
session_start();
$_SESSION["id"] = ' . $accId . ';
$_SERVER["REQUEST_METHOD"] = "GET";
$_GET = ["room_id" => ' . $roomId . ', "date" => "' . $testDate . '", "start" => "10:30", "end" => "11:30", "type" => "one-time"];
require __DIR__ . "/../app/controllers/check_conflict.php";
');
$checkOut = shell_exec('php ' . escapeshellarg($checkRunner));
@unlink($checkRunner);

$checkRes = json_decode($checkOut ?? '', true);
if (empty($checkRes['conflict']) || empty($checkRes['has_suggestions'])) {
    $pdo->prepare("DELETE FROM schedule WHERE schedule_id = ?")->execute([$scheduleId]);
    throw new RuntimeException("FAILED: check_conflict did not return conflict=true and has_suggestions=true. Response: " . $checkOut);
}
echo "PASS: check_conflict correctly signaled conflict=true and has_suggestions=true.\n";


$suggestRunner = __DIR__ . '/_run_suggest_tmp.php';
file_put_contents($suggestRunner, '<?php
session_start();
$_SESSION["id"] = ' . $accId . ';
$_SERVER["REQUEST_METHOD"] = "GET";
$_GET = ["room_id" => ' . $roomId . ', "date" => "' . $testDate . '", "start" => "10:30", "end" => "11:30", "type" => "one-time"];
require __DIR__ . "/../app/controllers/ai_suggest_controller.php";
');
$suggestOut = shell_exec('php ' . escapeshellarg($suggestRunner));
@unlink($suggestRunner);

$suggestRes = json_decode($suggestOut ?? '', true);
$pdo->prepare("DELETE FROM schedule WHERE schedule_id = ?")->execute([$scheduleId]);

if (empty($suggestRes['success']) || empty($suggestRes['suggestions'])) {
    throw new RuntimeException("FAILED: ai_suggest did not return valid suggestions. Response: " . $suggestOut);
}

echo "PASS: ai_suggest returned " . count($suggestRes['suggestions']) . " suggestions ready for 1-click apply.\n";
echo "\nALL TASK 3 INTEGRATION CHECKS PASSED (Exit 0)\n";
exit(0);
