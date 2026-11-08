<?php
/**
 * Admin Manage Rooms & Halls Controller
 * Handles administrative operations for classroom and campus building management:
 * - get_rooms, get_halls
 * - create_room, update_room, delete_room, update_status
 * - create_hall, update_hall, delete_hall
 * Strictly respects existing schema columns without adding new columns.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

// Session authentication check (401 if unauthenticated)
if (!isset($_SESSION["id"])) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "error" => "unauthorized",
        "message" => "Authentication required."
    ]);
    exit;
}

// Locate database configuration
if (file_exists(__DIR__ . '/../models/db.php')) {
    require_once __DIR__ . '/../models/db.php';
} else {
    require_once __DIR__ . '/../../includes/database_include.php';
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // Secondary DB check to confirm requester is indeed admin
    $adminStmt = $pdo->prepare("SELECT account_is_admin FROM account WHERE account_id = ?");
    $adminStmt->execute([$_SESSION["id"]]);
    $adminRow = $adminStmt->fetch();
    if (!$adminRow || (int)$adminRow['account_is_admin'] !== 1) {
        http_response_code(403);
        echo json_encode([
            "success" => false,
            "error" => "forbidden",
            "message" => "Administrative privileges required."
        ]);
        exit;
    }
} catch (PDOException $e) {
    error_log("Database connection error in admin_manage_rooms_controller: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "db_error",
        "message" => "Database connection failure."
    ]);
    exit;
}

// Parse request payload from JSON or form-data
$inputData = [];
$rawInput = file_get_contents('php://input');
if (!empty($rawInput)) {
    $decoded = json_decode($rawInput, true);
    if (is_array($decoded)) {
        $inputData = $decoded;
    }
}
$payload = array_merge($inputData, $_POST);

$action = $_GET["action"] ?? ($payload['action'] ?? '');

// Enforce HTTP POST on mutation endpoints (RFC 7231 / OWASP)
$mutationActions = [
    'create_room',
    'update_room',
    'delete_room',
    'create_hall',
    'update_hall',
    'delete_hall',
    'update_status'
];

if (in_array($action, $mutationActions, true) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "error" => "Method Not Allowed",
        "message" => "POST method required for this operation."
    ]);
    exit;
}

switch ($action) {
    case 'get_rooms':
        handleGetRooms($pdo);
        break;

    case 'get_halls':
        handleGetHalls($pdo);
        break;

    case 'create_room':
        handleCreateRoom($pdo, $payload);
        break;

    case 'update_room':
        handleUpdateRoom($pdo, $payload);
        break;

    case 'delete_room':
        handleDeleteRoom($pdo, $payload);
        break;

    case 'update_status':
        handleUpdateStatus($pdo, $payload);
        break;

    case 'create_hall':
        handleCreateHall($pdo, $payload);
        break;

    case 'update_hall':
        handleUpdateHall($pdo, $payload);
        break;

    case 'delete_hall':
        handleDeleteHall($pdo, $payload);
        break;

    default:
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "invalid_action",
            "message" => "Unknown action requested."
        ]);
        break;
}

// ----------------------------------------------------------------------
// Room Handlers
// ----------------------------------------------------------------------

/**
 * Fetch all rooms joined with hall information.
 */
function handleGetRooms(PDO $pdo): void {
    try {
        $stmt = $pdo->query("
            SELECT 
                r.room_id,
                r.hall_id,
                r.room_name,
                r.room_capacity,
                r.room_type,
                r.room_has_ac,
                r.room_status,
                h.hall_name,
                h.hall_desc
            FROM room r
            LEFT JOIN hall h ON r.hall_id = h.hall_id
            ORDER BY 
                CASE WHEN h.hall_name IS NULL THEN 1 ELSE 0 END ASC,
                h.hall_name ASC,
                r.room_name ASC
        ");
        $rooms = $stmt->fetchAll();
        echo json_encode(["success" => true, "data" => $rooms]);
    } catch (PDOException $e) {
        error_log("Error in handleGetRooms: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["success" => false, "error" => "query_failed", "message" => "Failed to retrieve rooms."]);
    }
}

/**
 * Create a new room with optional hall linkage.
 */
function handleCreateRoom(PDO $pdo, array $payload): void {
    $roomName = trim((string)($payload['room_name'] ?? ''));
    $hallIdRaw = $payload['hall_id'] ?? null;
    $hallId = (!empty($hallIdRaw) && $hallIdRaw !== 'none') ? (int)$hallIdRaw : null;
    $roomCapacity = isset($payload['room_capacity']) ? (int)$payload['room_capacity'] : 30;
    $roomType = trim((string)($payload['room_type'] ?? 'LECTURE'));
    $roomHasAc = !empty($payload['room_has_ac']) ? 1 : 0;
    $roomStatus = strtolower(trim((string)($payload['room_status'] ?? 'available')));

    if ($roomName === '') {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "missing_room_name", "message" => "Room name is required."]);
        return;
    }

    if (mb_strlen($roomName) > 20) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "name_too_long", "message" => "Room name cannot exceed 20 characters."]);
        return;
    }

    if (mb_strlen($roomType) > 20) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "type_too_long", "message" => "Room type cannot exceed 20 characters."]);
        return;
    }

    if ($roomCapacity <= 0 || $roomCapacity > 999) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "invalid_capacity", "message" => "Room capacity must be between 1 and 999."]);
        return;
    }

    $validStatuses = ['available', 'unavailable', 'maintenance'];
    if (!in_array($roomStatus, $validStatuses, true)) {
        $roomStatus = 'available';
    }

    try {
        // If hall_id is provided, verify hall exists
        if ($hallId !== null) {
            $hStmt = $pdo->prepare("SELECT COUNT(*) FROM hall WHERE hall_id = ?");
            $hStmt->execute([$hallId]);
            if ((int)$hStmt->fetchColumn() === 0) {
                http_response_code(400);
                echo json_encode(["success" => false, "error" => "invalid_hall", "message" => "The specified building does not exist."]);
                return;
            }
        }

        // Uniqueness check within same hall (or standalone)
        if ($hallId !== null) {
            $dupStmt = $pdo->prepare("SELECT COUNT(*) FROM room WHERE LOWER(TRIM(room_name)) = LOWER(TRIM(?)) AND hall_id = ?");
            $dupStmt->execute([$roomName, $hallId]);
        } else {
            $dupStmt = $pdo->prepare("SELECT COUNT(*) FROM room WHERE LOWER(TRIM(room_name)) = LOWER(TRIM(?)) AND hall_id IS NULL");
            $dupStmt->execute([$roomName]);
        }

        if ((int)$dupStmt->fetchColumn() > 0) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "duplicate_room_name", "message" => "A room with this name already exists in the selected building."]);
            return;
        }

        $insertStmt = $pdo->prepare("
            INSERT INTO room (hall_id, room_name, room_capacity, room_type, room_has_ac, room_status)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $insertStmt->execute([
            $hallId,
            $roomName,
            $roomCapacity,
            $roomType,
            $roomHasAc,
            $roomStatus
        ]);

        $createdId = (int)$pdo->lastInsertId();

        echo json_encode([
            "success" => true,
            "message" => "Room created successfully.",
            "data" => [
                "room_id" => $createdId,
                "hall_id" => $hallId,
                "room_name" => $roomName,
                "room_capacity" => $roomCapacity,
                "room_type" => $roomType,
                "room_has_ac" => $roomHasAc,
                "room_status" => $roomStatus
            ]
        ]);
    } catch (PDOException $e) {
        error_log("Error in handleCreateRoom: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["success" => false, "error" => "db_error", "message" => "Failed to create room."]);
    }
}

/**
 * Update an existing room's details.
 */
function handleUpdateRoom(PDO $pdo, array $payload): void {
    $roomId = (int)($payload['room_id'] ?? 0);
    $roomName = trim((string)($payload['room_name'] ?? ''));
    $hallIdRaw = $payload['hall_id'] ?? null;
    $hallId = (!empty($hallIdRaw) && $hallIdRaw !== 'none') ? (int)$hallIdRaw : null;
    $roomCapacity = isset($payload['room_capacity']) ? (int)$payload['room_capacity'] : 30;
    $roomType = trim((string)($payload['room_type'] ?? 'LECTURE'));
    $roomHasAc = !empty($payload['room_has_ac']) ? 1 : 0;
    $roomStatus = strtolower(trim((string)($payload['room_status'] ?? 'available')));

    if ($roomId <= 0) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "invalid_room_id", "message" => "Valid room ID is required."]);
        return;
    }

    if ($roomName === '') {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "missing_room_name", "message" => "Room name is required."]);
        return;
    }

    if (mb_strlen($roomName) > 20) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "name_too_long", "message" => "Room name cannot exceed 20 characters."]);
        return;
    }

    if (mb_strlen($roomType) > 20) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "type_too_long", "message" => "Room type cannot exceed 20 characters."]);
        return;
    }

    if ($roomCapacity <= 0 || $roomCapacity > 999) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "invalid_capacity", "message" => "Room capacity must be between 1 and 999."]);
        return;
    }

    $validStatuses = ['available', 'unavailable', 'maintenance'];
    if (!in_array($roomStatus, $validStatuses, true)) {
        $roomStatus = 'available';
    }

    try {
        // Verify room exists
        $checkStmt = $pdo->prepare("SELECT room_id FROM room WHERE room_id = ?");
        $checkStmt->execute([$roomId]);
        if (!$checkStmt->fetch()) {
            http_response_code(404);
            echo json_encode(["success" => false, "error" => "room_not_found", "message" => "Room not found."]);
            return;
        }

        // Verify hall exists if specified
        if ($hallId !== null) {
            $hStmt = $pdo->prepare("SELECT COUNT(*) FROM hall WHERE hall_id = ?");
            $hStmt->execute([$hallId]);
            if ((int)$hStmt->fetchColumn() === 0) {
                http_response_code(400);
                echo json_encode(["success" => false, "error" => "invalid_hall", "message" => "The specified building does not exist."]);
                return;
            }
        }

        // Uniqueness check excluding current room_id
        if ($hallId !== null) {
            $dupStmt = $pdo->prepare("SELECT COUNT(*) FROM room WHERE LOWER(TRIM(room_name)) = LOWER(TRIM(?)) AND hall_id = ? AND room_id != ?");
            $dupStmt->execute([$roomName, $hallId, $roomId]);
        } else {
            $dupStmt = $pdo->prepare("SELECT COUNT(*) FROM room WHERE LOWER(TRIM(room_name)) = LOWER(TRIM(?)) AND hall_id IS NULL AND room_id != ?");
            $dupStmt->execute([$roomName, $roomId]);
        }

        if ((int)$dupStmt->fetchColumn() > 0) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "duplicate_room_name", "message" => "A room with this name already exists in the selected building."]);
            return;
        }

        $updateStmt = $pdo->prepare("
            UPDATE room 
            SET hall_id = ?, room_name = ?, room_capacity = ?, room_type = ?, room_has_ac = ?, room_status = ?
            WHERE room_id = ?
        ");
        $updateStmt->execute([
            $hallId,
            $roomName,
            $roomCapacity,
            $roomType,
            $roomHasAc,
            $roomStatus,
            $roomId
        ]);

        echo json_encode(["success" => true, "message" => "Room updated successfully."]);
    } catch (PDOException $e) {
        error_log("Error in handleUpdateRoom: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["success" => false, "error" => "db_error", "message" => "Failed to update room."]);
    }
}

/**
 * Delete a room and purge dependent schedules/history safely in a transaction.
 */
function handleDeleteRoom(PDO $pdo, array $payload): void {
    $roomId = (int)($payload['room_id'] ?? 0);
    if ($roomId <= 0) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "invalid_room_id", "message" => "Valid room ID is required."]);
        return;
    }

    try {
        $checkStmt = $pdo->prepare("SELECT room_id FROM room WHERE room_id = ?");
        $checkStmt->execute([$roomId]);
        if (!$checkStmt->fetch()) {
            http_response_code(404);
            echo json_encode(["success" => false, "error" => "room_not_found", "message" => "Room not found."]);
            return;
        }

        $pdo->beginTransaction();

        // Remove history records referencing this room
        $pdo->prepare("DELETE FROM history WHERE room_id = ?")->execute([$roomId]);

        // Remove active and recurring schedules
        $pdo->prepare("DELETE FROM schedule WHERE room_id = ?")->execute([$roomId]);

        // Remove pending schedules
        $pdo->prepare("DELETE FROM pendingschedule WHERE room_id = ?")->execute([$roomId]);

        // Finally delete the room
        $pdo->prepare("DELETE FROM room WHERE room_id = ?")->execute([$roomId]);

        $pdo->commit();

        echo json_encode(["success" => true, "message" => "Room deleted successfully."]);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error in handleDeleteRoom: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["success" => false, "error" => "db_error", "message" => "Failed to delete room."]);
    }
}

/**
 * Fast status toggle for room (available / unavailable / maintenance).
 */
function handleUpdateStatus(PDO $pdo, array $payload): void {
    $roomId = (int)($payload['room_id'] ?? 0);
    $status = strtolower(trim((string)($payload['status'] ?? ($payload['room_status'] ?? ''))));

    $validStatuses = ['available', 'unavailable', 'maintenance'];
    if ($roomId <= 0 || !in_array($status, $validStatuses, true)) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "invalid_params", "message" => "Valid room_id and status are required."]);
        return;
    }

    try {
        $checkStmt = $pdo->prepare("SELECT room_id FROM room WHERE room_id = ?");
        $checkStmt->execute([$roomId]);
        if (!$checkStmt->fetch()) {
            http_response_code(404);
            echo json_encode(["success" => false, "error" => "room_not_found", "message" => "Room not found."]);
            return;
        }

        $stmt = $pdo->prepare("UPDATE room SET room_status = ? WHERE room_id = ?");
        $stmt->execute([$status, $roomId]);

        echo json_encode(["success" => true, "message" => "Room status updated successfully."]);
    } catch (PDOException $e) {
        error_log("Error in handleUpdateStatus: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["success" => false, "error" => "db_error", "message" => "Failed to update room status."]);
    }
}

// ----------------------------------------------------------------------
// Hall Handlers
// ----------------------------------------------------------------------

/**
 * Fetch all halls with count of assigned rooms.
 */
function handleGetHalls(PDO $pdo): void {
    try {
        $stmt = $pdo->query("
            SELECT 
                h.hall_id,
                h.hall_name,
                h.hall_desc,
                COUNT(r.room_id) AS room_count
            FROM hall h
            LEFT JOIN room r ON h.hall_id = r.hall_id
            GROUP BY h.hall_id, h.hall_name, h.hall_desc
            ORDER BY h.hall_name ASC
        ");
        $halls = $stmt->fetchAll();
        echo json_encode(["success" => true, "data" => $halls]);
    } catch (PDOException $e) {
        error_log("Error in handleGetHalls: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["success" => false, "error" => "query_failed", "message" => "Failed to retrieve halls."]);
    }
}

/**
 * Create a new campus building / hall.
 */
function handleCreateHall(PDO $pdo, array $payload): void {
    $hallName = trim((string)($payload['hall_name'] ?? ''));
    $hallDesc = trim((string)($payload['hall_desc'] ?? ''));

    if ($hallName === '') {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "missing_hall_name", "message" => "Building name is required."]);
        return;
    }

    if (mb_strlen($hallName) > 20) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "name_too_long", "message" => "Building name cannot exceed 20 characters."]);
        return;
    }

    if (mb_strlen($hallDesc) > 250) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "desc_too_long", "message" => "Building description cannot exceed 250 characters."]);
        return;
    }

    try {
        // Uniqueness check on hall_name
        $dupStmt = $pdo->prepare("SELECT COUNT(*) FROM hall WHERE LOWER(TRIM(hall_name)) = LOWER(TRIM(?))");
        $dupStmt->execute([$hallName]);
        if ((int)$dupStmt->fetchColumn() > 0) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "duplicate_hall_name", "message" => "A building with this name already exists."]);
            return;
        }

        $insertStmt = $pdo->prepare("INSERT INTO hall (hall_name, hall_desc) VALUES (?, ?)");
        $insertStmt->execute([$hallName, $hallDesc !== '' ? $hallDesc : null]);

        $createdId = (int)$pdo->lastInsertId();

        echo json_encode([
            "success" => true,
            "message" => "Building created successfully.",
            "data" => [
                "hall_id" => $createdId,
                "hall_name" => $hallName,
                "hall_desc" => $hallDesc
            ]
        ]);
    } catch (PDOException $e) {
        error_log("Error in handleCreateHall: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["success" => false, "error" => "db_error", "message" => "Failed to create building."]);
    }
}

/**
 * Update an existing building / hall.
 */
function handleUpdateHall(PDO $pdo, array $payload): void {
    $hallId = (int)($payload['hall_id'] ?? 0);
    $hallName = trim((string)($payload['hall_name'] ?? ''));
    $hallDesc = trim((string)($payload['hall_desc'] ?? ''));

    if ($hallId <= 0) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "invalid_hall_id", "message" => "Valid building ID is required."]);
        return;
    }

    if ($hallName === '') {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "missing_hall_name", "message" => "Building name is required."]);
        return;
    }

    if (mb_strlen($hallName) > 20) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "name_too_long", "message" => "Building name cannot exceed 20 characters."]);
        return;
    }

    if (mb_strlen($hallDesc) > 250) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "desc_too_long", "message" => "Building description cannot exceed 250 characters."]);
        return;
    }

    try {
        $checkStmt = $pdo->prepare("SELECT hall_id FROM hall WHERE hall_id = ?");
        $checkStmt->execute([$hallId]);
        if (!$checkStmt->fetch()) {
            http_response_code(404);
            echo json_encode(["success" => false, "error" => "hall_not_found", "message" => "Building not found."]);
            return;
        }

        // Uniqueness check excluding current hall
        $dupStmt = $pdo->prepare("SELECT COUNT(*) FROM hall WHERE LOWER(TRIM(hall_name)) = LOWER(TRIM(?)) AND hall_id != ?");
        $dupStmt->execute([$hallName, $hallId]);
        if ((int)$dupStmt->fetchColumn() > 0) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "duplicate_hall_name", "message" => "A building with this name already exists."]);
            return;
        }

        $updateStmt = $pdo->prepare("UPDATE hall SET hall_name = ?, hall_desc = ? WHERE hall_id = ?");
        $updateStmt->execute([$hallName, $hallDesc !== '' ? $hallDesc : null, $hallId]);

        echo json_encode(["success" => true, "message" => "Building updated successfully."]);
    } catch (PDOException $e) {
        error_log("Error in handleUpdateHall: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["success" => false, "error" => "db_error", "message" => "Failed to update building."]);
    }
}

/**
 * Delete a hall, ensuring no rooms are assigned to it.
 */
function handleDeleteHall(PDO $pdo, array $payload): void {
    $hallId = (int)($payload['hall_id'] ?? 0);
    if ($hallId <= 0) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "invalid_hall_id", "message" => "Valid building ID is required."]);
        return;
    }

    try {
        $checkStmt = $pdo->prepare("SELECT hall_id FROM hall WHERE hall_id = ?");
        $checkStmt->execute([$hallId]);
        if (!$checkStmt->fetch()) {
            http_response_code(404);
            echo json_encode(["success" => false, "error" => "hall_not_found", "message" => "Building not found."]);
            return;
        }

        // Foreign key safety: check if rooms reference this hall
        $roomCountStmt = $pdo->prepare("SELECT COUNT(*) FROM room WHERE hall_id = ?");
        $roomCountStmt->execute([$hallId]);
        $assignedRooms = (int)$roomCountStmt->fetchColumn();

        if ($assignedRooms > 0) {
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "error" => "hall_has_rooms",
                "message" => "Cannot delete this building because {$assignedRooms} room(s) are currently assigned to it. Please reassign those rooms before deleting."
            ]);
            return;
        }

        $pdo->beginTransaction();

        // Nullify or delete any legacy history records referencing this hall
        $pdo->prepare("UPDATE history SET hall_id = NULL WHERE hall_id = ?")->execute([$hallId]);

        // Delete the hall
        $pdo->prepare("DELETE FROM hall WHERE hall_id = ?")->execute([$hallId]);

        $pdo->commit();

        echo json_encode(["success" => true, "message" => "Building deleted successfully."]);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error in handleDeleteHall: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["success" => false, "error" => "db_error", "message" => "Failed to delete building."]);
    }
}
