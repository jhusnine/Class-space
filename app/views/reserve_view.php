<?php
session_start();
if (!isset($_SESSION["id"])) {
    header("Location: ../index.php");
    exit;
}

require_once __DIR__ . '/../../includes/semester_helper.php';
$currentSemester = getCurrentSemester();

$preHallId   = intval($_GET["hall_id"] ?? 0);
$preHallName = htmlspecialchars($_GET["hall_name"] ?? "");
$preRoomId   = intval($_GET["room_id"] ?? 0);
$preRoomName = htmlspecialchars($_GET["room_name"] ?? "");
$preDate     = htmlspecialchars($_GET["date"] ?? "");
$preDow      = htmlspecialchars($_GET["dow"] ?? "");
$preType     = htmlspecialchars($_GET["type"] ?? "one-time");
$preStart    = htmlspecialchars($_GET["start"] ?? "");
$preEnd      = htmlspecialchars($_GET["end"] ?? "");
$preUntil    = htmlspecialchars($_GET["until"] ?? "");
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

            <!-- 3-Step Progress Stepper -->
            <div class="wizard-stepper" id="wizard-stepper" aria-label="Reservation Progress">
                <div class="stepper-track">
                    <div class="stepper-progress-bar" id="stepper-progress-bar" style="width: 0%;"></div>
                </div>
                <div class="step-item active" data-step="1" role="button" tabindex="0" aria-current="step">
                    <div class="step-circle">1</div>
                    <div class="step-details">
                        <span class="step-number">Step 1</span>
                        <span class="step-label">Space & Purpose</span>
                    </div>
                </div>
                <div class="step-item" data-step="2" role="button" tabindex="0">
                    <div class="step-circle">2</div>
                    <div class="step-details">
                        <span class="step-number">Step 2</span>
                        <span class="step-label">Date & Schedule</span>
                    </div>
                </div>
                <div class="step-item" data-step="3" role="button" tabindex="0">
                    <div class="step-circle">3</div>
                    <div class="step-details">
                        <span class="step-number">Step 3</span>
                        <span class="step-label">Review & Confirm</span>
                    </div>
                </div>
            </div>

            <!-- Wizard Step 1: Space & Purpose -->
            <div class="wizard-step active" id="wizard-step-1">
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

                <div class="form-section-heading" id="subject-heading">
                    <span class="form-section-kicker">01</span>
                    <div>
                        <h4>Reservation Purpose</h4>
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
                        <h4>Space Selection</h4>
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

                <div class="wizard-nav-actions">
                    <button type="button" class="btn-primary btn-wizard-next" id="btn-step1-next">
                        Continue to Schedule <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>

            <!-- Wizard Step 2: Date & Schedule -->
            <div class="wizard-step" id="wizard-step-2" style="display: none;">
                <div class="form-section-heading" id="schedule-heading">
                    <span class="form-section-kicker">02</span>
                    <div>
                        <h4>Date & Schedule Details</h4>
                        <p>Select when and how often the room will be used.</p>
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

                <div id="field-semester" class="form-group" style="<?= ($preType === 'weekly') ? 'display:block;' : 'display:none;' ?>">
                    <label for="schedule-until">Semester Duration (Recurring Until)</label>
                    <div class="semester-input-row" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                        <input type="date" id="schedule-until" name="until_date" min="<?php echo date('Y-m-d'); ?>" max="<?= htmlspecialchars($currentSemester['end_date']) ?>" value="<?= htmlspecialchars(!empty($preUntil) ? $preUntil : $currentSemester['end_date']) ?>" style="max-width: 220px;">
                        <span class="semester-pill-badge" style="background: rgba(56, 189, 248, 0.15); color: #38bdf8; border: 1px solid rgba(56, 189, 248, 0.35); padding: 6px 12px; border-radius: 6px; font-size: 13px; font-weight: 600;">
                            <i class="fas fa-graduation-cap"></i> <?= htmlspecialchars($currentSemester['label']) ?>
                        </span>
                    </div>
                    <small style="color: var(--text-muted); font-size: 12px; margin-top: 4px; display: block;">
                        <i class="fas fa-info-circle"></i> Weekly schedule recurs every week until the end of the semester (<?= htmlspecialchars($currentSemester['end_date_formatted']) ?>).
                    </small>
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
                                <span>Suggested Alternatives</span>
                            </div>
                            <span class="ai-badge-chip">Available Options</span>
                        </div>
                        <p id="ai-summary-text" class="ai-summary-text">Searching campus rooms and schedule openings...</p>
                    </div>
                    <div id="ai-cards-list" class="ai-cards-list"></div>
                </div>

                <div class="wizard-nav-actions">
                    <button type="button" class="btn-secondary btn-wizard-back" id="btn-step2-back">
                        <i class="fas fa-arrow-left"></i> Back to Space
                    </button>
                    <button type="button" class="btn-primary btn-wizard-next" id="btn-step2-next">
                        Proceed to Review <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>

            <!-- Wizard Step 3: Review & Confirm -->
            <div class="wizard-step" id="wizard-step-3" style="display: none;">
                <div class="form-section-heading" id="review-heading">
                    <span class="form-section-kicker">03</span>
                    <div>
                        <h4>Review Reservation Details</h4>
                        <p>Verify your room, schedule, and request details before submitting.</p>
                    </div>
                </div>

                <div class="reservation-summary-card" id="reservation-summary-card">
                    <div class="summary-banner" id="summary-banner">
                        <div class="summary-banner-overlay"></div>
                        <div class="summary-banner-content">
                            <span class="summary-pill" id="summary-building-badge">Building</span>
                            <h3 id="summary-room-title">Room Name</h3>
                            <span class="summary-capacity-chip" id="summary-capacity-badge">Seating Capacity</span>
                        </div>
                    </div>

                    <div class="summary-details-grid">
                        <div class="summary-item">
                            <div class="summary-item-header">
                                <span class="summary-label">Subject</span>
                                <button type="button" class="summary-edit-btn" data-target-step="1" title="Edit subject">
                                    <i class="fas fa-pen"></i> Edit
                                </button>
                            </div>
                            <div class="summary-value" id="summary-subject">—</div>
                        </div>

                        <div class="summary-item">
                            <div class="summary-item-header">
                                <span class="summary-label">Building</span>
                                <button type="button" class="summary-edit-btn" data-target-step="1" title="Edit building">
                                    <i class="fas fa-pen"></i> Edit
                                </button>
                            </div>
                            <div class="summary-value" id="summary-building">—</div>
                        </div>

                        <div class="summary-item">
                            <div class="summary-item-header">
                                <span class="summary-label">Room</span>
                                <button type="button" class="summary-edit-btn" data-target-step="1" title="Edit room">
                                    <i class="fas fa-pen"></i> Edit
                                </button>
                            </div>
                            <div class="summary-value" id="summary-room">—</div>
                        </div>

                        <div class="summary-item">
                            <div class="summary-item-header">
                                <span class="summary-label">Schedule</span>
                                <button type="button" class="summary-edit-btn" data-target-step="2" title="Edit schedule">
                                    <i class="fas fa-pen"></i> Edit
                                </button>
                            </div>
                            <div class="summary-value" id="summary-schedule">—</div>
                        </div>

                        <div class="summary-item">
                            <div class="summary-item-header">
                                <span class="summary-label">Time Slot</span>
                                <button type="button" class="summary-edit-btn" data-target-step="2" title="Edit time">
                                    <i class="fas fa-pen"></i> Edit
                                </button>
                            </div>
                            <div class="summary-value" id="summary-time">—</div>
                        </div>
                    </div>

                    <div class="summary-status-box" id="summary-status-box">
                        <i class="fas fa-circle-check" aria-hidden="true"></i>
                        <div class="summary-status-text">
                            <strong>Conflict-Free & Verified</strong>
                            <p>Your request will be routed directly to the administrator for review upon submission.</p>
                        </div>
                    </div>
                </div>

                <div class="wizard-nav-actions">
                    <button type="button" class="btn-secondary btn-wizard-back" id="btn-step3-back">
                        <i class="fas fa-arrow-left"></i> Back to Edit
                    </button>
                    <button type="button" class="btn-primary submit-btn" id="submit-btn">
                        <i class="fas fa-paper-plane"></i> <?= ($reschedulePendingId > 0) ? 'Confirm & Update Reservation' : 'Confirm & Submit Reservation' ?>
                    </button>
                </div>
            </div>

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
<input type="hidden" id="preUntil" value="<?= $preUntil ?>">
<input type="hidden" id="autoCheck" value="<?= $autoCheck ?>">
<input type="hidden" id="reschedulePendingId" value="<?= $reschedulePendingId ?>">
<script src="../../public/js/toast.js?v=<?php echo time(); ?>"></script>
<script src="../../public/js/reserve_script.js?v=<?php echo time(); ?>"></script>
</body>
</html>
