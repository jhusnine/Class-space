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
	<title>ClassSpace - About Us</title>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
	<link rel="stylesheet" href="../../public/css/global.css">
	<link rel="stylesheet" href="../../public/css/about_style.css">
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
				<a href="about_view.php" class="footer-link active">About Us</a>
				<a href="contact_view.php" class="footer-link">Contact Us</a>
				<a href="logout.php" class="footer-link" id="logout">Log out</a>
			</div>
		</aside>
		
		<main class="main-content">
			<div class="page-header">
				<h1>About Class<span class="brand-highlight">Space</span></h1>
			</div>

			<div class="card">
				<p class="about-description">
					<strong>ClassSpace</strong> is an advanced digital platform designed to optimize educational resource management through intelligent real-time classroom availability and allocation. Our mission is to enhance the institutional experience by ensuring effortless and equitable access to spaces.
				</p>

				<h3 class="section-title">Key Features & Benefits:</h3>

				<div class="features-container">
					<p class="features">
						<strong>1. Live Availability View:</strong> Instantly access a clear, dynamic view of all campus rooms, allowing for immediate booking or scheduling decisions based on current use.
					</p>

					<p class="features">
						<strong>2. Conflict Resolution:</strong> Intelligent system check ensure no double-booking, resolving scheduling conflicts automatically before they arise.
					</p>

					<p class="features">
						<strong>3. Data-Driven Decisions:</strong> Leverage detailed analytics on space utilization to make informed future planning and resource allocation service.
					</p>

					<p class="feature-last">
						<strong>4. Optimized Workflow:</strong> Streamlined allocation planning suggest the most suitable rooms based on specific needs, saving time and energy.
					</p>
				</div>
			</div>
		</main>
	</div>
		
	<script src="../../public/js/toast.js?v=<?php echo time(); ?>"></script>
</body>
</html>