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
    <title>ClassSpace - Schedule Calendar</title>
    <link rel="icon" href="../../public/images/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../public/css/global.css?v=<?php echo filemtime(__DIR__ . '/../../public/css/global.css'); ?>">
    <link rel="stylesheet" href="../../public/css/calendar_style.css?v=<?php echo filemtime(__DIR__ . '/../../public/css/calendar_style.css'); ?>">
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
                <a href="calendar_view.php" class="nav-link active">
                    <i class="fas fa-calendar"></i>
                    <span>Master Calendar</span>
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
                <a href="logout.php" class="footer-link">Log out</a>
            </div>
        </div>
    </aside>

    <main class="main-content">
        <?php
        $utilityName = trim((string)($_SESSION["fname"] ?? "User") . " " . (string)($_SESSION["lname"] ?? ""));
        $utilityEmail = (string)($_SESSION["email"] ?? "");
        $utilityInitial = strtoupper(substr(trim((string)($_SESSION["fname"] ?? "U")), 0, 1));
        if ($utilityInitial === "") $utilityInitial = "U";
        $utilityCurrentPage = basename($_SERVER["PHP_SELF"] ?? "");
        ?>
        <nav class="user-utility-bar" aria-label="Account shortcuts">
            <div class="user-utility-actions">
                <a href="contact_view.php" class="utility-icon-link<?php echo $utilityCurrentPage === 'contact_view.php' ? ' is-active' : ''; ?>" aria-label="Contact Us" title="Contact Us"<?php echo $utilityCurrentPage === 'contact_view.php' ? ' aria-current="page"' : ''; ?>>
                    <i class="fa-regular fa-envelope" aria-hidden="true"></i>
                </a>
                <a href="notifications_view.php" class="utility-icon-link<?php echo $utilityCurrentPage === 'notifications_view.php' ? ' is-active' : ''; ?>" aria-label="Notifications" title="Notifications"<?php echo $utilityCurrentPage === 'notifications_view.php' ? ' aria-current="page"' : ''; ?>>
                    <i class="fas fa-bell" aria-hidden="true"></i>
                    <span id="notif-badge" class="notif-badge" aria-live="polite"></span>
                </a>
                <a href="profile_view.php" class="utility-profile-link<?php echo $utilityCurrentPage === 'profile_view.php' ? ' is-active' : ''; ?>" aria-label="Account Profile" title="Account Profile"<?php echo $utilityCurrentPage === 'profile_view.php' ? ' aria-current="page"' : ''; ?>>
                    <span class="utility-avatar" aria-hidden="true"><?php echo htmlspecialchars($utilityInitial, ENT_QUOTES, "UTF-8"); ?></span>
                    <span class="utility-profile-meta">
                        <span class="utility-profile-name"><?php echo htmlspecialchars($utilityName, ENT_QUOTES, "UTF-8"); ?></span>
                        <span class="utility-profile-email"><?php echo htmlspecialchars($utilityEmail !== "" ? $utilityEmail : "Account Profile", ENT_QUOTES, "UTF-8"); ?></span>
                    </span>
                </a>
            </div>
        </nav>



        <div class="page-header">
            <div>
                <h1>Schedule <span class="highlight">Calendar</span></h1>
                <p>View all approved reservations for your rooms this week.</p>
            </div>
        </div>

        <div class="cal-wrapper">

            <div class="cal-toolbar">
                <h1>SCHEDULE CALENDAR</h1>

                <select id="filter-hall" onchange="applyFilters()">
                    <option value="">All Halls</option>
                </select>

                <select id="filter-room" onchange="applyFilters()">
                    <option value="">All Rooms</option>
                </select>

                <div class="week-nav">
                    <button onclick="shiftWeek(-1)" title="Previous week">&#8592;</button>
                    <span id="week-label">Loading…</span>
                    <button onclick="shiftWeek(1)" title="Next week">&#8594;</button>
                </div>

                <button class="btn-primary" onclick="goToday()">Today</button>
            </div>

            <div class="cal-scroll">
                <div id="cal-grid" class="cal-grid">
                    <div class="loading-overlay loading-full">
                        <i class="fas fa-spinner spin"></i> Loading schedule…
                    </div>
                </div>
            </div>

            <div class="cal-legend">
                <span class="legend-item">
                    <span class="legend-dot" id="approved"></span> Approved
                </span>

                <span class="legend-item">
                    <span class="legend-dot" id="pending"></span> Pending (yours)
                </span>

                <span class="legend-item">
                    <span class="legend-dot" id="legend-conflict"></span> Double-Booked / Conflict
                </span>

                <span class="legend-note">
                    All approved reservations are visible to everyone.
                </span>
            </div>
        </div>
    </main>
</div>

<!-- AI Slot Inspector & Double-Booking Resolution Modal -->
<div id="cal-inspector-modal" class="cal-modal-backdrop" style="display:none;" onclick="handleModalBackdropClick(event)">
    <div class="cal-modal-card" role="dialog" aria-modal="true" aria-labelledby="modal-event-title">
        <div class="cal-modal-header">
            <div class="modal-title-wrap">
                <h3 id="modal-event-title"><i class="fas fa-calendar-check"></i> Reservation Inspector</h3>
                <span id="modal-conflict-pill" class="modal-conflict-pill" style="display:none;">
                    <i class="fas fa-exclamation-triangle"></i> Conflict Detected
                </span>
            </div>
            <button class="cal-modal-close" onclick="closeInspectorModal()" aria-label="Close modal">&times;</button>
        </div>
        <div class="cal-modal-body">
            <div class="modal-info-grid">
                <div class="modal-info-item">
                    <span class="modal-label">Room / Building</span>
                    <span class="modal-value" id="modal-room-hall">-</span>
                </div>
                <div class="modal-info-item">
                    <span class="modal-label">Date & Time</span>
                    <span class="modal-value" id="modal-date-time">-</span>
                </div>
                <div class="modal-info-item">
                    <span class="modal-label">Status</span>
                    <span class="modal-value" id="modal-status">-</span>
                </div>
                <div class="modal-info-item">
                    <span class="modal-label">Organizer / Purpose</span>
                    <span class="modal-value" id="modal-purpose">-</span>
                </div>
            </div>
            <div id="modal-conflict-box" class="modal-conflict-box" style="display:none;">
                <div class="conflict-box-title">
                    <i class="fas fa-triangle-exclamation"></i> Overlapping Reservation Detected
                </div>
                <p id="modal-conflict-details" class="conflict-box-desc"></p>
                <div class="conflict-box-hint">
                    <i class="fas fa-robot"></i> Use ClassSpace's CSP engine to find optimal non-conflicting slots or alternative rooms.
                </div>
                <div id="modal-ai-recommendations" class="modal-ai-recommendations" style="display:none;">
                    <div class="ai-rec-header">
                        <span><i class="fas fa-wand-magic-sparkles"></i> <strong>AI Recommended Alternative Rooms</strong></span>
                        <span class="ai-rec-subtitle">Click to immediately switch your reservation and resolve the calendar conflict.</span>
                    </div>
                    <div id="modal-ai-cards" class="modal-ai-cards"></div>
                </div>
            </div>
        </div>
        <div class="cal-modal-footer">
            <button type="button" class="btn-modal-secondary" onclick="closeInspectorModal()">Close</button>
            <button type="button" id="modal-btn-cancel-req" class="btn-modal-danger" style="display:none;" onclick="cancelPendingFromModal()">
                <i class="fas fa-trash-can"></i> Withdraw Request
            </button>
            <a id="modal-btn-ai-resolve" href="#" class="btn-modal-primary" style="display:none;">
                <i class="fas fa-pen-to-square"></i> Open Reservation Form
            </a>
        </div>
    </div>
</div>

<script src="../../public/js/toast.js?v=<?php echo time(); ?>"></script>
<script src="../../public/js/calendar_script.js?v=<?php echo time(); ?>"></script>
</body>
</html>