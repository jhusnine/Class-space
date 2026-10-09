<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION["id"])) {
    echo json_encode(["error" => "unauthorized"]);
    exit;
}

require "../../includes/database_include.php";

$query = isset($_GET["q"]) ? trim($_GET["q"]) : '';

if (empty($query)) {
    echo json_encode(["error" => "empty_query"]);
    exit;
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $search = "%" . $query . "%";

    $stmt = $pdo->prepare("
        CALL `spSearchRoom`(?);
    ");

    $stmt->execute([$search]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "data" => $results
    ]);

} catch (PDOException $e) {
    echo json_encode([
        "error" => "database_error",
        "message" => $e->getMessage(),
        "code" => $e->getCode()
    ]);
}
?>