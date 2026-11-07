<?php




$viewsDir = __DIR__ . '/../app/views/';
$pages = [
    'user_homepage_view.php' => ['active_nav' => 'user_homepage_view.php', 'active_footer' => null],
    'calendar_view.php'      => ['active_nav' => 'calendar_view.php',      'active_footer' => null],
    'notifications_view.php' => ['active_nav' => null,                    'active_footer' => null],
    'profile_view.php'       => ['active_nav' => null,                    'active_footer' => null],
    'security_view.php'      => ['active_nav' => 'security_view.php',      'active_footer' => null],
    'settings_view.php'      => ['active_nav' => 'settings_view.php',      'active_footer' => null],
    'reserve_view.php'       => ['active_nav' => null,                    'active_footer' => null],
    'about_view.php'         => ['active_nav' => null,                    'active_footer' => 'about_view.php'],
    'contact_view.php'       => ['active_nav' => null,                    'active_footer' => null],
];

echo "=== Collegiate Sidebar Consistency Test ===\n";
$allPassed = true;

foreach ($pages as $filename => $expected) {
    $filepath = $viewsDir . $filename;
    if (!file_exists($filepath)) {
        echo "[FAIL] Missing file: $filename\n";
        $allPassed = false;
        continue;
    }

    $content = file_get_contents($filepath);
    $errors = [];


    if (!str_contains($content, 'fonts.googleapis.com/css2?family=Plus+Jakarta+Sans')) {
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
    if (!str_contains($content, 'First City Providential College')) {
        $errors[] = "Missing brand subtitle 'First City Providential College'";
    }


    if (!str_contains($content, 'Campus Dashboard')) {
        $errors[] = "Missing nav label 'Campus Dashboard'";
    }
    if (!str_contains($content, 'Master Calendar')) {
        $errors[] = "Missing nav label 'Master Calendar'";
    }
    if (!str_contains($content, 'id="notif-badge"')) {
        $errors[] = "Missing #notif-badge";
    }


    if ($expected['active_nav'] !== null) {

        if (!preg_match('/href="' . preg_quote($expected['active_nav'], '/') . '"[^>]*class="[^"]*nav-link[^"]*active[^"]*"/', $content)) {
            $errors[] = "Expected active nav-link for " . $expected['active_nav'];
        }
    }


    if ($expected['active_footer'] !== null) {
        if (!preg_match('/href="' . preg_quote($expected['active_footer'], '/') . '"[^>]*class="[^"]*footer-link[^"]*active[^"]*"/', $content)) {
            $errors[] = "Expected active footer-link for " . $expected['active_footer'];
        }
    }


    if (!str_contains($content, 'sidebar-bottom')) {
        $errors[] = "Missing .sidebar-bottom";
    }
    if (!str_contains($content, 'sidebar-profile-card')) {
        $errors[] = "Missing .sidebar-profile-card";
    }
    if (!str_contains($content, 'user-avatar-circle')) {
        $errors[] = "Missing .user-avatar-circle";
    }
    if (!str_contains($content, 'BSIT &bull; Student')) {
        $errors[] = "Missing 'BSIT &bull; Student' subtitle";
    }
    if (!str_contains($content, 'sidebar-profile-logout')) {
        $errors[] = "Missing .sidebar-profile-logout";
    }
    if (!str_contains($content, 'about_view.php') || !str_contains($content, 'contact_view.php') || !str_contains($content, 'logout.php')) {
        $errors[] = "Missing footer route links";
    }

    if (empty($errors)) {
        echo "  [PASS] $filename: Sidebar structure, brand header, profile strip, & active states verified.\n";
    } else {
        echo "  [FAIL] $filename:\n    - " . implode("\n    - ", $errors) . "\n";
        $allPassed = false;
    }
}

if ($allPassed) {
    echo "\n=== ALL 9 USER PAGES HAVE FULLY CONSISTENT MODERN COLLEGIATE SIDEBAR (Exit 0) ===\n";
    exit(0);
} else {
    echo "\n=== ERRORS DETECTED IN SIDEBAR CONSISTENCY ===\n";
    exit(1);
}
