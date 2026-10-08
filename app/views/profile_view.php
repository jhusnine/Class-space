<?php 
	session_start();
	if (!isset($_SESSION["id"])) {
		header("Location: ../index.php");
		exit;
	}

	$fname = htmlspecialchars($_SESSION["fname"] ?? "User");
	$lname = htmlspecialchars($_SESSION["lname"] ?? "");
	$email = htmlspecialchars($_SESSION["email"] ?? "");
	$themeClass = (isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'light') ? 'light-mode' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>ClassSpace - Account Profile</title>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
	<link rel="stylesheet" href="../../public/css/global.css">
	<link rel="stylesheet" href="../../public/css/profile_style.css">
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
					<a href="profile_view.php" class="nav-link active">
						<i class="fas fa-user"></i>
						<span>Account Profile</span>
					</a>
				</li>
				<li class="nav-item">
					<a href="security_view.php" class="nav-link">
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
				<h1>Account <span class="highlight">Profile</span></h1>
			</div>

			<div class="card">
				<div class="profile-card">
					<button class="avatar"><?= strtoupper(substr($fname, 0, 1) . substr($lname, 0, 1)) ?></button>
					<div class="profile-info">
						<h3><?= $fname ?> <?= $lname ?></h3>
						<p>Member ID: 2026-FCPC-TEACHER</p>
					</div>
				</div>
			</div>
		</main>
	</div>

	<script src="../../public/js/toast.js?v=<?php echo time(); ?>"></script>
</body>
</html>