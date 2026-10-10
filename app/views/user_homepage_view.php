<?php
    require_once "../controllers/cleanup_schedules.php";
    session_start();

    if (!isset($_SESSION["id"])) {
        header("Location: ../index.php");
        exit;
    }

    $fname = htmlspecialchars($_SESSION["fname"] ?? "User");
    $lname = htmlspecialchars($_SESSION["lname"] ?? "");
    $firstInitial = strtoupper(substr(trim($_SESSION["fname"] ?? "U"), 0, 1));
    if ($firstInitial === "") $firstInitial = "U";

    // PHP cookie sync layer - avoids a theme "flicker" on first paint.
    $themeClass = (isset($_COOKIE["theme"]) && $_COOKIE["theme"] === "light") ? "light-mode" : "";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ClassSpace - Campus Dashboard</title>
    <link rel="icon" href="../../public/images/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
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
                <a href="user_homepage_view.php" class="nav-link active">
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

        <!-- Modern Collegiate Student Profile Strip -->
        <div class="sidebar-profile-card">
            <div class="user-avatar-circle"><?php echo $firstInitial; ?></div>
            <div class="sidebar-profile-meta">
                <div class="sidebar-profile-name"><?php echo $fname . ($lname ? " " . $lname : ""); ?></div>
                <div class="sidebar-profile-sub">Student &bull; BSIT</div>
            </div>
            <a href="logout.php" class="sidebar-profile-logout" id="logout" title="Log out">
                <i class="fas fa-arrow-right-from-bracket" aria-hidden="true"></i>
            </a>
        </div>

        <div class="sidebar-footer">
            <a href="about_view.php" class="footer-link">About Us</a>
            <a href="contact_view.php" class="footer-link">Contact Us</a>
            <a href="logout.php" class="footer-link">Log out</a>
        </div>
    </aside>

    <!-- ================= MAIN ================= -->
    <main class="main-content">

        <!-- Page header -->
        <div class="page-header">
            <div>
                <h1>
                    San Jose Del Monte Campus
                    <span class="header-academic-tag">AY 2026–2027</span>
                </h1>
                <p>Welcome back, <span class="highlight"><?php echo $fname; ?></span>. Real-time room occupancy, schedules, and reservations across all academic facilities.</p>
            </div>

            <div class="header-actions">
                <a href="reserve_view.php" class="btn-primary dashboard-primary-action">
                    <i class="fas fa-calendar-plus" aria-hidden="true"></i>
                    <span>Reserve a Room</span>
                </a>
                <div class="search-bar">
                    <input type="text" id="search-input" class="search-input"
                           placeholder="Search buildings or rooms (Ctrl+K)" autocomplete="off">
                    <button type="button" class="search-btn" aria-label="Search">
                        <i class="fas fa-search"></i>
                    </button>
                    <div id="search-results" class="search-results"></div>
                </div>
            </div>
        </div>

        <!-- Modern Structured KPI Strip -->
        <div class="stats-grid">
            <div class="stat-card" id="card-occupancy" tabindex="0" role="region" aria-label="Campus Room Occupancy">
                <div class="stat-icon"><i class="fas fa-chart-pie"></i></div>
                <h3>Campus Occupancy</h3>
                <div class="number" id="stat-occupancy" aria-live="polite">67%</div>
                <div class="occupancy-progress-bar" style="height:5px; background:rgba(255,255,255,0.08); border-radius:9999px; overflow:hidden; margin-top:8px;">
                    <div class="occupancy-progress-fill" id="occupancy-fill" style="width: 67%; height:100%; background:linear-gradient(90deg, #2F8585, #3D9E9E); border-radius:9999px;"></div>
                </div>
            </div>

            <div class="stat-card" id="card-active" data-filter="active" tabindex="0" role="button"
                 title="Click to see your active reservations">
                <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                <h3>Your Bookings</h3>
                <div class="number" id="stat-active" aria-live="polite">-</div>
                <div class="filter-hint">Click to filter</div>
            </div>

            <div class="stat-card" id="card-pending" data-filter="pending" tabindex="0" role="button"
                 title="Click to see rooms with pending requests">
                <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
                <h3>Pending Review</h3>
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

        <!-- Filter Toolbar with Building Pills & Status Edge Legend -->
        <div class="filter-bar">
            <div class="filter-pills" id="building-filter-pills">
                <button type="button" class="pill-tab active" data-building="all">
                    All Spaces <span class="pill-badge" id="count-all">...</span>
                </button>
                <button type="button" class="pill-tab" data-building="plaridel">
                    Plaridel Hall <span class="pill-badge" id="count-plaridel">...</span>
                </button>
                <button type="button" class="pill-tab" data-building="melchora">
                    Melchora Hall <span class="pill-badge" id="count-melchora">...</span>
                </button>
                <button type="button" class="pill-tab" data-building="gym">
                    Gymnasium <span class="pill-badge" id="count-gym">...</span>
                </button>
                <button type="button" class="pill-tab" data-building="hotel">
                    Training Hotel <span class="pill-badge" id="count-hotel">...</span>
                </button>
            </div>

            <div style="display:flex; align-items:center; gap:16px; flex-wrap:wrap;">
                <div class="room-status-legend">
                    <span class="legend-item"><span class="legend-edge-bar legend-edge-available"></span> Available</span>
                    <span class="legend-item"><span class="legend-edge-bar legend-edge-unavailable"></span> Unavailable</span>
                </div>

                <div class="quick-filter-toggles">
                    <button type="button" class="pill-tab" id="toggle-vacant-only"><i class="fas fa-circle-check"></i> Vacant Only</button>
                </div>
            </div>
        </div>

        <!-- Featured Rooms Catalog -->
        <div class="featured-section" id="featured-section">
            <div class="featured-header" style="margin-bottom: 16px;">
                <h2 id="rooms-section-title">Campus Rooms</h2>
                <div id="filter-label"></div>
                <button type="button" id="back-to-halls" class="btn-back-halls" style="display:none;">
                    <i class="fas fa-arrow-left"></i> All Buildings
                </button>
            </div>

            <div class="halls-grid" id="halls-grid" style="display:none;"></div>
            <div class="rooms-grid" id="rooms-grid">
                <p class="rooms-loading-msg">Loading campus rooms...</p>
            </div>
        </div>

    </main>
</div>

<!-- Slide-out Quick Reserve Drawer -->
<div class="drawer-backdrop" id="drawer-backdrop"></div>
<aside class="quick-reserve-drawer" id="quick-reserve-drawer" aria-labelledby="drawer-title" aria-hidden="true">
    <div class="drawer-header">
        <div>
            <h3 class="drawer-title" id="drawer-title">Quick Reserve</h3>
            <p id="drawer-room-subtitle" style="font-size:12px; color:var(--text-muted); margin-top:2px;"></p>
        </div>
        <button type="button" class="drawer-close-btn" id="drawer-close-btn" aria-label="Close drawer">
            <i class="fas fa-xmark"></i>
        </button>
    </div>

    <form id="drawer-reserve-form">
        <input type="hidden" id="drawer-room-id" name="room_id">
        <input type="hidden" id="drawer-hall-id" name="hall_id">

        <div class="drawer-field">
            <label for="drawer-date" class="drawer-label">Reservation Date</label>
            <input type="date" id="drawer-date" name="date" class="drawer-input" required>
        </div>

        <div class="drawer-field" style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
            <div>
                <label for="drawer-start-time" class="drawer-label">Start Time</label>
                <input type="time" id="drawer-start-time" name="start_time" class="drawer-input" value="09:00" required>
            </div>
            <div>
                <label for="drawer-end-time" class="drawer-label">End Time</label>
                <input type="time" id="drawer-end-time" name="end_time" class="drawer-input" value="11:00" required>
            </div>
        </div>

        <div class="drawer-field">
            <label for="drawer-purpose" class="drawer-label">Purpose / Event</label>
            <input type="text" id="drawer-purpose" name="purpose" class="drawer-input" placeholder="e.g., Study Session, Project Meeting" required>
        </div>

        <button type="submit" class="btn-drawer-submit" id="drawer-submit-btn">
            Confirm Quick Booking
        </button>

        <a href="#" id="drawer-advanced-link" class="drawer-secondary-link">
            Need recurring or multi-room booking? Go to full reservation page &rarr;
        </a>
    </form>
</aside>

<script src="../../public/js/toast.js?v=<?php echo time(); ?>"></script>
<script src="../../public/js/homepage_script.js?v=<?php echo time(); ?>"></script>
</body>
</html>
