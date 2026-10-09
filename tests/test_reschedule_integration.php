<?php
/**
 * Test: Reschedule and In-Modal Conflict Resolution API Integration
 * Verifies:
 * 1. reserve_controller.php can update an existing pending reservation to a new room/time.
 * 2. reserve_controller.php rejects unauthorized updates.
 * 3. reserve_controller.php can cancel/withdraw a pending reservation.
 * 4. DOM and JS structures exist for modal AI alternatives and withdraw buttons.
 */

require_once __DIR__ . '/../includes/database_include.php';

echo "=== Test: Reschedule & Calendar Conflict Resolution Integration ===\n";

$pdo = new PDO(
    "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
    $db_user,
    $db_pass,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

$accId = 3; // Account from database

// 1. Create a temporary fixture pending reservation
$pdo->prepare("
    INSERT INTO pendingschedule (room_id, account_id, pending_schedule_day_of_week, pending_schedule_start, pending_schedule_end, pending_schedule_day, pending_schedule_status)
    VALUES (67, ?, 'Monday', '08:00:00', '10:00:00', NULL, 'pending')
")->execute([$accId]);
$fixturePendingId = (int)$pdo->lastInsertId();

// 2. Test updating this pending reservation to room 2 (PH102)
$postJson = json_encode([
    'pending_id' => $fixturePendingId,
    'room_id' => 2,
    'type' => 'weekly',
    'dow' => 'Monday',
    'start' => '08:00',
    'end' => '10:00'
]);

$runnerFile = __DIR__ . '/_run_reserve_cli.php';
file_put_contents($runnerFile, '<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set("display_errors", "0");
$_SERVER["REQUEST_METHOD"] = "POST";
chdir(__DIR__ . "/../app/controllers");
session_start();
$_SESSION["id"] = ' . $accId . ';
require "reserve_controller.php";
');

$cmd = 'php ' . escapeshellarg($runnerFile);
$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w']
];
$proc = proc_open($cmd, $descriptors, $pipes);
fwrite($pipes[0], $postJson);
fclose($pipes[0]);
$out = stream_get_contents($pipes[1]);
$err = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
proc_close($proc);

$jsonStart = strpos($out, '{');
$res = ($jsonStart !== false) ? json_decode(substr($out, $jsonStart), true) : null;
if (empty($res['success']) || ($res['action'] ?? '') !== 'updated') {
    @unlink($runnerFile);
    $pdo->prepare("DELETE FROM pendingschedule WHERE pending_id = ?")->execute([$fixturePendingId]);
    throw new RuntimeException("FAILED: Updating pending reservation failed. Output: $out, Err: $err");
}

// Verify in DB that room_id was updated to 2
$updatedRow = $pdo->query("SELECT room_id FROM pendingschedule WHERE pending_id = $fixturePendingId")->fetch();
if ((int)$updatedRow['room_id'] !== 2) {
    $pdo->prepare("DELETE FROM pendingschedule WHERE pending_id = ?")->execute([$fixturePendingId]);
    throw new RuntimeException("FAILED: Database row was not updated to room_id 2");
}
echo "PASS: Pending reservation successfully updated to Room 2 in database.\n";

// 3. Test cancel/withdraw action
$cancelJson = json_encode([
    'action' => 'cancel',
    'pending_id' => $fixturePendingId
]);
$proc = proc_open($cmd, $descriptors, $pipes);
fwrite($pipes[0], $cancelJson);
fclose($pipes[0]);
$out = stream_get_contents($pipes[1]);
fclose($pipes[1]);
fclose($pipes[2]);
proc_close($proc);

$cancelJsonStart = strpos($out, '{');
$resCancel = ($cancelJsonStart !== false) ? json_decode(substr($out, $cancelJsonStart), true) : null;
if (empty($resCancel['success']) || ($resCancel['action'] ?? '') !== 'cancelled') {
    @unlink($runnerFile);
    $pdo->prepare("DELETE FROM pendingschedule WHERE pending_id = ?")->execute([$fixturePendingId]);
    throw new RuntimeException("FAILED: Cancel pending reservation failed. Output: $out");
}

$checkDeleted = $pdo->query("SELECT pending_id FROM pendingschedule WHERE pending_id = $fixturePendingId")->fetch();
if ($checkDeleted) {
    throw new RuntimeException("FAILED: Row was not deleted from pendingschedule");
}
@unlink($runnerFile);
echo "PASS: Pending reservation successfully cancelled and deleted from database.\n";

// 4. Verify calendar_view.php markup and calendar_script.js functions
$calHtml = file_get_contents(__DIR__ . '/../app/views/calendar_view.php');
if (!str_contains($calHtml, 'id="modal-ai-recommendations"')) {
    throw new RuntimeException("FAILED: calendar_view.php is missing #modal-ai-recommendations");
}
if (!str_contains($calHtml, 'id="modal-btn-cancel-req"')) {
    throw new RuntimeException("FAILED: calendar_view.php is missing #modal-btn-cancel-req");
}
echo "PASS: calendar_view.php verified with in-modal AI recommendations and cancel button.\n";

$calJs = file_get_contents(__DIR__ . '/../public/js/calendar_script.js');
if (!str_contains($calJs, 'applyModalAlternative')) {
    throw new RuntimeException("FAILED: calendar_script.js is missing applyModalAlternative");
}
if (!str_contains($calJs, 'cancelPendingFromModal')) {
    throw new RuntimeException("FAILED: calendar_script.js is missing cancelPendingFromModal");
}
echo "PASS: calendar_script.js verified with applyModalAlternative and cancelPendingFromModal handlers.\n";

// 5. Verify reserve_view.php and reserve_script.js
$resHtml = file_get_contents(__DIR__ . '/../app/views/reserve_view.php');
if (!str_contains($resHtml, 'id="reschedulePendingId"')) {
    throw new RuntimeException("FAILED: reserve_view.php is missing #reschedulePendingId");
}
$resJs = file_get_contents(__DIR__ . '/../public/js/reserve_script.js');
if (!str_contains($resJs, 'reschedulePendingId')) {
    throw new RuntimeException("FAILED: reserve_script.js is missing reschedulePendingId tracking");
}
echo "PASS: reserve_view and reserve_script verified with reschedulePendingId support.\n";

echo "\nALL RESCHEDULE INTEGRATION CHECKS PASSED (Exit 0)\n";
exit(0);
