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

    $stmtAdmin = $pdo->prepare("SELECT account_is_admin FROM account WHERE account_id = ? LIMIT 1");
    $stmtAdmin->execute([intval($_SESSION["id"])]);
    $admin = $stmtAdmin->fetch(PDO::FETCH_ASSOC);
    if (!$admin || !$admin["account_is_admin"]) {
        echo json_encode(["success" => false, "error" => "not_admin"]);
        exit;
    }
    $stmt = $pdo->query("
        CALL `spGetPending`();
    ");
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(["success" => true, "data" => $data]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "error" => "db_error", "message" => $e->getMessage()]);
}
?>