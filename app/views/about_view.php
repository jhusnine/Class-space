<?php
session_start();

if (!isset($_SESSION["id"])) {
    header("Location: ../index.php");
    exit;
}

header("Location: settings_view.php#about-settings");
exit;
