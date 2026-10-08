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
	<title>ClassSpace - General Settings</title>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
	<link rel="stylesheet" href="../../public/css/global.css">
	<link rel="stylesheet" href="../../public/css/settings_style.css">
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
                <a href="admin_setting_view.php" class="nav-link active">
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
			<div class="page-header settings-header">
				<h1>General Settings</h1>
			</div>

			<div class="settings-container">

				<div class="setting-row">
					<span class="setting-label">Dark Mode Appearance</span>

					<div class="moon-toggle" id="moon-btn" title="Toggle dark mode">
						<i class="fas <?php echo ($themeClass === 'light-mode') ? 'fa-sun' : 'fa-moon'; ?>"></i>
					</div>
				</div>

			</div>
		</main>
	</div>

	<script src="../../public/js/toast.js?v=<?php echo time(); ?>"></script>
	<script src="../../public/js/settings_script.js?v=<?php echo time(); ?>"></script>

</body>
</html>