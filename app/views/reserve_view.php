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
$firstInitial = strtoupper(substr(trim($_SESSION["fname"] ?? "U"), 0, 1));
if ($firstInitial === "") $firstInitial = "U";
$initials = strtoupper(substr($fname, 0, 1) . substr($lname, 0, 1));
$themeClass = (isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'light') ? 'light-mode' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ClassSpace - Reserve a Room</title>
    <link rel="icon" href="../../public/images/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../public/css/global.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../../public/css/reserve_style.css?v=<?php echo time(); ?>">
</head>
<body class="<?php echo $themeClass; ?>">
<div class="container">
    <?php
    $activeTab = 'reserve';
    require __DIR__ . '/partials/user_sidebar.php';
    ?>
    <main class="main-content">
        <?php require __DIR__ . '/partials/user_utility_bar.php'; ?>


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
                        <strong style="color: #38bdf8;">Rescheduling Pending Reservation
                        <div style="font-size: 12px; color: var(--text-low); margin-top: 2px;">
                            Applying an alternative room or time will update this reservation and resolve your calendar conflict.
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="form-section-heading" id="subject-heading">
                <span class="form-section-kicker">01</span>
                <div>
                    <h4>Reservation Subject</h4>
                    <p>Enter the class, activity, or purpose for this reservation.</p>
                </div>
            </div>

            <div class="form-group">
                <label for="reservation-subject">Subject</label>
                <input type="text" id="reservation-subject" name="subject" maxlength="150" autocomplete="off" placeholder="e.g., Biology laboratory class" required>
            </div>

            <div class="form-section-heading" id="location-heading">
                <span class="form-section-kicker">02</span>
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
                <span class="form-section-kicker">03</span>
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
                <span class="form-section-kicker">04</span>
                <div>
                    <h4>Review Reservation Details</h4>
                    <p>Check your room, schedule, and request details before submission.</p>
                </div>
            </div>

            <button type="button" class="btn-primary submit-btn" id="submit-btn">
                <i class="fas fa-clipboard-check" aria-hidden="true"></i>
                <?= ($reschedulePendingId > 0) ? 'Review Updated Reservation' : 'Review Reservation Details' ?>
            </button>

        </div>
    </main>
</div>

<div id="booking-review" class="booking-review" hidden aria-hidden="true">
    <div class="booking-review-backdrop" data-review-close="true"></div>
    <section class="booking-review-dialog" role="dialog" aria-modal="true" aria-labelledby="booking-review-title" tabindex="-1">
        <div class="booking-review-header">
            <div>
                <span class="booking-review-kicker">Before submission</span>
                <h3 id="booking-review-title">Review Reservation Details</h3>
                <p>Check the details below before sending your request to the administrator.</p>
            </div>
            <button type="button" class="booking-review-close" id="review-close" aria-label="Close review">
                <i class="fas fa-xmark" aria-hidden="true"></i>
            </button>
        </div>
        <div id="booking-review-status" class="booking-review-status">
            <i class="fas fa-circle-check" aria-hidden="true"></i>
            <span>Your request will remain pending until approved by an administrator.</span>
        </div>
        <dl class="booking-review-list">
            <div><dt>Subject</dt><dd id="review-subject">—</dd></div>
            <div><dt>Building</dt><dd id="review-building">—</dd></div>
            <div><dt>Room</dt><dd id="review-room">—</dd></div>
            <div><dt>Schedule</dt><dd id="review-schedule">—</dd></div>
            <div><dt>Time</dt><dd id="review-time">—</dd></div>
        </dl>
        <div class="booking-review-actions">
            <button type="button" class="review-edit-btn" id="review-edit">Back to edit</button>
            <button type="button" class="review-confirm-btn" id="review-confirm">
                <i class="fas fa-paper-plane" aria-hidden="true"></i>
                <?= ($reschedulePendingId > 0) ? 'Confirm update' : 'Confirm and submit' ?>
            </button>
        </div>
    </section>
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
