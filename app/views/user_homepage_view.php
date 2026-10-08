<?php
    require_once "../controllers/cleanup_schedules.php";
    session_start();

    if (!isset($_SESSION["id"])) {
        header("Location: ../index.php");
        exit;
    }

    $fname = htmlspecialchars($_SESSION["fname"] ?? "User");
    $lname = htmlspecialchars($_SESSION["lname"] ?? "");

    // PHP cookie sync layer - avoids a theme "flicker" on first paint.
    $themeClass = (isset($_COOKIE["theme"]) && $_COOKIE["theme"] === "light") ? "light-mode" : "";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ClassSpace - User Dashboard</title>
    <link rel="icon" href="../../public/images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../public/css/global.css">
    <link rel="stylesheet" href="../../public/css/homepage_style.css">
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
        <div class="logo">
            <img src="../../public/images/logo.png" alt="ClassSpace">
            <span>ClassSpace</span>
        </div>

        <ul class="nav-menu">
            <li class="nav-item">
                <a href="user_homepage_view.php" class="nav-link active">
                    <i class="fas fa-home"></i>
                    <span>Homepage</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="calendar_view.php" class="nav-link">
                    <i class="fas fa-calendar"></i>
                    <span>Calendar</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="notifications_view.php" class="nav-link">
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

        <div class="sidebar-footer">
            <a href="about_view.php" class="footer-link">About Us</a>
            <a href="contact_view.php" class="footer-link">Contact Us</a>
            <a href="logout.php" class="footer-link" id="logout">Log out</a>
        </div>
    </aside>

    <!-- ================= MAIN ================= -->
    <main class="main-content">

        <!-- Page header -->
        <div class="page-header">
            <div>
                <h1>Welcome Back, <span class="highlight"><?php echo $fname; ?></span></h1>
                <p>Here is what's happening with campus spaces today.</p>
            </div>

            <div class="header-actions">
                <a href="reserve_view.php" class="btn-primary dashboard-primary-action">
                    <i class="fas fa-calendar-plus" aria-hidden="true"></i>
                    <span>Reserve a Room</span>
                </a>
                <div class="search-bar">
                    <input type="text" id="search-input" class="search-input"
                           placeholder="Search buildings or rooms" autocomplete="off">
                    <button type="button" class="search-btn" aria-label="Search">
                        <i class="fas fa-search"></i>
                    </button>
                    <div id="search-results" class="search-results"></div>
                </div>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card" id="card-active" data-filter="active" tabindex="0" role="button"
                 title="Click to see your active reservations">
                <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                <h3>Active Reservations</h3>
                <div class="number" id="stat-active" aria-live="polite">-</div>
                <div class="filter-hint">Click to filter</div>
            </div>

            <div class="stat-card" id="card-pending" data-filter="pending" tabindex="0" role="button"
                 title="Click to see rooms with pending requests">
                <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
                <h3>Pending Requests</h3>
                <div class="number" id="stat-pending" aria-live="polite">-</div>
                <div class="filter-hint">Click to filter</div>
            </div>

            <div class="stat-card" id="card-available" data-filter="available" tabindex="0" role="button"
                 title="Click to see available rooms">
                <div class="stat-icon"><i class="fas fa-door-open"></i></div>
                <h3>Rooms Available</h3>
                <div class="number highlight" id="stat-available" aria-live="polite">-</div>
                <div class="filter-hint">Click to filter</div>
            </div>
        </div>

        <!-- Featured / browse section -->
        <div class="featured-section" id="featured-section">
            <div class="featured-header">
                <h2 id="rooms-section-title">Featured Rooms</h2>
                <div id="filter-label"></div>
                <button type="button" id="back-to-halls" class="btn-back-halls"
                        style="display:none;">
                    <i class="fas fa-arrow-left"></i> Back to Buildings
                </button>
            </div>

            <div class="halls-grid" id="halls-grid"></div>
            <div class="rooms-grid" id="rooms-grid" style="display:none;">
                <p class="rooms-empty"></p>
            </div>
        </div>

    </main>
</div>

<script src="../../public/js/toast.js?v=<?php echo time(); ?>"></script>
<script src="../../public/js/homepage_script.js?v=<?php echo time(); ?>"></script>
</body>
</html>
