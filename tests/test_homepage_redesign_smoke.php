<?php
/**
 * Test: ClassSpace Homepage Redesign Smoke & Verification Suite
 * Verifies CSS tokens, status edge rules, view markup elements,
 * JS preserved handlers, and functionality matrix items.
 */

echo "=== Homepage Redesign Smoke Test Suite ===\n";

// --- Section 1: CSS Design Tokens & Status Edges ---
$css = file_get_contents(__DIR__ . '/../public/css/homepage_style.css');

$requiredCssSnippets = [
    '#3D9B68',                  // Available institutional green
    '#7B8798',                  // Unavailable slate gray
    'border-left: 4px solid',   // 4px signature status edge
    'repeat(3, 1fr)',           // Predictable 3-column grid
    'aspect-ratio: 16 / 9',     // 16:9 photography ratio
    '.room-status-legend',      // Status legend indicator
    '.quick-reserve-drawer'     // In-page reservation drawer
];

foreach ($requiredCssSnippets as $snippet) {
    if (!str_contains($css, $snippet)) {
        throw new RuntimeException("FAIL [CSS]: Missing required snippet: " . $snippet);
    }
}
echo "PASS: Task 1 CSS tokens and status edges verified.\n";

// --- Section 2: View Markup & Preserved Elements ---
$view = file_get_contents(__DIR__ . '/../app/views/user_homepage_view.php');

$requiredViewElements = [
    'session_start()',
    'cleanup_schedules.php',
    'id="search-input"',
    'id="search-results"',
    'id="stat-active"',
    'id="stat-pending"',
    'id="stat-available"',
    'id="notif-badge"',
    'id="rooms-grid"',
    'id="quick-reserve-drawer"',
    'id="drawer-backdrop"',
    'class="sidebar-profile-card"',
    'class="room-status-legend"',
    'logout.php'
];

foreach ($requiredViewElements as $element) {
    if (!str_contains($view, $element)) {
        throw new RuntimeException("FAIL [View]: Missing required element: " . $element);
    }
}
echo "PASS: Task 2 view markup and preserved elements verified.\n";

// --- Section 3: JavaScript Handlers & Direct Catalog Data Binding ---
$js = file_get_contents(__DIR__ . '/../public/js/homepage_script.js');

$requiredJsSnippets = [
    'user_stats.php',
    'notifications_controller.php',
    'search_controller.php',
    'room-status-available',
    'room-status-unavailable',
    'card-media',
    'filterByBuilding',
    'initSharedMobileMenu',
    'wireImageFallbacks',
    'loadStats',
    'loadNotifBadge'
];

foreach ($requiredJsSnippets as $snippet) {
    if (!str_contains($js, $snippet)) {
        throw new RuntimeException("FAIL [JS]: Missing required snippet: " . $snippet);
    }
}
echo "PASS: Task 3 JavaScript handlers and preserved endpoints verified.\n";


