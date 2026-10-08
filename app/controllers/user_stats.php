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
    // Mode 1 — available rooms (no account needed, pass 0)
    $pdo->prepare("CALL `spGetUserStats`(0, 1, @count)")->execute();
    $availableCount = $pdo->query("SELECT @count")->fetchColumn();

    // Mode 2 — active reservations
    $pdo->prepare("CALL `spGetUserStats`($accountId, 2, @count)")->execute();
    $activeCount = $pdo->query("SELECT @count")->fetchColumn();

    // Mode 3 — pending requests
    $pdo->prepare("CALL `spGetUserStats`($accountId, 3, @count)")->execute();
    $pendingCount = $pdo->query("SELECT @count")->fetchColumn();

    echo json_encode([
        "success"   => true,
        "available" => (int)$availableCount,
        "active"    => (int)$activeCount,
        "pending"   => (int)$pendingCount,
    ]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
?>
