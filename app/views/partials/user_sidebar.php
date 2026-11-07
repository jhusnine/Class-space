<?php
/**
 * ClassSpace - User / Teacher Unified Sidebar Partial
 * 
 * Single source of truth providing canonical navigation, responsive mobile toggle,
 * and brand header across all portal views.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
</aside>

