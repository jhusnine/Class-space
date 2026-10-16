<?php
header('Content-Type: application/json');

require "../../includes/database_include.php";
$pdo = null;
try {
    $fname = isset($_POST["fname"]) ? trim($_POST["fname"]) : '';
    $lname = isset($_POST["lname"]) ? trim($_POST["lname"]) : '';
    $email = isset($_POST["email"]) ? trim($_POST["email"]) : '';
    $pass = isset($_POST["password"]) ? $_POST["password"] : '';
    $username = isset($_POST["username"]) ? trim($_POST["username"]) : '';

    if (empty($fname) || empty($lname) || empty($email) || empty($pass)) {
        echo json_encode(["error" => "missing_fields"]);
        exit;
    }

    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $checkEmailSql = "SELECT `account_email` FROM `account` WHERE `account_email` = ?;";
    $checkEmailStmt = $pdo->prepare($checkEmailSql);
    $checkEmailStmt->execute([$email]);
    $emailExists = $checkEmailStmt->fetchAll(PDO::FETCH_ASSOC);

    if ($emailExists) {
        echo json_encode(["error" => "email_taken"]);
        exit;
    }

    $passwordHash = password_hash($pass, PASSWORD_DEFAULT);
    if ($passwordHash === false) {
        echo json_encode(["error" => "registration_error"]);
        exit;
    }

    $username = strtolower(str_replace("@", "_", explode("@", $email)[0]));

    $pdo->beginTransaction();
    try {
        // Retain the existing account-creation routine, then replace its legacy
        // password representation before the new account is committed.
        $sql = "CALL `spInsertAccount`(?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$username, $pass, $lname, $fname, 0, $email]);
        $stmt->closeCursor();

        $passwordStmt = $pdo->prepare(
            "UPDATE `account` SET `account_password` = ? WHERE `account_email` = ?"
        );
        $passwordStmt->execute([$passwordHash, $email]);
        if ($passwordStmt->rowCount() < 1) {
            throw new RuntimeException('New account password hash could not be stored.');
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    echo json_encode(["success" => true, "message" => "Registration successful"]);

} catch(Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(["error" => "database_error"]);
}
?>
