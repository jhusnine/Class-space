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
	<title>ClassSpace - Contact Support</title>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
	<link rel="stylesheet" href="../../public/css/global.css?v=<?php echo time(); ?>">
	<link rel="stylesheet" href="../../public/css/contact_style.css?v=<?php echo time(); ?>">
	<link rel="stylesheet" href="../../public/css/notification_style.css?v=<?php echo time(); ?>">
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
				<a href="about_view.php" class="footer-link">About Us</a>
				<a href="contact_view.php" class="footer-link active">Contact Us</a>
				<a href="logout.php" class="footer-link" id="logout">Log out</a>
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
