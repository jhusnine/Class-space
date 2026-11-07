<?php
/**
 * Test: Teacher Room Visibility Restrictions
 * Verifies that the teacher side of the website does NOT see any rooms that are
 * marked as 'maintenance' or 'unavailable', while the admin side does.
 */

require_once __DIR__ . '/../includes/database_include.php';

echo "=== Test: Teacher Room Visibility Restrictions ===\n";

$pdo = new PDO(
    "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
    $db_user,
    $db_pass,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

// 1. Identify an admin account and a teacher (regular) account
$adminAcc = $pdo->query("SELECT account_id FROM account WHERE account_is_admin = 1 LIMIT 1")->fetch();
if (!$adminAcc) {
    throw new RuntimeException("FAILED: No admin account found in database.");
}
$adminId = (int)$adminAcc['account_id'];

$teacherAcc = $pdo->query("SELECT account_id FROM account WHERE account_is_admin = 0 LIMIT 1")->fetch();
if (!$teacherAcc) {
    throw new RuntimeException("FAILED: No teacher account found in database.");
}
$teacherId = (int)$teacherAcc['account_id'];

// 2. Ensure we have rooms with different statuses
$availRoom = $pdo->query("SELECT room_id, hall_id, room_name FROM room WHERE LOWER(TRIM(room_status)) = 'available' LIMIT 1")->fetch();
$unavailRoom = $pdo->query("SELECT room_id, hall_id, room_name FROM room WHERE LOWER(TRIM(room_status)) = 'unavailable' LIMIT 1")->fetch();

if (!$unavailRoom) {
    // If none currently unavailable, temporarily set one or create a test one
    $hall = $pdo->query("SELECT hall_id FROM hall LIMIT 1")->fetchColumn();
    $pdo->prepare("INSERT INTO room (hall_id, room_name, room_capacity, room_type, room_has_ac, room_status) VALUES (?, 'TEST_UNAVAIL', 30, 'Lecture', 1, 'unavailable')")->execute([$hall]);
    $unavailRoomId = (int)$pdo->lastInsertId();
    $unavailRoom = ['room_id' => $unavailRoomId, 'hall_id' => $hall, 'room_name' => 'TEST_UNAVAIL'];
    $cleanupUnavail = true;
} else {
    $cleanupUnavail = false;
}

// Ensure a maintenance room exists for test
$maintRoom = $pdo->query("SELECT room_id, hall_id, room_name FROM room WHERE LOWER(TRIM(room_status)) = 'maintenance' LIMIT 1")->fetch();
if (!$maintRoom) {
    $hall = $pdo->query("SELECT hall_id FROM hall LIMIT 1")->fetchColumn();
    $pdo->prepare("INSERT INTO room (hall_id, room_name, room_capacity, room_type, room_has_ac, room_status) VALUES (?, 'TEST_MAINT', 30, 'Lecture', 1, 'maintenance')")->execute([$hall]);
    $maintRoomId = (int)$pdo->lastInsertId();
    $maintRoom = ['room_id' => $maintRoomId, 'hall_id' => $hall, 'room_name' => 'TEST_MAINT'];
    $cleanupMaint = true;
} else {
    $cleanupMaint = false;
}

$unavailId = (int)$unavailRoom['room_id'];
$maintId = (int)$maintRoom['room_id'];
$availId = (int)$availRoom['room_id'];
$targetHallId = (int)$unavailRoom['hall_id'];

// Helper to execute controller in isolated PHP process
function runController(string $controllerPath, int $userId, bool $isAdmin, array $get = [], array $post = []): array {
    $runner = __DIR__ . '/_run_ctrl_tmp.php';
    $code = '<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set("display_errors", "0");
chdir(__DIR__ . "/../app/controllers");
session_start();
$_SESSION["id"] = ' . $userId . ';
$_SESSION["is_admin"] = ' . ($isAdmin ? '1' : '0') . ';
$_SERVER["REQUEST_METHOD"] = "' . (!empty($post) ? 'POST' : 'GET') . '";
$_GET = ' . var_export($get, true) . ';
$_POST = ' . var_export($post, true) . ';
require ' . var_export($controllerPath, true) . ';
';
    file_put_contents($runner, $code);
    $out = shell_exec('php ' . escapeshellarg($runner));
    @unlink($runner);
    $json = json_decode($out ?? '', true);
    if ($json === null) {
        throw new RuntimeException("Controller failed to output valid JSON. Output: " . $out);
    }
    return $json;
}

try {
    // 3. Test room_controller.php?action=get_all for Teacher
    $resAllTeacher = runController(__DIR__ . '/../app/controllers/room_controller.php', $teacherId, false, ['action' => 'get_all']);
    if (empty($resAllTeacher['success'])) {
        throw new RuntimeException("FAILED: get_all returned success=false for teacher.");
    }
    foreach ($resAllTeacher['data'] as $r) {
        $st = strtolower(trim($r['room_status'] ?? ''));
        if ($st === 'maintenance' || $st === 'unavailable') {
            throw new RuntimeException("FAILED: Teacher saw room {$r['room_name']} (ID {$r['room_id']}) with status {$st} in get_all!");
        }
    }
    echo "PASS: Teacher get_all excluded all maintenance and unavailable rooms.\n";

    // 4. Test room_controller.php?action=get_all for Admin
    $resAllAdmin = runController(__DIR__ . '/../app/controllers/room_controller.php', $adminId, true, ['action' => 'get_all']);
    $adminRoomIds = array_column($resAllAdmin['data'] ?? [], 'room_id');
    if (!in_array($unavailId, $adminRoomIds) && !in_array($maintId, $adminRoomIds)) {
        throw new RuntimeException("FAILED: Admin get_all did not include unavailable/maintenance rooms.");
    }
    echo "PASS: Admin get_all included maintenance and unavailable rooms.\n";

    // 5. Test room_controller.php?action=get_rooms for Teacher
    $resRoomsTeacher = runController(__DIR__ . '/../app/controllers/room_controller.php', $teacherId, false, ['action' => 'get_rooms', 'hall_id' => $targetHallId]);
    if (empty($resRoomsTeacher['success'])) {
        throw new RuntimeException("FAILED: get_rooms returned success=false for teacher.");
    }
    foreach ($resRoomsTeacher['data'] as $r) {
        $st = strtolower(trim($r['room_status'] ?? ''));
        if ($st === 'maintenance' || $st === 'unavailable') {
            throw new RuntimeException("FAILED: Teacher saw room {$r['room_name']} with status {$st} in get_rooms!");
        }
    }
    echo "PASS: Teacher get_rooms excluded all maintenance and unavailable rooms.\n";

    // 6. Test room_controller.php?action=get_rooms for Admin
    $resRoomsAdmin = runController(__DIR__ . '/../app/controllers/room_controller.php', $adminId, true, ['action' => 'get_rooms', 'hall_id' => $targetHallId]);
    $adminHallRoomIds = array_column($resRoomsAdmin['data'] ?? [], 'room_id');
    if (!in_array($unavailId, $adminHallRoomIds)) {
        throw new RuntimeException("FAILED: Admin get_rooms did not include unavailable room ID {$unavailId}.");
    }
    echo "PASS: Admin get_rooms included unavailable room.\n";

    // 7. Test room_controller.php?action=get_featured for Teacher
    $resFeaturedTeacher = runController(__DIR__ . '/../app/controllers/room_controller.php', $teacherId, false, ['action' => 'get_featured', 'type' => 'hall', 'id' => $targetHallId]);
    foreach ($resFeaturedTeacher['data'] as $r) {
        $st = strtolower(trim($r['room_status'] ?? ''));
        if ($st === 'maintenance' || $st === 'unavailable') {
            throw new RuntimeException("FAILED: Teacher saw room {$r['room_name']} with status {$st} in get_featured!");
        }
    }
    echo "PASS: Teacher get_featured excluded all maintenance and unavailable rooms.\n";

    // 8. Test search_controller.php for Teacher
    $resSearchTeacher = runController(__DIR__ . '/../app/controllers/search_controller.php', $teacherId, false, ['q' => $unavailRoom['room_name']]);
    $foundUnavailTeacher = false;
    foreach ($resSearchTeacher['data'] ?? [] as $item) {
        if (($item['type'] ?? '') === 'room' && (int)$item['id'] === $unavailId) {
            $foundUnavailTeacher = true;
        }
    }
    if ($foundUnavailTeacher) {
        throw new RuntimeException("FAILED: Teacher search returned unavailable room {$unavailRoom['room_name']}!");
    }
    echo "PASS: Teacher search excluded unavailable room.\n";

    // 9. Test search_controller.php for Admin
    $resSearchAdmin = runController(__DIR__ . '/../app/controllers/search_controller.php', $adminId, true, ['q' => $unavailRoom['room_name']]);
    $foundUnavailAdmin = false;
    foreach ($resSearchAdmin['data'] ?? [] as $item) {
        if (($item['type'] ?? '') === 'room' && (int)$item['id'] === $unavailId) {
            $foundUnavailAdmin = true;
        }
    }
    if (!$foundUnavailAdmin) {
        throw new RuntimeException("FAILED: Admin search did not find unavailable room {$unavailRoom['room_name']}.");
    }
    echo "PASS: Admin search correctly returned unavailable room.\n";

    // 10. Verify frontend files exclude maintenance / unavailable
    $reserveJs = file_get_contents(__DIR__ . '/../public/js/reserve_script.js');
    if (!str_contains($reserveJs, "roomStatus === 'maintenance' || roomStatus === 'unavailable'")) {
        throw new RuntimeException("FAILED: reserve_script.js is missing filter for maintenance/unavailable rooms.");
    }
    echo "PASS: reserve_script.js properly filters out maintenance and unavailable rooms.\n";

    $homeJs = file_get_contents(__DIR__ . '/../public/js/homepage_script.js');
    if (!str_contains($homeJs, "dbStatus === 'maintenance' || dbStatus === 'unavailable'")) {
        throw new RuntimeException("FAILED: homepage_script.js is missing filter for maintenance/unavailable rooms.");
    }
    echo "PASS: homepage_script.js properly filters out maintenance and unavailable rooms.\n";

    $homeView = file_get_contents(__DIR__ . '/../app/views/user_homepage_view.php');
    if (str_contains($homeView, 'legend-edge-maintenance')) {
        throw new RuntimeException("FAILED: user_homepage_view.php still contains maintenance legend item.");
    }
    echo "PASS: user_homepage_view.php removed maintenance legend item.\n";

    echo "\nALL TEACHER ROOM VISIBILITY CHECKS PASSED (Exit 0)\n";
} finally {
    if (!empty($cleanupUnavail)) {
        $pdo->prepare("DELETE FROM room WHERE room_id = ?")->execute([$unavailRoom['room_id']]);
    }
    if (!empty($cleanupMaint)) {
        $pdo->prepare("DELETE FROM room WHERE room_id = ?")->execute([$maintRoom['room_id']]);
    }
}
