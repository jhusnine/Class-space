<?php 
    session_start();
    if (!isset($_SESSION["id"])) {
        header("Location: ../index.php");
        exit;
    }

    $fname = htmlspecialchars($_SESSION["fname"] ?? "User");
    $lname = htmlspecialchars($_SESSION["lname"] ?? "");
    $firstInitial = strtoupper(substr(trim($_SESSION["fname"] ?? "U"), 0, 1));
    if ($firstInitial === "") $firstInitial = "U";

    $themeClass = (isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'light') ? 'light-mode' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ClassSpace - Notifications</title>
    <link rel="icon" href="../../public/images/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../public/css/global.css?v=<?php echo filemtime(__DIR__ . '/../../public/css/global.css'); ?>">
    <link rel="stylesheet" href="../../public/css/notification_style.css?v=<?php echo filemtime(__DIR__ . '/../../public/css/notification_style.css'); ?>">
</head>
<body class="<?php echo $themeClass; ?>">
<div class="container">

    <!-- ================= SIDEBAR ================= -->
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
                <span class="brand-subtitle">First City Providential</span>
            </div>
        </div>

        <ul class="nav-menu">
            <li class="nav-item">
                <a href="user_homepage_view.php" class="nav-link">
                    <i class="fas fa-home"></i>
                    <span>Campus Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="calendar_view.php" class="nav-link">
                    <i class="fas fa-calendar"></i>
                    <span>Master Calendar</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="notifications_view.php" class="nav-link active">
                    <i class="fas fa-bell"></i>
                    <span>Notifications</span>
                    <span id="notif-badge"></span>
                </a>
            </li>
            <li class="nav-item">
                <a href="profile_view.php" class="nav-link">
                    <i class="fas fa-user"></i>
                    <span>Account Profile</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="security_view.php" class="nav-link">
                    <i class="fas fa-shield"></i>
                    <span>Security</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="settings_view.php" class="nav-link">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </li>
        </ul>

        <!-- Modern Collegiate Student Profile Strip & Footer -->
        <div class="sidebar-bottom">
            <div class="sidebar-profile-card">
                <div class="user-avatar-circle"><?php echo $firstInitial; ?></div>
                <div class="sidebar-profile-meta">
                    <div class="sidebar-profile-name" title="<?php echo $fname . ($lname ? " " . $lname : ""); ?>"><?php echo $fname . ($lname ? " " . $lname : ""); ?></div>
                    <div class="sidebar-profile-sub">BSIT &bull; Student</div>
                </div>
                <a href="logout.php" class="sidebar-profile-logout" id="logout" title="Log out">
                    <i class="fas fa-arrow-right-from-bracket" aria-hidden="true"></i>
                </a>
            </div>

            <div class="sidebar-footer">
                <a href="about_view.php" class="footer-link">About Us</a>
                <span class="footer-dot">&bull;</span>
                <a href="contact_view.php" class="footer-link">Contact Us</a>
                <span class="footer-dot">&bull;</span>
                <a href="logout.php" class="footer-link">Log out</a>
            </div>
        </div>
    </aside>

    <main class="main-content">

        <div class="notif-page-header">
            <div>
                <h1 class="notif-title-main">Notifications</h1>
                <p class="notif-subtitle">Updates on your reservation requests.</p>
            </div>

            <div class="notif-actions">
                <div class="notif-search">
                    <input type="text" id="search-input" class="notif-search-input" placeholder="Search…">
                    <i class="fas fa-search notif-search-icon"></i>
                </div>

                <button class="btn-mark-all" onclick="markAllRead()">
                    <i class="fas fa-check-double"></i>
                    Mark all read
                </button>
            </div>
        </div>

        <div class="notif-tabs">
            <button class="notif-tab active" onclick="setTab(this,'all')">All</button>
            <button class="notif-tab" onclick="setTab(this,'approved')">Approved</button>
            <button class="notif-tab" onclick="setTab(this,'pending')">Pending</button>
            <button class="notif-tab" onclick="setTab(this,'declined')">Declined</button>
        </div>

        <div id="notifications-list">
            <div class="notif-loading">
                <i class="fas fa-spinner fa-spin"></i>
                Loading…
            </div>
        </div>
    </main>
</div>

<script src="../../public/js/toast.js?v=<?php echo time(); ?>"></script>
<script src="../../public/js/notifications_script.js?v=<?php echo time(); ?>"></script>
</body>
</html>