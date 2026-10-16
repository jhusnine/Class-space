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
    $accountId = intval($_SESSION["id"]);
    // Count visible rooms using the same current-availability rules as the user room list.
    // The Plaridel Dean & Program Head room is excluded from user-facing totals.
    $availableCount = $pdo->query("
        SELECT COUNT(*)
        FROM room r
        JOIN hall h ON r.hall_id = h.hall_id
        WHERE LOWER(COALESCE(r.room_status, '')) NOT IN ('unavailable', 'maintenance')
          AND NOT (
            (
              LOWER(CONCAT_WS(' ', r.room_name, r.room_type)) LIKE '%dean%'
              AND LOWER(CONCAT_WS(' ', r.room_name, r.room_type)) LIKE '%program head%'
            )
            OR (
              LOWER(h.hall_name) LIKE '%plaridel%'
              AND LOWER(CONCAT_WS(' ', r.room_name, r.room_type)) LIKE '%dean%'
              AND (
                LOWER(CONCAT_WS(' ', r.room_name, r.room_type)) LIKE '%dean room%'
                OR LOWER(CONCAT_WS(' ', r.room_name, r.room_type)) LIKE '%dean office%'
                OR LOWER(CONCAT_WS(' ', r.room_name, r.room_type)) LIKE '%dean s room%'
                OR LOWER(CONCAT_WS(' ', r.room_name, r.room_type)) LIKE '%dean s office%'
                OR LOWER(CONCAT_WS(' ', r.room_name, r.room_type)) LIKE '%room dean%'
                OR LOWER(CONCAT_WS(' ', r.room_name, r.room_type)) LIKE '%office dean%'
              )
            )
          )
          AND NOT EXISTS (
            SELECT 1
            FROM schedule sched
            WHERE sched.room_id = r.room_id
              AND (
                (sched.schedule_day IS NOT NULL
                  AND sched.schedule_day = CURDATE()
                  AND CURTIME() BETWEEN sched.schedule_start AND sched.schedule_end)
                OR
                (sched.schedule_day IS NULL
                  AND sched.schedule_day_of_week = DAYNAME(NOW())
                  AND CURTIME() BETWEEN sched.schedule_start AND sched.schedule_end)
              )
          )
    ")->fetchColumn();

    // Mode 2 — active reservations
    $stmtActive = $pdo->prepare("CALL `spGetUserStats`(?, 2, @count)");
    $stmtActive->execute([$accountId]);
    $stmtActive->closeCursor();
    $activeCount = $pdo->query("SELECT @count")->fetchColumn();

    // Mode 3 — pending requests
    $stmtPending = $pdo->prepare("CALL `spGetUserStats`(?, 3, @count)");
    $stmtPending->execute([$accountId]);
    $stmtPending->closeCursor();
    $pendingCount = $pdo->query("SELECT @count")->fetchColumn();

    echo json_encode([
        "success"   => true,
        "available" => (int)$availableCount,
        "active"    => (int)$activeCount,
        "pending"   => (int)$pendingCount,
    ]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
?>
