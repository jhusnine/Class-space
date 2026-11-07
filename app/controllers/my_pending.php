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
        SELECT
            pend.pending_id,
            COALESCE(details.reserve_title, '') AS subject,
            pend.pending_schedule_day_of_week AS schedule_day_of_week,
            pend.pending_schedule_start AS schedule_start,
            pend.pending_schedule_end AS schedule_end,
            pend.pending_schedule_day AS schedule_day,
            pend.pending_schedule_end_date AS schedule_end_date,
            r.room_id,
            r.room_name,
            r.room_type,
            h.hall_id,
            h.hall_name
        FROM pendingschedule AS pend
        INNER JOIN room AS r ON r.room_id = pend.room_id
        INNER JOIN hall AS h ON h.hall_id = r.hall_id
        LEFT JOIN reservedetails AS details ON details.reserve_id = pend.reserve_id
        WHERE pend.account_id = ?
          AND LOWER(TRIM(pend.pending_schedule_status)) = 'pending'
        ORDER BY pend.pending_id ASC
    ");
    $stmt->execute([$acct["account_id"]]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
    echo json_encode(["success" => true, "data" => $data]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
?>
