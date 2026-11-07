<?php
/**
 * ClassSpace - User / Teacher Unified Sidebar Partial
 * 
 * Single source of truth providing canonical navigation, responsive mobile toggle,
 * brand header, user profile card, and footer links across all portal views.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$rawFname = trim((string)($_SESSION["fname"] ?? "User"));
if ($rawFname === "") {
    $rawFname = "User";
}
$rawLname = trim((string)($_SESSION["lname"] ?? ""));

$sidebarFname = htmlspecialchars($rawFname, ENT_QUOTES, "UTF-8");
$sidebarLname = htmlspecialchars($rawLname, ENT_QUOTES, "UTF-8");
$sidebarFullName = trim($sidebarFname . ($sidebarLname !== "" ? " " . $sidebarLname : ""));
if ($sidebarFullName === "") {
    $sidebarFullName = "User";
}

$sidebarInitial = strtoupper(substr($rawFname, 0, 1));
if ($sidebarInitial === "") {
    $sidebarInitial = "U";
}
$sidebarInitial = htmlspecialchars($sidebarInitial, ENT_QUOTES, "UTF-8");

// Auto-detect active tab if not explicitly passed
if (!isset($activeTab) || empty($activeTab)) {
    $currentScript = basename($_SERVER["PHP_SELF"] ?? "");
    $activeTab = match ($currentScript) {
        'user_homepage_view.php' => 'dashboard',
        'calendar_view.php' => 'calendar',
        'reserve_view.php' => 'reserve',
        'settings_view.php', 'security_view.php' => 'settings',
        default => ''
    };
}
?>

<button type="button" class="mobile-menu-toggle" id="mobile-menu-toggle"
        aria-controls="classspace-sidebar" aria-expanded="false" aria-label="Open navigation menu">
    <i class="fas fa-bars" aria-hidden="true"></i>
</button>
<div class="mobile-menu-backdrop" id="mobile-menu-backdrop" hidden></div>

<aside class="sidebar" id="classspace-sidebar">
    <div class="logo brand-header">
        <div class="brand-logo-frame">
            <img src="../../public/images/logo.png" alt="ClassSpace">
        </div>
        <div class="brand-text-block">
            <span class="brand-title">ClassSpace</span>
        </div>
    </div>

    <ul class="nav-menu">
        <li class="nav-item">
            <a href="user_homepage_view.php" class="nav-link <?= $activeTab === 'dashboard' ? 'active' : '' ?>">
                <i class="fas fa-home" aria-hidden="true"></i>
                <span>Dashboard</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="calendar_view.php" class="nav-link <?= $activeTab === 'calendar' ? 'active' : '' ?>">
                <i class="fas fa-calendar" aria-hidden="true"></i>
                <span>Calendar</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="reserve_view.php" class="nav-link <?= $activeTab === 'reserve' ? 'active' : '' ?>">
                <i class="fas fa-calendar-plus" aria-hidden="true"></i>
                <span>Reserve</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="settings_view.php" class="nav-link <?= $activeTab === 'settings' ? 'active' : '' ?>">
                <i class="fas fa-cog" aria-hidden="true"></i>
                <span>Settings</span>
            </a>
        </li>
    </ul>

    <div class="sidebar-bottom">
        <div class="sidebar-profile-card">
            <div class="user-avatar-circle"><?= $sidebarInitial ?></div>
            <div class="sidebar-profile-meta">
                <div class="sidebar-profile-name" title="<?= $sidebarFullName ?>"><?= $sidebarFullName ?></div>
                <div class="sidebar-profile-sub">Teacher</div>
            </div>
            <a href="logout.php" class="sidebar-profile-logout" id="logout" title="Log out">
                <i class="fas fa-arrow-right-from-bracket" aria-hidden="true"></i>
            </a>
        </div>

        <div class="sidebar-footer">
            <a href="about_view.php" class="footer-link">About Us</a>
            <span class="footer-dot">&bull;</span>
            <a href="logout.php" class="footer-link">Log out</a>
        </div>
    </div>
</aside>

