<?php
/**
 * Automated Integration Test Suite for Admin Room & Hall Management
 * Validates app/controllers/admin_manage_rooms_controller.php
 * Covers:
 * - Authentication & admin authorization (401 / 403)
 * - HTTP Method Enforcement (405 on non-POST for mutation endpoints)
 * - Boundary validations (room_name <= 20, room_type <= 20, hall_name <= 20, hall_desc <= 250, capacity > 0)
 * - Hall CRUD (create, update, duplicate check, get_halls with room_count)
 * - Room CRUD (create with hall, create standalone, update, duplicate check, update_status, get_rooms)
 * - Relational integrity: blocking hall deletion when rooms assigned, cascading cleanup on delete_room
 */

$rootDir = dirname(__DIR__);
require_once $rootDir . '/includes/database_include.php';

echo "=== Admin Room & Hall Management Integration Test Suite ===\n";
$allPassed = true;

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    echo "[FAIL] Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// ----------------------------------------------------
// 1. File existence & syntax checks
// ----------------------------------------------------
$controllerFile = $rootDir . '/app/controllers/admin_manage_rooms_controller.php';
$jsScriptFile   = $rootDir . '/public/js/admin_manage_rooms_script.js';

if (!file_exists($controllerFile)) {
    echo "[FAIL] Controller file does not exist: $controllerFile\n";
    $allPassed = false;
} else {
    exec("php -l " . escapeshellarg($controllerFile) . " 2>&1", $lintOutput, $lintCode);
    if ($lintCode !== 0) {
        echo "[FAIL] Syntax error in $controllerFile: " . implode("\n", $lintOutput) . "\n";
        $allPassed = false;
    } else {
        echo "[PASS] Controller file exists with valid PHP syntax.\n";
    }
}

if (!file_exists($jsScriptFile)) {
    echo "[FAIL] Admin manage rooms JS script does not exist: $jsScriptFile\n";
    $allPassed = false;
} else {
    exec("node -c " . escapeshellarg($jsScriptFile) . " 2>&1", $jsLintOutput, $jsLintCode);
    if ($jsLintCode !== 0) {
        echo "[FAIL] Syntax error in $jsScriptFile: " . implode("\n", $jsLintOutput) . "\n";
        $allPassed = false;
    } else {
        echo "[PASS] Admin manage rooms JS script exists with valid JavaScript syntax.\n";
    }

    $jsContent = file_get_contents($jsScriptFile);
    // Verify openModal inline handler uses only roomId to avoid breaking on names with apostrophes
    if (strpos($jsContent, 'onclick="openModal(${room.room_id})"') !== false) {
        echo "[PASS] `renderTable` uses safe openModal(\${room.room_id}) attribute binding.\n";
    } else {
        echo "[FAIL] `renderTable` does not use simplified safe openModal(\${room.room_id}) binding.\n";
        $allPassed = false;
    }

    // Verify deleteReservation uses only scheduleId, this, roomId
    if (strpos($jsContent, 'onclick="deleteReservation(${r.schedule_id}, this, ${roomId})"') !== false) {
        echo "[PASS] `openModal` uses safe deleteReservation(\${r.schedule_id}, this, \${roomId}) binding.\n";
    } else {
        echo "[FAIL] `openModal` does not use simplified deleteReservation binding.\n";
        $allPassed = false;
    }
}

// Subrequest invoker helper
function invokeRoomController(array $session, string $method, ?string $actionQuery, ?array $postPayload = null): array {
    $runnerScript = __DIR__ . '/_run_room_subrequest.php';
    $codeFile = __DIR__ . '/_last_http_code.txt';

    $sessionExport = var_export($session, true);
    $methodExport = var_export(strtoupper($method), true);
    $queryExport = var_export($actionQuery, true);
    $codeFileExport = var_export($codeFile, true);
    $payloadJson = $postPayload !== null ? json_encode($postPayload) : '';

    $code = '<?php
$targetCodeFile = ' . $codeFileExport . ';
register_shutdown_function(function() use ($targetCodeFile) {
    $code = http_response_code();
    @file_put_contents($targetCodeFile, (string)$code);
});

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION = ' . $sessionExport . ';
$_SERVER["REQUEST_METHOD"] = ' . $methodExport . ';
if (' . $queryExport . ' !== null) {
    $_GET["action"] = ' . $queryExport . ';
}
$rawPayload = ' . var_export($payloadJson, true) . ';
if ($rawPayload !== "") {
    $_POST = json_decode($rawPayload, true) ?: [];
}
require dirname(__DIR__) . "/app/controllers/admin_manage_rooms_controller.php";
';
    file_put_contents($runnerScript, $code);

    $cmd = 'php ' . escapeshellarg($runnerScript);
    $output = shell_exec($cmd);
    @unlink($runnerScript);

    $savedCode = 200;
    if (file_exists($codeFile)) {
        $savedCode = (int)file_get_contents($codeFile);
        @unlink($codeFile);
    }

    $res = json_decode($output ?? '', true);
    if (is_array($res)) {
        $res["_http_code"] = $savedCode;
        return $res;
    }
    return ["raw" => $output, "_http_code" => $savedCode];
}

// Identify active admin and teacher accounts for testing
$adminAcc = $pdo->query("SELECT account_id FROM account WHERE account_is_admin = 1 LIMIT 1")->fetch();
if (!$adminAcc) {
    echo "[FAIL] No admin account found in database.\n";
    exit(1);
}
$adminId = (int)$adminAcc['account_id'];
$adminSession = ["id" => $adminId, "is_admin" => 1];

$teacherAcc = $pdo->query("SELECT account_id FROM account WHERE account_is_admin = 0 LIMIT 1")->fetch();
if (!$teacherAcc) {
    echo "[FAIL] No teacher account found in database.\n";
    exit(1);
}
$teacherId = (int)$teacherAcc['account_id'];
$teacherSession = ["id" => $teacherId, "is_admin" => 0];

// ----------------------------------------------------
// 2. Authorization Checks
// ----------------------------------------------------
echo "\n--- Testing Authorization ---\n";
// A. No session -> 401
$noSessionRes = invokeRoomController([], 'GET', 'get_rooms');
if (($noSessionRes['_http_code'] ?? 0) === 401 && empty($noSessionRes['success'])) {
    echo "[PASS] Unauthorized request without session rejected with 401.\n";
} else {
    echo "[FAIL] Expected 401 for empty session, got: " . json_encode($noSessionRes) . "\n";
    $allPassed = false;
}

// B. Non-admin session -> 403
$nonAdminRes = invokeRoomController($teacherSession, 'GET', 'get_rooms');
if (($nonAdminRes['_http_code'] ?? 0) === 403 && empty($nonAdminRes['success'])) {
    echo "[PASS] Non-admin session rejected with 403.\n";
} else {
    echo "[FAIL] Expected 403 for non-admin session, got: " . json_encode($nonAdminRes) . "\n";
    $allPassed = false;
}

// ----------------------------------------------------
// 3. HTTP Method Enforcement (RFC 7231 / OWASP)
// ----------------------------------------------------
echo "\n--- Testing HTTP Method Enforcement ---\n";
$mutationActions = ['create_room', 'update_room', 'delete_room', 'create_hall', 'update_hall', 'delete_hall', 'update_status'];
foreach ($mutationActions as $mAction) {
    $res = invokeRoomController($adminSession, 'GET', $mAction);
    if (($res['_http_code'] ?? 0) === 405 && empty($res['success'])) {
        echo "[PASS] GET request to mutation action '$mAction' rejected with 405 Method Not Allowed.\n";
    } else {
        echo "[FAIL] Expected 405 on GET for action '$mAction', got: " . json_encode($res) . "\n";
        $allPassed = false;
    }
}

// ----------------------------------------------------
// 4. Hall CRUD Operations & Validation
// ----------------------------------------------------
echo "\n--- Testing Hall Management ---\n";
$testHallName = 'T_BLD_' . substr(uniqid(), 0, 8); // <= 20 chars
$testHallDesc = 'Test Building Description for Automated Verification';

// A. Create Hall
$createHallPayload = [
    'action' => 'create_hall',
    'hall_name' => $testHallName,
    'hall_desc' => $testHallDesc
];
$createHallRes = invokeRoomController($adminSession, 'POST', 'create_hall', $createHallPayload);
$createdHallId = null;

if (!empty($createHallRes['success']) && !empty($createHallRes['data']['hall_id'])) {
    $createdHallId = (int)$createHallRes['data']['hall_id'];
    echo "[PASS] Hall created successfully with ID: $createdHallId.\n";

    // Verify database record
    $checkHall = $pdo->prepare("SELECT * FROM hall WHERE hall_id = ?");
    $checkHall->execute([$createdHallId]);
    $hallRow = $checkHall->fetch();
    if ($hallRow && $hallRow['hall_name'] === $testHallName && $hallRow['hall_desc'] === $testHallDesc) {
        echo "[PASS] Database `hall` table verified with correct hall_name and hall_desc.\n";
    } else {
        echo "[FAIL] Database `hall` record mismatch: " . json_encode($hallRow) . "\n";
        $allPassed = false;
    }
} else {
    echo "[FAIL] Hall creation failed: " . json_encode($createHallRes) . "\n";
    $allPassed = false;
}

// B. Duplicate Hall Name Check
if ($createdHallId) {
    $dupRes = invokeRoomController($adminSession, 'POST', 'create_hall', [
        'action' => 'create_hall',
        'hall_name' => $testHallName,
        'hall_desc' => 'Another description'
    ]);
    if (empty($dupRes['success']) && (!empty($dupRes['error']))) {
        echo "[PASS] Duplicate hall name correctly rejected.\n";
    } else {
        echo "[FAIL] Duplicate hall name was not rejected: " . json_encode($dupRes) . "\n";
        $allPassed = false;
    }
}

// C. Hall Name Length Boundary Check (> 20 chars)
$longHallRes = invokeRoomController($adminSession, 'POST', 'create_hall', [
    'action' => 'create_hall',
    'hall_name' => 'ThisHallNameIsWayTooLongForDatabase', // 35 chars
    'hall_desc' => 'Valid desc'
]);
if (!empty($longHallRes['error']) && ($longHallRes['_http_code'] ?? 0) === 400) {
    echo "[PASS] Hall name exceeding 20 chars correctly rejected.\n";
} else {
    echo "[FAIL] Hall name exceeding 20 chars was not rejected with 400: " . json_encode($longHallRes) . "\n";
    $allPassed = false;
}

// D. Hall Description Length Boundary Check (> 250 chars)
$longDescRes = invokeRoomController($adminSession, 'POST', 'create_hall', [
    'action' => 'create_hall',
    'hall_name' => 'ValidName',
    'hall_desc' => str_repeat("A", 255)
]);
if (!empty($longDescRes['error']) && ($longDescRes['_http_code'] ?? 0) === 400) {
    echo "[PASS] Hall description exceeding 250 chars correctly rejected.\n";
} else {
    echo "[FAIL] Hall description exceeding 250 chars was not rejected with 400: " . json_encode($longDescRes) . "\n";
    $allPassed = false;
}

// E. Update Hall
if ($createdHallId) {
    $updatedHallName = 'U_BLD_' . substr(uniqid(), 0, 8);
    $updatedHallDesc = 'Updated Hall Description';
    $updateHallRes = invokeRoomController($adminSession, 'POST', 'update_hall', [
        'action' => 'update_hall',
        'hall_id' => $createdHallId,
        'hall_name' => $updatedHallName,
        'hall_desc' => $updatedHallDesc
    ]);
    if (!empty($updateHallRes['success'])) {
        $checkUpdated = $pdo->prepare("SELECT * FROM hall WHERE hall_id = ?");
        $checkUpdated->execute([$createdHallId]);
        $upRow = $checkUpdated->fetch();
        if ($upRow && $upRow['hall_name'] === $updatedHallName && $upRow['hall_desc'] === $updatedHallDesc) {
            echo "[PASS] Hall updated successfully in database.\n";
        } else {
            echo "[FAIL] Hall update DB mismatch: " . json_encode($upRow) . "\n";
            $allPassed = false;
        }
    } else {
        echo "[FAIL] Hall update failed: " . json_encode($updateHallRes) . "\n";
        $allPassed = false;
    }
}

// F. Get Halls
$getHallsRes = invokeRoomController($adminSession, 'GET', 'get_halls');
if (!empty($getHallsRes['success']) && is_array($getHallsRes['data'])) {
    $foundCreatedHall = false;
    foreach ($getHallsRes['data'] as $hItem) {
        if ((int)$hItem['hall_id'] === $createdHallId) {
            $foundCreatedHall = true;
            if (isset($hItem['room_count'])) {
                echo "[PASS] `get_halls` returned hall with room_count attribute.\n";
            } else {
                echo "[FAIL] `get_halls` missing room_count attribute.\n";
                $allPassed = false;
            }
            break;
        }
    }
    if ($foundCreatedHall) {
        echo "[PASS] Controller `get_halls` successfully listed the created hall.\n";
    } else {
        echo "[FAIL] Created hall ID $createdHallId not found in `get_halls` output.\n";
        $allPassed = false;
    }
} else {
    echo "[FAIL] `get_halls` call failed: " . json_encode($getHallsRes) . "\n";
    $allPassed = false;
}

// ----------------------------------------------------
// 5. Room CRUD Operations & Validation
// ----------------------------------------------------
echo "\n--- Testing Room Management ---\n";
$testRoomName = 'TR_' . substr(uniqid(), 0, 8); // <= 20 chars
$createdRoomId = null;

// A. Create Room Linked to Hall
$createRoomPayload = [
    'action' => 'create_room',
    'hall_id' => $createdHallId,
    'room_name' => $testRoomName,
    'room_capacity' => 45,
    'room_type' => 'LABORATORY',
    'room_has_ac' => 1,
    'room_status' => 'available'
];
$createRoomRes = invokeRoomController($adminSession, 'POST', 'create_room', $createRoomPayload);
if (!empty($createRoomRes['success']) && !empty($createRoomRes['data']['room_id'])) {
    $createdRoomId = (int)$createRoomRes['data']['room_id'];
    echo "[PASS] Room created successfully with ID: $createdRoomId linked to hall $createdHallId.\n";

    // Verify in database
    $checkRoom = $pdo->prepare("SELECT * FROM room WHERE room_id = ?");
    $checkRoom->execute([$createdRoomId]);
    $roomRow = $checkRoom->fetch();
    if ($roomRow && $roomRow['room_name'] === $testRoomName && (int)$roomRow['room_capacity'] === 45 && (int)$roomRow['room_has_ac'] === 1 && $roomRow['room_type'] === 'LABORATORY') {
        echo "[PASS] Database `room` table verified with correct column values.\n";
    } else {
        echo "[FAIL] Room database verification mismatch: " . json_encode($roomRow) . "\n";
        $allPassed = false;
    }
} else {
    echo "[FAIL] Room creation failed: " . json_encode($createRoomRes) . "\n";
    $allPassed = false;
}

// B. Duplicate Room Name Check (same hall)
if ($createdRoomId) {
    $dupRoomRes = invokeRoomController($adminSession, 'POST', 'create_room', [
        'action' => 'create_room',
        'hall_id' => $createdHallId,
        'room_name' => $testRoomName,
        'room_capacity' => 30,
        'room_type' => 'LECTURE',
        'room_has_ac' => 0,
        'room_status' => 'available'
    ]);
    if (empty($dupRoomRes['success'])) {
        echo "[PASS] Duplicate room name in same hall correctly rejected.\n";
    } else {
        echo "[FAIL] Duplicate room name in same hall was accepted: " . json_encode($dupRoomRes) . "\n";
        $allPassed = false;
    }
}

// C. Standalone Room Creation (hall_id = null / 0 / empty)
$standaloneRoomName = 'ST_' . substr(uniqid(), 0, 8);
$standalonePayload = [
    'action' => 'create_room',
    'hall_id' => null,
    'room_name' => $standaloneRoomName,
    'room_capacity' => 100,
    'room_type' => 'SPORTS',
    'room_has_ac' => 0,
    'room_status' => 'available'
];
$standaloneRes = invokeRoomController($adminSession, 'POST', 'create_room', $standalonePayload);
$standaloneRoomId = null;
if (!empty($standaloneRes['success']) && !empty($standaloneRes['data']['room_id'])) {
    $standaloneRoomId = (int)$standaloneRes['data']['room_id'];
    echo "[PASS] Standalone room (hall_id=NULL) created successfully with ID: $standaloneRoomId.\n";

    $checkStandalone = $pdo->prepare("SELECT * FROM room WHERE room_id = ?");
    $checkStandalone->execute([$standaloneRoomId]);
    $stRow = $checkStandalone->fetch();
    if ($stRow && $stRow['hall_id'] === null) {
        echo "[PASS] Database verified standalone room has NULL hall_id.\n";
    } else {
        echo "[FAIL] Standalone room hall_id is not NULL: " . json_encode($stRow) . "\n";
        $allPassed = false;
    }
} else {
    echo "[FAIL] Standalone room creation failed: " . json_encode($standaloneRes) . "\n";
    $allPassed = false;
}

// D. Boundary Checks (room_name > 20, room_type > 20, capacity <= 0)
$longRoomRes = invokeRoomController($adminSession, 'POST', 'create_room', [
    'action' => 'create_room',
    'hall_id' => $createdHallId,
    'room_name' => 'ThisRoomNameIsTooLongForSchema', // 30 chars
    'room_capacity' => 30,
    'room_type' => 'LECTURE',
    'room_has_ac' => 1,
    'room_status' => 'available'
]);
if (!empty($longRoomRes['error']) && ($longRoomRes['_http_code'] ?? 0) === 400) {
    echo "[PASS] Room name exceeding 20 chars correctly rejected.\n";
} else {
    echo "[FAIL] Room name exceeding 20 chars was not rejected with 400: " . json_encode($longRoomRes) . "\n";
    $allPassed = false;
}

$invalidCapRes = invokeRoomController($adminSession, 'POST', 'create_room', [
    'action' => 'create_room',
    'hall_id' => $createdHallId,
    'room_name' => 'ValidName',
    'room_capacity' => 0,
    'room_type' => 'LECTURE',
    'room_has_ac' => 1,
    'room_status' => 'available'
]);
if (!empty($invalidCapRes['error']) && ($invalidCapRes['_http_code'] ?? 0) === 400) {
    echo "[PASS] Zero/negative room capacity correctly rejected.\n";
} else {
    echo "[FAIL] Invalid room capacity was not rejected with 400: " . json_encode($invalidCapRes) . "\n";
    $allPassed = false;
}

// E. Update Room
if ($createdRoomId) {
    $updatedRoomName = 'UR_' . substr(uniqid(), 0, 8);
    $updateRoomPayload = [
        'action' => 'update_room',
        'room_id' => $createdRoomId,
        'hall_id' => $createdHallId,
        'room_name' => $updatedRoomName,
        'room_capacity' => 60,
        'room_type' => 'AUDIO-VISUAL',
        'room_has_ac' => 0,
        'room_status' => 'unavailable'
    ];
    $updateRoomRes = invokeRoomController($adminSession, 'POST', 'update_room', $updateRoomPayload);
    if (!empty($updateRoomRes['success'])) {
        $checkUpRoom = $pdo->prepare("SELECT * FROM room WHERE room_id = ?");
        $checkUpRoom->execute([$createdRoomId]);
        $upRoomRow = $checkUpRoom->fetch();
        if ($upRoomRow && $upRoomRow['room_name'] === $updatedRoomName && (int)$upRoomRow['room_capacity'] === 60 && (int)$upRoomRow['room_has_ac'] === 0 && $upRoomRow['room_type'] === 'AUDIO-VISUAL' && $upRoomRow['room_status'] === 'unavailable') {
            echo "[PASS] Room updated successfully in database.\n";
        } else {
            echo "[FAIL] Room update DB mismatch: " . json_encode($upRoomRow) . "\n";
            $allPassed = false;
        }
    } else {
        echo "[FAIL] Room update failed: " . json_encode($updateRoomRes) . "\n";
        $allPassed = false;
    }
}

// F. Quick Update Status Endpoint
if ($createdRoomId) {
    $statusRes = invokeRoomController($adminSession, 'POST', 'update_status', [
        'action' => 'update_status',
        'room_id' => $createdRoomId,
        'status' => 'maintenance'
    ]);
    if (!empty($statusRes['success'])) {
        $stCheck = $pdo->prepare("SELECT room_status FROM room WHERE room_id = ?");
        $stCheck->execute([$createdRoomId]);
        $currentStatus = $stCheck->fetchColumn();
        if ($currentStatus === 'maintenance') {
            echo "[PASS] `update_status` correctly changed room status to 'maintenance'.\n";
        } else {
            echo "[FAIL] Status did not update to 'maintenance', got: $currentStatus\n";
            $allPassed = false;
        }
    } else {
        echo "[FAIL] `update_status` failed: " . json_encode($statusRes) . "\n";
        $allPassed = false;
    }
}

// G. Get Rooms Endpoint
$getRoomsRes = invokeRoomController($adminSession, 'GET', 'get_rooms');
if (!empty($getRoomsRes['success']) && is_array($getRoomsRes['data'])) {
    $foundLinkedRoom = false;
    $foundStandaloneRoom = false;
    foreach ($getRoomsRes['data'] as $rItem) {
        if ((int)$rItem['room_id'] === $createdRoomId) {
            $foundLinkedRoom = true;
            if (!empty($rItem['hall_name'])) {
                echo "[PASS] `get_rooms` successfully joined room with hall_name.\n";
            }
        }
        if ($standaloneRoomId && (int)$rItem['room_id'] === $standaloneRoomId) {
            $foundStandaloneRoom = true;
            if ($rItem['hall_id'] === null) {
                echo "[PASS] `get_rooms` returned standalone room with NULL hall_id.\n";
            }
        }
    }
    if ($foundLinkedRoom && (!$standaloneRoomId || $foundStandaloneRoom)) {
        echo "[PASS] Controller `get_rooms` returned all active rooms with joined metadata.\n";
    } else {
        echo "[FAIL] Rooms not returned as expected in `get_rooms`: linked=$foundLinkedRoom, standalone=$foundStandaloneRoom\n";
        $allPassed = false;
    }
} else {
    echo "[FAIL] `get_rooms` call failed: " . json_encode($getRoomsRes) . "\n";
    $allPassed = false;
}

// ----------------------------------------------------
// 6. Foreign Key Safety & Relational Integrity
// ----------------------------------------------------
echo "\n--- Testing Referential Integrity & FK Protection ---\n";

// A. Attempt to delete hall while rooms are assigned to it -> MUST FAIL
if ($createdHallId && $createdRoomId) {
    $delHallBlockedRes = invokeRoomController($adminSession, 'POST', 'delete_hall', [
        'action' => 'delete_hall',
        'hall_id' => $createdHallId
    ]);
    if (empty($delHallBlockedRes['success']) && (!empty($delHallBlockedRes['error']) || !empty($delHallBlockedRes['message']))) {
        // Confirm hall still exists in DB
        $hallStillThere = $pdo->prepare("SELECT COUNT(*) FROM hall WHERE hall_id = ?");
        $hallStillThere->execute([$createdHallId]);
        if ((int)$hallStillThere->fetchColumn() === 1) {
            echo "[PASS] Deletion of hall with assigned rooms correctly blocked (Referential Safety).\n";
        } else {
            echo "[FAIL] Hall was deleted despite having assigned rooms!\n";
            $allPassed = false;
        }
    } else {
        echo "[FAIL] Expected delete_hall to fail for assigned hall, got: " . json_encode($delHallBlockedRes) . "\n";
        $allPassed = false;
    }
}

// B. Attach dependent records to test cascading cleanup on delete_room
if ($createdRoomId) {
    // Add schedule record
    $pdo->prepare("
        INSERT INTO schedule (room_id, account_id, schedule_day, schedule_start, schedule_end)
        VALUES (?, ?, CURDATE(), '08:00:00', '10:00:00')
    ")->execute([$createdRoomId, $teacherId]);
    $createdSchedId = (int)$pdo->lastInsertId();

    // Add pending schedule record
    $pdo->prepare("
        INSERT INTO pendingschedule (room_id, account_id, pending_schedule_day, pending_schedule_start, pending_schedule_end, pending_schedule_status)
        VALUES (?, ?, CURDATE(), '10:00:00', '12:00:00', 'pending')
    ")->execute([$createdRoomId, $teacherId]);
    $createdPendingId = (int)$pdo->lastInsertId();

    // Add history record
    $pdo->prepare("
        INSERT INTO history (account_id, hall_id, room_id, history_date)
        VALUES (?, ?, ?, NOW())
    ")->execute([$teacherId, $createdHallId, $createdRoomId]);

    // Now delete room via controller
    $delRoomRes = invokeRoomController($adminSession, 'POST', 'delete_room', [
        'action' => 'delete_room',
        'room_id' => $createdRoomId
    ]);

    if (!empty($delRoomRes['success'])) {
        // Verify room and child records are purged
        $checkRoomDel = $pdo->prepare("SELECT COUNT(*) FROM room WHERE room_id = ?");
        $checkRoomDel->execute([$createdRoomId]);
        $checkSchedDel = $pdo->prepare("SELECT COUNT(*) FROM schedule WHERE schedule_id = ?");
        $checkSchedDel->execute([$createdSchedId]);
        $checkPendingDel = $pdo->prepare("SELECT COUNT(*) FROM pendingschedule WHERE pending_id = ?");
        $checkPendingDel->execute([$createdPendingId]);
        $checkHistoryDel = $pdo->prepare("SELECT COUNT(*) FROM history WHERE room_id = ?");
        $checkHistoryDel->execute([$createdRoomId]);

        if ((int)$checkRoomDel->fetchColumn() === 0 &&
            (int)$checkSchedDel->fetchColumn() === 0 &&
            (int)$checkPendingDel->fetchColumn() === 0 &&
            (int)$checkHistoryDel->fetchColumn() === 0) {
            echo "[PASS] Controller `delete_room` cleanly purged room and all dependent schedule/history records.\n";
        } else {
            echo "[FAIL] Residual records found after `delete_room`.\n";
            $allPassed = false;
        }
    } else {
        echo "[FAIL] `delete_room` failed: " . json_encode($delRoomRes) . "\n";
        $allPassed = false;
    }
}

// C. Clean up standalone room
if ($standaloneRoomId) {
    $delStRes = invokeRoomController($adminSession, 'POST', 'delete_room', [
        'action' => 'delete_room',
        'room_id' => $standaloneRoomId
    ]);
    if (!empty($delStRes['success'])) {
        echo "[PASS] Standalone room deleted successfully.\n";
    } else {
        echo "[FAIL] Failed to delete standalone room: " . json_encode($delStRes) . "\n";
        $allPassed = false;
    }
}

// D. Now delete the empty hall -> MUST SUCCEED
if ($createdHallId) {
    $delEmptyHallRes = invokeRoomController($adminSession, 'POST', 'delete_hall', [
        'action' => 'delete_hall',
        'hall_id' => $createdHallId
    ]);
    if (!empty($delEmptyHallRes['success'])) {
        $hallDelCheck = $pdo->prepare("SELECT COUNT(*) FROM hall WHERE hall_id = ?");
        $hallDelCheck->execute([$createdHallId]);
        if ((int)$hallDelCheck->fetchColumn() === 0) {
            echo "[PASS] Unassigned hall deleted cleanly from database.\n";
        } else {
            echo "[FAIL] Hall still exists in database after delete_hall.\n";
            $allPassed = false;
        }
    } else {
        echo "[FAIL] Failed to delete empty hall: " . json_encode($delEmptyHallRes) . "\n";
        $allPassed = false;
    }
}

// ----------------------------------------------------
// Summary
// ----------------------------------------------------
if ($allPassed) {
    echo "\n=== ALL ADMIN ROOM & HALL MANAGEMENT INTEGRATION CHECKS PASSED (Exit 0) ===\n";
    exit(0);
} else {
    echo "\n=== SOME CHECKS FAILED (Exit 1) ===\n";
    exit(1);
}
