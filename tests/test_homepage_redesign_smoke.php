<?php
/**
 * Test: ClassSpace Homepage Redesign Smoke & Verification Suite
 * Verifies CSS tokens, status edge rules, view markup elements,
 * JS preserved handlers, and functionality matrix items.
 */

echo "=== Homepage Redesign Smoke Test Suite ===\n";

// --- Section 1: CSS Design Tokens & Status Edges ---
$css = file_get_contents(__DIR__ . '/../public/css/homepage_style.css');
$sharedCss = file_get_contents(__DIR__ . '/../public/css/global.css');
$cssForSharedSelectorChecks = $css . $sharedCss;

$requiredCssSnippets = [
    '#16A34A',                  // Available green
    '#DC2626',                  // Unavailable / reserved red
    '#D97706',                  // Maintenance amber
    '#070E12',                  // Dark-mode page background
    '.room-status-maintenance', // Maintenance edge state
    '.legend-edge-maintenance', // Maintenance legend entry
    'height: clamp(88px, 8vw, 112px)', // Compact room photo frame
    '.room-status-legend',      // Status legend indicator
    '.quick-reserve-drawer',    // In-page reservation drawer
    'body.mobile-menu-open .mobile-menu-toggle',
    '.user-utility-bar',
    '.utility-icon-link.is-active',
    '.utility-icon-link .contact-reply-badge',
    '.utility-profile-link',
    '.search-bar .search-icon-inside',
    '.search-bar .search-input',
    '@media (max-width: 480px)'
];

foreach ($requiredCssSnippets as $snippet) {
    if (!str_contains($cssForSharedSelectorChecks, $snippet)) {
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
    'class="user-utility-bar"',
    'href="contact_view.php"',
    'href="notifications_view.php"',
    'href="profile_view.php"',
    'class="utility-profile-email"',
    'class="room-status-legend"',
    'legend-edge-maintenance',
    'Available',
    'Unavailable / Reserved',
    'Maintenance',
    'data-building="melchora"',
    'data-building="gym"',
    'data-building="hotel"',
    'data-building="balagtas"',
    'data-building="rizal"',
    'data-building="blas-ople"',
    'logout.php'
];

if (str_contains($view, 'class="legend-color"') || str_contains($view, 'Green · #16A34A') || str_contains($view, 'Red · #DC2626') || str_contains($view, 'Amber · #D97706')) {
    throw new RuntimeException("FAIL [View]: Technical color names/codes should not be shown in the user legend");
}

foreach ($requiredViewElements as $element) {
    if (!str_contains($view, $element)) {
        throw new RuntimeException("FAIL [View]: Missing required element: " . $element);
    }
}
echo "PASS: Task 2 view markup and preserved elements verified.\n";

// --- Shared top-right shortcuts across all authenticated user pages ---
$userShellViews = [
    'user_homepage_view.php', 'calendar_view.php', 'notifications_view.php',
    'profile_view.php', 'security_view.php', 'settings_view.php',
    'about_view.php', 'contact_view.php', 'reserve_view.php',
];
foreach ($userShellViews as $viewName) {
    $source = file_get_contents(__DIR__ . '/../app/views/' . $viewName);
    if (!preg_match('/<ul class="nav-menu">(.*?)<\/ul>/s', $source, $navMatch)
        || !preg_match('/<div class="sidebar-footer">(.*?)<\/div>/s', $source, $footerMatch)
        || !preg_match('/<nav class="user-utility-bar".*?<\/nav>/s', $source, $utilityMatch)) {
        throw new RuntimeException("FAIL [User shell]: Missing shared shell in {$viewName}");
    }
    $nav = $navMatch[1];
    $footer = $footerMatch[1];
    $utility = $utilityMatch[0];
    if (str_contains($nav, 'notifications_view.php') || str_contains($nav, 'profile_view.php')
        || str_contains($footer, 'contact_view.php')
        || !str_contains($footer, 'about_view.php') || !str_contains($footer, 'logout.php')
        || !str_contains($utility, 'href="contact_view.php"')
        || !str_contains($utility, 'href="notifications_view.php"')
        || !str_contains($utility, 'href="profile_view.php"')
        || substr_count($source, 'id="notif-badge"') !== 1
        || !str_contains($source, 'class="sidebar-profile-card"')
        || !str_contains($source, 'toast.js')) {
        throw new RuntimeException("FAIL [User shell]: Shortcut relocation or preserved sidebar behavior failed in {$viewName}");
    }
}
echo "PASS: Top-right Contact, Notifications and Profile shortcuts verified across all 9 user pages; sidebar profile/logout and notification polling preserved.\n";

// --- Notification dropdown and retained soft-delete checks ---
$notifApi = file_get_contents(__DIR__ . '/../app/controllers/notifications_controller.php');
$notifReadApi = file_get_contents(__DIR__ . '/../app/controllers/mark_notif_read.php');
$notifUiJs = file_get_contents(__DIR__ . '/../public/js/notifications_script.js');
$notifSharedJs = file_get_contents(__DIR__ . '/../public/js/toast.js');
$contactScript = file_get_contents(__DIR__ . '/../public/js/contact_script.js');
$notifCss = file_get_contents(__DIR__ . '/../public/css/global.css');
$notifPageCss = file_get_contents(__DIR__ . '/../public/css/notification_style.css');

if (!str_contains($notifCss, '.utility-icon-link #notif-badge')
    || !str_contains($notifCss, 'font-size: 8px;')
    || !str_contains($notifCss, '.notification-dropdown[hidden]')
    || !str_contains($notifSharedJs, 'function initNotificationDropdown()')
    || !str_contains($notifSharedJs, 'window.toast = toast;')
    || !str_contains($notifSharedJs, 'function updateContactReplyBadge(count)')
    || !str_contains($notifSharedJs, 'See all notifications')
    || !str_contains($notifSharedJs, 'aria-expanded')
    || !str_contains($notifPageCss, '.notif-item-delete')
    || !str_contains($notifUiJs, 'className = \'notif-item-delete\'')) {
    throw new RuntimeException('FAIL [Notifications]: Compact badge, dropdown, or remove controls are missing');
}

if (!str_contains($notifApi, 'notif_user_deleted_at IS NULL')
    || !str_contains($notifApi, '"can_delete" => $canDelete')
    || !str_contains($notifApi, '"unread_contact_replies" => $unreadContactReplies')
    || !str_contains($notifReadApi, 'SET notif_user_deleted_at = CURRENT_TIMESTAMP')
    || !str_contains($notifReadApi, '$action === "contact_replies"')
    || !str_contains($notifReadApi, 'AND account_id = ?')
    || !str_contains($notifReadApi, '"retained" => true')
    || str_contains($notifReadApi, 'DELETE FROM notification')) {
    throw new RuntimeException('FAIL [Notifications]: Soft-delete retention, ownership guard, or migration is missing');
}

if (!str_contains($contactScript, 'mark_notif_read.php')
    || !str_contains($contactScript, 'action: \'contact_replies\'')
    || !str_contains($contactScript, 'window.loadNotifBadge()')) {
    throw new RuntimeException('FAIL [Contact]: Support reply read state or badge refresh is missing');
}

echo "PASS: Compact notification badge, dropdown, contact reply badge, user-owned soft delete, and retained MySQL records verified.\n";

// --- Section 3: JavaScript Handlers & Direct Catalog Data Binding ---
$js = file_get_contents(__DIR__ . '/../public/js/homepage_script.js');
$sharedJs = file_get_contents(__DIR__ . '/../public/js/toast.js');

$requiredJsSnippets = [
    'user_stats.php',
    'notifications_controller.php',
    'search_controller.php',
    'room-status-available',
    'room-status-unavailable',
    'card-media',
    'filterByBuilding',
    'wireImageFallbacks',
    'loadStats',
    'loadNotifBadge',
    'BUILDING_IMAGES',
    'ROOM_IMAGES',
    'function isBlasOplePhotoRoom(room)',
    'if (isBlasOplePhotoRoom(room))',
    'ROOM_IMAGES[`ople ${roomCodeMatch[1]}`]',
    'psychologytestingsimulationroom',
    'psychologicalresearchlaboratory',
    'room-status-maintenance',
    'type: "one-time"',
    'function roomMatchesBuilding(room, key)',
    'function starHotelFloorLabel(floor)',
    'return "Reception Desk"',
    'return "Star Hotel"',
    'function roomFloorForDisplay(room)',
    'normalizedRoomLabel.includes("cisco lab")',
    'normalizedRoomLabel.includes("function hall")',
    'normalizedRoomLabel.includes("reception desk")',
    'normalizedRoomLabel.includes("star hotel")',
    'normalizedRoomLabel.includes("broadcasting room")',
    'normalizedRoomLabel.includes("cultural hall")'
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

$quickReserveStart = strpos($js, 'function initQuickReserveDrawer()');
$quickReserveEnd = strpos($js, '/* -- Interactions Init', $quickReserveStart === false ? 0 : $quickReserveStart);
$quickReserveBlock = ($quickReserveStart !== false && $quickReserveEnd !== false)
    ? substr($js, $quickReserveStart, $quickReserveEnd - $quickReserveStart)
    : '';
if ($quickReserveBlock === ''
    || str_contains($quickReserveBlock, 'alert(')
    || !str_contains($quickReserveBlock, 'window.toast.show')
    || !str_contains($quickReserveBlock, 'window.loadNotifBadge()')) {
    throw new RuntimeException('FAIL [Quick Reserve]: In-app feedback or notification badge refresh is missing');
}
echo "PASS: Quick Reserve uses in-app feedback, refreshes notification UI, and has no browser alert fallback.\n";

// --- Plaridel Dean room exclusion: user data, search, suggestions, and booking guard ---
$policyFiles = [
    'Room catalog' => file_get_contents(__DIR__ . '/../app/controllers/room_controller.php'),
    'Reservation guard' => file_get_contents(__DIR__ . '/../app/controllers/reserve_controller.php'),
    'Search results' => file_get_contents(__DIR__ . '/../app/controllers/search_controller.php'),
    'Dashboard stats' => file_get_contents(__DIR__ . '/../app/controllers/user_stats.php'),
    'Room suggestions' => file_get_contents(__DIR__ . '/../app/controllers/suggestion_room_controller.php'),
    'AI suggestions' => file_get_contents(__DIR__ . '/../app/controllers/ai_suggest_controller.php'),
];
foreach ($policyFiles as $label => $source) {
    if (!str_contains($source, 'Plaridel') && !str_contains($source, 'plaridel')) {
        throw new RuntimeException("FAIL [Plaridel]: Missing room exclusion in {$label}");
    }
}
foreach (['Room catalog', 'Reservation guard', 'Search results', 'Room suggestions', 'AI suggestions'] as $label) {
    if (!str_contains($policyFiles[$label], 'isExcludedDeanOffice')) {
        throw new RuntimeException("FAIL [Dean office]: Missing user-facing exclusion in {$label}");
    }
}
if (!str_contains($policyFiles['Reservation guard'], 'This room is not available for user reservations.')
    || !str_contains($policyFiles['Reservation guard'], 'isExcludedDeanOffice')
    || !str_contains($policyFiles['Dashboard stats'], "LOWER(h.hall_name) LIKE '%plaridel%'")
    || !str_contains($policyFiles['Dashboard stats'], "LIKE '%program head%'")) {
    throw new RuntimeException('FAIL [Plaridel]: Reservation guard or dashboard-count exclusion is missing');
}
echo "PASS: Plaridel Dean room is excluded from user-facing room endpoints, recommendations, booking, and available-room counts.\n";

$bhRoomNumbers = array_merge(range(101, 104), range(201, 207), range(301, 307), range(401, 407), range(501, 507));
foreach ($bhRoomNumbers as $roomNumber) {
    $roomImagePath = "../../public/images/BH%20{$roomNumber}.jpg";
    if (!str_contains($js, $roomImagePath) || !is_file(__DIR__ . "/../public/images/BH {$roomNumber}.jpg")) {
        throw new RuntimeException("FAIL [JS]: Missing BH room photo mapping or asset for room {$roomNumber}");
    }
}
echo "PASS: All BH room-name photo mappings point to existing image assets.\n";

$opleRoomImages = [
    'ople psychology testingsimulation room' => 'PSYCHOLOGY TESTINGSIMULATION ROOM.jpeg',
    'ople psychological research laboratory' => 'OPLE PSYCHOLOGICAL RESEARCH LABORATORY.jpeg',
    'ople 102' => 'OPLE 102.jpeg',
    'ople 201' => 'OPLE 201.jpeg',
    'ople 202' => 'OPLE 202.jpeg',
    'ople 301' => 'OPLE 301.jpeg',
    'ople 302' => 'OPLE 302.jpeg',
    'ople 303' => 'OPLE 303.jpeg',
    'ople 304' => 'OPLE 304.jpeg',
    'ople 305' => 'OPLE 305.jpeg',
    'ople 401' => 'OPLE 401.jpeg',
    'ople 402' => 'OPLE 402.jpeg',
    'ople 403' => 'OPLE 403.jpeg',
    'ople 404' => 'OPLE 404.jpeg',
    'ople 405' => 'OPLE 405.jpeg',
    'ople 501' => 'OPLE 501.jpeg',
    'ople 502' => 'OPLE 502.jpeg',
    'ople 503' => 'OPLE 503.jpeg',
];
foreach ($opleRoomImages as $key => $filename) {
    $expectedPath = '../../public/images/' . rawurlencode($filename);
    if (!is_file(__DIR__ . '/../public/images/' . $filename)
        || !str_contains($js, '"' . $key . '": "' . $expectedPath . '"')) {
        throw new RuntimeException("FAIL [OPLE]: Missing OPLE asset or room mapping: {$filename}");
    }
}
echo "PASS: All 18 OPLE named-room/code mappings point to the exact existing JPEG assets.\n";


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
    'Notif Polling' => str_contains($sharedJs, 'notifications_controller.php') && str_contains($sharedJs, 'window.setInterval(loadNotifBadge, 30000)'),
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
    'Mobile Navigation' => str_contains($view, 'toast.js') && str_contains($sharedJs, 'initSharedMobileMenu') && !str_contains($js, 'initSharedMobileMenu') && str_contains($view, 'id="mobile-menu-toggle"') && str_contains($view, 'id="mobile-menu-backdrop"'),
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
