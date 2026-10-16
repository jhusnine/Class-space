<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION["id"])) {
    echo json_encode(["success" => false, "error" => "unauthorized"]);
    exit;
}

require "../../includes/database_include.php";
$body = json_decode(file_get_contents("php://input"), true);
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "invalid_request"]);
    exit;
}

$notifId = $body["notif_id"] ?? null;
$action = $body["action"] ?? "read";
$accountId = intval($_SESSION["id"]);

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $tableExists = $pdo->query("SHOW TABLES LIKE 'notification'")->fetchColumn();
    if (!$tableExists) {
        echo json_encode(["success" => true, "deleted" => false, "retained" => true]);
        exit;
    }

    $hasSoftDeleteColumn = (bool)$pdo->query(
        "SHOW COLUMNS FROM notification LIKE 'notif_user_deleted_at'"
    )->fetchColumn();
    $visibleRowsOnly = $hasSoftDeleteColumn ? " AND notif_user_deleted_at IS NULL" : "";

    if ($action === "delete") {
        if (!$hasSoftDeleteColumn) {
            http_response_code(409);
            echo json_encode([
                "success" => false,
                "error" => "database_migration_required",
                "retained" => true,
            ]);
            exit;
        }

        $validatedId = filter_var($notifId, FILTER_VALIDATE_INT);
        if ($validatedId === false || $validatedId < 1) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "invalid_notification_id"]);
            exit;
        }

        // Keep the row and message intact; record only when the owner hid it.
        $stmt = $pdo->prepare("\n            UPDATE notification
            SET notif_user_deleted_at = CURRENT_TIMESTAMP
            WHERE notif_id = ?
              AND account_id = ?
              AND notif_user_deleted_at IS NULL
        ");
        $stmt->execute([$validatedId, $accountId]);
        echo json_encode(["success" => true, "deleted" => $stmt->rowCount() > 0, "retained" => true]);
        exit;
    }

    if ($action === "contact_replies") {
        $stmt = $pdo->prepare("
            UPDATE notification
            SET notif_is_read = 1
            WHERE account_id = ?
              AND notif_is_read = 0
              AND notif_type = 'message'
              AND notif_title IN ('Admin Reply', 'ICT Support Reply', 'Support Reply')
              {$visibleRowsOnly}
        ");
        $stmt->execute([$accountId]);
        echo json_encode(["success" => true, "updated" => $stmt->rowCount()]);
        exit;
    }

    if ($action !== "read") {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "invalid_action"]);
        exit;
    }

    if ($notifId === "all") {
        $pdo->prepare("UPDATE notification SET notif_is_read = 1 WHERE account_id = ? {$visibleRowsOnly}")
            ->execute([$accountId]);
    } else {
        $validatedId = filter_var($notifId, FILTER_VALIDATE_INT);
        if ($validatedId === false || $validatedId < 1) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "invalid_notification_id"]);
            exit;
        }
        $pdo->prepare("UPDATE notification SET notif_is_read = 1 WHERE notif_id = ? AND account_id = ? {$visibleRowsOnly}")
            ->execute([$validatedId, $accountId]);
    }

    echo json_encode(["success" => true]);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
?>
