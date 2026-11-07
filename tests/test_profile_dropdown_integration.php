<?php
/**
 * Integration Test for User Utility Bar Profile Dropdown
 */

error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION["id"] = 1;
$_SESSION["fname"] = "Juan";
$_SESSION["lname"] = "Dela Cruz";
$_SESSION["email"] = "juan@example.com";
$_SESSION["role"] = "Teacher";
$_SESSION["fname"] = "Juan";
$_SESSION["lname"] = "Dela Cruz";
$_SESSION["email"] = "juan@example.com";
$_SESSION["role"] = "Teacher";

$views = [
    'user_homepage_view.php',
    'calendar_view.php',
    'reserve_view.php',
    'settings_view.php',
    'contact_view.php',
    'notifications_view.php',
    'profile_view.php'
];

$allPassed = true;
echo "=== Profile Dropdown Integration Verification ===\n";

$originalCwd = getcwd();
chdir(__DIR__ . '/../app/views');

foreach ($views as $view) {
    ob_start();
    require __DIR__ . '/../app/views/' . $view;
    $html = ob_get_clean();

    $checks = [
        "Trigger exists" => str_contains($html, 'id="profile-dropdown-trigger"'),
        "Dropdown menu exists" => str_contains($html, 'id="profile-dropdown"'),
        "User name in dropdown" => str_contains($html, 'Juan Dela Cruz'),
        "User email in dropdown" => str_contains($html, 'juan@example.com'),
        "User role in dropdown" => str_contains($html, 'Teacher'),
        "Profile link in dropdown" => str_contains($html, 'href="profile_view.php" class="profile-dropdown-item'),
        "Settings link in dropdown" => str_contains($html, 'href="settings_view.php" class="profile-dropdown-item'),
        "Logout link in dropdown" => str_contains($html, 'href="logout.php" class="profile-dropdown-item item-logout"'),
        "No sidebar-profile-card" => !str_contains($html, 'class="sidebar-profile-card"'),
        "No sidebar-bottom" => !str_contains($html, 'class="sidebar-bottom"'),
    ];

    $failed = [];
    foreach ($checks as $name => $pass) {
        if (!$pass) {
            $failed[] = $name;
        }
    }

    if (empty($failed)) {
        echo "  [PASS] $view: Profile dropdown and clean sidebar verified.\n";
    } else {
        echo "  [FAIL] $view:\n    - " . implode("\n    - ", $failed) . "\n";
        $allPassed = false;
    }
}

if ($allPassed) {
    echo "\nSUCCESS: All 7 views render the profile dropdown and have a clean sidebar (Exit 0)\n";
    exit(0);
} else {
    echo "\nFAILED: Issues detected in profile dropdown integration\n";
    exit(1);
}
