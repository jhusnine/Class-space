<?php

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION["id"])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require "../../includes/database_include.php";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

$adminId = intval($_SESSION["id"]);
$action = isset($_GET['action']) ? trim($_GET['action']) : '';

// Verify admin
$adminCheck = $pdo->prepare("SELECT account_is_admin FROM account WHERE account_id = ?");
$adminCheck->execute([$adminId]);
$admin = $adminCheck->fetch();

if (!$admin || !$admin['account_is_admin']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit;
}

// ==================== GET USERS WITH CONVERSATIONS ====================
if ($action === 'get_users') {
    try {
        $stmt = $pdo->prepare("
            SELECT DISTINCT
                acc.account_id,
                acc.account_fname,
                acc.account_lname,
                acc.account_email,
                acc.account_username,
                (SELECT COUNT(*) FROM contacts 
                 WHERE contact_sender = acc.account_id 
                 AND contact_receiver = ? 
                 AND contact_created > NOW() - INTERVAL 1 DAY) as unread_count
            FROM account acc
            INNER JOIN contacts c ON (acc.account_id = c.contact_sender OR acc.account_id = c.contact_receiver)
            WHERE acc.account_id != ? AND acc.account_is_admin = 0
            GROUP BY acc.account_id
            ORDER BY (SELECT MAX(contact_created) FROM contacts 
                     WHERE (contact_sender = acc.account_id AND contact_receiver = ?) 
                     OR (contact_sender = ? AND contact_receiver = acc.account_id)) DESC
        ");

        $stmt->execute([$adminId, $adminId, $adminId, $adminId]);
        $users = $stmt->fetchAll();

        $unreadStmt = $pdo->prepare("
            SELECT COUNT(*) as unread FROM contacts
            WHERE contact_receiver = ? AND contact_created > NOW() - INTERVAL 1 DAY
        ");
        $unreadStmt->execute([$adminId]);
        $unreadCount = intval($unreadStmt->fetch()['unread']);

        echo json_encode([
            'success' => true,
            'data' => $users,
            'unreadCount' => $unreadCount
        ]);
        exit;
    } catch (PDOException $e) {
        http_response_code(500);
        // ✅ FIXED: was json_encode($e) which leaks raw DB error — now safe message
        echo json_encode(['success' => false, 'message' => 'Error loading users']);
        exit;
    }
}

// ==================== GET MESSAGES WITH SPECIFIC USER ====================
if ($action === 'get_messages') {
    try {
        $userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

        if ($userId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
            exit;
        }

        $userCheck = $pdo->prepare("SELECT account_id FROM account WHERE account_id = ?");
        $userCheck->execute([$userId]);
        if (!$userCheck->fetch()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit;
        }

        $stmt = $pdo->prepare("
            SELECT contact_id, contact_sender, contact_receiver, contact_content, contact_created
            FROM contacts
            WHERE (contact_sender = ? AND contact_receiver = ?)
               OR (contact_sender = ? AND contact_receiver = ?)
            ORDER BY contact_created ASC
        ");

        $stmt->execute([$adminId, $userId, $userId, $adminId]);
        $messages = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'data' => $messages
        ]);
        exit;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error loading messages']);
        exit;
    }
}

// ==================== SEND REPLY ====================
if ($action === 'send_message' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $userId = isset($input['user_id']) ? intval($input['user_id']) : 0;
        $text = isset($input['message_text']) ? trim($input['message_text']) : '';

        if ($userId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
            exit;
        }

        if (empty($text)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Message cannot be empty']);
            exit;
        }

        if (strlen($text) > 500) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Message too long']);
            exit;
        }

        $userCheck = $pdo->prepare("SELECT account_id FROM account WHERE account_id = ?");
        $userCheck->execute([$userId]);
        if (!$userCheck->fetch()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO contacts (contact_sender, contact_receiver, contact_content, contact_created)
            VALUES (?, ?, ?, NOW())
        ");

        $result = $stmt->execute([$adminId, $userId, $text]);

        if ($result) {
            $notifStmt = $pdo->prepare("
                INSERT INTO notification (account_id, notif_type, notif_title, notif_message)
                VALUES (?, 'message', 'Admin Reply', ?)
            ");
            $notifStmt->execute([$userId, "You have a new reply from admin"]);

            echo json_encode([
                'success' => true,
                'message' => 'Reply sent',
                'contact_id' => $pdo->lastInsertId()
            ]);
        } else {
            throw new Exception('Failed to send reply');
        }
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>