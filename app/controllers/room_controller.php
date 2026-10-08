<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION["id"])) {
    echo json_encode(["success" => false, "error" => "unauthorized"]);
    exit;
}

require "../../includes/database_include.php";

$pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $db_user, $db_pass);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$action    = $_GET["action"] ?? '';
$sessionId = intval($_SESSION["id"]);

// ==================== HELPER: CHECK IF ADMIN ====================
function isAdmin($pdo, $sessionId) {
    $stmt = $pdo->prepare("SELECT account_is_admin FROM account WHERE account_id = ? LIMIT 1");
    $stmt->execute([$sessionId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row && $row["account_is_admin"];
}

// ==================== HELPER: FORMAT TIME ====================
function formatTime($t) {
    if (!$t) return '';
    $parts = explode(':', $t);
    $h = intval($parts[0]);
    $m = $parts[1];
    $suffix = ($h >= 12) ? 'PM' : 'AM';
    $h12 = $h % 12;
    if ($h12 === 0) $h12 = 12;
    return $h12 . ':' . $m . ' ' . $suffix;
}

// ==================== ACTION: GET HALLS ====================
// Used by: user reservation form dropdown
if ($action === 'get_halls') {
    $rows = $pdo->query("SELECT hall_id, hall_name FROM hall ORDER BY hall_name")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(["success" => true, "data" => $rows]);
    exit;
}

// ==================== ACTION: GET ROOMS BY HALL ====================
// Used by: user reservation form — populates rooms after selecting a hall
if ($action === 'get_rooms') {
    $hallId = intval($_GET["hall_id"] ?? 0);
    if (!$hallId) {
        echo json_encode(["success" => false, "data" => []]);
        exit;
    }

    $stmt = $pdo->prepare("CALL `spGetRooms`(?)");
    $stmt->execute([$hallId]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    echo json_encode(["success" => true, "data" => $data]);
    exit;
}

// ==================== ACTION: GET ALL ROOMS (ADMIN) ====================
// Used by: admin manage rooms page
// GET ?action=get_all_rooms
if ($action === 'get_all_rooms') {
    if (!isAdmin($pdo, $sessionId)) {
        echo json_encode(["success" => false, "error" => "not_admin"]);
        exit;
    }

    $stmt = $pdo->query("
        SELECT
            r.room_id,
            r.room_name,
            r.room_type,
            r.room_status,
            r.room_has_ac,
            r.room_capacity,
            h.hall_id,
            h.hall_name
        FROM room r
        JOIN hall h ON r.hall_id = h.hall_id
        ORDER BY h.hall_name ASC, r.room_name ASC
    ");

    echo json_encode(["success" => true, "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

// ==================== ACTION: GET ALL ROOMS (USER, WITH FLAGS) ====================
// Used by: user homepage on load — populates allRoomsCache so stat filters work immediately
// GET ?action=get_all
if ($action === 'get_all') {
    $stmt = $pdo->prepare("
        SELECT
            r.room_id,
            r.hall_id,
            r.room_name,
            r.room_capacity,
            r.room_type,
            r.room_has_ac,
            r.room_status,
            h.hall_name,

            (
                SELECT COUNT(*)
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
            ) AS is_occupied_now,

            (
                SELECT COUNT(*)
                FROM schedule sched
                WHERE sched.room_id = r.room_id
                  AND sched.account_id = ?) AS is_my_active,

            (
                SELECT COUNT(*)
                FROM pendingschedule pend
                WHERE pend.room_id = r.room_id
                  AND pend.account_id = ?
                  AND pend.pending_schedule_status = 'pending'
            ) AS has_pending

        FROM room r
        JOIN hall h ON r.hall_id = h.hall_id
        ORDER BY r.room_name ASC
    ");

    $stmt->execute([$sessionId, $sessionId]);
    $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rooms as $index => $room) {
        $conflictStmt = $pdo->prepare("
            SELECT schedule_start, schedule_end
            FROM schedule
            WHERE room_id = ?
              AND (
                (schedule_day IS NOT NULL
                    AND schedule_day = CURDATE()
                    AND CURTIME() BETWEEN schedule_start AND schedule_end)
                OR
                (schedule_day IS NULL
                    AND schedule_day_of_week = DAYNAME(NOW())
                    AND CURTIME() BETWEEN schedule_start AND schedule_end)
              )
            LIMIT 1
        ");
        $conflictStmt->execute([$room["room_id"]]);
        $conflict = $conflictStmt->fetch(PDO::FETCH_ASSOC);

        $rooms[$index]["conflict_start"] = $conflict ? $conflict["schedule_start"] : null;
        $rooms[$index]["conflict_end"]   = $conflict ? $conflict["schedule_end"]   : null;

        $dbStatus = strtolower($room["room_status"] ?? "");
        if ($dbStatus === "unavailable" || $dbStatus === "maintenance") {
            $rooms[$index]["computed_status"] = $dbStatus;
        } else if (intval($room["is_occupied_now"]) > 0) {
            $rooms[$index]["computed_status"] = "unavailable";
        } else {
            $rooms[$index]["computed_status"] = "available";
        }

        $rooms[$index]["is_my_active"] = intval($room["is_my_active"]);
        $rooms[$index]["has_pending"]  = intval($room["has_pending"]);
    }

    echo json_encode(["success" => true, "data" => $rooms]);
    exit;
}

// ==================== ACTION: GET FEATURED ROOMS ====================
// Used by: user homepage — shows rooms in a selected hall or a specific room
// GET ?action=get_featured&type=hall|room&id=
if ($action === 'get_featured') {
    $type = $_GET["type"] ?? '';
    $id   = intval($_GET["id"] ?? 0);

    if (!$type || !$id) {
        echo json_encode(["success" => false, "error" => "missing_params"]);
        exit;
    }

    if ($type === "hall") {
        $whereClause = "r.hall_id = ?";
    } else {
        $whereClause = "r.room_id = ?";
    }

    // Main room query — includes subqueries for occupancy and user-specific flags
    $stmt = $pdo->prepare("
        SELECT
            r.room_id,
            r.hall_id,
            r.room_name,
            r.room_capacity,
            r.room_type,
            r.room_has_ac,
            r.room_status,
            h.hall_name,

            (
                SELECT COUNT(*)
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
            ) AS is_occupied_now,

            (
                SELECT COUNT(*)
                FROM schedule sched
                WHERE sched.room_id = r.room_id
                  AND sched.account_id = ?
                  AND (
                    (sched.schedule_day IS NOT NULL
                        AND sched.schedule_day = CURDATE()
                        AND CURTIME() BETWEEN sched.schedule_start AND sched.schedule_end)
                    OR
                    (sched.schedule_day IS NULL
                        AND sched.schedule_day_of_week = DAYNAME(NOW())
                        AND CURTIME() BETWEEN sched.schedule_start AND sched.schedule_end)
                  )
            ) AS is_my_active,

            (
                SELECT COUNT(*)
                FROM pendingschedule pend
                WHERE pend.room_id = r.room_id
                  AND pend.account_id = ?
                  AND pend.pending_schedule_status = 'pending'
            ) AS has_pending

        FROM room r
        JOIN hall h ON r.hall_id = h.hall_id
        WHERE {$whereClause}
        ORDER BY r.room_name ASC
    ");

    $stmt->execute([$sessionId, $sessionId, $id]);
    $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // For each room, do a separate simple query to get conflict_start / conflict_end
    // This replaces the repeated correlated subqueries that were in the original SELECT
    foreach ($rooms as $index => $room) {
        $conflictStmt = $pdo->prepare("
            SELECT schedule_start, schedule_end
            FROM schedule
            WHERE room_id = ?
              AND (
                (schedule_day IS NOT NULL
                    AND schedule_day = CURDATE()
                    AND CURTIME() BETWEEN schedule_start AND schedule_end)
                OR
                (schedule_day IS NULL
                    AND schedule_day_of_week = DAYNAME(NOW())
                    AND CURTIME() BETWEEN schedule_start AND schedule_end)
              )
            LIMIT 1
        ");
        $conflictStmt->execute([$room["room_id"]]);
        $conflict = $conflictStmt->fetch(PDO::FETCH_ASSOC);

        $rooms[$index]["conflict_start"] = $conflict ? $conflict["schedule_start"] : null;
        $rooms[$index]["conflict_end"]   = $conflict ? $conflict["schedule_end"]   : null;

        // Compute display status
        $dbStatus = strtolower($room["room_status"] ?? "");

        if ($dbStatus === "unavailable" || $dbStatus === "maintenance") {
            $rooms[$index]["computed_status"] = $dbStatus;
        } else if (intval($room["is_occupied_now"]) > 0) {
            $rooms[$index]["computed_status"] = "unavailable";
        } else {
            $rooms[$index]["computed_status"] = "available";
        }

        // Cast flags to int so JS gets 0/1
        $rooms[$index]["is_my_active"] = intval($room["is_my_active"]);
        $rooms[$index]["has_pending"]  = intval($room["has_pending"]);
    }

    echo json_encode(["success" => true, "data" => $rooms]);
    exit;
}

// ==================== ACTION: UPDATE ROOM STATUS (ADMIN) ====================
// Used by: admin manage rooms page
if ($action === 'update_status') {
    if (!isAdmin($pdo, $sessionId)) {
        echo json_encode(["success" => false, "error" => "not_admin"]);
        exit;
    }

    $body   = json_decode(file_get_contents("php://input"), true);
    $roomId = intval($body["room_id"] ?? 0);
    $status = $body["status"] ?? '';

    $allowed = ["available", "unavailable", "maintenance"];
    if (!$roomId || !in_array($status, $allowed)) {
        echo json_encode(["success" => false, "error" => "invalid_params"]);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE room SET room_status = ? WHERE room_id = ?");
    $stmt->execute([$status, $roomId]);

    echo json_encode(["success" => true, "message" => "Status updated."]);
    exit;
}

// ==================== FALLTHROUGH ====================
echo json_encode(["success" => false, "error" => "invalid_action"]);
?>