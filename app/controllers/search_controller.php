<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION["id"])) {
    echo json_encode(["error" => "unauthorized"]);
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

$query = isset($_GET["q"]) ? trim($_GET["q"]) : '';

if (empty($query)) {
    echo json_encode(["error" => "empty_query"]);
    exit;
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $search = "%" . $query . "%";

    $stmt = $pdo->prepare("
        CALL `spSearchRoom`(?);
    ");

    $stmt->execute([$search]);
    $results = array_values(array_filter($stmt->fetchAll(PDO::FETCH_ASSOC), function ($result) {
        return !isExcludedDeanOffice($result);
    }));

    $isAdminUser = isAdmin($pdo, intval($_SESSION["id"]));
    if (!$isAdminUser && !empty($results)) {
        $roomIds = [];
        foreach ($results as $res) {
            if (($res['type'] ?? '') === 'room' && !empty($res['id'])) {
                $roomIds[] = intval($res['id']);
            }
        }
        if (!empty($roomIds)) {
            $inPlaceholders = implode(',', array_fill(0, count($roomIds), '?'));
            $statusStmt = $pdo->prepare("
                SELECT room_id, room_status 
                FROM room 
                WHERE room_id IN ($inPlaceholders)
            ");
            $statusStmt->execute($roomIds);
            $badRooms = [];
            while ($row = $statusStmt->fetch(PDO::FETCH_ASSOC)) {
                $st = strtolower(trim((string)$row['room_status']));
                if ($st === 'maintenance' || $st === 'unavailable') {
                    $badRooms[(int)$row['room_id']] = true;
                }
            }
            $results = array_values(array_filter($results, function ($res) use ($badRooms) {
                if (($res['type'] ?? '') === 'room' && isset($badRooms[(int)$res['id']])) {
                    return false;
                }
                return true;
            }));
        }
    }

    echo json_encode([
        "success" => true,
        "data" => $results
    ]);

} catch (PDOException $e) {
    echo json_encode([
        "error" => "database_error",
        "message" => $e->getMessage(),
        "code" => $e->getCode()
    ]);
}
?>
