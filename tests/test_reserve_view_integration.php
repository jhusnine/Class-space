<?php
/**
 * Test: Reservation View & AI Suggestions Integration
 * Verifies that the reservation form DOM contains required AI elements,
 * styles are defined, and the API chain (check_conflict -> ai_suggest) integrates properly.
 */

require_once __DIR__ . '/../includes/database_include.php';

echo "=== Task 3: Reservation View & AI Integration Test ===\n";

// 1. Verify View Markup & AI Suggestions Container
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

// 1b. Verify 3-Step Wizard & Stepper Skeleton Markup
if (!str_contains($viewHtml, 'id="wizard-stepper"')) {
    throw new RuntimeException("FAILED: reserve_view.php is missing #wizard-stepper");
}
if (!str_contains($viewHtml, 'stepper-progress-bar')) {
    throw new RuntimeException("FAILED: reserve_view.php is missing .stepper-progress-bar");
}
foreach ([1, 2, 3] as $step) {
    if (!preg_match('/class="[^"]*step-item[^"]*"[^>]*data-step="' . $step . '"|data-step="' . $step . '"[^>]*class="[^"]*step-item[^"]*"/s', $viewHtml)) {
        throw new RuntimeException("FAILED: reserve_view.php is missing .step-item with data-step=\"$step\"");
    }
}

$step1Pos = strpos($viewHtml, 'id="wizard-step-1"');
$step2Pos = strpos($viewHtml, 'id="wizard-step-2"');
$step3Pos = strpos($viewHtml, 'id="wizard-step-3"');
if ($step1Pos === false) {
    throw new RuntimeException("FAILED: reserve_view.php is missing #wizard-step-1");
}
if ($step2Pos === false) {
    throw new RuntimeException("FAILED: reserve_view.php is missing #wizard-step-2");
}
if ($step3Pos === false) {
    throw new RuntimeException("FAILED: reserve_view.php is missing #wizard-step-3");
}
if (!($step1Pos < $step2Pos && $step2Pos < $step3Pos)) {
    throw new RuntimeException("FAILED: Wizard steps are not in sequential order (1 -> 2 -> 3)");
}

$step1Html = substr($viewHtml, $step1Pos, $step2Pos - $step1Pos);
$step2Html = substr($viewHtml, $step2Pos, $step3Pos - $step2Pos);
$step3Html = substr($viewHtml, $step3Pos);
$preHallPos = strpos($step3Html, 'id="preHallId"');
if ($preHallPos !== false) {
    $step3Html = substr($step3Html, 0, $preHallPos);
}

// Step 1 required elements
$step1Required = [
    'id="reservation-subject"',
    'id="hall-select"',
    'id="room-select"',
    'id="room-preview"',
    'id="btn-step1-next"'
];
foreach ($step1Required as $req) {
    if (!str_contains($step1Html, $req)) {
        throw new RuntimeException("FAILED: #wizard-step-1 is missing $req");
    }
}

// Step 2 required elements
$step2Required = [
    'id="schedule-date"',
    'id="schedule-dow"',
    'id="schedule-until"',
    'id="schedule-start"',
    'id="schedule-end"',
    'id="conflict-warning"',
    'id="ai-suggestions-container"',
    'id="btn-step2-back"',
    'id="btn-step2-next"'
];
if (!preg_match('/<input[^>]+name="res-type"/s', $step2Html)) {
    throw new RuntimeException("FAILED: #wizard-step-2 is missing input[name=\"res-type\"]");
}
foreach ($step2Required as $req) {
    if (!str_contains($step2Html, $req)) {
        throw new RuntimeException("FAILED: #wizard-step-2 is missing $req");
    }
}

// Step 3 required elements
$step3Required = [
    'id="reservation-summary-card"',
    'id="summary-subject"',
    'id="summary-building"',
    'id="summary-room"',
    'id="summary-schedule"',
    'id="summary-time"',
    'id="btn-step3-back"',
    'id="submit-btn"'
];
foreach ($step3Required as $req) {
    if (!str_contains($step3Html, $req)) {
        throw new RuntimeException("FAILED: #wizard-step-3 is missing $req");
    }
}

// Legacy modal removal verification
if (str_contains($viewHtml, 'id="booking-review"')) {
    throw new RuntimeException("FAILED: Legacy modal #booking-review was not removed from reserve_view.php");
}

// Hidden parameters verification
$hiddenParams = [
    'preHallId', 'preHallName', 'preRoomId', 'preRoomName',
    'preDate', 'preDow', 'preType', 'preStart', 'preEnd', 'preUntil',
    'autoCheck', 'reschedulePendingId'
];
foreach ($hiddenParams as $param) {
    if (!str_contains($viewHtml, 'id="' . $param . '"')) {
        throw new RuntimeException("FAILED: reserve_view.php is missing hidden parameter #$param");
    }
}
echo "PASS: 3-step wizard markup and progress stepper skeleton verified.\n";

// 2. Verify Styles
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

// 3. Verify JavaScript Handlers
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

// 4. Verify API Chain Response
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

// Fixture
$pdo->prepare("DELETE FROM schedule WHERE room_id = ? AND schedule_day = ?")->execute([$roomId, $testDate]);
$pdo->prepare("
    INSERT INTO schedule (room_id, account_id, schedule_day, schedule_day_of_week, schedule_start, schedule_end)
    VALUES (?, ?, ?, NULL, '10:00:00', '12:00:00')
")->execute([$roomId, $accId, $testDate]);
$scheduleId = $pdo->lastInsertId();

// Verify conflict check returns has_suggestions = true
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

// Verify ai_suggest_controller returns valid suggestions for these parameters
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
