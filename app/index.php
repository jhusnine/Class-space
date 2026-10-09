<?php
session_start();
if (isset($_SESSION["id"])) {
    header("Location: views/user_homepage_view.php");
    exit;
}
$themeClass = (isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'light') ? 'light-mode' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <title>ClassSpace - Sign In</title>
    <link rel="icon" type="image/png" href="../public/images/logo.png">
    <link rel="stylesheet" href="../public/css/login_style.css?v=<?php echo time(); ?>">
</head>
<body class="<?php echo $themeClass; ?>">

<div class="wrapper">

    <!-- LEFT: wave / welcome / features -->
    <aside class="brand-panel">
        <svg class="wave" viewBox="0 0 300 900" preserveAspectRatio="none" aria-hidden="true">
            <defs>
                <linearGradient id="waveLight" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0" stop-color="#3A9191"/>
                    <stop offset="1" stop-color="#2F8585"/>
                </linearGradient>
            </defs>
            <path d="M300 0 H120 C170 140 160 260 90 380 C20 500 60 640 130 720 C175 775 200 830 190 900 H300 Z" fill="#17233A"/>
            <path d="M300 0 H150 C195 130 185 250 120 365 C55 480 90 620 150 700 C190 755 215 825 205 900 H300 Z" fill="url(#waveLight)"/>
        </svg>

        <div class="welcome">
            <p class="welcome-small">Welcome back!</p>
            <h2 class="welcome-big">Good to see you<br>Again!</h2>
        </div>

        <div class="features">
            <div class="feature">
                <div class="feature-icon"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i></div>
                <div>
                    <div class="feature-title">Reservations</div>
                    <div class="feature-desc">Book rooms in a few clicks</div>
                </div>
            </div>
            <div class="feature">
                <div class="feature-icon"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i></div>
                <div>
                    <div class="feature-title">Security</div>
                    <div class="feature-desc">Protected school accounts</div>
                </div>
            </div>
            <div class="feature">
                <div class="feature-icon"><i class="fa-solid fa-bolt" aria-hidden="true"></i></div>
                <div>
                    <div class="feature-title">Speed</div>
                    <div class="feature-desc">Real-time room availability</div>
                </div>
            </div>
        </div>
    </aside>

    <!-- RIGHT: login form -->
    <main class="form-panel">
        <div class="form-card">
            <div class="logo-badge">
                <img src="../public/images/logo.png" alt="ClassSpace">
            </div>

            <h2 class="form-title">Login to your account</h2>
            <p class="form-subtitle">Sign in to access ClassSpace.</p>
            <div id="error-msg" role="alert" aria-live="polite"></div>

            <form id="loginForm">
                <div class="form-group">
                    <label for="email" class="field-label">Email or Username</label>
                    <div class="input-wrapper">
                        <i class="fa-regular fa-envelope input-icon" aria-hidden="true"></i>
                        <input type="text" id="email" name="email" autocomplete="username" placeholder="Enter your email or username" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password" class="field-label">Password</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-lock input-icon" aria-hidden="true"></i>
                        <input type="password" id="password" name="upass" autocomplete="current-password" placeholder="Enter your password" required>
                        <button type="button" class="eye-btn" id="password-toggle-login"
                                aria-label="Show password" aria-pressed="false">
                            <i id="password-eye-login" class="fa-solid fa-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

                <div class="form-row">
                    <label class="checkbox-label">
                        <input type="checkbox" id="remember" name="remember"> Remember Me
                    </label>
                </div>
                <button type="submit" id="signin-btn" class="btn-signin">Sign In</button>
            </form>

            <p class="signup-text">
                Need an account?
                <button type="button" class="signup-link contact-admin-link"
                        aria-controls="contact-guidance">
                    Contact the ICT Administrator
                </button>
            </p>
            <div id="contact-guidance" class="contact-guidance" hidden role="status">
                Please contact your school’s ICT Department through its official channel or in person to request an account.
                <button type="button" class="contact-guidance-close"
                        aria-label="Close ICT Administrator guidance">&times;</button>
            </div>
        </div>

        <p class="compliance-footer">
            ClassSpace &middot; Aligned with RA 10173 (Data Privacy Act) and RA 8792 (E-Commerce Act) &middot; SDG 9
        </p>
    </main>
</div>


<!-- Register modal kept in markup for backend/JS compatibility, but no
     longer reachable from the Login page itself (see signup-text above). -->
<div id="register-modal" class="modal-overlay">
    <div class="modal-card modal-register-card">
        <button class="modal-close">&times;</button>

        <h3>Create Account</h3>
        <p>Join ClassSpace to manage classrooms</p>

        <div class="register-name-grid">
            <div class="input-wrapper">
                <input type="text" id="reg-fname" placeholder="First name">
            </div>

            <div class="input-wrapper">
                <input type="text" id="reg-lname" placeholder="Last name">
            </div>
        </div>

        <div class="input-wrapper input-margin-top">
            <svg class="input-icon" width="17" height="17" viewBox="0 0 24 24" fill="none">
                <path fill-rule="evenodd" clip-rule="evenodd" d="M3.75 5.25L3 6V18L3.75 18.75H20.25L21 18V6L20.25 5.25H3.75ZM4.5 7.7V17.25H19.5V7.7L12 14.51L4.5 7.7ZM18.31 6.75H5.69L12 12.49L18.31 6.75Z" fill="currentColor"/>
            </svg>
            <input type="email" id="reg-email" placeholder="Email address">
        </div>

        <div class="input-wrapper input-margin-top">
            <input type="text" id="reg-dept" placeholder="Username">
        </div>

        <div class="input-wrapper input-margin-top">
            <svg class="input-icon" width="17" height="17" viewBox="0 0 24 24" fill="none">
                <path d="M12 14.5V16.5M7 10V8C7 5.24 9.24 3 12 3s5 2.24 5 5v2M7 10c-.83.05-1.42.18-1.86.4A4 4 0 0 0 3.4 12.2C3 13 3 14 3 15.2v1.6c0 1.68 0 2.52.33 3.16a4 4 0 0 0 1.7 1.7C5.67 22 6.6 22 8.8 22h6.4c1.68 0 2.52 0 3.16-.34a4 4 0 0 0 1.7-1.7C20 19.32 20 18.48 20 16.8v-1.6c0-1.2 0-2-.33-2.8a4 4 0 0 0-1.7-1.8C17.57 10.18 17 10.05 16 10M7 10h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            </svg>
            <input type="password" id="reg-pass" placeholder="Password (min. 8 chars)">
            <button type="button" class="eye-btn" aria-label="Toggle password visibility">
                <svg id="eye-open-reg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                </svg>
                <svg id="eye-closed-reg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                    <line x1="1" y1="1" x2="23" y2="23"></line>
                </svg>
            </button>
        </div>

        <div class="input-wrapper input-margin-top">
            <svg class="input-icon" width="17" height="17" viewBox="0 0 24 24" fill="none">
                <path d="M12 14.5V16.5M7 10V8C7 5.24 9.24 3 12 3s5 2.24 5 5v2M7 10c-.83.05-1.42.18-1.86.4A4 4 0 0 0 3.4 12.2C3 13 3 14 3 15.2v1.6c0 1.68 0 2.52.33 3.16a4 4 0 0 0 1.7 1.7C5.67 22 6.6 22 8.8 22h6.4c1.68 0 2.52 0 3.16-.34a4 4 0 0 0 1.7-1.7C20 19.32 20 18.48 20 16.8v-1.6c0-1.2 0-2-.33-2.8a4 4 0 0 0-1.7-1.8C17.57 10.18 17 10.05 16 10M7 10h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            </svg>
            <input type="password" id="reg-confirm" placeholder="Confirm password">
            <button type="button" class="eye-btn" aria-label="Toggle confirm password visibility">
                <svg id="eye-open-confirm" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                </svg>
                <svg id="eye-closed-confirm" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                    <line x1="1" y1="1" x2="23" y2="23"></line>
                </svg>
            </button>
        </div>

        <div id="reg-error" class="register-error"></div>

        <button class="btn-signin register-btn-spacing" id="register-btn">
            CREATE ACCOUNT
        </button>
    </div>
</div>

<script src="../public/js/toast.js?v=<?php echo time(); ?>"></script>
<script src="../public/js/login_script.js?v=<?php echo time(); ?>"></script>
</body>
</html>