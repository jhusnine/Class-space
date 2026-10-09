<?php
session_start();
if (!isset($_SESSION["id"])) {
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
    <title>ClassSpace - Manage Rooms</title>

    <link class="styles" rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../public/css/global.css">
<link rel="stylesheet" href="../../public/css/admin_manage_rooms_style.css">
</head>
<body class="<?php echo htmlspecialchars($themeClass, ENT_QUOTES, 'UTF-8'); ?>">

<div class="container">

    <aside class="sidebar">
        <div class="logo">
            <img src="../../public/images/logo.png" alt="ClassSpace">
            <span>ClassSpace</span>
        </div>

        <ul class="nav-menu">
            <li class="nav-item">
                <a href="admin_homepage_view.php" class="nav-link ">
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
                <a href="admin_reports_view.php" class="nav-link ">
                    <i class="fas fa-file-alt"></i>
                    <span>Reports</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="admin_messages_view.php" class="nav-link ">
                    <i class="fas fa-envelope"></i>
                    <span>Messages</span>
                    <span class="notif-badge" id="messageBadge">0</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="admin_setting_view.php" class="nav-link ">
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
                    Manage <span class="highlight">Rooms</span>
                </h1>
                <p class="page-subtitle">
                    View, update status, and manage reservations for all rooms.
                </p>
            </div>
        </div>

        <div class="table-card">
            <div class="table-toolbar">
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

                <div class="toolbar-search search-bar">
                    <input
                        type="text"
                        id="search-input"
                        class="search-input"
                        placeholder="Search halls or rooms…"
                        autocomplete="off"
                    >
                    <button class="search-btn" onclick="triggerSearch()">
                        <i class="fas fa-search"></i>
                    </button>
                    <div id="search-results" class="search-results"></div>
                </div>
            </div>

            <table class="rooms-table">
                <thead>
                    <tr>
                        <th class="th-id">#</th>
                        <th>Building</th>
                        <th>Room Name</th>
                        <th class="th-room_capacity">Capacity</th>
                        <th class="th-action">Action</th>
                    </tr>
                </thead>
                <tbody id="rooms-tbody">
                    <tr>
                        <td colspan="5" class="loading-cell">
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
    </main>
</div>

<div class="modal-overlay" id="res-modal">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title" id="modal-title">
                Reservations — <span>Room</span>
            </div>
            <button class="modal-close" onclick="closeModal()">
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

<script src="../../public/js/toast.js?v=<?php echo time(); ?>"></script>
<script src="../../public/js/admin_manage_rooms_script.js?v=<?php echo time(); ?>"></script>

</body>
</html>