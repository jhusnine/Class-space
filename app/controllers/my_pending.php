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

    $stmtAcct = $pdo->prepare("SELECT account_id FROM account WHERE account_id = ? LIMIT 1");
    $stmtAcct->execute([$_SESSION["id"]]);
    $acct = $stmtAcct->fetch(PDO::FETCH_ASSOC);
    $stmtAcct->closeCursor();
    if (!$acct) {
        echo json_encode(["success" => false, "data" => []]);
        exit;
    }

    $stmt = $pdo->prepare("
        CALL `spGetMyPending`(?);
    ");
    $stmt->execute([$acct["account_id"]]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
    echo json_encode(["success" => true, "data" => $data]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
?>