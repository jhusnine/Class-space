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
    $stmt->closeCursor();

    if (!$user) {
        echo json_encode(["error" => "wrong_email"]);
        exit;
    }

    $storedHash = (string)($user['account_password'] ?? '');
    $passwordValid = password_verify($pass, $storedHash);

    // One-time migration for legacy, unsalted SHA-256 hashes.
    if (!$passwordValid
        && preg_match('/\A[a-f0-9]{64}\z/i', $storedHash) === 1
        && hash_equals(strtolower($storedHash), hash('sha256', $pass))) {
        $passwordValid = true;
        $upgradedHash = password_hash($pass, PASSWORD_DEFAULT);
        if ($upgradedHash === false) {
            throw new RuntimeException('Unable to create a secure password hash.');
        }

        $upgradeStmt = $pdo->prepare(
            'UPDATE account SET account_password = ? WHERE account_id = ? AND account_password = ?'
        );
        $upgradeStmt->execute([$upgradedHash, $user['account_id'], $storedHash]);

        // If another concurrent login already upgraded the row, verify the stored replacement.
        if ($upgradeStmt->rowCount() === 0) {
            $currentHashStmt = $pdo->prepare('SELECT account_password FROM account WHERE account_id = ?');
            $currentHashStmt->execute([$user['account_id']]);
            $currentHash = $currentHashStmt->fetchColumn();
            $passwordValid = is_string($currentHash) && password_verify($pass, $currentHash);
        }
    }

    // Rehash valid modern hashes when PHP's default algorithm or cost changes.
    if ($passwordValid && password_verify($pass, $storedHash)
        && password_needs_rehash($storedHash, PASSWORD_DEFAULT)) {
        $refreshedHash = password_hash($pass, PASSWORD_DEFAULT);
        if ($refreshedHash === false) {
            throw new RuntimeException('Unable to refresh the password hash.');
        }
        $refreshStmt = $pdo->prepare(
            'UPDATE account SET account_password = ? WHERE account_id = ? AND account_password = ?'
        );
        $refreshStmt->execute([$refreshedHash, $user['account_id'], $storedHash]);
        if ($refreshStmt->rowCount() === 0) {
            $currentHashStmt = $pdo->prepare('SELECT account_password FROM account WHERE account_id = ?');
            $currentHashStmt->execute([$user['account_id']]);
            $currentHash = $currentHashStmt->fetchColumn();
            $passwordValid = is_string($currentHash) && password_verify($pass, $currentHash);
        }
    }

    if (!$passwordValid) {
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

} catch (Throwable $e) {
    echo json_encode(["error" => "database_error"]);
}
?>
