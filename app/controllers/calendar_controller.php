<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION["id"])) {
    echo json_encode(["success" => false, "error" => "unauthorized"]);
    exit;
}

require "../../includes/database_include.php";

function isAdmin($pdo, $sessionId) {
    if (!empty($_SESSION["is_admin"])) {
        return true;
    }
    $stmt = $pdo->prepare("SELECT account_is_admin FROM account WHERE account_id = ? LIMIT 1");
    $stmt->execute([$sessionId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row && (int)$row["account_is_admin"] === 1;
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $sessionId = intval($_SESSION["id"]);
    $isAdminUser = isAdmin($pdo, $sessionId);
    $statusWhere = $isAdminUser ? "" : "WHERE LOWER(TRIM(r.room_status)) NOT IN ('maintenance', 'unavailable')";

    $stmt = $pdo->query("
        SELECT
            sched.schedule_id,
            sched.schedule_day_of_week,
            sched.schedule_start,
            sched.schedule_end,
            sched.schedule_day,
            sched.schedule_end_date,
            sched.account_id,
            COALESCE(details.reserve_title, '') AS subject,
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
        LEFT JOIN reservedetails details ON details.reserve_id = sched.reserve_id
        {$statusWhere}
        ORDER BY sched.schedule_day ASC, sched.schedule_start ASC
    ");

    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(["success" => true, "data" => $data]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "error" => "db_error", "message" => $e->getMessage()]);
}
?>
