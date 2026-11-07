<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

// Session check
if (!isset($_SESSION["id"]) || empty($_SESSION["is_admin"])) {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "unauthorized", "message" => "Admin access required."]);
    exit;
}

require_once __DIR__ . "/../../includes/database_include.php";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // Secondary DB check to confirm requester is indeed admin
    $adminStmt = $pdo->prepare("SELECT account_is_admin FROM account WHERE account_id = ?");
    $adminStmt->execute([$_SESSION["id"]]);
    $adminRow = $adminStmt->fetch();
    if (!$adminRow || (int)$adminRow['account_is_admin'] !== 1) {
        http_response_code(403);
        echo json_encode(["success" => false, "error" => "forbidden", "message" => "Administrative privileges required."]);
        exit;
    }
} catch (PDOException $e) {
    error_log("Database connection error in admin_teachers_controller: " . $e->getMessage());
    echo json_encode(["success" => false, "error" => "db_error", "message" => "Database connection failure."]);
    exit;
}

// Support both GET query action and POST/JSON action
$action = $_GET["action"] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($action)) {
    $postBody = json_decode(file_get_contents('php://input'), true);
    if (is_array($postBody) && !empty($postBody['action'])) {
        $action = $postBody['action'];
    } elseif (!empty($_POST['action'])) {
        $action = $_POST['action'];
    }
}

// Enforce POST method for mutation endpoints (RFC 7231 / OWASP)
$mutationActions = ['create_teacher', 'update_teacher', 'reset_password', 'delete_teacher'];
if (in_array($action, $mutationActions, true) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "method_not_allowed", "message" => "POST method required for this operation."]);
    exit;
}

switch ($action) {
    case 'get_teachers':
        handleGetTeachers($pdo);
        break;

    case 'create_teacher':
        handleCreateTeacher($pdo);
        break;

    case 'update_teacher':
        handleUpdateTeacher($pdo);
        break;

    case 'reset_password':
        handleResetPassword($pdo);
        break;

    case 'delete_teacher':
        handleDeleteTeacher($pdo);
        break;

    default:
        echo json_encode(["success" => false, "error" => "invalid_action", "message" => "Unknown action requested."]);
        break;
}

/**
 * Fetch all teachers (account_is_admin = 0) with their settings and reservation statistics.
 */
function handleGetTeachers(PDO $pdo): void {
    try {
        $sql = "
            SELECT 
                a.account_id,
                a.account_username,
                a.account_fname,
                a.account_lname,
                a.account_email,
                a.account_is_admin,
                COALESCE(s.setting_dark_mode, 0) AS setting_dark_mode,
                COALESCE(s.setting_language, 'en') AS setting_language,
                COALESCE(sched_counts.active_schedules, 0) AS active_schedules,
                COALESCE(pend_counts.pending_schedules, 0) AS pending_schedules
            FROM account a
            LEFT JOIN settings s ON a.account_id = s.account_id
            LEFT JOIN (
                SELECT account_id, COUNT(*) AS active_schedules 
                FROM schedule 
                WHERE (schedule_day IS NOT NULL AND schedule_day >= CURDATE())
                   OR (schedule_day IS NULL AND (schedule_end_date IS NULL OR schedule_end_date >= CURDATE()))
                GROUP BY account_id
            ) sched_counts ON a.account_id = sched_counts.account_id
            LEFT JOIN (
                SELECT account_id, COUNT(*) AS pending_schedules 
                FROM pendingschedule 
                WHERE pending_schedule_status = 'pending'
                GROUP BY account_id
            ) pend_counts ON a.account_id = pend_counts.account_id
            WHERE a.account_is_admin = 0
            ORDER BY a.account_id DESC
        ";

        $stmt = $pdo->query($sql);
        $teachers = $stmt->fetchAll();

        // Calculate summary statistics
        $totalTeachers = count($teachers);
        $withBookings = 0;
        $totalPending = 0;

        foreach ($teachers as $t) {
            if ((int)$t['active_schedules'] > 0) {
                $withBookings++;
            }
            $totalPending += (int)$t['pending_schedules'];
        }

        echo json_encode([
            "success" => true,
            "data" => $teachers,
            "stats" => [
                "total_teachers" => $totalTeachers,
                "with_bookings" => $withBookings,
                "total_pending" => $totalPending
            ]
        ]);
    } catch (PDOException $e) {
        error_log("Error in handleGetTeachers: " . $e->getMessage());
        echo json_encode(["success" => false, "error" => "db_error", "message" => "Failed to load teachers list."]);
    }
}

/**
 * Create a new teacher account and populate all associated tables (account, settings, notification).
 */
function handleCreateTeacher(PDO $pdo): void {
    $input = getRequestPayload();

    $fname = trim($input["fname"] ?? '');
    $lname = trim($input["lname"] ?? '');
    $email = strtolower(trim($input["email"] ?? ''));
    $rawUsername = trim($input["username"] ?? '');
    $password = $input["password"] ?? '';
    $darkMode = isset($input["dark_mode"]) ? (int)$input["dark_mode"] : 0;
    $language = trim($input["language"] ?? 'en');

    // Validation
    if ($fname === '' || $lname === '' || $email === '' || $password === '') {
        echo json_encode(["success" => false, "error" => "missing_fields", "message" => "First name, last name, email, and password are required."]);
        return;
    }

    if (mb_strlen($fname) > 80 || mb_strlen($lname) > 80) {
        echo json_encode(["success" => false, "error" => "name_too_long", "message" => "First and last names cannot exceed 80 characters."]);
        return;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(["success" => false, "error" => "invalid_email", "message" => "Please provide a valid email address."]);
        return;
    }

    // spGetAccountInfo limit is 100 characters for email and username
    if (strlen($email) > 100) {
        echo json_encode(["success" => false, "error" => "invalid_email", "message" => "Email address cannot exceed 100 characters."]);
        return;
    }

    if (strlen($password) < 6) {
        echo json_encode(["success" => false, "error" => "weak_password", "message" => "Password must be at least 6 characters."]);
        return;
    }

    // Sanitize username first to prevent blank strings from bypass
    $username = strtolower(preg_replace('/[^a-zA-Z0-9_.-]/', '', $rawUsername));
    if ($username === '') {
        $cleanEmailPrefix = preg_replace('/[^a-zA-Z0-9_]/', '', explode('@', $email)[0]);
        $username = strtolower($cleanEmailPrefix);
        if ($username === '') {
            $username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $fname . $lname));
        }
    }

    if (strlen($username) > 100) {
        echo json_encode(["success" => false, "error" => "invalid_username", "message" => "Username cannot exceed 100 characters."]);
        return;
    }

    // Enforce allowed languages
    $allowedLangs = ['en', 'fil', 'es'];
    if (!in_array($language, $allowedLangs, true)) {
        $language = 'en';
    }
    $darkMode = ($darkMode === 1) ? 1 : 0;

    $pdo->beginTransaction();
    try {
        // Check uniqueness of email
        $chkEmail = $pdo->prepare("SELECT account_id FROM account WHERE account_email = ? FOR UPDATE");
        $chkEmail->execute([$email]);
        if ($chkEmail->fetch()) {
            $pdo->rollBack();
            echo json_encode(["success" => false, "error" => "email_taken", "message" => "A user with this email address already exists."]);
            return;
        }

        // Check uniqueness of username, append number if auto-generated collision occurs
        $chkUser = $pdo->prepare("SELECT account_id FROM account WHERE account_username = ? FOR UPDATE");
        $chkUser->execute([$username]);
        if ($chkUser->fetch()) {
            if ($rawUsername === '') {
                $suffix = 1;
                $origUser = $username;
                do {
                    $username = substr($origUser, 0, 94) . $suffix;
                    $chkUser->execute([$username]);
                    $suffix++;
                } while ($chkUser->fetch());
            } else {
                $pdo->rollBack();
                echo json_encode(["success" => false, "error" => "username_taken", "message" => "This username is already taken. Please choose another."]);
                return;
            }
        }

        // Hash password securely
        $hash = password_hash($password, PASSWORD_DEFAULT);
        if ($hash === false) {
            throw new RuntimeException("Failed to hash password.");
        }

        // 1. Insert into `account`
        $stmtAcc = $pdo->prepare("
            INSERT INTO account (account_username, account_password, account_lname, account_fname, account_is_admin, account_email)
            VALUES (?, ?, ?, ?, 0, ?)
        ");
        $stmtAcc->execute([$username, $hash, $lname, $fname, $email]);
        $teacherId = (int)$pdo->lastInsertId();

        // 2. Insert into `settings` (Ensures setting table has complete record for teacher)
        $stmtSet = $pdo->prepare("
            INSERT INTO settings (account_id, setting_dark_mode, setting_language)
            VALUES (?, ?, ?)
        ");
        $stmtSet->execute([$teacherId, $darkMode, $language]);

        // 3. Insert welcome notification into `notification`
        $welcomeTitle = "Welcome to ClassSpace!";
        $welcomeMsg = "Welcome, {$fname}! Your faculty teacher account has been created by the system administrator. You can now browse campus spaces, request room reservations, and manage your teaching schedule.";
        $stmtNotif = $pdo->prepare("
            INSERT INTO notification (account_id, notif_type, notif_title, notif_message, notif_is_read, notif_created)
            VALUES (?, 'system', ?, ?, 0, NOW())
        ");
        $stmtNotif->execute([$teacherId, $welcomeTitle, $welcomeMsg]);

        $pdo->commit();

        echo json_encode([
            "success" => true,
            "message" => "Teacher account successfully created.",
            "data" => [
                "account_id" => $teacherId,
                "account_username" => $username,
                "account_fname" => $fname,
                "account_lname" => $lname,
                "account_email" => $email,
                "setting_dark_mode" => $darkMode,
                "setting_language" => $language
            ]
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error in handleCreateTeacher: " . $e->getMessage());
        echo json_encode(["success" => false, "error" => "create_failed", "message" => "Failed to create teacher account. Please try again."]);
    }
}

/**
 * Update teacher details (name, email, username, preferences).
 */
function handleUpdateTeacher(PDO $pdo): void {
    $input = getRequestPayload();

    $teacherId = isset($input["account_id"]) ? (int)$input["account_id"] : 0;
    $fname = trim($input["fname"] ?? '');
    $lname = trim($input["lname"] ?? '');
    $email = strtolower(trim($input["email"] ?? ''));
    $rawUsername = trim($input["username"] ?? '');
    $darkMode = isset($input["dark_mode"]) ? (int)$input["dark_mode"] : 0;
    $language = trim($input["language"] ?? 'en');

    if ($teacherId <= 0 || $fname === '' || $lname === '' || $email === '' || $rawUsername === '') {
        echo json_encode(["success" => false, "error" => "missing_fields", "message" => "All fields are required."]);
        return;
    }

    if (mb_strlen($fname) > 80 || mb_strlen($lname) > 80) {
        echo json_encode(["success" => false, "error" => "name_too_long", "message" => "First and last names cannot exceed 80 characters."]);
        return;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(["success" => false, "error" => "invalid_email", "message" => "Please provide a valid email address."]);
        return;
    }

    if (strlen($email) > 100) {
        echo json_encode(["success" => false, "error" => "invalid_email", "message" => "Email address cannot exceed 100 characters."]);
        return;
    }

    $username = strtolower(preg_replace('/[^a-zA-Z0-9_.-]/', '', $rawUsername));
    if ($username === '') {
        echo json_encode(["success" => false, "error" => "invalid_username", "message" => "Username must contain valid alphanumeric characters."]);
        return;
    }

    if (strlen($username) > 100) {
        echo json_encode(["success" => false, "error" => "invalid_username", "message" => "Username cannot exceed 100 characters."]);
        return;
    }

    $allowedLangs = ['en', 'fil', 'es'];
    if (!in_array($language, $allowedLangs, true)) {
        $language = 'en';
    }
    $darkMode = ($darkMode === 1) ? 1 : 0;

    $pdo->beginTransaction();
    try {
        // Confirm teacher exists and is not an admin
        $chkAcc = $pdo->prepare("SELECT account_id, account_is_admin FROM account WHERE account_id = ? FOR UPDATE");
        $chkAcc->execute([$teacherId]);
        $existing = $chkAcc->fetch();

        if (!$existing) {
            $pdo->rollBack();
            echo json_encode(["success" => false, "error" => "not_found", "message" => "Teacher account not found."]);
            return;
        }

        if ((int)$existing['account_is_admin'] === 1) {
            $pdo->rollBack();
            echo json_encode(["success" => false, "error" => "forbidden", "message" => "Cannot edit administrator accounts from teacher manager."]);
            return;
        }

        // Email uniqueness among other accounts
        $chkEmail = $pdo->prepare("SELECT account_id FROM account WHERE account_email = ? AND account_id <> ?");
        $chkEmail->execute([$email, $teacherId]);
        if ($chkEmail->fetch()) {
            $pdo->rollBack();
            echo json_encode(["success" => false, "error" => "email_taken", "message" => "This email is already associated with another account."]);
            return;
        }

        // Username uniqueness among other accounts
        $chkUser = $pdo->prepare("SELECT account_id FROM account WHERE account_username = ? AND account_id <> ?");
        $chkUser->execute([$username, $teacherId]);
        if ($chkUser->fetch()) {
            $pdo->rollBack();
            echo json_encode(["success" => false, "error" => "username_taken", "message" => "This username is already taken."]);
            return;
        }

        // 1. Update `account`
        $updAcc = $pdo->prepare("
            UPDATE account 
            SET account_fname = ?, account_lname = ?, account_email = ?, account_username = ?
            WHERE account_id = ?
        ");
        $updAcc->execute([$fname, $lname, $email, $username, $teacherId]);

        // 2. Update or insert `settings`
        $chkSet = $pdo->prepare("SELECT setting_id FROM settings WHERE account_id = ?");
        $chkSet->execute([$teacherId]);
        if ($chkSet->fetch()) {
            $updSet = $pdo->prepare("
                UPDATE settings 
                SET setting_dark_mode = ?, setting_language = ? 
                WHERE account_id = ?
            ");
            $updSet->execute([$darkMode, $language, $teacherId]);
        } else {
            $insSet = $pdo->prepare("
                INSERT INTO settings (account_id, setting_dark_mode, setting_language)
                VALUES (?, ?, ?)
            ");
            $insSet->execute([$teacherId, $darkMode, $language]);
        }

        $pdo->commit();

        echo json_encode([
            "success" => true,
            "message" => "Teacher details updated successfully."
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error in handleUpdateTeacher: " . $e->getMessage());
        echo json_encode(["success" => false, "error" => "update_failed", "message" => "Failed to update teacher. Please try again."]);
    }
}

/**
 * Reset a teacher's password.
 */
function handleResetPassword(PDO $pdo): void {
    $input = getRequestPayload();

    $teacherId = isset($input["account_id"]) ? (int)$input["account_id"] : 0;
    $newPassword = $input["password"] ?? '';

    if ($teacherId <= 0 || empty($newPassword)) {
        echo json_encode(["success" => false, "error" => "missing_fields", "message" => "Teacher ID and new password are required."]);
        return;
    }

    if (strlen($newPassword) < 6) {
        echo json_encode(["success" => false, "error" => "weak_password", "message" => "Password must be at least 6 characters."]);
        return;
    }

    $pdo->beginTransaction();
    try {
        $chkAcc = $pdo->prepare("SELECT account_id, account_fname, account_is_admin FROM account WHERE account_id = ? FOR UPDATE");
        $chkAcc->execute([$teacherId]);
        $existing = $chkAcc->fetch();

        if (!$existing) {
            $pdo->rollBack();
            echo json_encode(["success" => false, "error" => "not_found", "message" => "Teacher account not found."]);
            return;
        }

        if ((int)$existing['account_is_admin'] === 1) {
            $pdo->rollBack();
            echo json_encode(["success" => false, "error" => "forbidden", "message" => "Cannot reset password for administrators through this interface."]);
            return;
        }

        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        if ($hash === false) {
            throw new RuntimeException("Password hash generation failed.");
        }

        // Update password
        $upd = $pdo->prepare("UPDATE account SET account_password = ? WHERE account_id = ?");
        $upd->execute([$hash, $teacherId]);

        // Security notification
        $notifStmt = $pdo->prepare("
            INSERT INTO notification (account_id, notif_type, notif_title, notif_message, notif_is_read, notif_created)
            VALUES (?, 'system', 'Security Notice: Password Reset', 'Your account password has been reset by the system administrator.', 0, NOW())
        ");
        $notifStmt->execute([$teacherId]);

        $pdo->commit();

        echo json_encode([
            "success" => true,
            "message" => "Password for {$existing['account_fname']} successfully reset."
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error in handleResetPassword: " . $e->getMessage());
        echo json_encode(["success" => false, "error" => "reset_failed", "message" => "Failed to reset password. Please try again."]);
    }
}

/**
 * Safely delete teacher account and all related database entries (respecting foreign key integrity).
 */
function handleDeleteTeacher(PDO $pdo): void {
    $input = getRequestPayload();

    $teacherId = isset($input["account_id"]) ? (int)$input["account_id"] : 0;

    if ($teacherId <= 0) {
        echo json_encode(["success" => false, "error" => "invalid_id", "message" => "Valid teacher ID is required."]);
        return;
    }

    if ($teacherId === (int)$_SESSION["id"]) {
        echo json_encode(["success" => false, "error" => "self_delete", "message" => "You cannot delete your own account."]);
        return;
    }

    $pdo->beginTransaction();
    try {
        $chkAcc = $pdo->prepare("SELECT account_id, account_fname, account_lname, account_is_admin FROM account WHERE account_id = ? FOR UPDATE");
        $chkAcc->execute([$teacherId]);
        $existing = $chkAcc->fetch();

        if (!$existing) {
            $pdo->rollBack();
            echo json_encode(["success" => false, "error" => "not_found", "message" => "Account not found."]);
            return;
        }

        if ((int)$existing['account_is_admin'] === 1) {
            $pdo->rollBack();
            echo json_encode(["success" => false, "error" => "forbidden", "message" => "Cannot delete an administrator account."]);
            return;
        }

        // 1. Settings
        $pdo->prepare("DELETE FROM settings WHERE account_id = ?")->execute([$teacherId]);

        // 2. Notifications
        $pdo->prepare("DELETE FROM notification WHERE account_id = ?")->execute([$teacherId]);

        // 3. Contacts / Messages
        $pdo->prepare("DELETE FROM contacts WHERE contact_sender = ? OR contact_receiver = ?")->execute([$teacherId, $teacherId]);

        // 4. History
        $pdo->prepare("DELETE FROM history WHERE account_id = ?")->execute([$teacherId]);

        // 5. Pending schedules + orphan reservedetails
        $stmtPendRes = $pdo->prepare("SELECT reserve_id FROM pendingschedule WHERE account_id = ? AND reserve_id IS NOT NULL");
        $stmtPendRes->execute([$teacherId]);
        $pendResIds = $stmtPendRes->fetchAll(PDO::FETCH_COLUMN);

        $pdo->prepare("DELETE FROM pendingschedule WHERE account_id = ?")->execute([$teacherId]);

        // 6. Schedules + orphan reservedetails
        $stmtSchedRes = $pdo->prepare("SELECT reserve_id FROM schedule WHERE account_id = ? AND reserve_id IS NOT NULL");
        $stmtSchedRes->execute([$teacherId]);
        $schedResIds = $stmtSchedRes->fetchAll(PDO::FETCH_COLUMN);

        $pdo->prepare("DELETE FROM schedule WHERE account_id = ?")->execute([$teacherId]);

        // Clean up orphaned reservedetails if no other schedule or pendingschedule points to them
        $allResIds = array_unique(array_merge($pendResIds, $schedResIds));
        foreach ($allResIds as $resId) {
            $chkOrphan = $pdo->prepare("
                SELECT (
                    (SELECT COUNT(*) FROM schedule WHERE reserve_id = ?) +
                    (SELECT COUNT(*) FROM pendingschedule WHERE reserve_id = ?)
                ) AS ref_count
            ");
            $chkOrphan->execute([$resId, $resId]);
            $refCount = (int)$chkOrphan->fetchColumn();
            if ($refCount === 0) {
                $pdo->prepare("DELETE FROM reservedetails WHERE reserve_id = ?")->execute([$resId]);
            }
        }

        // 7. Delete from account
        $stmtDel = $pdo->prepare("DELETE FROM account WHERE account_id = ? AND account_is_admin = 0");
        $stmtDel->execute([$teacherId]);

        $pdo->commit();

        echo json_encode([
            "success" => true,
            "message" => "Teacher {$existing['account_fname']} {$existing['account_lname']} has been deleted."
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error in handleDeleteTeacher: " . $e->getMessage());
        echo json_encode(["success" => false, "error" => "delete_failed", "message" => "Failed to delete teacher account. Please try again."]);
    }
}

/**
 * Helper to retrieve request data from JSON body or $_POST.
 */
function getRequestPayload(): array {
    $raw = file_get_contents('php://input');
    if (!empty($raw)) {
        $json = json_decode($raw, true);
        if (is_array($json)) {
            return $json;
        }
    }
    return $_POST;
}
