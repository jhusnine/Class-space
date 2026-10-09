<?php
session_start();

if (!isset($_SESSION["id"])) {
    header("Location: ../index.php");
    exit;
}
$themeClass = (isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'light') ? 'light-mode' : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ClassSpace - Schedule Calendar</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../public/css/global.css">
    <link rel="stylesheet" href="../../public/css/calendar_style.css?v=<?php echo time(); ?>">
</head>

<body class="<?php echo $themeClass; ?>">
<div class="container">

    <aside class="sidebar">
        <div class="logo">
            <img src="../../public/images/logo.png" alt="ClassSpace">
            <span>ClassSpace</span>
        </div>

        <ul class="nav-menu">
            <li class="nav-item">
                <a href="user_homepage_view.php" class="nav-link">
                    <i class="fas fa-home"></i>
                    <span>Homepage</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="calendar_view.php" class="nav-link active">
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

    <main class="main-content">

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