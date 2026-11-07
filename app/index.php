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

<div class="split-layout">
    <!-- LEFT: BRAND SHOWCASE -->
    <aside class="brand-panel">
        <div class="brand-content-wrapper">
            <header class="brand-header">
                <div class="brand-logo-group">
                    <img src="../public/images/logo.png" alt="ClassSpace Logo">
                    <span class="brand-logo-title">ClassSpace</span>
                </div>
            </header>

            <div class="brand-hero">
                <span class="hero-tag">INSTITUTIONAL SUITE</span>
                <h1>Intelligent Classroom &amp; Facility Allocation</h1>
                <p>Streamlined room reservations, automated schedule conflict detection, and real-time campus facility analytics.</p>

                <div class="features-grid">
                    <div class="feature-card">
                        <div class="feature-icon-box">
                            <i class="fa-solid fa-bolt-lightning" aria-hidden="true"></i>
                        </div>
                        <div class="feature-content">
                            <h4>Real-Time Conflict Prevention</h4>
                            <p>Instant schedule validation prevents double bookings across all university buildings.</p>
                        </div>
                    </div>

                    <div class="feature-card">
                        <div class="feature-icon-box">
                            <i class="fa-regular fa-calendar-check" aria-hidden="true"></i>
                        </div>
                        <div class="feature-content">
                            <h4>Unified Schedule Calendar</h4>
                            <p>Transparent schedule overview for faculty, department chairs, and student councils.</p>
                        </div>
                    </div>

                    <div class="feature-card">
                        <div class="feature-icon-box">
                            <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                        </div>
                        <div class="feature-content">
                            <h4>Secured Role-Based Access</h4>
                            <p>Protected sign-in strictly adhering to Philippine Data Privacy Act standards.</p>
                        </div>
                    </div>
                </div>
            </div>

            <footer class="brand-footer">
                <span>ClassSpace v2.4</span>
            </footer>
        </div>
    </aside>

    <!-- RIGHT: AUTHENTICATION PANEL -->
    <main class="form-panel">
        <div class="form-container">
            <div class="form-header">
                <img class="mobile-logo" src="../public/images/logo.png" alt="ClassSpace">
                <h2 class="form-title">Welcome back</h2>
                <p class="form-subtitle">Enter your institutional credentials to continue</p>
            </div>

            <div id="error-msg" role="alert" aria-live="polite"></div>

            <form id="loginForm">
                <div class="form-group">
                    <label for="email" class="field-label">Email or Username</label>
                    <div class="input-wrapper">
                        <i class="fa-regular fa-envelope input-icon" aria-hidden="true"></i>
                        <input type="text" id="email" name="email" autocomplete="username" placeholder="name@school.edu.ph or username" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password" class="field-label">Password</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-lock input-icon" aria-hidden="true"></i>
                        <input type="password" id="password" name="upass" autocomplete="current-password" placeholder="••••••••••••" required>
                        <button type="button" class="eye-btn" id="password-toggle-login" aria-label="Show password" aria-pressed="false">
                            <i id="password-eye-login" class="fa-regular fa-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

                <div class="form-options">
                    <label class="checkbox-container">
                        <input type="checkbox" id="remember" name="remember"> Remember me
                    </label>
                </div>

                <button type="submit" id="signin-btn" class="btn-submit">
                    <span>Sign In</span>
                    <i class="fa-solid fa-arrow-right" style="font-size: 12px;" aria-hidden="true"></i>
                </button>
            </form>

            <div class="forgot-section">
                <span>Forgot password?</span>
                <button type="button" class="signup-link contact-admin-link" aria-controls="contact-guidance" aria-expanded="false">
                    Contact ICT Administrator
                </button>
                <div id="contact-guidance" class="contact-guidance" hidden role="status">
                    <i class="fa-solid fa-circle-info" style="color: var(--primary-hover); margin-right: 4px;" aria-hidden="true"></i>
                    Please contact your school’s ICT Department through its official channel or in person to reset your password or verify account status.
                    <button type="button" class="contact-guidance-close" aria-label="Close ICT Administrator guidance">&times;</button>
                </div>
            </div>

            <p class="compliance-footer">
                ClassSpace &middot; Aligned with RA 10173 (Data Privacy Act) and RA 8792 &middot; SDG 9
            </p>
        </div>
    </main>
</div>

<!-- REGISTRATION MODAL -->
<div id="register-modal" class="modal-overlay">
    <div class="modal-card modal-register-card">
        <button class="modal-close" aria-label="Close modal">&times;</button>

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
            <i class="fa-regular fa-envelope input-icon" aria-hidden="true"></i>
            <input type="email" id="reg-email" placeholder="Email address">
        </div>

        <div class="input-wrapper input-margin-top">
            <i class="fa-regular fa-user input-icon" aria-hidden="true"></i>
            <input type="text" id="reg-dept" placeholder="Username">
        </div>

        <div class="input-wrapper input-margin-top">
            <i class="fa-solid fa-lock input-icon" aria-hidden="true"></i>
            <input type="password" id="reg-pass" placeholder="Password (min. 8 chars)">
            <button type="button" class="eye-btn" aria-label="Toggle password visibility">
                <i id="eye-open-reg" class="fa-regular fa-eye" aria-hidden="true"></i>
                <i id="eye-closed-reg" class="fa-regular fa-eye-slash" aria-hidden="true"></i>
            </button>
        </div>

        <div class="input-wrapper input-margin-top">
            <i class="fa-solid fa-lock input-icon" aria-hidden="true"></i>
            <input type="password" id="reg-confirm" placeholder="Confirm password">
            <button type="button" class="eye-btn" aria-label="Toggle confirm password visibility">
                <i id="eye-open-confirm" class="fa-regular fa-eye" aria-hidden="true"></i>
                <i id="eye-closed-confirm" class="fa-regular fa-eye-slash" aria-hidden="true"></i>
            </button>
        </div>

        <div id="reg-error" class="register-error"></div>

        <button class="btn-submit register-btn-spacing" id="register-btn">
            CREATE ACCOUNT
        </button>
    </div>
</div>

<script src="../public/js/toast.js?v=<?php echo time(); ?>"></script>
<script src="../public/js/login_script.js?v=<?php echo time(); ?>"></script>
</body>
</html>
