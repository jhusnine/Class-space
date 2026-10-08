<?php
session_start();
if (!isset($_SESSION["id"])) {
    header("Location: ../index.php");
    exit;
}
$themeClass = (isset($_COOKIE['theme']) && trim($_COOKIE['theme']) === 'light') ? 'light-mode' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ClassSpace - Admin Messages</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../public/css/global.css">
    <link rel="stylesheet" href="../../public/css/admin_messages_style.css?v=<?php echo time(); ?>">
</head>
<body class="<?php echo $themeClass; ?>">
    <div class="container">
            <aside class="sidebar">

        <div class="logo">
            <img src="../../public/images/logo.png" alt="ClassSpace">
            <span>ClassSpace</span>
        </div>

        <ul class="nav-menu">

            <li class="nav-item">
                <a href="admin_homepage_view.php" class="nav-link ">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="admin_manage_rooms_view.php" class="nav-link ">
                    <i class="fas fa-door-open"></i>
                    <span>Manage Rooms</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="admin_reports_view.php" class="nav-link ">
                    <i class="fas fa-file-alt"></i>
                    <span>Reports</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="admin_messages_view.php" class="nav-link active">
                    <i class="fas fa-envelope"></i>
                    <span>Messages</span>
                    <span class="notif-badge" id="messageBadge">0</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="admin_setting_view.php" class="nav-link ">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </li>

        </ul>

        <div class="sidebar-footer">
            <a href="logout.php" class="footer-link logout">Log out</a>
        </div>

    </aside>

        <main class="main-content">
            <div class="page-header">
                <h1>User Messages</h1>
            </div>

            <div class="messages-wrapper">
                <!-- LEFT: USER LIST -->
                <div class="users-panel">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input 
                            type="text" 
                            id="userSearch" 
                            class="search-input" 
                            placeholder="Search users..."
                        >
                    </div>

                    <div class="users-list" id="usersList">
                        <div class="loading-spinner">
                            <i class="fas fa-spinner fa-spin"></i>
                            <p>Loading users...</p>
                        </div>
                    </div>
                </div>

                <!-- RIGHT: MESSAGE THREAD -->
                <div class="thread-panel">
                    <div class="thread-header" id="threadHeader">
                        <h2>Select a user to view messages</h2>
                    </div>

                    <div class="thread-messages" id="threadMessages">
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>No messages yet</p>
                        </div>
                    </div>

                    <div class="thread-input" id="threadInput">
                        <textarea 
                            id="replyInput" 
                            class="reply-textarea" 
                            placeholder="Type your reply... (Max 500 characters)"
                            maxlength="500"
                        ></textarea>
                        <div class="input-footer">
                            <span class="char-count"><span id="replyCharCount">0</span>/500</span>
                            <button class="send-btn" id="replyBtn">
                                <i class="fas fa-paper-plane"></i> Reply
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="../../public/js/toast.js?v=<?php echo time(); ?>"></script>
    <script src="../../public/js/admin_messages_user_script.js?v=<?php echo time(); ?>"></script>
</body>
</html>