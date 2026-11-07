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
			<?php require __DIR__ . '/partials/user_utility_bar.php'; ?>


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
