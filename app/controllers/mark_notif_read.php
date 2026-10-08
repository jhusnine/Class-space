<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION["id"])) { echo json_encode(["success" => false]); exit; }

require "../../includes/database_include.php";
$body    = json_decode(file_get_contents("php://input"), true);
$notifId = $body["notif_id"] ?? null;
$accountId = intval($_SESSION["id"]);

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($notifId === "all") {
        $pdo->prepare("UPDATE notification SET notif_is_read = 1 WHERE account_id = ?")
            ->execute([$accountId]);
    } elseif ($notifId) {
        $pdo->prepare("UPDATE notification SET notif_is_read = 1 WHERE notif_id = ? AND account_id = ?")
            ->execute([intval($notifId), $accountId]);
    }

    echo json_encode(["success" => true]);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
?>