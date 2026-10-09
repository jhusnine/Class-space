<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION["id"])) { echo json_encode(["error" => "unauthorized"]); exit; }

require "../../includes/database_include.php";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pending   = $pdo->query("SELECT COUNT(*) FROM pendingschedule WHERE pending_schedule_status = 'pending'")->fetchColumn();
    $rooms     = $pdo->query("SELECT COUNT(*) FROM room")->fetchColumn();
    $schedules = $pdo->query("SELECT COUNT(*) FROM schedule")->fetchColumn();

    echo json_encode(["success" => true, "pending" => $pending, "rooms" => $rooms, "schedules" => $schedules]);
} catch (PDOException $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
?>