<?php
/**
 * admin_report_controller.php
 * Report Generation feature — Admin side.
 *
 * Combines:
 *   - `schedule`         -> Approved reservations (no status column; presence here means approved)
 *   - `pendingschedule`  -> Pending / Rejected reservations (via pending_schedule_status)
 *
 * NOTE: There is no "purpose/details" field populated anywhere in the existing
 * reserve_controller.php / pendingschedule inserts, so it is intentionally
 * left out of this report rather than invented.
 *
 * NOTE on date filtering: recurring "weekly" reservations have
 * schedule_day / pending_schedule_day = NULL (they only have a day-of-week).
 * The `date` filter below only matches one-time reservations that have a
 * specific date on record. Weekly ones are still shown (with their day of
 * week) unless filtered out by a different filter.
 */

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

    // Verify admin
    $stmtAdmin = $pdo->prepare("SELECT account_is_admin FROM account WHERE account_id = ? LIMIT 1");
    $stmtAdmin->execute([intval($_SESSION["id"])]);
    $admin = $stmtAdmin->fetch(PDO::FETCH_ASSOC);
    if (!$admin || !$admin["account_is_admin"]) {
        echo json_encode(["success" => false, "error" => "not_admin"]);
        exit;
    }

    // ==================== READ FILTERS ====================
    $status = isset($_GET['status']) ? trim($_GET['status']) : '';       // '', 'Approved', 'Pending', 'Rejected'
    $hallId = isset($_GET['hall_id']) ? intval($_GET['hall_id']) : 0;
    $roomId = isset($_GET['room_id']) ? intval($_GET['room_id']) : 0;
    $date   = isset($_GET['date']) ? trim($_GET['date']) : '';           // 'YYYY-MM-DD', matches one-time reservations only

    $allowedStatus = ['Approved', 'Pending', 'Rejected'];
    if ($status !== '' && !in_array($status, $allowedStatus, true)) {
        $status = '';
    }

    // ==================== BASE QUERY (UNION) ====================
    $sql = "
        SELECT
            report.ref_id,
            report.status,
            report.day_of_week,
            report.specific_date,
            report.start_time,
            report.end_time,
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
                sched.schedule_start         AS start_time,
                sched.schedule_end           AS end_time
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
                pend.pending_schedule_start            AS start_time,
                pend.pending_schedule_end              AS end_time
            FROM pendingschedule pend
            WHERE pend.pending_schedule_status IN ('pending', 'rejected')
        ) AS report
        JOIN room r     ON report.room_id = r.room_id
        JOIN hall h     ON r.hall_id = h.hall_id
        JOIN account acc ON report.account_id = acc.account_id
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