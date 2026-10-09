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
    $stmt = $pdo->query("
        SELECT
            sched.schedule_id,
            sched.schedule_day_of_week,
            sched.schedule_start,
            sched.schedule_end,
            sched.schedule_day,
            sched.account_id,
            r.room_id,
            r.room_name,
            r.room_type,
            h.hall_id,
            h.hall_name,
            acc.account_fname,
            acc.account_lname
        FROM schedule sched
        JOIN room    r ON sched.room_id    = r.room_id
        JOIN hall    h ON r.hall_id    = h.hall_id
        JOIN account acc ON sched.account_id = acc.account_id
        ORDER BY sched.schedule_day ASC, sched.schedule_start ASC
    ");

    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(["success" => true, "data" => $data]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "error" => "db_error", "message" => $e->getMessage()]);
}
?>