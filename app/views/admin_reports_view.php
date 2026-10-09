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
    <title>ClassSpace - Reports</title>

    <link class="styles" rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../public/css/global.css">
    <link rel="stylesheet" href="../../public/css/admin_manage_rooms_style.css">

    <style>
        .report-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: flex-end;
            margin-bottom: 16px;
        }
        .report-filter-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
            min-width: 160px;
        }
        .report-filter-group label {
            font-size: 12px;
            opacity: 0.75;
        }
        .report-filter-group select,
        .report-filter-group input {
            padding: 8px 10px;
            border-radius: 6px;
        }
        .report-actions {
            display: flex;
            gap: 8px;
        }

        @media print {
            .sidebar, .page-header, .report-filters, .report-actions,
            .table-footer, .no-print {
                display: none !important;
            }
            .main-content { margin: 0 !important; padding: 0 !important; }
            .table-card { box-shadow: none !important; border: none !important; }
        }
    </style>
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
                <a href="admin_manage_rooms_view.php" class="nav-link ">
                    <i class="fas fa-door-open"></i>
                    <span>Manage Rooms</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="admin_reports_view.php" class="nav-link active">
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
                    Reservation <span class="highlight">Reports</span>
                </h1>
                <p class="page-subtitle">
                    View and filter all reservation records — approved, pending, and rejected.
                </p>
            </div>
        </div>

        <div class="table-card">

            <div class="report-filters">
                <div class="report-filter-group">
                    <label for="filter-status">Status</label>
                    <select id="filter-status" onchange="loadReport()">
                        <option value="">All Statuses</option>
                        <option value="Approved">Approved</option>
                        <option value="Pending">Pending</option>
                        <option value="Rejected">Rejected</option>
                    </select>
                </div>

                <div class="report-filter-group">
                    <label for="filter-hall">Building</label>
                    <select id="filter-hall" onchange="onHallChange()">
                        <option value="">All Buildings</option>
                    </select>
                </div>

                <div class="report-filter-group">
                    <label for="filter-room">Room</label>
                    <select id="filter-room" onchange="loadReport()">
                        <option value="">All Rooms</option>
                    </select>
                </div>

                <div class="report-filter-group">
                    <label for="filter-date">Date</label>
                    <input type="date" id="filter-date" onchange="loadReport()">
                </div>

                <div class="report-actions">
                    <button class="btn-primary" onclick="clearFilters()">Clear Filters</button>
                    <button class="btn-primary" onclick="window.print()">
                        <i class="fas fa-print"></i> Print
                    </button>
                </div>
            </div>

            <div class="showing-label" id="report-count" style="margin-bottom:10px;">
                Loading…
            </div>

            <table class="rooms-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Requester</th>
                        <th>Room</th>
                        <th>Building</th>
                        <th>Day / Date</th>
                        <th>Time</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="report-tbody">
                    <tr>
                        <td colspan="7" class="loading-cell">
                            <div class="spinner spinner-center"></div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </main>
</div>

<script src="../../public/js/toast.js?v=<?php echo time(); ?>"></script>
<script src="../../public/js/admin_reports_script.js?v=<?php echo time(); ?>"></script>

</body>
</html>