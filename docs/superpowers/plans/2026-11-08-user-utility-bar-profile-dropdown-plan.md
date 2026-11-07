# User Utility Bar Partial & Profile Dropdown Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Extract the repeated user utility bar into a reusable partial `app/views/partials/user_utility_bar.php`, add an accessible top-right profile dropdown menu containing user details, navigation links, and logout, and completely remove the bottom profile card and logout from `app/views/partials/user_sidebar.php`.

**Architecture:** 
- The user utility bar is extracted from 7 consumer views into `app/views/partials/user_utility_bar.php`.
- The top-right profile card is upgraded to a dropdown trigger with an accessible menu (`#profile-dropdown`) containing avatar, full name, email, role, profile links, settings link, about link, and logout.
- Interactivity (toggle, outside click, Escape key, mutual exclusion with notification dropdown) is implemented in `public/js/toast.js`.
- Dropdown styling with responsive viewport handling is added to `public/css/global.css`.
- The sidebar bottom container (`.sidebar-bottom`, `.sidebar-profile-card`, and `.sidebar-footer`) is removed from `app/views/partials/user_sidebar.php`.

**Tech Stack:** PHP 8.2, Vanilla JavaScript (ES6+), CSS3 with CSS Custom Properties, FontAwesome 6.4.

## Global Constraints
- Must sanitize all dynamic user strings via `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
- Empty strings for `$utilityFname` must fall back cleanly to `'User'`.
- No inline styles; all presentation uses design tokens from `global.css`.
- Both `test_sidebar_and_header_consistency.php` and `test_sidebar_consistency.php` must pass with exit code `0`.
- Mutual exclusion: opening profile dropdown must close notification dropdown and vice versa.

---

### Task 1: Create `app/views/partials/user_utility_bar.php`

**Files:**
- Create: `app/views/partials/user_utility_bar.php`
- Test: `tests/test_sidebar_and_header_consistency.php`

**Interfaces:**
- Consumes: `$_SESSION['fname']`, `$_SESSION['lname']`, `$_SESSION['email']`, `$_SESSION['role']`, `$_SERVER['PHP_SELF']`.
- Produces: HTML component `<nav class="user-utility-bar" aria-label="Account shortcuts">` containing:
  - `.utility-icon-link[href="contact_view.php"]`
  - `.utility-icon-link[href="notifications_view.php"]` with `#notif-badge`
  - `.utility-profile-wrapper` with `#profile-dropdown-trigger` button and `#profile-dropdown` menu.

- [ ] **Step 1: Write failing test assertions in `tests/test_sidebar_and_header_consistency.php`**

Add checks verifying `app/views/partials/user_utility_bar.php` exists, lints cleanly, and includes `#profile-dropdown-trigger` and `#profile-dropdown`:

```php
// In tests/test_sidebar_and_header_consistency.php:
$utilityPartial = $viewsDir . '/partials/user_utility_bar.php';
if (!file_exists($utilityPartial)) {
    $failures[] = "Utility bar partial not found at: $utilityPartial";
} else {
    exec("php -l " . escapeshellarg($utilityPartial) . " 2>&1", $lintOutput, $lintCode);
    if ($lintCode !== 0) {
        $failures[] = "Utility bar partial has PHP syntax errors:\n" . implode("\n", $lintOutput);
    }
    $utilContent = file_get_contents($utilityPartial);
    if (!str_contains($utilContent, 'id="profile-dropdown-trigger"')) {
        $failures[] = "Utility bar partial missing #profile-dropdown-trigger.";
    }
    if (!str_contains($utilContent, 'id="profile-dropdown"')) {
        $failures[] = "Utility bar partial missing #profile-dropdown.";
    }
    if (!str_contains($utilContent, 'href="profile_view.php"')) {
        $failures[] = "Profile dropdown missing link to profile_view.php.";
    }
    if (!str_contains($utilContent, 'href="settings_view.php"')) {
        $failures[] = "Profile dropdown missing link to settings_view.php.";
    }
    if (!str_contains($utilContent, 'href="logout.php"')) {
        $failures[] = "Profile dropdown missing link to logout.php.";
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php C:\xampp\htdocs\classspace\tests\test_sidebar_and_header_consistency.php`
Expected: FAILED with "Utility bar partial not found"

- [ ] **Step 3: Create `app/views/partials/user_utility_bar.php`**

```php
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
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php C:\xampp\htdocs\classspace\tests\test_sidebar_and_header_consistency.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git -C "C:\xampp\htdocs\classspace" add app/views/partials/user_utility_bar.php tests/test_sidebar_and_header_consistency.php
git -C "C:\xampp\htdocs\classspace" commit -m "feat(ui): create user utility bar partial with profile dropdown markup"
```

---

### Task 2: Style Profile Dropdown in `public/css/global.css`

**Files:**
- Modify: `public/css/global.css`
- Test: Visual & Browser test

**Interfaces:**
- Produces: CSS rules for `.utility-profile-wrapper`, `.utility-profile-caret`, `.profile-dropdown`, `.profile-dropdown-header`, `.profile-dropdown-avatar`, `.profile-dropdown-user`, `.profile-dropdown-name`, `.profile-dropdown-email`, `.profile-dropdown-role`, `.profile-dropdown-menu`, `.profile-dropdown-item`, `.profile-dropdown-divider`, and `.item-logout`.

- [ ] **Step 1: Add profile dropdown CSS rules to `public/css/global.css`**

Directly under line 700 (near `.notification-dropdown`), add:

```css
/* Utility Profile Wrapper & Dropdown */
.utility-profile-wrapper {
    position: relative;
    display: inline-flex;
}

.utility-profile-link {
    cursor: pointer;
    background: transparent;
    border: 1px solid transparent;
    font-family: inherit;
    text-align: left;
}

.utility-profile-caret {
    font-size: 10px;
    color: var(--text-low);
    transition: transform 0.2s ease, color 0.2s ease;
    margin-left: 2px;
}

.utility-profile-link[aria-expanded="true"] .utility-profile-caret {
    transform: rotate(180deg);
    color: var(--brand-accent);
}

.profile-dropdown {
    position: absolute;
    top: calc(100% + 8px);
    right: 0;
    z-index: 120;
    display: flex;
    flex-direction: column;
    width: min(290px, calc(100vw - 24px));
    border: 1px solid var(--border-color);
    border-radius: 14px;
    background: var(--bg-surface);
    color: var(--text-high);
    box-shadow: 0 16px 40px rgba(0, 0, 0, 0.24);
    overflow: hidden;
    animation: dropdownFadeIn 0.15s ease-out;
}

.profile-dropdown[hidden] {
    display: none !important;
}

@keyframes dropdownFadeIn {
    from {
        opacity: 0;
        transform: translateY(-4px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.profile-dropdown-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
    border-bottom: 1px solid var(--border-color);
    background: color-mix(in srgb, var(--brand-accent) 6%, transparent);
}

.profile-dropdown-avatar {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--brand-accent) 0%, #0ea5e9 100%);
    color: #fff;
    display: grid;
    place-items: center;
    font-weight: 700;
    font-size: 14px;
    flex-shrink: 0;
}

.profile-dropdown-user {
    display: flex;
    flex-direction: column;
    min-width: 0;
    gap: 2px;
}

.profile-dropdown-name {
    font-size: 13px;
    font-weight: 700;
    color: var(--text-high);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.profile-dropdown-email {
    font-size: 11px;
    color: var(--text-low);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.profile-dropdown-role {
    display: inline-block;
    align-self: flex-start;
    padding: 1px 7px;
    margin-top: 3px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    background: color-mix(in srgb, var(--brand-accent) 18%, transparent);
    color: var(--brand-accent);
    border: 1px solid color-mix(in srgb, var(--brand-accent) 30%, transparent);
}

.profile-dropdown-menu {
    list-style: none;
    margin: 0;
    padding: 6px 0;
}

.profile-dropdown-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 16px;
    font-size: 13px;
    font-weight: 500;
    color: var(--text-high);
    text-decoration: none;
    transition: background 0.15s ease, color 0.15s ease;
}

.profile-dropdown-item i {
    width: 16px;
    font-size: 13px;
    color: var(--text-low);
    text-align: center;
    transition: color 0.15s ease;
}

.profile-dropdown-item:hover,
.profile-dropdown-item.is-active {
    background: var(--bg-nav-hover);
    color: var(--text-high);
}

.profile-dropdown-item:hover i,
.profile-dropdown-item.is-active i {
    color: var(--brand-accent);
}

.profile-dropdown-divider {
    height: 1px;
    background: var(--border-color);
    margin: 6px 0;
}

.profile-dropdown-item.item-logout {
    color: #ef4444;
}

.profile-dropdown-item.item-logout i {
    color: #ef4444;
}

.profile-dropdown-item.item-logout:hover {
    background: color-mix(in srgb, #ef4444 10%, transparent);
    color: #dc2626;
}

.profile-dropdown-item.item-logout:hover i {
    color: #dc2626;
}
```

- [ ] **Step 2: Commit CSS changes**

```bash
git -C "C:\xampp\htdocs\classspace" add public/css/global.css
git -C "C:\xampp\htdocs\classspace" commit -m "feat(ui): add profile dropdown styles in global.css"
```

---

### Task 3: Implement Interactivity in `public/js/toast.js`

**Files:**
- Modify: `public/js/toast.js`

**Interfaces:**
- Produces: `initProfileDropdown()` function, automatically invoked on page load, handling click toggle, closing on click outside, closing on Escape, and closing notification dropdown when opened.

- [ ] **Step 1: Add `initProfileDropdown()` to `public/js/toast.js`**

```javascript
function initProfileDropdown() {
    const trigger = document.getElementById('profile-dropdown-trigger');
    const panel = document.getElementById('profile-dropdown');
    if (!trigger || !panel) return;

    const setOpen = (isOpen) => {
        panel.hidden = !isOpen;
        trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        if (isOpen) {
            // Close notification dropdown if open
            const notifPanel = document.getElementById('notification-dropdown');
            const notifTrigger = document.querySelector('.user-utility-bar .utility-icon-link[href="notifications_view.php"]');
            if (notifPanel && !notifPanel.hidden) {
                notifPanel.hidden = true;
                if (notifTrigger) notifTrigger.setAttribute('aria-expanded', 'false');
            }
        }
    };

    trigger.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        setOpen(panel.hidden);
    });

    panel.addEventListener('click', (event) => {
        // If clicking a link in the dropdown, let navigation proceed
        if (event.target.closest('a')) {
            setOpen(false);
        }
    });

    document.addEventListener('click', (event) => {
        if (!panel.hidden && !panel.contains(event.target) && !trigger.contains(event.target)) {
            setOpen(false);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !panel.hidden) {
            setOpen(false);
            trigger.focus();
        }
    });
}
```

Also update `initNotificationDropdown()` in `toast.js` so opening notifications closes profile dropdown:
```javascript
// Inside setOpen in initNotificationDropdown:
if (isOpen) {
    const profilePanel = document.getElementById('profile-dropdown');
    const profileTrigger = document.getElementById('profile-dropdown-trigger');
    if (profilePanel && !profilePanel.hidden) {
        profilePanel.hidden = true;
        if (profileTrigger) profileTrigger.setAttribute('aria-expanded', 'false');
    }
}
```

And initialize in `DOMContentLoaded`:
```javascript
if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => {
        initSharedMobileMenu();
        initNotificationDropdown();
        initProfileDropdown();
    });
} else {
    initSharedMobileMenu();
    initNotificationDropdown();
    initProfileDropdown();
}
```

- [ ] **Step 2: Commit JS updates**

```bash
git -C "C:\xampp\htdocs\classspace" add public/js/toast.js
git -C "C:\xampp\htdocs\classspace" commit -m "feat(ui): add initProfileDropdown to toast.js"
```

---

### Task 4: Clean Sidebar Partial `app/views/partials/user_sidebar.php` & Update Consistency Tests

**Files:**
- Modify: `app/views/partials/user_sidebar.php`
- Modify: `tests/test_sidebar_consistency.php`
- Modify: `tests/test_sidebar_and_header_consistency.php`

**Interfaces:**
- Produces: `user_sidebar.php` with only brand header and nav items (no `.sidebar-bottom`, `.sidebar-profile-card`, or `.sidebar-footer`).

- [ ] **Step 1: Remove `.sidebar-bottom` from `app/views/partials/user_sidebar.php`**

Delete lines 88-105:
```php
    <div class="sidebar-bottom">
        <div class="sidebar-profile-card">
...
    </div>
```
Also clean unused `$rawFname`, `$rawLname`, `$sidebarFname`, `$sidebarLname`, `$sidebarFullName`, `$sidebarInitial` from the top of `user_sidebar.php` since they are now exclusively used by `user_utility_bar.php`.

- [ ] **Step 2: Update `test_sidebar_and_header_consistency.php`**

Add assertion that `user_sidebar.php` does NOT contain `sidebar-bottom` or `sidebar-profile-card`:
```php
if (str_contains($partialContent, 'sidebar-profile-card') || str_contains($partialContent, 'sidebar-bottom')) {
    $failures[] = "Sidebar partial should NOT contain .sidebar-profile-card or .sidebar-bottom.";
}
```

- [ ] **Step 3: Update `test_sidebar_consistency.php`**

Remove assertions looking for `sidebar-bottom`, `sidebar-profile-card`, `user-avatar-circle`, `sidebar-profile-logout`, and `footer route links` from the sidebar consistency test.

- [ ] **Step 4: Run consistency test**

Run: `php C:\xampp\htdocs\classspace\tests\test_sidebar_and_header_consistency.php`
Run: `php C:\xampp\htdocs\classspace\tests\test_sidebar_consistency.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git -C "C:\xampp\htdocs\classspace" add app/views/partials/user_sidebar.php tests/test_sidebar_consistency.php tests/test_sidebar_and_header_consistency.php
git -C "C:\xampp\htdocs\classspace" commit -m "refactor(ui): remove profile card and bottom footer from sidebar partial"
```

---

### Task 5: Integrate `user_utility_bar.php` across all 7 Consumer Views

**Files:**
- Modify: `app/views/user_homepage_view.php`
- Modify: `app/views/calendar_view.php`
- Modify: `app/views/reserve_view.php`
- Modify: `app/views/settings_view.php`
- Modify: `app/views/contact_view.php`
- Modify: `app/views/notifications_view.php`
- Modify: `app/views/profile_view.php`
- Modify: `tests/test_sidebar_and_header_consistency.php`

- [ ] **Step 1: Add assertion in `test_sidebar_and_header_consistency.php`**

Assert that all 7 views include `partials/user_utility_bar.php`:
```php
if (!str_contains($content, 'partials/user_utility_bar.php')) {
    $failures[] = "View $viewFile does not include partials/user_utility_bar.php";
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php C:\xampp\htdocs\classspace\tests\test_sidebar_and_header_consistency.php`
Expected: FAILED with "View user_homepage_view.php does not include partials/user_utility_bar.php"

- [ ] **Step 3: Replace inline `<nav class="user-utility-bar">` in all 7 views with partial include**

Replace the 15-25 lines of inline `<nav class="user-utility-bar">` and its `$utility...` PHP variables in:
- `app/views/user_homepage_view.php`
- `app/views/calendar_view.php`
- `app/views/reserve_view.php`
- `app/views/settings_view.php`
- `app/views/contact_view.php`
- `app/views/notifications_view.php`
- `app/views/profile_view.php`

with:
```php
<?php require __DIR__ . '/partials/user_utility_bar.php'; ?>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php C:\xampp\htdocs\classspace\tests\test_sidebar_and_header_consistency.php`
Expected: SUCCESS: All sidebar and layout consistency checks PASSED.

- [ ] **Step 5: Commit**

```bash
git -C "C:\xampp\htdocs\classspace" add app/views/ tests/test_sidebar_and_header_consistency.php
git -C "C:\xampp\htdocs\classspace" commit -m "refactor(views): use shared user_utility_bar partial across all 7 user views"
```

---

### Task 6: Full Verification with Automated Test Suite & Browser Checks

**Files:**
- Test: All test suites in `tests/`
- Runtime: Verify on running PHP development server (`http://localhost:8000`)

- [ ] **Step 1: Run automated regression tests**

Run:
```powershell
php C:\xampp\htdocs\classspace\tests\test_sidebar_and_header_consistency.php
php C:\xampp\htdocs\classspace\tests\test_sidebar_consistency.php
```
Expected: Both exit 0.

- [ ] **Step 2: Browser verification script**

Run node/curl test to verify:
1. `#profile-dropdown-trigger` exists.
2. `#profile-dropdown` is initially hidden.
3. Clicking trigger toggles visibility.
4. Header baseline remains standardized at `66px`.

- [ ] **Step 3: Final git status check and clean commit history**

```bash
git -C "C:\xampp\htdocs\classspace" status
git -C "C:\xampp\htdocs\classspace" log -n 5 --oneline
```
