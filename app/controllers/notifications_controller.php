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
        echo json_encode(["success" => true, "data" => [], "unread" => 0]);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT notif_id, notif_type, notif_title, notif_message,
               notif_is_read, notif_created
        FROM   notification
        WHERE  account_id = ?
        ORDER  BY notif_created DESC
        LIMIT  50
    ");
    $stmt->execute([$accountId]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $unread = array_reduce($data, fn($c, $r) => $c + ($r['notif_is_read'] ? 0 : 1), 0);

    echo json_encode(["success" => true, "data" => $data, "unread" => $unread]);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
?>