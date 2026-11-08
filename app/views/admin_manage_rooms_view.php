<?php
session_start();
if (!isset($_SESSION["id"]) || empty($_SESSION["is_admin"])) {
    header("Location: ../index.php");
    exit;
}
$themeClass = '';
if (isset($_COOKIE['theme'])) {
    if (trim($_COOKIE['theme']) === 'light') {
        $themeClass = 'light-mode';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ClassSpace - Manage Rooms &amp; Buildings</title>

    <link class="styles" rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../public/css/global.css">
    <link rel="stylesheet" href="../../public/css/admin_manage_rooms_style.css">
</head>
<body class="admin-shell <?php echo htmlspecialchars($themeClass, ENT_QUOTES, 'UTF-8'); ?>">

<div class="container">

    <aside class="sidebar">
        <div class="logo">
            <img src="../../public/images/logo.png" alt="ClassSpace">
            <span class="brand-text-block"><strong class="brand-title">ClassSpace</strong></span>
        </div>

        <ul class="nav-menu">
            <li class="nav-item">
                <a href="admin_homepage_view.php" class="nav-link">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="admin_manage_rooms_view.php" class="nav-link active">
                    <i class="fas fa-door-open"></i>
                    <span>Manage Rooms</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="admin_manage_teachers_view.php" class="nav-link">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <span>Teachers</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="admin_reports_view.php" class="nav-link">
                    <i class="fas fa-file-alt"></i>
                    <span>Reports</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="admin_messages_view.php" class="nav-link">
                    <i class="fas fa-envelope"></i>
                    <span>Messages</span>
                    <span class="notif-badge" id="messageBadge">0</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="admin_setting_view.php" class="nav-link">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </li>
        </ul>

        <div class="sidebar-footer">
            <a href="logout.php" class="footer-link logout">Log out</a>
        </div>
    </aside>

    <main class="main-content">
        <div class="page-header">
            <div>
                <h1 class="page-title">
                    Manage <span class="highlight">Rooms &amp; Buildings</span>
                </h1>
                <p class="page-subtitle">
                    Oversee campus learning spaces, adjust seating and amenities, configure buildings, and manage reservations.
                </p>
            </div>
        </div>

        <!-- Segmented Tab Switcher -->
        <div class="segmented-tabs-wrapper">
            <button type="button" class="tab-btn active" id="tab-btn-rooms">
                <i class="fas fa-door-open"></i>
                <span>Rooms Directory</span>
                <span class="tab-badge" id="tab-rooms-badge">0</span>
            </button>
            <button type="button" class="tab-btn" id="tab-btn-halls">
                <i class="fas fa-building"></i>
                <span>Campus Buildings &amp; Halls</span>
                <span class="tab-badge" id="tab-halls-badge">0</span>
            </button>
        </div>

        <!-- TAB 1: Rooms Directory -->
        <div id="tab-rooms-content" class="tab-content active">
            <div class="table-card">
                <div class="table-toolbar">
                    <div class="table-toolbar-filters">
                        <div class="table-stats">
                            <div class="tstat tstat-total" id="tstat-total">
                                <span class="dot"></span>
                                Total: <strong id="ts-total">—</strong>
                            </div>

                            <div class="tstat tstat-avail" id="tstat-available"
                                 onclick="filterByStatus('available')" title="Click to filter available rooms">
                                <span class="dot"></span>
                                Available: <strong id="ts-avail">—</strong>
                            </div>

                            <div class="tstat tstat-unavail" id="tstat-unavailable"
                                 onclick="filterByStatus('unavailable')" title="Click to filter unavailable rooms">
                                <span class="dot"></span>
                                Unavailable: <strong id="ts-unavail">—</strong>
                            </div>

                            <div class="tstat tstat-maint" id="tstat-maintenance"
                                 onclick="filterByStatus('maintenance')" title="Click to filter rooms under maintenance">
                                <span class="dot"></span>
                                Maintenance: <strong id="ts-maint">—</strong>
                            </div>
                        </div>

                        <div class="building-filter-wrap">
                            <label for="filter-hall" class="filter-label"><i class="fas fa-filter"></i></label>
                            <select id="filter-hall" class="filter-select">
                                <option value="">All Buildings</option>
                            </select>
                        </div>
                    </div>

                    <div class="table-toolbar-actions">
                        <div class="toolbar-search search-bar">
                            <input
                                type="text"
                                id="search-input"
                                class="search-input"
                                placeholder="Search rooms or halls…"
                                autocomplete="off"
                            >
                            <button type="button" class="search-btn" onclick="triggerSearch()" aria-label="Search rooms">
                                <i class="fas fa-search"></i>
                            </button>
                            <div id="search-results" class="search-results"></div>
                        </div>

                        <button type="button" class="btn-primary" id="btn-open-create-room">
                            <i class="fas fa-plus"></i> Add Room
                        </button>
                    </div>
                </div>

                <table class="rooms-table">
                    <thead>
                        <tr>
                            <th class="th-id">#</th>
                            <th>Room</th>
                            <th>Classification</th>
                            <th>Capacity &amp; Features</th>
                            <th>Status</th>
                            <th class="th-action">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="rooms-tbody">
                        <tr>
                            <td colspan="6" class="loading-cell">
                                <div class="spinner spinner-center"></div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div class="table-footer">
                    <div class="showing-label" id="showing-label">
                        Showing <strong>—</strong> of <strong>—</strong> rooms
                    </div>

                    <div class="pagination-right">
                        <div class="per-page-wrap">
                            Rows per page:
                            <select id="per-page" class="per-page-select" onchange="changePerPage()">
                                <option value="10" selected>10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                            </select>
                        </div>
                        <div class="pag-btns" id="pag-btns"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 2: Campus Buildings & Halls -->
        <div id="tab-halls-content" class="tab-content" style="display: none;">
            <div class="table-card">
                <div class="table-toolbar">
                    <div class="table-toolbar-filters">
                        <div class="table-stats">
                            <div class="tstat tstat-total" id="hstat-total">
                                <span class="dot"></span>
                                Total Buildings: <strong id="hs-total">—</strong>
                            </div>
                        </div>
                    </div>

                    <div class="table-toolbar-actions">
                        <div class="toolbar-search search-bar">
                            <input
                                type="text"
                                id="hall-search-input"
                                class="search-input"
                                placeholder="Search buildings…"
                                autocomplete="off"
                            >
                            <button type="button" class="search-btn" id="btn-search-hall" aria-label="Search buildings">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>

                        <button type="button" class="btn-primary" id="btn-open-create-hall">
                            <i class="fas fa-plus"></i> Add Building
                        </button>
                    </div>
                </div>

                <div class="halls-grid-wrapper">
                    <div class="halls-grid" id="halls-grid">
                        <div class="loading-cell" style="padding: 40px; text-align: center; width: 100%;">
                            <div class="spinner spinner-center"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- ==========================================
     MODAL: CREATE / EDIT ROOM
     ========================================== -->
<div class="modal-overlay" id="modal-room" role="dialog" aria-modal="true" aria-labelledby="modal-room-title">
    <div class="modal-box modal-room-box">
        <div class="modal-header">
            <div class="modal-title" id="modal-room-title">
                <i class="fas fa-door-open"></i> <span id="modal-room-title-text">Create New Room</span>
            </div>
            <button type="button" class="modal-close" onclick="closeAllModals()" aria-label="Close dialog">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="form-room">
            <input type="hidden" id="room-id" name="room_id">
            <div class="modal-body modal-room-body">
                <div class="room-modal-grid">
                    <!-- Form Controls -->
                    <div class="room-modal-controls">
                        <div class="form-group">
                            <label class="form-label" for="room-name-input">Room Name <span class="req">*</span></label>
                            <input type="text" id="room-name-input" name="room_name" class="form-input" required maxlength="20" placeholder="e.g. MH101 or Lab 3">
                            <div class="form-helper">Maximum 20 characters (must be unique per building).</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="room-hall-select">Building Assignment</label>
                            <select id="room-hall-select" name="hall_id" class="form-select">
                                <option value="">None (Standalone Facility)</option>
                                <!-- Populated dynamically with campus halls -->
                            </select>
                        </div>

                        <div class="form-row-2col">
                            <div class="form-group">
                                <label class="form-label" for="room-type-select">Classification</label>
                                <select id="room-type-select" name="room_type" class="form-select">
                                    <option value="LECTURE" selected>LECTURE</option>
                                    <option value="LABORATORY">LABORATORY</option>
                                    <option value="AUDIO-VISUAL">AUDIO-VISUAL</option>
                                    <option value="CLINIC">CLINIC</option>
                                    <option value="SPORTS">SPORTS</option>
                                    <option value="OFFICE">OFFICE</option>
                                    <option value="OTHER">OTHER</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="room-capacity-input">Seating Capacity</label>
                                <input type="number" id="room-capacity-input" name="room_capacity" class="form-input" min="1" max="999" value="40" required>
                            </div>
                        </div>

                        <div class="form-row-2col">
                            <div class="form-group">
                                <label class="form-label" for="room-status-select">Status</label>
                                <select id="room-status-select" name="room_status" class="form-select">
                                    <option value="available" selected>Available</option>
                                    <option value="unavailable">Unavailable</option>
                                    <option value="maintenance">Maintenance</option>
                                </select>
                            </div>

                            <div class="form-group form-group-checkbox">
                                <label class="form-label">Amenities</label>
                                <label class="checkbox-container" for="room-ac-input">
                                    <input type="checkbox" id="room-ac-input" name="room_has_ac" value="1">
                                    <span class="checkbox-checkmark"></span>
                                    <span class="checkbox-label-text"><i class="fas fa-snowflake"></i> Air Conditioned</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Dynamic Live Preview Card -->
                    <div class="room-modal-preview">
                        <div class="preview-heading">Live Dynamic Preview</div>
                        <div class="room-preview-card" id="room-preview-card">
                            <div class="preview-banner">
                                <img id="room-preview-img" class="preview-banner-img" src="../../public/images/login_background.jpg" alt="Room Building Preview">
                                <span id="room-preview-status" class="preview-status-pill status-available">Available</span>
                            </div>
                            <div class="preview-body">
                                <div class="preview-title-row">
                                    <h4 id="room-preview-title" class="preview-room-title">Room Name</h4>
                                    <span id="room-preview-hall" class="room-hall-pill">None (Standalone Facility)</span>
                                </div>
                                <div class="preview-chips-row">
                                    <span id="room-preview-type" class="type-pill">LECTURE</span>
                                    <span id="room-preview-capacity" class="capacity-pill"><i class="fas fa-chair"></i> 40 seats</span>
                                    <span id="room-preview-ac" class="amenity-chip-ac" style="display: none;"><i class="fas fa-snowflake"></i> AC</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" id="btn-cancel-room" onclick="closeAllModals()">Cancel</button>
                <button type="submit" class="btn-primary" id="btn-submit-room">
                    <i class="fas fa-save"></i> <span>Save Room</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ==========================================
     MODAL: CREATE / EDIT CAMPUS HALL
     ========================================== -->
<div class="modal-overlay" id="modal-hall" role="dialog" aria-modal="true" aria-labelledby="modal-hall-title">
    <div class="modal-box modal-hall-box">
        <div class="modal-header">
            <div class="modal-title" id="modal-hall-title">
                <i class="fas fa-building"></i> <span id="modal-hall-title-text">Add Campus Building</span>
            </div>
            <button type="button" class="modal-close" onclick="closeAllModals()" aria-label="Close dialog">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="form-hall">
            <input type="hidden" id="hall-id" name="hall_id">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="hall-name-input">Building / Hall Name <span class="req">*</span></label>
                    <input type="text" id="hall-name-input" name="hall_name" class="form-input" required maxlength="20" placeholder="e.g. Rizal Hall">
                    <div class="form-helper">Maximum 20 characters (must be unique).</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="hall-desc-input">Facility Description</label>
                    <textarea id="hall-desc-input" name="hall_desc" class="form-textarea" rows="4" maxlength="250" placeholder="Overview of building facilities, wings, departments, or amenities..."></textarea>
                    <div class="form-helper" style="display: flex; justify-content: flex-end;">
                        <span><span id="hall-desc-count">0</span> / 250 characters</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" id="btn-cancel-hall" onclick="closeAllModals()">Cancel</button>
                <button type="submit" class="btn-primary" id="btn-submit-hall">
                    <i class="fas fa-save"></i> <span>Save Building</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ==========================================
     MODAL: DELETE CONFIRMATION
     ========================================== -->
<div class="modal-overlay" id="modal-delete-confirm" role="dialog" aria-modal="true" aria-labelledby="delete-title">
    <div class="modal-box modal-confirm-box">
        <div class="modal-header">
            <div class="modal-title" id="delete-title" style="color: var(--danger-red, #ef4444);">
                <i class="fas fa-exclamation-triangle"></i> <span>Confirm Deletion</span>
            </div>
            <button type="button" class="modal-close" onclick="closeAllModals()" aria-label="Close dialog">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="danger-confirm-card">
                <i class="fas fa-trash-alt danger-icon-large"></i>
                <div class="danger-text-content">
                    <h4 id="delete-entity-heading">Are you sure you want to permanently delete this item?</h4>
                    <p id="delete-desc">This action cannot be undone and will permanently remove this record.</p>
                </div>
            </div>
            <div id="delete-extra-warning" class="delete-warning-box" style="margin-top: 14px; display: none;"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-secondary" id="btn-delete-cancel" onclick="closeAllModals()">Cancel</button>
            <button type="button" class="btn-danger" id="btn-delete-confirm">
                <i class="fas fa-trash-alt"></i> Delete Permanently
            </button>
        </div>
    </div>
</div>

<!-- ==========================================
     MODAL: RESERVATIONS DETAILS
     ========================================== -->
<div class="modal-overlay" id="res-modal" role="dialog" aria-modal="true" aria-labelledby="modal-title">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title" id="modal-title">
                Reservations — <span>Room</span>
            </div>
            <button type="button" class="modal-close" onclick="closeModal()" aria-label="Close dialog">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body" id="modal-body">
            <div class="modal-loading">
                <div class="spinner"></div>
                Loading…
            </div>
        </div>
    </div>
</div>

<script>
function closeAllModals() {
    document.querySelectorAll('.modal-overlay').forEach(function(m) {
        m.classList.remove('open');
    });
}
</script>
<script src="../../public/js/toast.js?v=<?php echo time(); ?>"></script>
<script src="../../public/js/admin_manage_rooms_script.js?v=<?php echo time(); ?>"></script>

</body>
</html>
