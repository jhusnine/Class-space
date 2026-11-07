<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION["id"])) {
    echo json_encode(["success" => false, "error" => "unauthorized"]);
    exit;
}

require "../../includes/database_include.php";

function isExcludedDeanOffice($room, $hallName = '')
{
    $hall = strtolower(trim((string) ($hallName !== '' ? $hallName : ($room['hall_name'] ?? ($room['name'] ?? '')))));
    $roomLabel = strtolower(trim((string) ($room['room_name'] ?? '')) . ' ' . trim((string) ($room['room_type'] ?? '')));
    $normalizedLabel = preg_replace('/[^a-z0-9]+/', ' ', $roomLabel);
    $isDeanProgramHeadOffice = strpos($normalizedLabel, 'dean') !== false
        && preg_match('/\bprogram head\b/', $normalizedLabel) === 1;
    $isPlaridelDeanRoom = strpos($hall, 'plaridel') !== false
        && strpos($normalizedLabel, 'dean') !== false
        && (preg_match('/\bdean (?:s )?(?:room|office)\b/', $normalizedLabel) === 1
            || preg_match('/\b(?:room|office) dean\b/', $normalizedLabel) === 1);

    return $isDeanProgramHeadOffice || $isPlaridelDeanRoom;
}

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
    $accountId = intval($_SESSION["id"]);


    $isAdminUser = isAdmin($pdo, $accountId);
    $statusWhere = $isAdminUser ? "" : "WHERE LOWER(TRIM(r.room_status)) NOT IN ('maintenance', 'unavailable')";

    $stmt = $pdo->prepare("
        SELECT r.room_id, r.room_name, r.room_capacity, r.room_type, r.room_has_ac,
               r.room_status, h.hall_id, h.hall_name
        FROM room r
        JOIN hall h ON r.hall_id = h.hall_id
        {$statusWhere}
        ORDER BY RAND()
        LIMIT 13
    ");
    $stmt->execute();
    $rooms = array_values(array_filter($stmt->fetchAll(PDO::FETCH_ASSOC), function ($room) use ($isAdminUser) {
        if (isExcludedDeanOffice($room)) return false;
        if (!$isAdminUser) {
            $st = strtolower(trim((string)($room['room_status'] ?? '')));
            if ($st === 'maintenance' || $st === 'unavailable') return false;
        }
        return true;
    }));
    $rooms = array_slice($rooms, 0, 12);


    foreach ($rooms as &$room) {


        $checkBooking = $pdo->prepare("
            SELECT COUNT(*) FROM schedule sched
            WHERE sched.room_id = ? AND (
                (sched.schedule_day = CURDATE() AND CURTIME() BETWEEN sched.schedule_start AND sched.schedule_end)
                OR (sched.schedule_day IS NULL AND sched.schedule_day_of_week = DAYNAME(NOW()) AND (sched.schedule_end_date IS NULL OR sched.schedule_end_date >= CURDATE()) AND CURTIME() BETWEEN sched.schedule_start AND sched.schedule_end)
            )
        ");
        $checkBooking->execute([$room['room_id']]);
        $isBooked = $checkBooking->fetchColumn() > 0;


        if (strtolower($room['room_status']) === 'maintenance') {
            $room['computed_status'] = 'maintenance';
        } else if (strtolower($room['room_status']) === 'unavailable' || $isBooked) {
            $room['computed_status'] = 'unavailable';
        } else {
            $room['computed_status'] = 'available';
        }


        $checkActive = $pdo->prepare("
            SELECT COUNT(*) FROM schedule sched
            WHERE sched.room_id = ? AND sched.account_id = ? AND (
                (sched.schedule_day = CURDATE() AND CURTIME() BETWEEN sched.schedule_start AND sched.schedule_end)
                OR (sched.schedule_day IS NULL AND sched.schedule_day_of_week = DAYNAME(NOW()) AND (sched.schedule_end_date IS NULL OR sched.schedule_end_date >= CURDATE()) AND CURTIME() BETWEEN sched.schedule_start AND sched.schedule_end)
            )
        ");
        $checkActive->execute([$room['room_id'], $accountId]);
        $room['is_my_active'] = (bool)$checkActive->fetchColumn();


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
