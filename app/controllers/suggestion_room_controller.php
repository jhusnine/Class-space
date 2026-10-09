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

    // Get random rooms with all details
    $stmt = $pdo->prepare("
        SELECT r.room_id, r.room_name, r.room_capacity, r.room_type, r.room_has_ac,
               r.room_status, h.hall_id, h.hall_name
        FROM room r
        JOIN hall h ON r.hall_id = h.hall_id
        ORDER BY RAND()
        LIMIT 12
    ");
    $stmt->execute();
    $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Process each room with proper data
    foreach ($rooms as &$room) {
        
        // Check if room is currently booked
        $checkBooking = $pdo->prepare("
            SELECT COUNT(*) FROM schedule sched
            WHERE sched.room_id = ? AND (
                (sched.schedule_day = CURDATE() AND CURTIME() BETWEEN sched.schedule_start AND sched.schedule_end)
                OR (sched.schedule_day IS NULL AND sched.schedule_day_of_week = DAYNAME(NOW()) AND CURTIME() BETWEEN sched.schedule_start AND sched.schedule_end)
            )
        ");
        $checkBooking->execute([$room['room_id']]);
        $isBooked = $checkBooking->fetchColumn() > 0;
        
        // Set computed_status
        if (strtolower($room['room_status']) === 'maintenance') {
            $room['computed_status'] = 'maintenance';
        } else if (strtolower($room['room_status']) === 'unavailable' || $isBooked) {
            $room['computed_status'] = 'unavailable';
        } else {
            $room['computed_status'] = 'available';
        }
        
        // Check if current user has active reservation on this room
        $checkActive = $pdo->prepare("
            SELECT COUNT(*) FROM schedule sched
            WHERE sched.room_id = ? AND sched.account_id = ? AND (
                (sched.schedule_day = CURDATE() AND CURTIME() BETWEEN sched.schedule_start AND sched.schedule_end)
                OR (sched.schedule_day IS NULL AND sched.schedule_day_of_week = DAYNAME(NOW()) AND CURTIME() BETWEEN sched.schedule_start AND sched.schedule_end)
            )
        ");
        $checkActive->execute([$room['room_id'], $accountId]);
        $room['is_my_active'] = (bool)$checkActive->fetchColumn();
        
        // Check if room has ANY pending requests
        $checkPending = $pdo->prepare("
            SELECT COUNT(*) FROM pendingschedule pend
            WHERE pend.room_id = ? AND pend.pending_schedule_status = 'pending'
        ");
        $checkPending->execute([$room['room_id']]);
        $room['has_pending'] = (bool)$checkPending->fetchColumn();
    }

    echo json_encode([
        "success" => true, 
        "data" => $rooms
    ]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
?>