<?php
/**
 * Automated Verification Test for Admin Teacher Account Management
 * Thoroughly exercises app/controllers/admin_teachers_controller.php via subrequests,
 * verifying database integrity across all related tables (account, settings, notification),
 * edge case validation, method enforcement, and admin sidebar consistency.
 */

$rootDir = dirname(__DIR__);
require_once $rootDir . '/includes/database_include.php';

echo "=== Admin Teacher Management Test Suite ===\n";
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
// 1. File existence & PHP syntax checks
// ----------------------------------------------------
$requiredFiles = [
    $rootDir . '/app/controllers/admin_teachers_controller.php',
    $rootDir . '/app/views/admin_manage_teachers_view.php',
    $rootDir . '/public/css/admin_manage_teachers_style.css',
    $rootDir . '/public/js/admin_manage_teachers_script.js'
];

foreach ($requiredFiles as $file) {
    if (!file_exists($file)) {
        echo "[FAIL] Missing required file: $file\n";
        $allPassed = false;
    } else {
        if (str_ends_with($file, '.php')) {
            exec("php -l " . escapeshellarg($file) . " 2>&1", $lintOutput, $lintCode);
            if ($lintCode !== 0) {
                echo "[FAIL] Syntax error in $file\n";
                $allPassed = false;
            }
        }
    }
}
if ($allPassed) {
    echo "[PASS] All controller, view, CSS, and JS files exist with valid syntax.\n";
}

// ----------------------------------------------------
// 2. Admin Sidebar Consistency Check
// ----------------------------------------------------
$adminViews = [
    'admin_homepage_view.php',
    'admin_manage_rooms_view.php',
    'admin_manage_teachers_view.php',
    'admin_reports_view.php',
    'admin_messages_view.php',
    'admin_setting_view.php'
];

foreach ($adminViews as $viewName) {
    $path = $rootDir . '/app/views/' . $viewName;
    if (!file_exists($path)) {
        echo "[FAIL] Admin view missing: $viewName\n";
        $allPassed = false;
        continue;
    }

    $content = file_get_contents($path);
    $requiredLinks = [
        'admin_homepage_view.php' => 'Dashboard',
        'admin_manage_rooms_view.php' => 'Manage Rooms',
        'admin_manage_teachers_view.php' => 'Teachers',
        'admin_reports_view.php' => 'Reports',
        'admin_messages_view.php' => 'Messages',
        'admin_setting_view.php' => 'Settings'
    ];

    foreach ($requiredLinks as $linkHref => $linkText) {
        if (!str_contains($content, $linkHref)) {
            echo "[FAIL] $viewName is missing link to $linkHref ($linkText)\n";
            $allPassed = false;
        }
    }
}
echo "[PASS] Admin sidebar consistency verified across all 6 admin views.\n";

// ----------------------------------------------------
// 3. Controller Subrequest Invoker Helper
// ----------------------------------------------------
function invokeTeacherController(array $session, string $method, ?string $actionQuery, ?array $postPayload = null): array {
    $runnerScript = __DIR__ . '/_run_teacher_subrequest.php';

    $sessionExport = var_export($session, true);
    $methodExport = var_export(strtoupper($method), true);
    $queryExport = var_export($actionQuery, true);
    $payloadJson = $postPayload !== null ? json_encode($postPayload) : '';

    $code = '<?php
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
    // Mock php://input via custom stream wrapper or global assignment
    $_POST = json_decode($rawPayload, true) ?: [];
}
// Capture output
ob_start();
require dirname(__DIR__) . "/app/controllers/admin_teachers_controller.php";
$output = ob_get_clean();
echo $output;
';
    file_put_contents($runnerScript, $code);

    $cmd = 'php ' . escapeshellarg($runnerScript);
    $output = shell_exec($cmd);
    @unlink($runnerScript);

    $res = json_decode($output ?? '', true);
    return is_array($res) ? $res : ["raw" => $output];
}

// ----------------------------------------------------
// 4. Test Controller Authorization & Method Enforcement
// ----------------------------------------------------
// A. Unauthorized (no session)
$unauthRes = invokeTeacherController([], 'GET', 'get_teachers');
if (isset($unauthRes['error']) && $unauthRes['error'] === 'unauthorized') {
    echo "[PASS] Unauthorized request without session rejected.\n";
} else {
    echo "[FAIL] Unauthorized request was not rejected properly: " . json_encode($unauthRes) . "\n";
    $allPassed = false;
}

// B. Forbidden (non-admin session)
$nonAdminRes = invokeTeacherController(["id" => 2, "is_admin" => 0], 'GET', 'get_teachers');
if (isset($nonAdminRes['error']) && ($nonAdminRes['error'] === 'unauthorized' || $nonAdminRes['error'] === 'forbidden')) {
    echo "[PASS] Non-admin session forbidden.\n";
} else {
    echo "[FAIL] Non-admin session was not forbidden properly: " . json_encode($nonAdminRes) . "\n";
    $allPassed = false;
}

// C. Method not allowed (GET request on mutation action)
$adminSession = ["id" => 1, "is_admin" => 1];
$methodRes = invokeTeacherController($adminSession, 'GET', 'create_teacher');
if (isset($methodRes['error']) && $methodRes['error'] === 'method_not_allowed') {
    echo "[PASS] GET method on mutation endpoint rejected with 405 method_not_allowed.\n";
} else {
    echo "[FAIL] Mutation endpoint allowed GET method: " . json_encode($methodRes) . "\n";
    $allPassed = false;
}

// ----------------------------------------------------
// 5. Test Controller CRUD & Multi-Table Database Coverage
// ----------------------------------------------------
$testEmail = 'prof.subreq.tester@fcpc.edu.ph';
$testUser = 'subreq_prof';
$testFname = 'Diana';
$testLname = 'Prince';
$testPass = 'Amazon#2026!';

// Cleanup beforehand
$pdo->prepare("DELETE FROM settings WHERE account_id IN (SELECT account_id FROM account WHERE account_email = ?)")->execute([$testEmail]);
$pdo->prepare("DELETE FROM notification WHERE account_id IN (SELECT account_id FROM account WHERE account_email = ?)")->execute([$testEmail]);
$pdo->prepare("DELETE FROM account WHERE account_email = ?")->execute([$testEmail]);

// --- A. CREATE TEACHER VIA CONTROLLER ---
$createPayload = [
    "action" => "create_teacher",
    "fname" => $testFname,
    "lname" => $testLname,
    "email" => $testEmail,
    "username" => $testUser,
    "password" => $testPass,
    "dark_mode" => 1,
    "language" => "fil"
];

$createRes = invokeTeacherController($adminSession, 'POST', 'create_teacher', $createPayload);

if (!empty($createRes['success']) && !empty($createRes['data']['account_id'])) {
    $createdTeacherId = (int)$createRes['data']['account_id'];
    echo "[PASS] Teacher created via controller successfully (ID: $createdTeacherId).\n";

    // Verify `account` table
    $accRow = $pdo->prepare("SELECT * FROM account WHERE account_id = ?");
    $accRow->execute([$createdTeacherId]);
    $acc = $accRow->fetch();

    if ($acc && $acc['account_email'] === $testEmail &&
        $acc['account_username'] === $testUser &&
        (int)$acc['account_is_admin'] === 0 &&
        password_verify($testPass, $acc['account_password'])) {
        echo "[PASS] Database `account` table verified with correct columns and password hash.\n";
    } else {
        echo "[FAIL] Database `account` table mismatch.\n";
        $allPassed = false;
    }

    // Verify `settings` table
    $setRow = $pdo->prepare("SELECT * FROM settings WHERE account_id = ?");
    $setRow->execute([$createdTeacherId]);
    $set = $setRow->fetch();

    if ($set && (int)$set['setting_dark_mode'] === 1 && $set['setting_language'] === 'fil') {
        echo "[PASS] Database `settings` table populated with dark_mode=1 and language='fil'.\n";
    } else {
        echo "[FAIL] Database `settings` table missing or invalid.\n";
        $allPassed = false;
    }

    // Verify `notification` table
    $notifRow = $pdo->prepare("SELECT * FROM notification WHERE account_id = ?");
    $notifRow->execute([$createdTeacherId]);
    $notif = $notifRow->fetch();

    if ($notif && str_contains($notif['notif_title'], 'Welcome') && (int)$notif['notif_is_read'] === 0) {
        echo "[PASS] Database `notification` table populated with welcome notification.\n";
    } else {
        echo "[FAIL] Database `notification` table missing or invalid.\n";
        $allPassed = false;
    }

    // --- B. GET TEACHERS VIA CONTROLLER ---
    $getRes = invokeTeacherController($adminSession, 'GET', 'get_teachers');
    if (!empty($getRes['success']) && is_array($getRes['data'])) {
        $foundInList = false;
        foreach ($getRes['data'] as $t) {
            if ((int)$t['account_id'] === $createdTeacherId) {
                $foundInList = true;
                break;
            }
        }
        if ($foundInList && isset($getRes['stats']['total_teachers'])) {
            echo "[PASS] Controller `get_teachers` returned newly provisioned teacher with summary stats.\n";
        } else {
            echo "[FAIL] Newly created teacher not found in `get_teachers` response.\n";
            $allPassed = false;
        }
    } else {
        echo "[FAIL] `get_teachers` controller call failed: " . json_encode($getRes) . "\n";
        $allPassed = false;
    }

    // --- C. UPDATE TEACHER VIA CONTROLLER ---
    $updatedEmail = 'diana.updated@fcpc.edu.ph';
    $updatedUser = 'diana_updated';
    $updatePayload = [
        "action" => "update_teacher",
        "account_id" => $createdTeacherId,
        "fname" => "Diana Updated",
        "lname" => "Prince",
        "email" => $updatedEmail,
        "username" => $updatedUser,
        "dark_mode" => 0,
        "language" => "en"
    ];
    $updateRes = invokeTeacherController($adminSession, 'POST', 'update_teacher', $updatePayload);

    if (!empty($updateRes['success'])) {
        $accCheck = $pdo->query("SELECT * FROM account WHERE account_id = $createdTeacherId")->fetch();
        $setCheck = $pdo->query("SELECT * FROM settings WHERE account_id = $createdTeacherId")->fetch();

        if ($accCheck['account_email'] === $updatedEmail &&
            $accCheck['account_username'] === $updatedUser &&
            (int)$setCheck['setting_dark_mode'] === 0 &&
            $setCheck['setting_language'] === 'en') {
            echo "[PASS] Controller `update_teacher` updated account and settings in database.\n";
        } else {
            echo "[FAIL] Update verification failed in database.\n";
            $allPassed = false;
        }
    } else {
        echo "[FAIL] Controller `update_teacher` failed: " . json_encode($updateRes) . "\n";
        $allPassed = false;
    }

    // --- D. RESET PASSWORD VIA CONTROLLER ---
    $newSecret = 'NewKrypton#2026!';
    $resetPayload = [
        "action" => "reset_password",
        "account_id" => $createdTeacherId,
        "password" => $newSecret
    ];
    $resetRes = invokeTeacherController($adminSession, 'POST', 'reset_password', $resetPayload);

    if (!empty($resetRes['success'])) {
        $resetAcc = $pdo->query("SELECT account_password FROM account WHERE account_id = $createdTeacherId")->fetch();
        if (password_verify($newSecret, $resetAcc['account_password'])) {
            echo "[PASS] Controller `reset_password` updated password hash and passed verification.\n";
        } else {
            echo "[FAIL] Password hash verification failed.\n";
            $allPassed = false;
        }
    } else {
        echo "[FAIL] Controller `reset_password` failed: " . json_encode($resetRes) . "\n";
        $allPassed = false;
    }

    // --- E. VALIDATION EDGE CASES VIA CONTROLLER ---
    // Blank sanitized username rejection
    $blankUserPayload = [
        "action" => "update_teacher",
        "account_id" => $createdTeacherId,
        "fname" => "Valid",
        "lname" => "Name",
        "email" => $updatedEmail,
        "username" => "   !!!@@@###   ",
        "dark_mode" => 0,
        "language" => "en"
    ];
    $blankRes = invokeTeacherController($adminSession, 'POST', 'update_teacher', $blankUserPayload);
    if (!empty($blankRes['error']) && $blankRes['error'] === 'invalid_username') {
        echo "[PASS] Controller correctly rejected blank/invalid sanitized username on update.\n";
    } else {
        echo "[FAIL] Controller allowed blank sanitized username: " . json_encode($blankRes) . "\n";
        $allPassed = false;
    }

    // Length limit checks (name > 80 chars, email > 100 chars)
    $longNamePayload = [
        "action" => "create_teacher",
        "fname" => str_repeat("A", 85),
        "lname" => "Valid",
        "email" => "toolong@fcpc.edu.ph",
        "username" => "validname",
        "password" => "ValidPass#123",
        "dark_mode" => 0,
        "language" => "en"
    ];
    $longNameRes = invokeTeacherController($adminSession, 'POST', 'create_teacher', $longNamePayload);
    if (!empty($longNameRes['error']) && $longNameRes['error'] === 'name_too_long') {
        echo "[PASS] Controller correctly rejected name exceeding 80 characters.\n";
    } else {
        echo "[FAIL] Controller failed to reject oversized name: " . json_encode($longNameRes) . "\n";
        $allPassed = false;
    }

    // --- F. DELETE TEACHER VIA CONTROLLER ---
    $deletePayload = [
        "action" => "delete_teacher",
        "account_id" => $createdTeacherId
    ];
    $deleteRes = invokeTeacherController($adminSession, 'POST', 'delete_teacher', $deletePayload);

    if (!empty($deleteRes['success'])) {
        $delAcc = $pdo->query("SELECT COUNT(*) FROM account WHERE account_id = $createdTeacherId")->fetchColumn();
        $delSet = $pdo->query("SELECT COUNT(*) FROM settings WHERE account_id = $createdTeacherId")->fetchColumn();
        $delNotif = $pdo->query("SELECT COUNT(*) FROM notification WHERE account_id = $createdTeacherId")->fetchColumn();

        if ((int)$delAcc === 0 && (int)$delSet === 0 && (int)$delNotif === 0) {
            echo "[PASS] Controller `delete_teacher` cleanly purged account, settings, and notification.\n";
        } else {
            echo "[FAIL] Residual database records found after deletion.\n";
            $allPassed = false;
        }
    } else {
        echo "[FAIL] Controller `delete_teacher` failed: " . json_encode($deleteRes) . "\n";
        $allPassed = false;
    }
} else {
    echo "[FAIL] Teacher creation via controller failed: " . json_encode($createRes) . "\n";
    $allPassed = false;
}

if ($allPassed) {
    echo "\n=== ALL ADMIN TEACHER MANAGEMENT INTEGRATION CHECKS PASSED (Exit 0) ===\n";
    exit(0);
} else {
    echo "\n=== SOME CHECKS FAILED (Exit 1) ===\n";
    exit(1);
}
