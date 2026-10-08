<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION["id"])) {
    echo json_encode(["success" => false, "error" => "unauthorized"]);
    exit;
}

require "../../includes/database_include.php";

$roomId = intval($_GET["room_id"] ?? 0);
if (!$roomId) {
    echo json_encode(["success" => false, "error" => "invalid_params"]);
    exit;
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Verify admin
    $stmtAdmin = $pdo->prepare("SELECT account_is_admin FROM account WHERE account_id = ? LIMIT 1");
    $stmtAdmin->execute([intval($_SESSION["id"])]);
    $admin = $stmtAdmin->fetch(PDO::FETCH_ASSOC);
    if (!$admin || !$admin["account_is_admin"]) {
        echo json_encode(["success" => false, "error" => "not_admin"]);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT
            sched.schedule_id,
            sched.schedule_day_of_week,
            sched.schedule_start,
            sched.schedule_end,
            sched.schedule_day,
            acc.account_fname   AS fname,
            acc.account_lname   AS lname,
            acc.account_username AS username
        FROM schedule sched
        JOIN account acc ON sched.account_id = acc.account_id
        WHERE sched.room_id = ?
        ORDER BY sched.schedule_day ASC, sched.schedule_start ASC
    ");
    $stmt->execute([$roomId]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(["success" => true, "data" => $data]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "error" => "db_error", "message" => $e->getMessage()]);
}
?>
