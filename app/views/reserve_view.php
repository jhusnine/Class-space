<?php
session_start();
if (!isset($_SESSION["id"])) {
    header("Location: ../index.php");
    exit;
}

$preHallId   = intval($_GET["hall_id"] ?? 0);
$preHallName = htmlspecialchars($_GET["hall_name"] ?? "");
$preRoomId   = intval($_GET["room_id"] ?? 0);
$preRoomName = htmlspecialchars($_GET["room_name"] ?? "");
$preDate     = htmlspecialchars($_GET["date"] ?? "");
$preDow      = htmlspecialchars($_GET["dow"] ?? "");
$preType     = htmlspecialchars($_GET["type"] ?? "one-time");
$preStart    = htmlspecialchars($_GET["start"] ?? "");
$preEnd      = htmlspecialchars($_GET["end"] ?? "");
$autoCheck   = !empty($_GET["auto_check"]) ? 1 : 0;
$reschedulePendingId = intval($_GET["reschedule_pending_id"] ?? 0);

if ($preType === 'weekly' || (!empty($preDow) && empty($preDate))) {
    $preType = 'weekly';
}

$fname     = htmlspecialchars($_SESSION["fname"] ?? "User");
$lname    = htmlspecialchars($_SESSION["lname"] ?? "");
$initials = strtoupper(substr($fname, 0, 1) . substr($lname, 0, 1));
$themeClass = (isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'light') ? 'light-mode' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ClassSpace - Reserve a Room</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../public/css/global.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../../public/css/reserve_style.css?v=<?php echo time(); ?>">
</head>
<body class="<?php echo $themeClass; ?>">
<div class="container">
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
                <a href="user_homepage_view.php" class="nav-link">
                    <i class="fas fa-home"></i><span>Homepage</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="calendar_view.php" class="nav-link">
                    <i class="fas fa-calendar"></i><span>Calendar</span>
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
                    <i class="fas fa-user"></i><span>Account Profile</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="security_view.php" class="nav-link">
                    <i class="fas fa-shield"></i><span>Security</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="settings_view.php" class="nav-link">
                    <i class="fas fa-cog"></i><span>Settings</span>
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
                <h1>Reserve a <span class="highlight">Room</span></h1>
                <p>Fill in the details below to submit a reservation request.</p>
            </div>
        </div>

        <div class="card reservation-card">
            <div class="card-header">
                <h3>Reservation Form</h3>
                <p class="card-subtext">
                    All fields are required. Your request will be reviewed by the admin.
                </p>
            </div>

            <?php if ($reschedulePendingId > 0): ?>
                <div class="reschedule-banner" style="background: rgba(14, 165, 233, 0.12); border: 1px solid rgba(56, 189, 248, 0.4); border-radius: 8px; padding: 12px 16px; margin: 16px 0; display: flex; align-items: center; gap: 12px; color: var(--text-high);">
                    <i class="fas fa-arrows-rotate" style="color: #38bdf8; font-size: 20px;"></i>
                    <div>
                        <strong style="color: #38bdf8;">Rescheduling Pending Reservation #<?= $reschedulePendingId ?></strong>
                        <div style="font-size: 12px; color: var(--text-low); margin-top: 2px;">
                            Applying an alternative room or time will update this reservation and resolve your calendar conflict.
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="form-section-heading" id="location-heading">
                <span class="form-section-kicker">01</span>
                <div>
                    <h4>Location</h4>
                    <p>Choose the building and room you want to reserve.</p>
                </div>
            </div>

            <div class="form-group">
                <label for="hall-select">Building (Hall)</label>
                <select id="hall-select">
                    <option value="">— Select a building —</option>
                </select>
            </div>

            <div class="form-group">
                <label for="room-select">Room</label>
                <select id="room-select" disabled>
                    <option value="">— Select a building first —</option>
                </select>
            </div>

            <div id="room-preview" class="room-preview" aria-live="polite"></div>

            <div class="form-section-heading" id="schedule-heading">
                <span class="form-section-kicker">02</span>
                <div>
                    <h4>Schedule</h4>
                    <p>Select when the room will be used.</p>
                </div>
            </div>

            <div class="form-group">
                <span class="field-label">Reservation Type</span>
                <div class="radio-group">
                    <label class="radio-item">
                        <input type="radio" name="res-type" value="one-time" <?= ($preType === 'weekly') ? '' : 'checked' ?>>
                        One-time
                    </label>

                    <label class="radio-item">
                        <input type="radio" name="res-type" value="weekly" <?= ($preType === 'weekly') ? 'checked' : '' ?>>
                        Weekly (recurring)
                    </label>
                </div>
            </div>

            <div id="field-date" class="form-group" style="<?= ($preType === 'weekly') ? 'display:none;' : '' ?>">
                <label for="schedule-date">Date</label>
                <input type="date" id="schedule-date" min="<?php echo date('Y-m-d'); ?>" value="<?= $preDate ?>">
            </div>

            <div id="field-dow" class="form-group" style="<?= ($preType === 'weekly') ? 'display:block;' : 'display:none;' ?>">
                <label for="schedule-dow">Day of Week</label>
                <select id="schedule-dow">
                    <option value="">— Select a day —</option>
                    <?php foreach (['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $d): ?>
                        <option value="<?= $d ?>" <?= (strcasecmp($preDow, $d) === 0) ? 'selected' : '' ?>><?= $d ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="time-grid">
                <div class="form-group">
                    <label for="schedule-start">Start Time</label>
                    <input type="time" id="schedule-start" value="<?= $preStart ?>">
                </div>

                <div class="form-group">
                    <label for="schedule-end">End Time</label>
                    <input type="time" id="schedule-end" value="<?= $preEnd ?>">
                </div>
            </div>

            <div id="conflict-warning" class="conflict-warning" role="alert" aria-live="polite">
                <i class="fas fa-triangle-exclamation" aria-hidden="true"></i> <span id="conflict-msg"></span>
            </div>

            <!-- ClassSpace AI / Constraint Satisfaction Engine Recommendations -->
            <div id="ai-suggestions-container" class="ai-suggestions-container" style="display: none;" aria-live="polite">
                <div class="ai-header">
                    <div class="ai-title-row">
                        <div class="ai-title">
                            <i class="fas fa-wand-magic-sparkles ai-sparkle-icon" aria-hidden="true"></i>
                            <span>ClassSpace AI Assistant</span>
                        </div>
                        <span class="ai-badge-chip">CSP Engine</span>
                    </div>
                    <p id="ai-summary-text" class="ai-summary-text">Analyzing campus rooms and open slots...</p>
                </div>
                <div id="ai-cards-list" class="ai-cards-list"></div>
            </div>

            <div class="form-section-heading action-heading" id="action-heading">
                <span class="form-section-kicker">03</span>
                <div>
                    <h4>Review and submit</h4>
                    <p>Check your details before sending the request.</p>
                </div>
            </div>

            <button class="btn-primary submit-btn" id="submit-btn">
                <i class="fas fa-paper-plane"></i> <?= ($reschedulePendingId > 0) ? 'Update & Resolve Reservation' : 'Submit Reservation' ?>
            </button>
        </div>
    </main>
</div>
<input type="hidden" id="preHallId" value="<?= $preHallId ?>">
<input type="hidden" id="preHallName" value="<?= $preHallName ?>">
<input type="hidden" id="preRoomId" value="<?= $preRoomId ?>">
<input type="hidden" id="preRoomName" value="<?= $preRoomName ?>">
<input type="hidden" id="preDate" value="<?= $preDate ?>">
<input type="hidden" id="preDow" value="<?= $preDow ?>">
<input type="hidden" id="preType" value="<?= $preType ?>">
<input type="hidden" id="preStart" value="<?= $preStart ?>">
<input type="hidden" id="preEnd" value="<?= $preEnd ?>">
<input type="hidden" id="autoCheck" value="<?= $autoCheck ?>">
<input type="hidden" id="reschedulePendingId" value="<?= $reschedulePendingId ?>">
<script src="../../public/js/toast.js?v=<?php echo time(); ?>"></script>
<script src="../../public/js/reserve_script.js?v=<?php echo time(); ?>"></script>
</body>
</html>