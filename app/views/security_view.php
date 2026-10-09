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
	<title>ClassSpace - Security Settings</title>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
	<link rel="stylesheet" href="../../public/css/global.css">
</head>
<body class="<?php echo $themeClass; ?>">
	<div class="container">
		<aside class="sidebar">
			<div class="logo">
				<img src="../../public/images/logo.png" alt="ClassSpace">
				<span>ClassSpace</span>
			</div>

			<nav class="nav-menu">
				<li class="nav-item">
					<a href="user_homepage_view.php" class="nav-link">
						<i class="fas fa-home"></i>
						<span>Homepage</span>
					</a>
				</li>
				<li class="nav-item">
					<a href="calendar_view.php" class="nav-link">
						<i class="fas fa-calendar"></i>
						<span>Calendar</span>
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
						<i class="fas fa-user"></i>
						<span>Account Profile</span>
					</a>
				</li>
				<li class="nav-item">
					<a href="security_view.php" class="nav-link active">
						<i class="fas fa-shield"></i>
						<span>Security</span>
					</a>
				</li>
				<li class="nav-item">
					<a href="settings_view.php" class="nav-link">
						<i class="fas fa-cog"></i>
						<span>Settings</span>
					</a>
				</li>
			</nav>

			<div class="sidebar-footer">
				<a href="about_view.php" class="footer-link">About Us</a>
				<a href="contact_view.php" class="footer-link">Contact Us</a>
				<a href="logout.php" class="footer-link" id="logout">Log out</a>
			</div>
		</aside>
		<main class="main-content">
			<div class="page-header">
				<h1>Security Settings</h1>
			</div>
			<div class="card security-card">
				<div class="security-row">
					<div>
						<h3>Two-Factor Authentication</h3>
						<p class="security-desc">Add an extra layer of security to your account.</p>
					</div>
					<div class="toggle-switch active" onclick="toggleTFA(this)"></div>
				</div>
			</div>
			<div class="card security-card">
				<h3>Active Sessions</h3>
				<p class="security-session-text">Logged in via Chrome on Windows Desktop.</p>
				<button class="btn-danger" onclick="revokeSession(this)">Revoke</button>
			</div>
		</main>
	</div>

	<script src="../../public/js/toast.js?v=<?php echo time(); ?>"></script>
	<script src="../../public/js/security_script.js?v=<?php echo time(); ?>"></script>
</body>
</html>