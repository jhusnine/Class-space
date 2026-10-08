<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION["id"])) {
    echo json_encode(["success" => false, "error" => "unauthorized"]);
    exit;
}

require "../../includes/database_include.php";

$body       = json_decode(file_get_contents("php://input"), true);
$scheduleId = intval($body["schedule_id"] ?? 0);

if (!$scheduleId) {
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

    // Fetch schedule info for notification before deleting
    $stmtSched = $pdo->prepare("
        SELECT sched.*, r.room_name, h.hall_name
        FROM schedule sched
        JOIN room r ON sched.room_id = r.room_id
        JOIN hall h ON r.hall_id = h.hall_id
        WHERE sched.schedule_id = ?
        LIMIT 1
    ");
    $stmtSched->execute([$scheduleId]);
    $sched = $stmtSched->fetch(PDO::FETCH_ASSOC);

    if (!$sched) {
        echo json_encode(["success" => false, "error" => "not_found"]);
        exit;
    }

    $pdo->beginTransaction();

    // Delete the schedule
    $pdo->prepare("DELETE FROM schedule WHERE schedule_id = ?")->execute([$scheduleId]);

    // Notify the user
    $roomHall  = "{$sched['room_name']} ({$sched['hall_name']})";
    $dayLabel  = $sched['schedule_day']
        ? date('M j, Y', strtotime($sched['schedule_day']))
        : "every {$sched['schedule_day_of_week']}";

    $h = intval(explode(':', $sched['schedule_start'])[0]);
    $m = explode(':', $sched['schedule_start'])[1];
    $startFmt = ($h % 12 ?: 12) . ":$m " . ($h >= 12 ? 'PM' : 'AM');

    $h2 = intval(explode(':', $sched['schedule_end'])[0]);
    $m2 = explode(':', $sched['schedule_end'])[1];
    $endFmt = ($h2 % 12 ?: 12) . ":$m2 " . ($h2 >= 12 ? 'PM' : 'AM');

    $pdo->prepare("
        INSERT INTO notification (account_id, notif_type, notif_title, notif_message)
        VALUES (?, 'cancelled', 'Reservation Cancelled', ?)
    ")->execute([
        $sched["account_id"],
        "Your reservation for {$roomHall} on {$dayLabel} ({$startFmt} – {$endFmt}) has been cancelled by an administrator."
    ]);

    $pdo->commit();
    echo json_encode(["success" => true, "message" => "Reservation deleted."]);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(["success" => false, "error" => "db_error", "message" => $e->getMessage()]);
}
?>
