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