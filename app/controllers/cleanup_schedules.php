<?php
require "../../includes/database_include.php";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $today = date('Y-m-d');

    // Remove one-time schedules where the date has passed
    $pdo->prepare("
        DELETE FROM schedule 
        WHERE schedule_day IS NOT NULL 
          AND schedule_day < ?
    ")->execute([$today]);

    // Remove one-time pending schedules that have passed too
    $pdo->prepare("
        DELETE FROM pendingschedule 
        WHERE schedule_day IS NOT NULL 
          AND schedule_day < ?
    ")->execute([$today]);

} catch (PDOException $e) {
    // Fail silently on page load — log it if you have logging set up
    error_log("Cleanup error: " . $e->getMessage());
}
?>