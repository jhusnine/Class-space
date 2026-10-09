<?php
/**
 * ================================================================================
 * FIXED: contact_messages_controller.php (USER SIDE)
 * User messaging system - Send concerns to admin, receive replies
 * ================================================================================
 * 
 * FIXES APPLIED:
 * ✅ Uses correct `contacts` table
 * ✅ Dynamic admin ID retrieval
 * ✅ Proper sender/receiver logic
 * ✅ Input validation
 * ✅ Security checks
 * ================================================================================
 */

session_start();
header('Content-Type: application/json');

// Security: Verify session
if (!isset($_SESSION["id"]) || !is_numeric($_SESSION["id"])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Database configuration
require "../../includes/database_include.php";
$charset = 'utf8mb4';

try {
    $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    error_log('DB Error: ' . $e->getMessage());
    exit;
}

$currentUserId = intval($_SESSION["id"]);
$action = isset($_GET['action']) ? trim($_GET['action']) : '';

// ==================== GET ADMIN ID ====================
function getAdminId($pdo) {
    $stmt = $pdo->query("SELECT account_id FROM account WHERE account_is_admin = 1 LIMIT 1");
    $admin = $stmt->fetch();
    return $admin ? intval($admin['account_id']) : 1; // Default to 1 if not found
}

$adminId = getAdminId($pdo);

// ==================== ACTION: GET MESSAGES WITH ADMIN ====================
if ($action === 'get_messages') {
    try {
        // Get all messages between current user and admin
        $stmt = $pdo->prepare("
            SELECT 
                contact_id,
                contact_sender,
                contact_receiver,
                contact_content,
                contact_created
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
            'count' => count($messages),
            'adminId' => $adminId
        ]);
        exit;

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to load messages']);
        error_log('DB Error: ' . $e->getMessage());
        exit;
    }
}

// ==================== ACTION: SEND MESSAGE ====================
if ($action === 'send_message' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $messageText = isset($input['message_text']) ? trim($input['message_text']) : '';
        $subject = isset($input['subject']) ? trim($input['subject']) : 'General Concern';
        
        // Validation
        if (empty($messageText)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Message cannot be empty']);
            exit;
        }
        
        if (strlen($messageText) > 500) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Message too long (max 500 chars)']);
            exit;
        }
        
        // Format message with subject if provided
        $finalMessage = !empty($subject) ? "[{$subject}] {$messageText}" : $messageText;
        
        // Insert message (user sends to admin)
        $insertStmt = $pdo->prepare("
            INSERT INTO contacts 
            (contact_sender, contact_receiver, contact_content, contact_created)
            VALUES (?, ?, ?, NOW())
        ");
        
        $result = $insertStmt->execute([$currentUserId, $adminId, $finalMessage]);
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Your message has been sent successfully!',
                'contact_id' => $pdo->lastInsertId(),
                'adminId' => $adminId
            ]);
        } else {
            throw new Exception('Failed to send message');
        }
        exit;

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
        error_log('DB Error: ' . $e->getMessage());
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

// ==================== ACTION: GET ADMIN INFO ====================
if ($action === 'get_admin_info') {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                account_id,
                account_fname,
                account_lname,
                account_email
            FROM account
            WHERE account_is_admin = 1
            LIMIT 1
        ");
        $stmt->execute();
        $admin = $stmt->fetch();
        
        if ($admin) {
            echo json_encode([
                'success' => true,
                'data' => $admin
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'No admin found'
            ]);
        }
        exit;

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
        error_log('DB Error: ' . $e->getMessage());
        exit;
    }
}

// ==================== INVALID ACTION ====================
http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Invalid action']);
exit;
?>