# Design Spec: User Utility Bar Partial & Top-Right Profile Dropdown

**Date:** 2026-11-08  
**Status:** Approved  
**Scope:** User portal views (`user_homepage_view.php`, `calendar_view.php`, `reserve_view.php`, `settings_view.php`, `contact_view.php`, `notifications_view.php`, `profile_view.php`)

---

## 1. Problem Statement
1. **Cluttered Sidebar:** The bottom of the collegiate sidebar currently contains an inline profile card (`.sidebar-profile-card`) and footer (`.sidebar-footer`), including duplicate logout buttons and static role text. As modern application navigation patterns evolve, user identity and authentication actions are expected in the persistent top-right utility area rather than cluttering the primary navigation drawer.
2. **Duplicated Utility Bar Markup:** Across all 7 user views, ~25 lines of `<nav class="user-utility-bar">` markup are duplicated verbatim. Any change to account shortcuts or badges requires updating 7 separate template files.
3. **No Centralized User Menu:** Clicking the top-right profile currently only navigates directly to `profile_view.php` without providing quick access to account settings, about modal/page, or logout.

---

## 2. Proposed Architecture

```
app/views/
├── partials/
│   ├── user_sidebar.php          <-- Cleaned: No .sidebar-bottom, no profile card, no logout
│   └── user_utility_bar.php      <-- New: Reusable utility bar with profile dropdown
├── user_homepage_view.php        <-- Includes user_utility_bar.php
├── calendar_view.php             <-- Includes user_utility_bar.php
├── reserve_view.php              <-- Includes user_utility_bar.php
├── settings_view.php             <-- Includes user_utility_bar.php
├── contact_view.php              <-- Includes user_utility_bar.php
├── notifications_view.php        <-- Includes user_utility_bar.php
└── profile_view.php              <-- Includes user_utility_bar.php

public/
├── css/
│   └── global.css                <-- Dropdown styling (.profile-dropdown, menu items, responsive)
└── js/
    └── toast.js                  <-- Handles profile dropdown toggle, click-outside, and keyboard Escape
```

---

## 3. Detailed Component Specifications

### 3.1 New Partial: `app/views/partials/user_utility_bar.php`
- **Data & Sanitization:**
  - Safely extract session values:
    ```php
    $utilityFname = trim((string)($_SESSION["fname"] ?? "User"));
    if ($utilityFname === "") $utilityFname = "User";
    $utilityLname = trim((string)($_SESSION["lname"] ?? ""));
    $utilityFullName = htmlspecialchars(trim($utilityFname . ($utilityLname !== "" ? " " . $utilityLname : "")), ENT_QUOTES, "UTF-8");
    $utilityEmail = htmlspecialchars((string)($_SESSION["email"] ?? ""), ENT_QUOTES, "UTF-8");
    $utilityInitial = strtoupper(substr($utilityFname, 0, 1));
    if ($utilityInitial === "") $utilityInitial = "U";
    $utilityRole = htmlspecialchars((string)($_SESSION["role"] ?? "Student"), ENT_QUOTES, "UTF-8");
    $utilityCurrentPage = basename($_SERVER["PHP_SELF"] ?? "");
    ```
- **Markup Structure:**
  - Container `<nav class="user-utility-bar" aria-label="Account shortcuts">`
  - Action container `<div class="user-utility-actions">`
    - **Contact Icon Link:** `<a href="contact_view.php" class="utility-icon-link ...">`
    - **Notification Button:** `<a href="notifications_view.php" class="utility-icon-link ...">` with `<span id="notif-badge" class="notif-badge">`
    - **Profile Trigger Wrapper:** `<div class="utility-profile-wrapper">`
      - Trigger button: `<button type="button" class="utility-profile-link" id="profile-dropdown-trigger" aria-haspopup="true" aria-expanded="false" aria-controls="profile-dropdown">`
        - Avatar: `<span class="utility-avatar"><?= $utilityInitial ?></span>`
        - Meta text: `<span class="utility-profile-meta"><span class="utility-profile-name"><?= $utilityFullName ?></span><span class="utility-profile-email"><?= $utilityEmail !== "" ? $utilityEmail : "Account Profile" ?></span></span>`
        - Caret indicator: `<i class="fas fa-chevron-down utility-profile-caret" aria-hidden="true"></i>`
      - Dropdown Menu: `<div class="profile-dropdown" id="profile-dropdown" role="menu" aria-label="User Account Menu" hidden>`
        - **Dropdown Header:**
          - `<div class="profile-dropdown-header">`
            - `<span class="profile-dropdown-avatar"><?= $utilityInitial ?></span>`
            - `<div class="profile-dropdown-user">`
              - `<strong class="profile-dropdown-name"><?= $utilityFullName ?></strong>`
              - `<span class="profile-dropdown-email"><?= $utilityEmail ?></span>`
              - `<span class="profile-dropdown-role"><?= $utilityRole ?></span>`
        - **Dropdown Menu List:**
          - `<ul class="profile-dropdown-menu" role="none">`
            - `<li role="none"><a href="profile_view.php" class="profile-dropdown-item" role="menuitem"><i class="fas fa-user"></i> <span>View Profile</span></a></li>`
            - `<li role="none"><a href="settings_view.php" class="profile-dropdown-item" role="menuitem"><i class="fas fa-cog"></i> <span>Account Settings</span></a></li>`
            - `<li role="none"><a href="settings_view.php#about-settings" class="profile-dropdown-item" role="menuitem"><i class="fas fa-circle-info"></i> <span>About ClassSpace</span></a></li>`
            - `<li class="profile-dropdown-divider" role="separator"></li>`
            - `<li role="none"><a href="logout.php" class="profile-dropdown-item item-logout" role="menuitem"><i class="fas fa-arrow-right-from-bracket"></i> <span>Log Out</span></a></li>`

### 3.2 Cleaned Sidebar Partial: `app/views/partials/user_sidebar.php`
- Remove the entire `<div class="sidebar-bottom">` block (lines 88-105).
- Clean up any unused session assignments in `user_sidebar.php` related only to the removed profile card.
- Retain only the brand header (`ClassSpace` logo & title) and navigation menu (`Dashboard`, `Calendar`, `Reserve`, `Settings`).

### 3.3 UI Styling in `public/css/global.css`
- Wrap trigger and dropdown within `.utility-profile-wrapper { position: relative; }`.
- Style `.profile-dropdown`:
  - `position: absolute; top: calc(100% + 8px); right: 0; min-width: 240px; border-radius: var(--radius-md); background: var(--bg-surface); border: 1px solid var(--border-color); box-shadow: 0 16px 40px rgba(0, 0, 0, 0.25); z-index: 120;`
  - `.profile-dropdown[hidden] { display: none !important; }`
- Style `.profile-dropdown-header`:
  - Padding, border-bottom, avatar + name + email + role badge.
- Style `.profile-dropdown-item`:
  - Flex layout, gap 10px, subtle icon color, hover background `var(--bg-nav-hover)`, text `var(--text-high)`.
- Style `.item-logout`:
  - Warning/danger hover color (`var(--danger, #EF4444)` or accent border) to clearly distinguish destructive action.
- Mobile responsiveness:
  - When screen is `< 768px`, utility profile dropdown positions safely within viewport boundaries without overflowing right.

### 3.4 Interactivity in `public/js/toast.js`
- Create `initProfileDropdown()` function:
  - Query `#profile-dropdown-trigger` and `#profile-dropdown`.
  - Toggle `hidden` property and update `aria-expanded` (`true`/`false`).
  - Coordinate with `notification-dropdown`: opening profile dropdown automatically closes notification dropdown, and vice-versa.
  - Event listeners:
    - Click on trigger -> toggle menu.
    - Click outside wrapper -> close menu.
    - Press `Escape` -> close menu and restore focus to trigger.
  - Initialize on `DOMContentLoaded` alongside `initSharedMobileMenu()` and `initNotificationDropdown()`.

---

## 4. Consumer Views Integration
Replace the static `<nav class="user-utility-bar">` block in:
1. `app/views/user_homepage_view.php`
2. `app/views/calendar_view.php`
3. `app/views/reserve_view.php`
4. `app/views/settings_view.php`
5. `app/views/contact_view.php`
6. `app/views/notifications_view.php`
7. `app/views/profile_view.php`

with:
```php
<?php require __DIR__ . '/partials/user_utility_bar.php'; ?>
```

---

## 5. Automated Verification Plan
1. **`tests/test_sidebar_and_header_consistency.php`:**
   - Assert `app/views/partials/user_utility_bar.php` exists and has valid PHP syntax.
   - Assert all 7 views include `partials/user_utility_bar.php`.
   - Assert `app/views/partials/user_sidebar.php` does NOT contain `.sidebar-profile-card` or `class="sidebar-bottom"`.
   - Assert profile dropdown has links to `profile_view.php`, `settings_view.php`, and `logout.php`.
2. **`tests/test_sidebar_consistency.php`:**
   - Update assertions so it does not look for removed sidebar profile cards or bottom elements in `user_sidebar.php`.
3. **Browser Runtime Test:**
   - Run browser script to verify clicking `#profile-dropdown-trigger` toggles `#profile-dropdown` visibility, renders username and email, and closes on Escape/click-outside.

