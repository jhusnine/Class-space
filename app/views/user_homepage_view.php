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
    <link rel="stylesheet" href="../../public/css/global.css?v=<?php echo filemtime(__DIR__ . '/../../public/css/global.css'); ?>">
    <link rel="stylesheet" href="../../public/css/homepage_style.css?v=<?php echo filemtime(__DIR__ . '/../../public/css/homepage_style.css'); ?>">
</head>
<body class="<?php echo $themeClass; ?>">
<div class="container">


    <?php
    $activeTab = 'dashboard';
    require __DIR__ . '/partials/user_sidebar.php';
    ?>


    <main class="main-content">
        <?php require __DIR__ . '/partials/user_utility_bar.php'; ?>




        <div class="page-header">
            <div>
                <h1>
                    Campus <span class="highlight">Dashboard</span>
                    <span class="header-academic-tag">AY 2026-2027</span>
                </h1>
                <p>First City Providential College &bull; Welcome Back, <span class="highlight"><?php echo $fname; ?></span>!</p>
            </div>

            <div class="header-actions">
                <a href="reserve_view.php" class="btn-primary dashboard-primary-action">
                    <i class="fas fa-calendar-plus" aria-hidden="true"></i>
                    <span>Reserve a Room</span>
                </a>
                <div class="search-bar">
                    <i class="fas fa-search search-icon-inside" aria-hidden="true"></i>
                    <input type="text" id="search-input" class="search-input"
                           placeholder="Search buildings or rooms" autocomplete="off">
                    <span class="search-kbd-badge">Ctrl+K</span>
                    <button type="button" class="search-btn" aria-label="Search" style="display:none;">
                        <i class="fas fa-search"></i>
                    </button>
                    <div id="search-results" class="search-results"></div>
                </div>
            </div>
        </div>


        <div class="stats-grid">
            <div class="stat-card" id="card-active" data-filter="active" tabindex="0" role="button"
                 title="Click to see your active reservations">
                <div class="stat-card-header">
                    <h3>Your Reservations</h3>
                    <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                </div>
                <div class="number" id="stat-active" aria-live="polite">-</div>
                <div class="filter-hint">Click to filter</div>
            </div>

            <div class="stat-card" id="card-pending" data-filter="pending" tabindex="0" role="button"
                 title="Click to see rooms with pending requests">
                <div class="stat-card-header">
                    <h3>Pending Review</h3>
                    <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
                </div>
                <div class="number" id="stat-pending" aria-live="polite">-</div>
                <div class="filter-hint">Click to filter</div>
            </div>

            <div class="stat-card" id="card-available" data-filter="available" tabindex="0" role="button"
                 title="Click to see available rooms">
                <div class="stat-card-header">
                    <h3>Rooms Available</h3>
                    <div class="stat-icon"><i class="fas fa-door-open"></i></div>
                </div>
                <div class="number highlight" id="stat-available" aria-live="polite">-</div>
                <div class="filter-hint">Click to filter</div>
            </div>
        </div>


        <div class="filter-bar">
            <div class="filter-pills" id="building-filter-pills">
                <button type="button" class="pill-tab" data-building="plaridel">
                    Plaridel Hall <span class="pill-badge" id="count-plaridel">...</span>
                </button>
                <button type="button" class="pill-tab" data-building="melchora">
                    Melchora Aquino Hall <span class="pill-badge" id="count-melchora">...</span>
                </button>
                <button type="button" class="pill-tab" data-building="gym">
                    FCPC Gymnasium <span class="pill-badge" id="count-gym">...</span>
                </button>
                <button type="button" class="pill-tab" data-building="hotel">
                    FCPC Star Hotel <span class="pill-badge" id="count-hotel">...</span>
                </button>
                <button type="button" class="pill-tab" data-building="balagtas">
                    Balagtas Hall <span class="pill-badge" id="count-balagtas">...</span>
                </button>
                <button type="button" class="pill-tab" data-building="rizal">
                    Rizal Hall <span class="pill-badge" id="count-rizal">...</span>
                </button>
                <button type="button" class="pill-tab" data-building="blas-ople">
                    Blas Ople Hall <span class="pill-badge" id="count-blas-ople">...</span>
                </button>
            </div>

            <div class="quick-filter-toggles">
                <button type="button" class="pill-tab" id="toggle-vacant-only"><i class="fas fa-circle-check"></i> Vacant Only</button>
            </div>
        </div>


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
            <label for="drawer-purpose" class="drawer-label">Subject</label>
            <input type="text" id="drawer-purpose" name="subject" class="drawer-input" maxlength="150" placeholder="e.g., Biology laboratory class" required>
        </div>

        <button type="submit" class="btn-drawer-submit" id="drawer-submit-btn">
            Review Booking Details
        </button>

        <a href="#" id="drawer-advanced-link" class="drawer-secondary-link">
            Need recurring or multi-room booking? Go to full reservation page &rarr;
        </a>
    </form>
</aside>

<div id="quick-booking-review" class="quick-booking-review" hidden aria-hidden="true">
    <div class="quick-booking-review-backdrop" data-quick-review-close="true"></div>
    <section class="quick-booking-review-dialog" role="dialog" aria-modal="true" aria-labelledby="quick-review-title">
        <div class="quick-booking-review-header">
            <div>
                <span class="booking-review-kicker">Final review</span>
                <h3 id="quick-review-title">Confirm Quick Booking</h3>
                <p>Review these details before submitting the reservation request.</p>
            </div>
            <button type="button" class="booking-review-close" id="quick-review-close" aria-label="Close review">
                <i class="fas fa-xmark" aria-hidden="true"></i>
            </button>
        </div>
        <div class="booking-review-status">
            <i class="fas fa-hourglass-half" aria-hidden="true"></i>
            <span>This booking will be marked Pending Approval until an administrator reviews it.</span>
        </div>
        <dl class="booking-review-list">
            <div><dt>Subject</dt><dd id="quick-review-subject">—</dd></div>
            <div><dt>Building</dt><dd id="quick-review-building">—</dd></div>
            <div><dt>Room</dt><dd id="quick-review-room">—</dd></div>
            <div><dt>Date</dt><dd id="quick-review-date">—</dd></div>
            <div><dt>Time</dt><dd id="quick-review-time">—</dd></div>
            <div><dt>Purpose</dt><dd id="quick-review-purpose">—</dd></div>
        </dl>
        <div class="booking-review-actions">
            <button type="button" class="review-edit-btn" id="quick-review-edit">Back to edit</button>
            <button type="button" class="review-confirm-btn" id="quick-review-confirm">
                <i class="fas fa-paper-plane" aria-hidden="true"></i> Submit Reservation Request
            </button>
        </div>
    </section>
</div>

<script src="../../public/js/toast.js?v=<?php echo time(); ?>"></script>
<script src="../../public/js/homepage_script.js?v=<?php echo time(); ?>"></script>
</body>
</html>
