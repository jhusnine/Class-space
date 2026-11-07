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


    $status = isset($_GET['status']) ? trim($_GET['status']) : '';
    $hallId = isset($_GET['hall_id']) ? intval($_GET['hall_id']) : 0;
    $roomId = isset($_GET['room_id']) ? intval($_GET['room_id']) : 0;
    $date   = isset($_GET['date']) ? trim($_GET['date']) : '';

    $allowedStatus = ['Approved', 'Pending', 'Rejected'];
    if ($status !== '' && !in_array($status, $allowedStatus, true)) {
        $status = '';
    }


    $sql = "
        SELECT
            report.ref_id,
            report.status,
            report.day_of_week,
            report.specific_date,
            report.end_date,
            report.start_time,
            report.end_time,
            COALESCE(details.reserve_title, '') AS subject,
            r.room_name,
            r.room_id,
            h.hall_name,
            h.hall_id,
            acc.account_fname AS fname,
            acc.account_lname AS lname,
            acc.account_username AS username
        FROM (
            SELECT
                sched.schedule_id            AS ref_id,
                'Approved'                   AS status,
                sched.room_id                AS room_id,
                sched.account_id             AS account_id,
                sched.schedule_day_of_week   AS day_of_week,
                sched.schedule_day           AS specific_date,
                sched.schedule_end_date      AS end_date,
                sched.schedule_start         AS start_time,
                sched.schedule_end           AS end_time,
                sched.reserve_id             AS reserve_id
            FROM schedule sched

            UNION ALL

            SELECT
                pend.pending_id AS ref_id,
                CASE
                    WHEN pend.pending_schedule_status = 'pending'  THEN 'Pending'
                    WHEN pend.pending_schedule_status = 'rejected' THEN 'Rejected'
                    ELSE pend.pending_schedule_status
                END AS status,
                pend.room_id                          AS room_id,
                pend.account_id                       AS account_id,
                pend.pending_schedule_day_of_week      AS day_of_week,
                pend.pending_schedule_day              AS specific_date,
                pend.pending_schedule_end_date         AS end_date,
                pend.pending_schedule_start            AS start_time,
                pend.pending_schedule_end              AS end_time,
                pend.reserve_id                         AS reserve_id
            FROM pendingschedule pend
            WHERE pend.pending_schedule_status IN ('pending', 'rejected')
        ) AS report
        JOIN room r     ON report.room_id = r.room_id
        JOIN hall h     ON r.hall_id = h.hall_id
        JOIN account acc ON report.account_id = acc.account_id
        LEFT JOIN reservedetails details ON details.reserve_id = report.reserve_id
        WHERE 1=1
    ";

    $params = [];

    if ($status !== '') {
        $sql .= " AND report.status = ?";
        $params[] = $status;
    }
    if ($hallId) {
        $sql .= " AND h.hall_id = ?";
        $params[] = $hallId;
    }
    if ($roomId) {
        $sql .= " AND r.room_id = ?";
        $params[] = $roomId;
    }
    if ($date !== '') {
        $sql .= " AND report.specific_date = ?";
        $params[] = $date;
    }

    $sql .= " ORDER BY report.specific_date DESC, report.start_time DESC, report.ref_id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(["success" => true, "data" => $data]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "error" => "db_error", "message" => $e->getMessage()]);
}
?>
