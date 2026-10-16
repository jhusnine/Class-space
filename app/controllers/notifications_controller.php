<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION["id"])) {
    echo json_encode(["success" => false, "error" => "unauthorized"]);
    exit;
}
require "../../includes/database_include.php";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $accountId = intval($_SESSION["id"]);

    $tableExists = $pdo->query("SHOW TABLES LIKE 'notification'")->fetchColumn();
    if (!$tableExists) {
        echo json_encode([
            "success" => true,
            "data" => [],
            "unread" => 0,
            "unread_contact_replies" => 0,
            "can_delete" => false,
        ]);
        exit;
    }

    // Additive migration support: keep legacy notification reads working until
    // notif_user_deleted_at has been added by the supplied SQL migration.
    $canDelete = (bool)$pdo->query(
        "SHOW COLUMNS FROM notification LIKE 'notif_user_deleted_at'"
    )->fetchColumn();
    $visibleRowsOnly = $canDelete ? " AND notif_user_deleted_at IS NULL" : "";

    $stmt = $pdo->prepare("
        SELECT notif_id, notif_type, notif_title, notif_message,
               notif_is_read, notif_created
        FROM   notification
        WHERE  account_id = ? {$visibleRowsOnly}
        ORDER  BY notif_created DESC
        LIMIT  50
    ");
    $stmt->execute([$accountId]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $unread = array_reduce($data, fn($count, $row) => $count + ($row['notif_is_read'] ? 0 : 1), 0);

    $contactReplyStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM notification
        WHERE account_id = ?
          AND notif_is_read = 0
          AND notif_type = 'message'
          AND notif_title IN ('Admin Reply', 'ICT Support Reply', 'Support Reply')
          {$visibleRowsOnly}
    ");
    $contactReplyStmt->execute([$accountId]);
    $unreadContactReplies = (int)$contactReplyStmt->fetchColumn();

    echo json_encode([
        "success" => true,
        "data" => $data,
        "unread" => $unread,
        "unread_contact_replies" => $unreadContactReplies,
        "can_delete" => $canDelete,
    ]);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
?>
