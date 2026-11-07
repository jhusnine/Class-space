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
        SELECT
            pend.pending_id,
            pend.account_id,
            pend.room_id,
            COALESCE(details.reserve_title, '') AS subject,
            pend.pending_schedule_day_of_week,
            pend.pending_schedule_start,
            pend.pending_schedule_end,
            pend.pending_schedule_day,
            pend.pending_schedule_end_date,
            pend.pending_schedule_status,
            acc.account_fname AS fname,
            acc.account_lname AS lname,
            acc.account_username AS username,
            r.room_name,
            h.hall_name
        FROM pendingschedule AS pend
        INNER JOIN account AS acc ON acc.account_id = pend.account_id
        INNER JOIN room AS r ON r.room_id = pend.room_id
        INNER JOIN hall AS h ON h.hall_id = r.hall_id
        LEFT JOIN reservedetails AS details ON details.reserve_id = pend.reserve_id
        WHERE LOWER(TRIM(pend.pending_schedule_status)) = 'pending'
        ORDER BY pend.pending_id ASC
    ");
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(["success" => true, "data" => $data]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "error" => "db_error", "message" => $e->getMessage()]);
}
?>
