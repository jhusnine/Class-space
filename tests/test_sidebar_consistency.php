<?php

$viewsDir = __DIR__ . '/../app/views/';
$partialPath = $viewsDir . 'partials/user_sidebar.php';
$pages = [
    'user_homepage_view.php' => ['active_tab' => 'dashboard'],
    'calendar_view.php'      => ['active_tab' => 'calendar'],
    'notifications_view.php' => ['active_tab' => null],
    'profile_view.php'       => ['active_tab' => null],
    'settings_view.php'      => ['active_tab' => 'settings'],
    'reserve_view.php'       => ['active_tab' => 'reserve'],
    'contact_view.php'       => ['active_tab' => null],
];

echo "=== Collegiate Sidebar Consistency Test ===\n";
$allPassed = true;

if (!file_exists($partialPath)) {
    echo "[FAIL] Missing shared sidebar partial: partials/user_sidebar.php\n";
    exit(1);
}
$partialContent = file_get_contents($partialPath);
if (str_contains($partialContent, 'First City Providential College')) {
    echo "[FAIL] Brand subtitle 'First City Providential College' should be removed from sidebar partial.\n";
    exit(1);
}

foreach ($pages as $filename => $expected) {
    $filepath = $viewsDir . $filename;
    if (!file_exists($filepath)) {
        echo "[FAIL] Missing file: $filename\n";
        $allPassed = false;
        continue;
    }

    $source = file_get_contents($filepath);
    $content = $source . "\n" . $partialContent;
    $errors = [];

    if (!str_contains($source, 'partials/user_sidebar.php')) {
        $errors[] = "Missing inclusion of partials/user_sidebar.php";
    }

    if (!str_contains($source, 'fonts.googleapis.com/css2?family=Plus+Jakarta+Sans')) {
        $errors[] = "Missing Plus Jakarta Sans font link";
    }

    if (!str_contains($content, 'id="mobile-menu-toggle"')) {
        $errors[] = "Missing #mobile-menu-toggle";
    }
    if (!str_contains($content, 'id="mobile-menu-backdrop"')) {
        $errors[] = "Missing #mobile-menu-backdrop";
    }
    if (!str_contains($content, 'id="classspace-sidebar"')) {
        $errors[] = "Missing #classspace-sidebar";
    }

    if (!str_contains($content, 'brand-header') || !str_contains($content, 'brand-logo-frame')) {
        $errors[] = "Missing brand-header or brand-logo-frame";
    }
    if (!str_contains($content, 'class="brand-title">ClassSpace')) {
        $errors[] = "Missing brand title 'ClassSpace'";
    }

    if (!str_contains($content, '<span>Dashboard</span>')) {
        $errors[] = "Missing nav label 'Dashboard'";
    }
    if (!str_contains($content, '<span>Calendar</span>')) {
        $errors[] = "Missing nav label 'Calendar'";
    }

    if ($expected['active_tab'] !== null) {
        if (!str_contains($source, "\$activeTab = '{$expected['active_tab']}';") && !str_contains($source, "\$activeTab = \"{$expected['active_tab']}\";")) {
            $errors[] = "Expected activeTab variable '{$expected['active_tab']}' in $filename";
        }
    }

    if (str_contains($content, 'sidebar-bottom') || str_contains($content, 'sidebar-profile-card')) {
        $errors[] = "Sidebar should not contain .sidebar-bottom or .sidebar-profile-card";
    }

    if (empty($errors)) {
        echo "  [PASS] $filename: Sidebar structure, brand header, & active states verified.\n";
    } else {
        echo "  [FAIL] $filename:\n    - " . implode("\n    - ", $errors) . "\n";
        $allPassed = false;
    }
}

if ($allPassed) {
    echo "\n=== ALL 7 USER PAGES HAVE FULLY CONSISTENT MODERN COLLEGIATE SIDEBAR (Exit 0) ===\n";
    exit(0);
} else {
    echo "\n=== ERRORS DETECTED IN SIDEBAR CONSISTENCY ===\n";
    exit(1);
}
