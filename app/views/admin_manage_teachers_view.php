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

$fname = htmlspecialchars($_SESSION["fname"] ?? "Admin", ENT_QUOTES, 'UTF-8');
$lname = htmlspecialchars($_SESSION["lname"] ?? "", ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ClassSpace - Manage Teachers</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../public/css/global.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../../public/css/admin_manage_teachers_style.css?v=<?php echo time(); ?>">
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
                <a href="admin_manage_rooms_view.php" class="nav-link">
                    <i class="fas fa-door-open"></i>
                    <span>Manage Rooms</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="admin_manage_teachers_view.php" class="nav-link active">
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
                    Manage <span class="highlight">Teachers</span>
                </h1>
                <p class="page-subtitle">
                    Provision new faculty accounts, manage profile information, reset credentials, and monitor teacher reservation activity.
                </p>
            </div>
            <div>
                <button type="button" class="btn-primary" id="btn-open-create-modal">
                    <i class="fas fa-user-plus"></i>
                    <span>Add Teacher</span>
                </button>
            </div>
        </div>

        <div class="table-card">
            <div class="table-toolbar">
                <div class="table-stats">
                    <div class="tstat tstat-total is-selected" id="tstat-total" title="Show all registered teachers">
                        <span class="dot"></span>
                        Total Teachers: <strong id="ts-total">—</strong>
                    </div>

                    <div class="tstat tstat-active-bk" id="tstat-active" title="Filter teachers with active room schedules">
                        <span class="dot"></span>
                        Active Bookings: <strong id="ts-active">—</strong>
                    </div>

                    <div class="tstat tstat-pending-req" id="tstat-pending" title="Filter teachers with pending requests">
                        <span class="dot"></span>
                        Pending Requests: <strong id="ts-pending">—</strong>
                    </div>
                </div>

                <div class="toolbar-controls">
                    <div class="search-wrap">
                        <i class="fas fa-search search-icon"></i>
                        <input
                            type="text"
                            id="teacher-search-input"
                            class="search-input"
                            placeholder="Search name, @user, or email…"
                            autocomplete="off"
                        >
                        <button type="button" id="clear-search-btn" class="clear-search-btn" title="Clear search">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>

            <table class="teachers-table">
                <thead>
                    <tr>
                        <th class="th-id">#</th>
                        <th class="th-teacher">Teacher</th>
                        <th class="th-email">Email Address</th>
                        <th class="th-settings">Preferences</th>
                        <th class="th-activity">Reservations</th>
                        <th class="th-actions">Actions</th>
                    </tr>
                </thead>
                <tbody id="teachers-tbody">
                    <tr>
                        <td colspan="6" class="loading-cell" style="text-align: center; padding: 40px; color: var(--text-low);">
                            <i class="fas fa-circle-notch fa-spin"></i> Loading faculty accounts…
                        </td>
                    </tr>
                </tbody>
            </table>

            <div class="table-footer">
                <div class="showing-label" id="showing-label">
                    Showing <strong>—</strong> of <strong>—</strong> teachers
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
    </main>
</div>

<!-- ==========================================
     MODAL: CREATE TEACHER
     ========================================== -->
<div class="modal-overlay" id="modal-create-teacher" role="dialog" aria-modal="true" aria-labelledby="modal-create-title">
    <div class="modal-card">
        <div class="modal-header">
            <div class="modal-title" id="modal-create-title">
                <i class="fas fa-user-plus"></i> Create Teacher Account
            </div>
            <button type="button" class="modal-close" onclick="closeAllModals()" aria-label="Close dialog">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="form-create-teacher">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="create-fname">First Name <span class="req">*</span></label>
                        <input type="text" id="create-fname" class="form-input" placeholder="e.g. Maria" required maxlength="80">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="create-lname">Last Name <span class="req">*</span></label>
                        <input type="text" id="create-lname" class="form-input" placeholder="e.g. Santos" required maxlength="80">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="create-email">Email Address <span class="req">*</span></label>
                    <input type="email" id="create-email" class="form-input" placeholder="e.g. maria.santos@fcpc.edu.ph" required maxlength="250">
                    <div class="form-helper">The teacher will use this email or username to sign in to ClassSpace.</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="create-username">Username</label>
                    <input type="text" id="create-username" class="form-input" placeholder="e.g. mariasantos (auto-generated if empty)" maxlength="250">
                    <div class="form-helper">Unique handle for campus directories and messages.</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="create-password">Initial Password <span class="req">*</span></label>
                    <div class="password-input-group">
                        <input type="password" id="create-password" class="form-input" required minlength="6">
                        <button type="button" class="toggle-password-btn" onclick="togglePasswordVisibility('create-password', this)" title="Toggle visibility">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="password-actions-bar">
                        <button type="button" class="quick-action-link" onclick="fillGeneratedPassword('create-password')">
                            <i class="fas fa-wand-magic-sparkles"></i> Generate Strong Password
                        </button>
                        <button type="button" class="quick-action-link" onclick="copyPassword('create-password')">
                            <i class="fas fa-copy"></i> Copy Password
                        </button>
                    </div>
                </div>

                <div class="form-grid" style="margin-top: 16px;">
                    <div class="form-group">
                        <label class="form-label" for="create-darkmode">Interface Theme</label>
                        <select id="create-darkmode" class="form-select">
                            <option value="0" selected>Light Mode (Default)</option>
                            <option value="1">Dark Mode</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="create-language">Preferred Language</label>
                        <select id="create-language" class="form-select">
                            <option value="en" selected>English (en)</option>
                            <option value="fil">Filipino (fil)</option>
                            <option value="es">Spanish (es)</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeAllModals()">Cancel</button>
                <button type="submit" class="btn-primary" id="btn-submit-create">
                    <i class="fas fa-check"></i> Create Account
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ==========================================
     MODAL: EDIT TEACHER
     ========================================== -->
<div class="modal-overlay" id="modal-edit-teacher" role="dialog" aria-modal="true" aria-labelledby="modal-edit-title">
    <div class="modal-card">
        <div class="modal-header">
            <div class="modal-title" id="modal-edit-title">
                <i class="fas fa-user-edit"></i> Edit Teacher Profile
            </div>
            <button type="button" class="modal-close" onclick="closeAllModals()" aria-label="Close dialog">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="form-edit-teacher">
            <input type="hidden" id="edit-account-id">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="edit-fname">First Name <span class="req">*</span></label>
                        <input type="text" id="edit-fname" class="form-input" required maxlength="80">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit-lname">Last Name <span class="req">*</span></label>
                        <input type="text" id="edit-lname" class="form-input" required maxlength="80">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit-email">Email Address <span class="req">*</span></label>
                    <input type="email" id="edit-email" class="form-input" required maxlength="250">
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit-username">Username <span class="req">*</span></label>
                    <input type="text" id="edit-username" class="form-input" required maxlength="250">
                </div>

                <div class="form-grid" style="margin-top: 16px;">
                    <div class="form-group">
                        <label class="form-label" for="edit-darkmode">Interface Theme</label>
                        <select id="edit-darkmode" class="form-select">
                            <option value="0">Light Mode</option>
                            <option value="1">Dark Mode</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit-language">Preferred Language</label>
                        <select id="edit-language" class="form-select">
                            <option value="en">English (en)</option>
                            <option value="fil">Filipino (fil)</option>
                            <option value="es">Spanish (es)</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeAllModals()">Cancel</button>
                <button type="submit" class="btn-primary" id="btn-submit-edit">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ==========================================
     MODAL: RESET PASSWORD
     ========================================== -->
<div class="modal-overlay" id="modal-reset-password" role="dialog" aria-modal="true" aria-labelledby="modal-reset-title">
    <div class="modal-card">
        <div class="modal-header">
            <div class="modal-title" id="modal-reset-title">
                <i class="fas fa-key"></i> Reset Teacher Password
            </div>
            <button type="button" class="modal-close" onclick="closeAllModals()" aria-label="Close dialog">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="form-reset-password">
            <input type="hidden" id="reset-account-id">
            <div class="modal-body">
                <p style="font-size: 13px; color: var(--text-low); margin-bottom: 16px;">
                    Resetting password for: <strong id="reset-teacher-name" style="color: var(--text-high);">Teacher</strong>
                </p>

                <div class="form-group">
                    <label class="form-label" for="reset-password">New Password <span class="req">*</span></label>
                    <div class="password-input-group">
                        <input type="password" id="reset-password" class="form-input" required minlength="6">
                        <button type="button" class="toggle-password-btn" onclick="togglePasswordVisibility('reset-password', this)" title="Toggle visibility">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="password-actions-bar">
                        <button type="button" class="quick-action-link" onclick="fillGeneratedPassword('reset-password')">
                            <i class="fas fa-wand-magic-sparkles"></i> Generate New Password
                        </button>
                        <button type="button" class="quick-action-link" onclick="copyPassword('reset-password')">
                            <i class="fas fa-copy"></i> Copy Password
                        </button>
                    </div>
                    <div class="form-helper">The teacher will also receive an automated security notification regarding this password update.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeAllModals()">Cancel</button>
                <button type="submit" class="btn-primary" id="btn-submit-reset">
                    <i class="fas fa-check-circle"></i> Set New Password
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ==========================================
     MODAL: DELETE CONFIRMATION
     ========================================== -->
<div class="modal-overlay" id="modal-delete-teacher" role="dialog" aria-modal="true" aria-labelledby="modal-delete-title">
    <div class="modal-card">
        <div class="modal-header">
            <div class="modal-title" id="modal-delete-title" style="color: var(--danger-red);">
                <i class="fas fa-exclamation-triangle"></i> Delete Teacher Account
            </div>
            <button type="button" class="modal-close" onclick="closeAllModals()" aria-label="Close dialog">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="danger-confirm-card">
                <i class="fas fa-trash-alt danger-icon-large"></i>
                <div class="danger-text-content">
                    <h4>Are you sure you want to permanently delete this teacher?</h4>
                    <p id="delete-teacher-name" style="font-weight: 600; color: var(--text-high);"></p>
                </div>
            </div>

            <div id="delete-active-warnings"></div>

            <p style="font-size: 12px; color: var(--text-muted); margin-top: 16px;">
                This action is irreversible. All profile information, preferences, notifications, messages, and associated room schedules will be completely removed from the database.
            </p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-secondary" onclick="closeAllModals()">Cancel</button>
            <button type="button" class="btn-danger" id="btn-confirm-delete">
                <i class="fas fa-trash-alt"></i> Permanently Delete
            </button>
        </div>
    </div>
</div>

<script src="../../public/js/toast.js?v=<?php echo time(); ?>"></script>
<script src="../../public/js/admin_manage_teachers_script.js?v=<?php echo time(); ?>"></script>

</body>
</html>

