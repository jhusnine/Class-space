<?php
require "../../includes/database_include.php";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $today = date('Y-m-d');


    $pdo->prepare("
        DELETE FROM schedule
        WHERE schedule_day IS NOT NULL
          AND schedule_day < ?
    ")->execute([$today]);


    $pdo->prepare("
        DELETE FROM pendingschedule
        WHERE pending_schedule_day IS NOT NULL
          AND pending_schedule_day < ?
    ")->execute([$today]);

} catch (PDOException $e) {

    error_log("Cleanup error: " . $e->getMessage());
}
?>
