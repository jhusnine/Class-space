<?php
	session_start();
	if (!isset($_SESSION["id"])) {
		header("Location: ../index.php");
		exit;
	}

	$fname = htmlspecialchars($_SESSION["fname"] ?? "User");
	$lname = htmlspecialchars($_SESSION["lname"] ?? "");
	$email = htmlspecialchars($_SESSION["email"] ?? "");
	$firstInitial = strtoupper(substr(trim($_SESSION["fname"] ?? "U"), 0, 1));
	if ($firstInitial === "") $firstInitial = "U";
	$themeClass = (isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'light') ? 'light-mode' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>ClassSpace - Account Profile</title>
	<link rel="icon" href="../../public/images/logo.png">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
	<link rel="stylesheet" href="../../public/css/global.css?v=<?php echo filemtime(__DIR__ . '/../../public/css/global.css'); ?>">
	<link rel="stylesheet" href="../../public/css/profile_style.css?v=<?php echo filemtime(__DIR__ . '/../../public/css/profile_style.css'); ?>">
</head>
<body class="<?php echo $themeClass; ?>">
	<div class="container">


		<?php
		require __DIR__ . '/partials/user_sidebar.php';
		?>

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


			<div class="page-header">
				<div>
					<h1>Account <span class="highlight">Profile</span></h1>
					<p>View and manage your personal teacher credentials.</p>
				</div>
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
