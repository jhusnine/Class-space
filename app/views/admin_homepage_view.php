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

$fname = htmlspecialchars($_SESSION["fname"] ?? "Admin");
$lname = htmlspecialchars($_SESSION["lname"] ?? "");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ClassSpace - Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../public/css/global.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../../public/css/admin_homepage_style.css?v=<?php echo time(); ?>">
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
                <a href="admin_homepage_view.php" class="nav-link active">
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
                <a href="admin_reports_view.php" class="nav-link ">
                    <i class="fas fa-file-alt"></i>
                    <span>Reports</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="admin_messages_view.php" class="nav-link ">
                    <i class="fas fa-envelope"></i>
                    <span>Messages</span>
                    <span class="notif-badge" id="messageBadge" >0</span>
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
                <h1>
                    Welcome,
                    <span class="highlight"><?php echo $fname; ?></span>
                </h1>
                <p>
                    Admin Dashboard - manage reservations and campus spaces.
                </p>
            </div>
        </div>

        <div class="stats-grid" id="stats-grid">

            <div class="stat-card">
                <h3>Pending Requests</h3>
                <div class="number highlight" id="stat-pending">—</div>
            </div>

            <div class="stat-card">
                <h3>Total Rooms</h3>
                <div class="number" id="stat-rooms">—</div>
            </div>

            <div class="stat-card">
                <h3>Active Schedules</h3>
                <div class="number" id="stat-schedules">—</div>
            </div>

        </div>
        
        <div class="card">
            <div class="card-header">
                <div>
                    <h2>Pending Requests</h2>
                    <p class="card-subtitle">
                        Sorted by oldest submission first.
                    </p>
                </div>
            </div>
            <div id="pending-table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Requested By</th>
                            <th>Room</th>
                            <th>Hall</th>
                            <th>Type</th>
                            <th>Day / Date</th>
                            <th>Time</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody id="pending-tbody">
                        <tr>
                            <td colspan="8" class="loading-cell">
                                Loading...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<script src="../../public/js/toast.js?v=<?php echo time(); ?>"></script>
<script src="../../public/js/admin_homepage_script.js?v=<?php echo time(); ?>"></script>

</body>
</html>