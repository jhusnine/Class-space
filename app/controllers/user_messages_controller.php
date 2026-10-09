<?php
/**
 * user_messages_controller.php
 * User-side messaging - simple communication with admin
 */

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION["id"])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$host = 'localhost';
$db = 'classspace';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

$currentUserId = intval($_SESSION["id"]);
$action = isset($_GET['action']) ? trim($_GET['action']) : '';

// Get admin ID
$adminStmt = $pdo->prepare("SELECT account_id FROM account WHERE account_is_admin = 1 LIMIT 1");
$adminStmt->execute();
$admin = $adminStmt->fetch();
$adminId = $admin ? intval($admin['account_id']) : 1;

// ==================== GET MESSAGES ====================
if ($action === 'get_messages') {
    try {
        $stmt = $pdo->prepare("
            SELECT contact_id, contact_sender, contact_receiver, contact_content, contact_created
            FROM contacts
            WHERE (contact_sender = ? AND contact_receiver = ?)
               OR (contact_sender = ? AND contact_receiver = ?)
            ORDER BY contact_created ASC
        ");
        
        $stmt->execute([$currentUserId, $adminId, $adminId, $currentUserId]);
        $messages = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'data' => $messages,
            'adminId' => $adminId
        ]);
        exit;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error loading messages']);
        exit;
    }
}

// ==================== SEND MESSAGE ====================
if ($action === 'send_message' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $text = isset($input['message_text']) ? trim($input['message_text']) : '';

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

        $stmt = $pdo->prepare("
            INSERT INTO contacts (contact_sender, contact_receiver, contact_content, contact_created)
            VALUES (?, ?, ?, NOW())
        ");

        $result = $stmt->execute([$currentUserId, $adminId, $text]);

        if ($result) {
            // Create notification for admin
            $notifStmt = $pdo->prepare("
                INSERT INTO notification (account_id, notif_type, notif_title, notif_message)
                VALUES (?, 'message', 'New User Message', ?)
            ");
            $notifStmt->execute([$adminId, "New message from user"]);

            echo json_encode([
                'success' => true,
                'message' => 'Message sent',
                'contact_id' => $pdo->lastInsertId()
            ]);
        } else {
            throw new Exception('Failed to send message');
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