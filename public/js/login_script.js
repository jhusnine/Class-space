/*
 * ClassSpace Login Script
 *
 * Login-only behavior:
 * - Preserves the existing global theme cookie when a theme control exists.
 * - Keeps password visibility controls accessible.
 * - Preserves login controller payload and redirect behavior.
 * - Preserves registration compatibility hooks.
 * - Does not create or enable a Login-page theme toggle.
 */

(function () {
    'use strict';

    function get(id) {
        return document.getElementById(id);
    }

    function setText(element, value) {
        if (element) {
            element.textContent = value;
        }
    }

    function setButtonState(button, label, disabled) {
        if (!button) {
            return;
        }

        button.textContent = label;
        button.disabled = disabled;
    }

    function setThemeCookie(theme) {
        var expires = new Date();
        expires.setTime(expires.getTime() + (30 * 24 * 60 * 60 * 1000));

        document.cookie = [
            'theme=' + encodeURIComponent(theme),
            'expires=' + expires.toUTCString(),
            'path=/',
            'SameSite=Lax'
        ].join('; ');
    }

    function updateThemeButton(button, isLightMode) {
        if (!button) {
            return;
        }

        button.setAttribute('aria-pressed', String(isLightMode));
        button.setAttribute(
            'aria-label',
            isLightMode ? 'Switch to dark mode' : 'Switch to light mode'
        );
        button.setAttribute(
            'title',
            isLightMode ? 'Switch to dark mode' : 'Switch to light mode'
        );

        /* Uses the existing Font Awesome icon system; no emoji. */
        button.innerHTML = isLightMode
            ? '<i class="fa-solid fa-moon" aria-hidden="true"></i>'
            : '<i class="fa-solid fa-sun" aria-hidden="true"></i>';
    }

    function initGlobalThemeControl() {
        var themeToggleButton = get('theme-toggle');

        /* The Login page intentionally has no theme button. */
        if (!themeToggleButton) {
            return;
        }

        themeToggleButton.addEventListener('click', function () {
            var isLightMode = document.body.classList.toggle('light-mode');

            updateThemeButton(themeToggleButton, isLightMode);
            setThemeCookie(isLightMode ? 'light' : 'dark');
        });

        updateThemeButton(
            themeToggleButton,
            document.body.classList.contains('light-mode')
        );
    }

    function updatePasswordControl(input, openIcon, closedIcon, button) {
        var isVisible = input.type === 'text';

        input.type = isVisible ? 'password' : 'text';

        if (openIcon) {
            openIcon.style.display = isVisible ? 'block' : 'none';
        }

        if (closedIcon) {
            closedIcon.style.display = isVisible ? 'none' : 'block';
        }

        if (button) {
            button.setAttribute('aria-pressed', String(!isVisible));
            button.setAttribute(
                'aria-label',
                isVisible ? 'Show password' : 'Hide password'
            );
        }
    }

    /* Kept globally available because index.php uses inline onclick hooks. */
    window.togglePassword = function (inputId, iconOrOpenId, closedIconId) {
        var input = get(inputId);
        var iconOrOpen = get(iconOrOpenId);
        var closedIcon = closedIconId ? get(closedIconId) : null;
        var button = input ? input.closest('.eye-btn') : null;

        if (!input) {
            return;
        }

        var isVisible = input.type === 'text';
        input.type = isVisible ? 'password' : 'text';

        if (closedIcon && iconOrOpen) {
            iconOrOpen.style.display = isVisible ? 'block' : 'none';
            closedIcon.style.display = isVisible ? 'none' : 'block';
        } else if (iconOrOpen) {
            iconOrOpen.classList.toggle('fa-eye', isVisible);
            iconOrOpen.classList.toggle('fa-eye-slash', !isVisible);
        }

        if (button) {
            button.setAttribute('aria-pressed', String(!isVisible));
            button.setAttribute('aria-label', isVisible ? 'Show password' : 'Hide password');
        }

        input.focus();
    };

    window.showContactGuidance = function () {
        var guidance = get('contact-guidance');
        var trigger = document.querySelector('.contact-admin-link');

        if (guidance) {
            guidance.hidden = false;
        }

        if (trigger) {
            trigger.setAttribute('aria-expanded', 'true');
        }
    };

    window.hideContactGuidance = function () {
        var guidance = get('contact-guidance');
        var trigger = document.querySelector('.contact-admin-link');

        if (guidance) {
            guidance.hidden = true;
        }

        if (trigger) {
            trigger.setAttribute('aria-expanded', 'false');
            trigger.focus();
        }
    };

    window.handleSignIn = function (event) {
        if (event) {
            event.preventDefault();
        }

        var emailInput = get('email');
        var passwordInput = get('password');
        var errorElement = get('error-msg');
        var signInButton = get('signin-btn');

        var email = emailInput ? emailInput.value.trim() : '';
        var password = passwordInput ? passwordInput.value : '';

        setText(errorElement, '');

        if (!email || !password) {
            setText(errorElement, 'Please fill in all fields.');
            return false;
        }

        setButtonState(signInButton, 'SIGNING IN...', true);

        var requestBody = new URLSearchParams({
            email: email,
            upass: password
        });

        fetch('controllers/login_controller.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
            },
            body: requestBody.toString()
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Login request failed.');
                }

                return response.json();
            })
            .then(function (data) {
                setButtonState(signInButton, 'SIGN IN', false);

                if (data && data.success) {
                    window.location.href = Number(data.isadmin) === 1
                        ? 'views/admin_homepage_view.php'
                        : 'views/user_homepage_view.php';
                    return;
                }

                var messages = {
                    wrong_email: 'No account found with this email.',
                    wrong_password: 'Incorrect password.',
                    missing_fields: 'Please fill in all fields.'
                };

                setText(
                    errorElement,
                    messages[data && data.error] || 'Login failed. Please try again.'
                );
            })
            .catch(function () {
                setButtonState(signInButton, 'SIGN IN', false);
                setText(errorElement, 'Server error. Please try again.');
            });

        return false;
    };

    /* Kept for backend/JS compatibility; registration remains unreachable
       unless the existing page explicitly exposes a registration trigger. */
    window.openRegister = function (event) {
        if (event) {
            event.preventDefault();
        }

        var modal = get('register-modal');

        if (modal) {
            modal.style.display = 'flex';
        }
    };

    window.closeRegister = function () {
        var modal = get('register-modal');
        var errorElement = get('reg-error');
        var passwordInput = get('reg-pass');
        var confirmInput = get('reg-confirm');

        if (modal) {
            modal.style.display = 'none';
        }

        setText(errorElement, '');

        if (passwordInput) {
            passwordInput.type = 'password';
        }

        if (confirmInput) {
            confirmInput.type = 'password';
        }
    };

    window.handleRegister = function () {
        var firstNameInput = get('reg-fname');
        var lastNameInput = get('reg-lname');
        var emailInput = get('reg-email');
        var usernameInput = get('reg-dept');
        var passwordInput = get('reg-pass');
        var confirmInput = get('reg-confirm');
        var errorElement = get('reg-error');
        var registerButton = get('register-btn');

        var firstName = firstNameInput ? firstNameInput.value.trim() : '';
        var lastName = lastNameInput ? lastNameInput.value.trim() : '';
        var email = emailInput ? emailInput.value.trim() : '';
        var username = usernameInput ? usernameInput.value.trim() : '';
        var password = passwordInput ? passwordInput.value : '';
        var confirmation = confirmInput ? confirmInput.value : '';

        setText(errorElement, '');

        if (!firstName || !lastName || !email || !password) {
            setText(errorElement, 'Please fill in all required fields.');
            return;
        }

        if (password.length < 8) {
            setText(errorElement, 'Password must be at least 8 characters.');
            return;
        }

        if (password !== confirmation) {
            setText(errorElement, 'Passwords do not match.');
            return;
        }

        setButtonState(registerButton, 'CREATING...', true);

        var requestBody = new URLSearchParams({
            fname: firstName,
            lname: lastName,
            email: email,
            password: password,
            username: username
        });

        fetch('controllers/register_controller.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
            },
            body: requestBody.toString()
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Registration request failed.');
                }

                return response.json();
            })
            .then(function (data) {
                setButtonState(registerButton, 'CREATE ACCOUNT', false);

                if (data && data.success) {
                    window.closeRegister();

                    if (emailInput) {
                        emailInput.value = email;
                    }

                    if (typeof window.toast !== 'undefined' && window.toast.show) {
                        window.toast.show(
                            'Account created! You can now sign in.',
                            'success',
                            3000
                        );
                    }
                    return;
                }

                setText(
                    errorElement,
                    data && data.error === 'email_taken'
                        ? 'This email is already registered.'
                        : 'Registration failed. Please try again.'
                );
            })
            .catch(function () {
                setButtonState(registerButton, 'CREATE ACCOUNT', false);
                setText(errorElement, 'Server error. Please try again.');
            });
    };

    function initLoginBehavior() {
        var loginForm = get('loginForm');
        var passwordButton = get('password-toggle-login');
        var contactButton = document.querySelector('.contact-admin-link');
        var contactCloseButton = document.querySelector('.contact-guidance-close');
        var registerModal = get('register-modal');
        var registerCloseButton = registerModal
            ? registerModal.querySelector('.modal-close')
            : null;
        var registerPasswordButton = get('reg-pass')
            ? get('reg-pass').closest('.eye-btn')
            : null;
        var registerConfirmButton = get('reg-confirm')
            ? get('reg-confirm').closest('.eye-btn')
            : null;
        var registerButton = get('register-btn');

        if (loginForm) {
            loginForm.addEventListener('submit', window.handleSignIn);
        }

        if (passwordButton) {
            passwordButton.addEventListener('click', function () {
                window.togglePassword('password', 'password-eye-login');
            });
        }

        if (contactButton) {
            contactButton.addEventListener('click', window.showContactGuidance);
        }

        if (contactCloseButton) {
            contactCloseButton.addEventListener('click', window.hideContactGuidance);
        }

        if (registerCloseButton) {
            registerCloseButton.addEventListener('click', window.closeRegister);
        }

        if (registerPasswordButton) {
            registerPasswordButton.addEventListener('click', function () {
                window.togglePassword('reg-pass', 'eye-open-reg', 'eye-closed-reg');
            });
        }

        if (registerConfirmButton) {
            registerConfirmButton.addEventListener('click', function () {
                window.togglePassword('reg-confirm', 'eye-open-confirm', 'eye-closed-confirm');
            });
        }

        if (registerButton) {
            registerButton.addEventListener('click', window.handleRegister);
        }

        if (registerModal) {
            registerModal.addEventListener('click', function (event) {
                if (event.target === registerModal) {
                    window.closeRegister();
                }
            });
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && contactButton) {
                var guidance = get('contact-guidance');
                if (guidance && !guidance.hidden) {
                    window.hideContactGuidance();
                }
            }

            if (event.key === 'Escape' && registerModal && registerModal.style.display === 'flex') {
                window.closeRegister();
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initGlobalThemeControl();
        initLoginBehavior();
    });
})();
