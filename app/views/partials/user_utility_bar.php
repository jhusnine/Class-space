<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$rawFname = trim((string)($_SESSION["fname"] ?? "User"));
if ($rawFname === "") {
    $rawFname = "User";
}
$rawLname = trim((string)($_SESSION["lname"] ?? ""));
$utilityFullName = htmlspecialchars(trim($rawFname . ($rawLname !== "" ? " " . $rawLname : "")), ENT_QUOTES, "UTF-8");
$utilityEmail = htmlspecialchars((string)($_SESSION["email"] ?? ""), ENT_QUOTES, "UTF-8");
$utilityInitial = strtoupper(substr($rawFname, 0, 1));
if ($utilityInitial === "") {
    $utilityInitial = "U";
}
$utilityInitial = htmlspecialchars($utilityInitial, ENT_QUOTES, "UTF-8");

$utilityRole = htmlspecialchars(ucfirst((string)($_SESSION["role"] ?? "Student")), ENT_QUOTES, "UTF-8");
$utilityCurrentPage = basename($_SERVER["PHP_SELF"] ?? "");
?>
<nav class="user-utility-bar" aria-label="Account shortcuts">
    <div class="user-utility-actions">
        <a href="contact_view.php" class="utility-icon-link<?= $utilityCurrentPage === 'contact_view.php' ? ' is-active' : '' ?>" aria-label="Contact Us" title="Contact Us"<?= $utilityCurrentPage === 'contact_view.php' ? ' aria-current="page"' : '' ?>>
            <i class="fa-regular fa-envelope" aria-hidden="true"></i>
        </a>
        <a href="notifications_view.php" class="utility-icon-link<?= $utilityCurrentPage === 'notifications_view.php' ? ' is-active' : '' ?>" aria-label="Notifications" title="Notifications"<?= $utilityCurrentPage === 'notifications_view.php' ? ' aria-current="page"' : '' ?>>
            <i class="fas fa-bell" aria-hidden="true"></i>
            <span id="notif-badge" class="notif-badge" aria-live="polite"></span>
        </a>
        <div class="utility-profile-wrapper">
            <button type="button" class="utility-profile-link<?= $utilityCurrentPage === 'profile_view.php' ? ' is-active' : '' ?>" id="profile-dropdown-trigger" aria-haspopup="true" aria-expanded="false" aria-controls="profile-dropdown" aria-label="User Account Menu">
                <span class="utility-avatar" aria-hidden="true"><?= $utilityInitial ?></span>
                <span class="utility-profile-meta">
                    <span class="utility-profile-name"><?= $utilityFullName ?></span>
                    <span class="utility-profile-email"><?= $utilityEmail !== "" ? $utilityEmail : "Account Profile" ?></span>
                </span>
                <i class="fas fa-chevron-down utility-profile-caret" aria-hidden="true"></i>
            </button>
            <div class="profile-dropdown" id="profile-dropdown" role="menu" aria-label="User Account Menu" hidden>
                <div class="profile-dropdown-header">
                    <span class="profile-dropdown-avatar" aria-hidden="true"><?= $utilityInitial ?></span>
                    <div class="profile-dropdown-user">
                        <strong class="profile-dropdown-name"><?= $utilityFullName ?></strong>
                        <span class="profile-dropdown-email"><?= $utilityEmail !== "" ? $utilityEmail : "No email set" ?></span>
                        <span class="profile-dropdown-role"><?= $utilityRole ?></span>
                    </div>
                </div>
                <ul class="profile-dropdown-menu" role="none">
                    <li role="none">
                        <a href="profile_view.php" class="profile-dropdown-item<?= $utilityCurrentPage === 'profile_view.php' ? ' is-active' : '' ?>" role="menuitem">
                            <i class="fas fa-user" aria-hidden="true"></i>
                            <span>View Profile</span>
                        </a>
                    </li>
                    <li role="none">
                        <a href="settings_view.php" class="profile-dropdown-item<?= $utilityCurrentPage === 'settings_view.php' ? ' is-active' : '' ?>" role="menuitem">
                            <i class="fas fa-cog" aria-hidden="true"></i>
                            <span>Account Settings</span>
                        </a>
                    </li>
                    <li role="none">
                        <a href="settings_view.php#about-settings" class="profile-dropdown-item" role="menuitem">
                            <i class="fas fa-circle-info" aria-hidden="true"></i>
                            <span>About ClassSpace</span>
                        </a>
                    </li>
                    <li class="profile-dropdown-divider" role="separator"></li>
                    <li role="none">
                        <a href="logout.php" class="profile-dropdown-item item-logout" role="menuitem">
                            <i class="fas fa-arrow-right-from-bracket" aria-hidden="true"></i>
                            <span>Log Out</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</nav>

