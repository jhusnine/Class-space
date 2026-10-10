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
    'repeat(auto-fill, minmax(', // Responsive auto-fill grid
    'aspect-ratio: 1 / 1',      // 1:1 square room card ratio
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
    'loadNotifBadge',
    'BUILDING_IMAGES',
    'ROOM_TYPE_IMAGES'
];

foreach ($requiredJsSnippets as $snippet) {
    if (!str_contains($js, $snippet)) {
        throw new RuntimeException("FAIL [JS]: Missing required snippet: " . $snippet);
    }
}

if (str_contains($js, 'public/images/rooms/') || str_contains($js, 'public/images/halls/')) {
    throw new RuntimeException("FAIL [JS]: Non-existent rooms/ or halls/ paths detected in homepage_script.js");
}
echo "PASS: Task 3 JavaScript handlers, zero-404 resolvers, and preserved endpoints verified.\n";

// --- Section 4: Quick Reserve Drawer Interactions ---
$requiredDrawerSnippets = [
    'openQuickReserveDrawer',
    'closeQuickReserveDrawer',
    'quick-reserve-drawer',
    'drawer-backdrop',
    'drawer-reserve-form',
    'drawer-submit-btn',
    'drawer-advanced-link'
];

foreach ($requiredDrawerSnippets as $snippet) {
    if (!str_contains($js, $snippet)) {
        throw new RuntimeException("FAIL [Drawer]: Missing required snippet: " . $snippet);
    }
}
echo "PASS: Task 4 Quick Reserve Drawer interactions verified.\n";

// --- Section 5: Exhaustive Functionality Preservation Matrix (17 Items) ---
echo "--- Section 5: Functionality Preservation Matrix Verification ---\n";

$matrixChecks = [
    // 1. Session & Auth
    'Auth Guard' => str_contains($view, 'session_start()') && str_contains($view, 'header("Location: ../index.php")'),
    // 2. Schedule Auto-Cleanup
    'Schedule Cleanup' => str_contains($view, 'require_once "../controllers/cleanup_schedules.php"'),
    // 3. Theme Cookie Detection
    'Theme Sync' => str_contains($view, '$_COOKIE["theme"] === "light"'),
    // 4. Stats Counters Polling
    'Stats Polling' => str_contains($js, 'user_stats.php') && str_contains($js, 'setInterval(loadStats, 60000)'),
    // 5. Notification Badges Polling
    'Notif Polling' => str_contains($js, 'notifications_controller.php') && str_contains($js, 'setInterval(loadNotifBadge, 30000)'),
    // 6. Live Search Autocomplete
    'Search Autocomplete' => str_contains($js, 'search_controller.php') && str_contains($js, 'runSearch'),
    // 7. Enter/Click Search Execution
    'Search Execution' => str_contains($js, 'triggerSearch') && str_contains($view, 'class="search-btn"'),
    // 8. Click-Outside Search Dismissal
    'Search Dismiss' => str_contains($js, 'search-bar') && str_contains($js, 'resultsBox.innerHTML = ""'),
    // 9. Stat-Card Interactive Filtering
    'Stat Filtering' => str_contains($js, 'filterRooms') && str_contains($view, 'id="card-active"') && str_contains($view, 'id="card-pending"') && str_contains($view, 'id="card-available"'),
    // 10. Floor & Group Sorter
    'Floor Sorter' => str_contains($js, 'floorFromRoomName') && str_contains($js, 'roomFloor') && str_contains($js, 'floor-section'),
    // 11. Conflict Time Ranges
    'Conflict Ranges' => str_contains($js, 'conflict_start') && str_contains($js, 'conflict_end'),
    // 12. Air-Conditioning Chips
    'AC Chips' => str_contains($js, 'room_has_ac') && str_contains($js, 'Air-conditioned'),
    // 13. Pending / Active Badges
    'Pending & Active Badges' => str_contains($js, 'is_my_active') && str_contains($js, 'has_pending') && str_contains($js, 'active-user-badge'),
    // 14. Image Error Fallback
    'Image Fallbacks' => str_contains($js, 'wireImageFallbacks') && str_contains($js, 'fallbackUsed'),
    // 15. Mobile Drawer Navigation
    'Mobile Navigation' => str_contains($js, 'initSharedMobileMenu') && str_contains($view, 'id="mobile-menu-toggle"') && str_contains($view, 'id="mobile-menu-backdrop"'),
    // 16. Reservation Toast Alert
    'Toast Alerts' => str_contains($js, 'submitted') && str_contains($js, 'replaceState'),
    // 17. All Navigation Routes (8 routes)
    'Route: Calendar' => str_contains($view, 'href="calendar_view.php"'),
    'Route: Notifications' => str_contains($view, 'href="notifications_view.php"'),
    'Route: Profile' => str_contains($view, 'href="profile_view.php"'),
    'Route: Security' => str_contains($view, 'href="security_view.php"'),
    'Route: Settings' => str_contains($view, 'href="settings_view.php"'),
    'Route: About' => str_contains($view, 'href="about_view.php"'),
    'Route: Contact' => str_contains($view, 'href="contact_view.php"'),
    'Route: Logout' => str_contains($view, 'href="logout.php"')
];

foreach ($matrixChecks as $name => $passed) {
    if (!$passed) {
        throw new RuntimeException("FAIL [Preservation Matrix]: Feature not preserved: " . $name);
    }
    echo "  [OK] Preserved: {$name}\n";
}
echo "PASS: All 17 items in Functionality Preservation Matrix fully verified.\n";

// --- Section 6: Mock Session HTML Render Verification ---
$_SESSION['id'] = 999;
$_SESSION['fname'] = 'Alexander';
$_SESSION['lname'] = 'Wright';
$_COOKIE['theme'] = 'dark';

$origCwd = getcwd();
chdir(__DIR__ . '/../app/views');
ob_start();
@include 'user_homepage_view.php';
$renderedHtml = ob_get_clean();
chdir($origCwd);

if (!str_contains($renderedHtml, 'Alexander Wright')) {
    throw new RuntimeException("FAIL [Render]: Student name not rendered in HTML output");
}
if (!str_contains($renderedHtml, 'id="quick-reserve-drawer"')) {
    throw new RuntimeException("FAIL [Render]: Quick Reserve drawer not rendered in HTML output");
}
echo "PASS: Section 6 Mock Session HTML Render verified (Name & Drawer present).\n";
echo "=== Homepage Redesign Test Suite Completed Successfully ===\n";
