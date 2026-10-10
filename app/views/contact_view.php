<?php 
	session_start();
	if (!isset($_SESSION["id"])) {
		header("Location: ../index.php");
		exit;
	}

	$fname = htmlspecialchars($_SESSION["fname"] ?? "User");
	$lname = htmlspecialchars($_SESSION["lname"] ?? "");
	$firstInitial = strtoupper(substr(trim($_SESSION["fname"] ?? "U"), 0, 1));
	if ($firstInitial === "") $firstInitial = "U";

	$themeClass = (isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'light') ? 'light-mode' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>ClassSpace - Contact Support</title>
	<link rel="icon" href="../../public/images/logo.png">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
	<link rel="stylesheet" href="../../public/css/global.css?v=<?php echo time(); ?>">
	<link rel="stylesheet" href="../../public/css/contact_style.css?v=<?php echo time(); ?>">
	<link rel="stylesheet" href="../../public/css/notification_style.css?v=<?php echo time(); ?>">
</head>
<body class="<?php echo $themeClass; ?>">
	<div class="container">

		<!-- ================= SIDEBAR ================= -->
		<button type="button" class="mobile-menu-toggle" id="mobile-menu-toggle"
				aria-controls="classspace-sidebar" aria-expanded="false" aria-label="Open navigation menu">
			<i class="fas fa-bars" aria-hidden="true"></i>
		</button>
		<div class="mobile-menu-backdrop" id="mobile-menu-backdrop" hidden></div>
		<aside class="sidebar" id="classspace-sidebar">
			<div class="logo brand-header">
				<div class="brand-logo-frame">
					<img src="../../public/images/logo.png" alt="ClassSpace">
				</div>
				<div class="brand-text-block">
					<span class="brand-title">ClassSpace</span>
					<span class="brand-subtitle">First City Providential</span>
				</div>
			</div>

			<ul class="nav-menu">
				<li class="nav-item">
					<a href="user_homepage_view.php" class="nav-link">
						<i class="fas fa-home"></i>
						<span>Campus Dashboard</span>
					</a>
				</li>
				<li class="nav-item">
					<a href="calendar_view.php" class="nav-link">
						<i class="fas fa-calendar"></i>
						<span>Master Calendar</span>
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
			</ul>

			<!-- Modern Collegiate Student Profile Strip & Footer -->
			<div class="sidebar-bottom">
				<div class="sidebar-profile-card">
					<div class="user-avatar-circle"><?php echo $firstInitial; ?></div>
					<div class="sidebar-profile-meta">
						<div class="sidebar-profile-name" title="<?php echo $fname . ($lname ? " " . $lname : ""); ?>"><?php echo $fname . ($lname ? " " . $lname : ""); ?></div>
						<div class="sidebar-profile-sub">BSIT &bull; Student</div>
					</div>
					<a href="logout.php" class="sidebar-profile-logout" id="logout" title="Log out">
						<i class="fas fa-arrow-right-from-bracket" aria-hidden="true"></i>
					</a>
				</div>

				<div class="sidebar-footer">
					<a href="about_view.php" class="footer-link">About Us</a>
					<span class="footer-dot">&bull;</span>
					<a href="contact_view.php" class="footer-link active">Contact Us</a>
					<span class="footer-dot">&bull;</span>
					<a href="logout.php" class="footer-link">Log out</a>
				</div>
			</div>
		</aside>
		
		<main class="main-content">
			<div class="page-header">
				<h1>Contact Support</h1>
			</div>

			<div class="split-workspace">
				
				<!-- LEFT: Contact Form -->
				<div class="card contact-card">
					<div class="contact-info-wrapper">
						<div class="contact-info">
							<i class="fas fa-envelope"></i>
							<a href="mailto:ictsupport@fcpc.edu.ph" class="contact-link">
								ictsupport@fcpc.edu.ph
							</a>
						</div>
						<div class="contact-info">
							<i class="fas fa-phone"></i>
							<span>0917 709 1075</span>
						</div>
					</div>

				<form id="contactForm" onsubmit="handleContactSubmit(event)">
					<div class="form-group">
						<label>Subject</label>
						<input type="text" class="form-input" placeholder="Subject" required>
					</div>
					<div class="form-group">
						<label>Message</label>
						<textarea id="contact-message-textarea" class="form-input" placeholder="Your Message..." required></textarea>
					</div>
					<button type="submit" class="btn-primary submit-full">
						Send Message
					</button>
				</form>
				</div>

				<!-- RIGHT: Live Chat Thread with Admin -->
				<div class="message-dashboard">
					<div class="dashboard-title">MESSAGES WITH SUPPORT</div>

					<div class="dashboard-container-box">
						<div class="chat-main-window">
							<div class="chat-header-user">
								<div class="avatar-circle"></div>
								<span id="active-chat-user">ICT Support / Admin</span>
							</div>

							<div class="chat-body-stream" id="chat-stream">
								<div class="empty-chat-state">
									<i class="fas fa-spinner fa-spin"></i>
									<p>Loading messages...</p>
								</div>
							</div>

							<div class="chat-footer-bar">
								<div class="action-icons">
									<i class="far fa-file-alt" title="Document"></i>
									<i class="far fa-image" title="Image"></i>
								</div>
								<div class="input-wrapper">
									<input 
										type="text" 
										id="reply-message-input" 
										placeholder="Type a message..." 
										onkeypress="checkEnterKey(event)"
									>
									<i class="fas fa-paper-plane send-btn-icon" onclick="sendMessageFromInput()"></i>
								</div>
							</div>
						</div>
					</div>
				</div>

			</div>
		</main>
	</div>

	<script src="../../public/js/toast.js?v=<?php echo time(); ?>"></script>
	<script src="../../public/js/contact_script.js?v=<?php echo time(); ?>"></script>
</body>
</html>
