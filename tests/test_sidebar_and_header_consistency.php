<?php
/**
 * Automated Consistency Test for User Sidebar and Header Layout
 */

$rootDir = dirname(__DIR__);
$viewsDir = $rootDir . '/app/views';
$cssDir = $rootDir . '/public/css';
$sidebarPartial = $viewsDir . '/partials/user_sidebar.php';

$views = [
    'user_homepage_view.php',
    'calendar_view.php',
    'reserve_view.php',
    'settings_view.php',
    'contact_view.php',
    'notifications_view.php',
    'profile_view.php'
];

$failures = [];

// 1. Partial exists
if (!file_exists($sidebarPartial)) {
    $failures[] = "Sidebar partial not found at: $sidebarPartial";
} else {
    exec("php -l " . escapeshellarg($sidebarPartial) . " 2>&1", $lintOutput, $lintCode);
    if ($lintCode !== 0) {
        $failures[] = "Sidebar partial has PHP syntax errors:\n" . implode("\n", $lintOutput);
    }
}

// 2. Canonical items in partial
if (file_exists($sidebarPartial)) {
    $partialContent = file_get_contents($sidebarPartial);
    if (!str_contains($partialContent, 'Dashboard')) {
        $failures[] = "Sidebar partial is missing 'Dashboard' link.";
    }
    if (!str_contains($partialContent, 'Calendar')) {
        $failures[] = "Sidebar partial is missing 'Calendar' link.";
    }
    if (!str_contains($partialContent, 'Reserve')) {
        $failures[] = "Sidebar partial is missing 'Reserve' link.";
    }
    if (!str_contains($partialContent, 'Settings')) {
        $failures[] = "Sidebar partial is missing 'Settings' link.";
    }
    if (str_contains($partialContent, 'href="security_view.php"')) {
        $failures[] = "Sidebar partial should NOT contain a link to security_view.php (Security is under Settings).";
    }
    if (str_contains($partialContent, 'College College')) {
        $failures[] = "Sidebar partial contains duplicate 'College College' typo.";
    }
}

// 3. Check every view includes the partial, has .page-header and h1, and does not duplicate sidebar
foreach ($views as $viewFile) {
    $filePath = $viewsDir . '/' . $viewFile;
    if (!file_exists($filePath)) {
        $failures[] = "View file missing: $viewFile";
        continue;
    }
    $content = file_get_contents($filePath);
    if (!str_contains($content, 'partials/user_sidebar.php')) {
        $failures[] = "View $viewFile does not include partials/user_sidebar.php";
    }
    if (str_contains($content, '<aside class="sidebar" id="classspace-sidebar">') && !str_contains($content, 'require __DIR__ . \'/partials/user_sidebar.php\'') && !str_contains($content, 'include __DIR__ . \'/partials/user_sidebar.php\'')) {
        $failures[] = "View $viewFile still contains hardcoded <aside class=\"sidebar\"> markup.";
    }
    if (str_contains($content, '<a href="security_view.php"') && str_contains($content, 'nav-link')) {
        $failures[] = "View $viewFile still contains obsolete security_view.php in navigation.";
    }
    if (!str_contains($content, 'class="page-header') && !str_contains($content, 'class=\'page-header')) {
        $failures[] = "View $viewFile is missing standardized .page-header container.";
    }
    if (!preg_match('/<h1[^>]*>/', $content)) {
        $failures[] = "View $viewFile is missing <h1> heading element.";
    }
}

// 4. Check CSS rules for stable layout and header alignment
$globalCss = $cssDir . '/global.css';
if (file_exists($globalCss)) {
    $content = file_get_contents($globalCss);
    if (!str_contains($content, 'scrollbar-gutter: stable;')) {
        $failures[] = "global.css is missing 'scrollbar-gutter: stable;' to prevent page jump.";
    }
    if (!str_contains($content, 'padding: 16px 32px 32px;')) {
        $failures[] = "global.css .main-content does not have standardized padding '16px 32px 32px;'.";
    }
    if (!str_contains($content, 'margin: 0 0 6px;')) {
        $failures[] = "global.css .user-utility-bar does not have tightened margin of '0 0 6px;'.";
    }
}

// 5. Check messages_style.css doesn't override sidebar
$messagesCss = $cssDir . '/messages_style.css';
if (file_exists($messagesCss)) {
    $content = file_get_contents($messagesCss);
    if (preg_match('/^\.sidebar\s*\{/m', $content)) {
        $failures[] = "messages_style.css contains duplicate .sidebar declaration.";
    }
}

// 6. Check reserve_style.css does not center .page-header
$reserveCss = $cssDir . '/reserve_style.css';
if (file_exists($reserveCss)) {
    $content = file_get_contents($reserveCss);
    if (preg_match('/\.page-header\s*\{[^}]*margin-inline:\s*auto/s', $content)) {
        $failures[] = "reserve_style.css still centers .page-header with 'margin-inline: auto'.";
    }
}

echo "\n=== Sidebar & Header Consistency Test Results ===\n";
if (empty($failures)) {
    echo "SUCCESS: All sidebar and layout consistency checks PASSED.\n";
    exit(0);
} else {
    echo "FAILED: " . count($failures) . " issue(s) found:\n";
    foreach ($failures as $f) {
        echo " - " . $f . "\n";
    }
    exit(1);
}
