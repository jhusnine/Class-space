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
					<a href="logout.php" class="footer-link">Log out</a>
				</div>
			</div>
		</aside>
		
		<main class="main-content">
			<?php
			$utilityName = trim((string)($_SESSION["fname"] ?? "User") . " " . (string)($_SESSION["lname"] ?? ""));
			$utilityEmail = (string)($_SESSION["email"] ?? "");
			$utilityInitial = strtoupper(substr(trim((string)($_SESSION["fname"] ?? "U")), 0, 1));
			if ($utilityInitial === "") $utilityInitial = "U";
			$utilityCurrentPage = basename($_SERVER["PHP_SELF"] ?? "");
			?>
			<nav class="user-utility-bar" aria-label="Account shortcuts">
				<div class="user-utility-actions">
					<a href="contact_view.php" class="utility-icon-link<?php echo $utilityCurrentPage === 'contact_view.php' ? ' is-active' : ''; ?>" aria-label="Contact Us" title="Contact Us"<?php echo $utilityCurrentPage === 'contact_view.php' ? ' aria-current="page"' : ''; ?>>
						<i class="fa-regular fa-envelope" aria-hidden="true"></i>
					</a>
					<a href="notifications_view.php" class="utility-icon-link<?php echo $utilityCurrentPage === 'notifications_view.php' ? ' is-active' : ''; ?>" aria-label="Notifications" title="Notifications"<?php echo $utilityCurrentPage === 'notifications_view.php' ? ' aria-current="page"' : ''; ?>>
						<i class="fas fa-bell" aria-hidden="true"></i>
						<span id="notif-badge" class="notif-badge" aria-live="polite"></span>
					</a>
					<a href="profile_view.php" class="utility-profile-link<?php echo $utilityCurrentPage === 'profile_view.php' ? ' is-active' : ''; ?>" aria-label="Account Profile" title="Account Profile"<?php echo $utilityCurrentPage === 'profile_view.php' ? ' aria-current="page"' : ''; ?>>
						<span class="utility-avatar" aria-hidden="true"><?php echo htmlspecialchars($utilityInitial, ENT_QUOTES, "UTF-8"); ?></span>
						<span class="utility-profile-meta">
							<span class="utility-profile-name"><?php echo htmlspecialchars($utilityName, ENT_QUOTES, "UTF-8"); ?></span>
							<span class="utility-profile-email"><?php echo htmlspecialchars($utilityEmail !== "" ? $utilityEmail : "Account Profile", ENT_QUOTES, "UTF-8"); ?></span>
						</span>
					</a>
				</div>
			</nav>


			<div class="contact-page">
				<div class="contact-watermark" aria-hidden="true">Contact</div>

				<section class="contact-grid">

					<!-- LEFT: intro -->
					<div class="contact-intro">
						<span class="contact-badge"><i class="fas fa-headset" aria-hidden="true"></i> Contact Support</span>
						<h1>Get in touch</h1>
						<p>Questions about a room reservation or your account? Message ICT Support in the chat below and the reply will show up there.</p>
					</div>

					<!-- RIGHT: ways to reach support -->
					<div class="contact-tiles">
						<a class="contact-tile" href="mailto:ictsupport@fcpc.edu.ph">
							<span class="tile-icon"><i class="far fa-envelope" aria-hidden="true"></i></span>
							<span class="tile-text"><strong>Email us</strong><span>ictsupport@fcpc.edu.ph</span></span>
							<span class="tile-arrow"><i class="fas fa-arrow-right" aria-hidden="true"></i></span>
						</a>

						<a class="contact-tile" href="tel:09177091075">
							<span class="tile-icon"><i class="fas fa-phone" aria-hidden="true"></i></span>
							<span class="tile-text"><strong>Call us</strong><span>0917 709 1075</span></span>
							<span class="tile-arrow"><i class="fas fa-arrow-right" aria-hidden="true"></i></span>
						</a>

						<a class="contact-tile" href="#support-chat">
							<span class="tile-icon"><i class="far fa-comments" aria-hidden="true"></i></span>
							<span class="tile-text"><strong>Chat with us</strong><span>Message ICT Support below</span></span>
							<span class="tile-arrow"><i class="fas fa-arrow-right" aria-hidden="true"></i></span>
						</a>
					</div>

				</section>

				<!-- CHAT THREAD -->
				<section class="message-dashboard" id="support-chat">
					<div class="dashboard-title">Messages with support</div>

					<div class="dashboard-container-box">
						<div class="chat-main-window">
							<div class="chat-header-user">
								<div class="avatar-circle">S</div>
								<div>
									<span id="active-chat-user">ICT Support</span>
									<div class="chat-header-sub">Replies appear here</div>
								</div>
							</div>

							<div class="chat-toolbar">
								<div class="chat-filters" id="chatFilters">
									<button type="button" class="chip active" data-filter="all">All</button>
									<button type="button" class="chip" data-filter="sent">Sent</button>
									<button type="button" class="chip" data-filter="received">Replies</button>
								</div>
								<label class="chat-search">
									<i class="fas fa-search" aria-hidden="true"></i>
									<input type="text" id="chatSearch" placeholder="Search messages" aria-label="Search messages">
								</label>
							</div>

							<div class="chat-body-stream" id="chat-stream" data-initial="<?php echo $firstInitial; ?>">
								<div class="empty-chat-state">
									<i class="fas fa-spinner fa-spin"></i>
									<p>Loading messages...</p>
								</div>
							</div>

							<div class="chat-footer-bar">
								<div class="input-wrapper">
									<input type="text" id="reply-message-input" placeholder="Type a message"
										   onkeydown="checkEnterKey(event)" maxlength="500" autocomplete="off">
									<button type="button" class="send-btn-icon" onclick="sendMessageFromInput()" aria-label="Send message">
										<i class="fas fa-paper-plane" aria-hidden="true"></i>
									</button>
								</div>
							</div>
						</div>
					</div>
				</section>
			</div>
		</main>
	</div>

	<script src="../../public/js/toast.js?v=<?php echo time(); ?>"></script>
	<script src="../../public/js/contact_script.js?v=<?php echo time(); ?>"></script>
</body>
</html>