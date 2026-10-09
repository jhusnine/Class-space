<?php
session_start();
header('Content-Type: application/json');

if (isset($_SESSION["id"])) {
    echo json_encode(["success" => true]);
    exit;
}
require "../../includes/database_include.php";

$email = isset($_POST["email"]) ? trim($_POST["email"]) : '';
$pass  = isset($_POST["upass"]) ? $_POST["upass"] : '';

if (empty($email) || empty($pass)) {
    echo json_encode(["error" => "missing_fields"]);
    exit;
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("CALL `spGetAccountInfo`(?,?)");
    $stmt->execute([$email, $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(["error" => "wrong_email"]);
        exit;
    }

    $hashed = hash('sha256', $pass);
    if ($user['account_password'] !== $hashed) {
        echo json_encode(["error" => "wrong_password"]);
        exit;
    }
    $_SESSION["id"]       = $user["account_id"];
    $_SESSION["username"] = $user["account_username"];
    $_SESSION["fname"]    = $user["account_fname"];
    $_SESSION["lname"]    = $user["account_lname"];
    $_SESSION["email"]    = $user["account_email"];
    $_SESSION["is_admin"] = $user["account_is_admin"];

    echo json_encode(["success" => true, "isadmin" => $user["account_is_admin"]]);

} catch (PDOException $e) {
    echo json_encode([
        "error"   => "database_error",
        "message" => $e->getMessage(),
        "code"    => $e->getCode()
    ]);
}
?>