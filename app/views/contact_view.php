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


		<?php
		require __DIR__ . '/partials/user_sidebar.php';
		?>

		<main class="main-content">
			<?php require __DIR__ . '/partials/user_utility_bar.php'; ?>


			<div class="page-header">
				<div>
					<h1>Contact <span class="highlight">Support</span></h1>
					<p>Questions about a room reservation or your account? Reach out to ICT Support or start a chat session.</p>
				</div>
			</div>

			<div class="contact-page">
				<div class="contact-watermark" aria-hidden="true">Contact</div>

				<section class="contact-grid">

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

						<a class="contact-tile contact-tile-chat" href="#support-chat" id="chat-with-us-btn" role="button" aria-haspopup="dialog" aria-expanded="false">
							<span class="tile-icon"><i class="far fa-comments" aria-hidden="true"></i></span>
							<span class="tile-text"><strong>Chat with us</strong><span>Message ICT Support</span></span>
							<span class="tile-arrow"><i class="fas fa-arrow-right" aria-hidden="true"></i></span>
						</a>
					</div>

				</section>


				<!-- Support Chat Pop-up Modal -->
				<div class="chat-modal-overlay" id="chat-modal" style="display:none;" onclick="handleChatModalBackdropClick(event)">
					<div class="chat-modal-card" id="support-chat" role="dialog" aria-modal="true" aria-labelledby="active-chat-user">
						<div class="chat-main-window">
							<div class="chat-header-user">
								<div class="avatar-circle">S</div>
								<div class="chat-header-info">
									<span id="active-chat-user">ICT Support</span>
									<div class="chat-header-sub">Replies appear here</div>
								</div>
								<button type="button" class="chat-modal-close" onclick="closeChatModal()" aria-label="Close support chat">
									<i class="fas fa-times" aria-hidden="true"></i>
								</button>
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
									<input type="text" id="reply-message-input" placeholder="Type a message..."
										   onkeydown="checkEnterKey(event)" maxlength="500" autocomplete="off">
									<button type="button" class="send-btn-icon" onclick="sendMessageFromInput()" aria-label="Send message">
										<i class="fas fa-paper-plane" aria-hidden="true"></i>
									</button>
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
