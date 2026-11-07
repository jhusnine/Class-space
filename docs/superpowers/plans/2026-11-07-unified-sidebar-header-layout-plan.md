# Unified Sidebar & Header Layout Alignment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Standardize the user portal sidebar into a reusable partial, eliminate the obsolete Security tab, remove competing CSS overrides, and align page headers and container padding across all user views.

**Architecture:** Create a reusable partial `app/views/partials/user_sidebar.php` with intelligent `$activeTab` detection. Refactor `.main-content` top padding and `.page-header` typography in `public/css/global.css`. Purge obsolete `.sidebar` overrides in `messages_style.css` and `reserve_style.css`. Integrate the partial across all user views and verify via automated integration testing and Puppeteer visual checks.

**Tech Stack:** PHP 8.2, Vanilla CSS3 / CSS Custom Properties, FontAwesome 6, Puppeteer.

## Global Constraints
- Do not break existing page controller actions, session variables, or database interactions.
- All dynamic session outputs (`fname`, `lname`, `$firstInitial`) must be safely escaped.
- Mobile toggle `#mobile-menu-toggle`, `#classspace-sidebar`, and `#mobile-menu-backdrop` IDs must be preserved for responsive menu JavaScript compatibility.
- Ensure light mode (`body.light-mode`) and dark mode remain visually balanced without unstyled flashes or broken colors.

---

### Task 1: Create Automated Consistency Test

**Files:**
- Create: `tests/test_sidebar_and_header_consistency.php`

**Interfaces:**
- Consumes: PHP view files in `app/views/`
- Produces: CLI exit code 0 when all views include the partial, contain no leftover `security_view.php` nav links, and contain canonical nav labels.

- [ ] **Step 1: Write the integration test**
  Write a test asserting:
  - `app/views/partials/user_sidebar.php` exists and parses cleanly with `php -l`.
  - All 7 user views include `partials/user_sidebar.php`.
  - No user view contains a direct sidebar nav link to `security_view.php`.
  - All user views use the canonical nav labels: `Dashboard`, `Calendar`, `Reserve`, `Settings`.
  - No duplicate `.sidebar` style block remains in `messages_style.css`.

- [ ] **Step 2: Run test to confirm failure**
  Run `php tests/test_sidebar_and_header_consistency.php` and verify it fails on missing partial and leftover sidebar blocks.

---

### Task 2: Create Reusable Sidebar Partial `app/views/partials/user_sidebar.php`

**Files:**
- Create: `app/views/partials/user_sidebar.php`

**Interfaces:**
- Consumes: `$_SESSION`, optional `$activeTab` string (`'dashboard'`, `'calendar'`, `'reserve'`, `'settings'`)
- Produces: Standardized HTML for mobile toggle, backdrop, logo brand header, 4 canonical nav items, profile card with initial avatar, and footer links.

- [ ] **Step 1: Implement `app/views/partials/user_sidebar.php`**
  Implement the partial with:
  - Automatic `$activeTab` resolution if not explicitly passed.
  - Safe HTML escaping for user name and initial.
  - Brand header with logo, title `ClassSpace`, and subtitle `First City Providential College`.
  - 4 items: Dashboard, Calendar, Reserve, Settings (with active class on matching item).
  - Bottom profile card with user avatar initial, name, "Teacher" role badge, logout button, and `About Us • Log out` footer links.

- [ ] **Step 2: Syntax check**
  Run `php -l app/views/partials/user_sidebar.php` to verify no syntax errors.

---

### Task 3: Refactor CSS for Tighter Top Padding, Consistent Baseline, and Purge Overrides

**Files:**
- Modify: `public/css/global.css`
- Modify: `public/css/messages_style.css`
- Modify: `public/css/reserve_style.css`
- Modify: `public/css/settings_style.css`

**Interfaces:**
- Consumes: Design tokens in `global.css`
- Produces: Consistent vertical header baseline and clean sidebar rules.

- [ ] **Step 1: Update `global.css`**
  - Adjust `.main-content` padding from `40px` to `24px 36px 36px`.
  - Adjust `.user-utility-bar` margin to `0 0 12px`.
  - Ensure `.page-header` has consistent margin-bottom `24px` and `h1` has `font-size: 26px; line-height: 1.25; margin: 0;`.
  - Add unified `.header-academic-tag` and `.page-subtitle` styles.

- [ ] **Step 2: Purge conflicting CSS in `messages_style.css` and `reserve_style.css`**
  - Remove duplicate `.sidebar`, `.logo`, `.nav-menu`, `.nav-link` declarations in `messages_style.css` and `reserve_style.css`.

- [ ] **Step 3: Align `settings_style.css`**
  - Adjust `.settings-header` and `.settings-eyebrow` so the `Settings` `<h1>` title aligns to the exact same vertical coordinate as Dashboard and Calendar headers.

---

### Task 4: Integrate Partial in Core Views

**Files:**
- Modify: `app/views/user_homepage_view.php`
- Modify: `app/views/calendar_view.php`
- Modify: `app/views/reserve_view.php`
- Modify: `app/views/settings_view.php`
- Modify: `app/views/contact_view.php`
- Modify: `app/views/notifications_view.php`
- Modify: `app/views/profile_view.php`

**Interfaces:**
- Consumes: `app/views/partials/user_sidebar.php`
- Produces: Cleaned views referencing the single sidebar component.

- [ ] **Step 1: Update `user_homepage_view.php`**
  Replace lines 36–97 with `<?php $activeTab = 'dashboard'; require_once __DIR__ . '/partials/user_sidebar.php'; ?>`.

- [ ] **Step 2: Update `calendar_view.php`**
  Replace lines 36–102 with `<?php $activeTab = 'calendar'; require_once __DIR__ . '/partials/user_sidebar.php'; ?>`.
  Remove redundant secondary `<h1>` in `.cal-toolbar`.

- [ ] **Step 3: Update `reserve_view.php`**
  Replace sidebar lines with `<?php $activeTab = 'reserve'; require_once __DIR__ . '/partials/user_sidebar.php'; ?>`.

- [ ] **Step 4: Update `settings_view.php`**
  Replace sidebar lines with `<?php $activeTab = 'settings'; require_once __DIR__ . '/partials/user_sidebar.php'; ?>`.

- [ ] **Step 5: Update `contact_view.php`, `notifications_view.php`, and `profile_view.php`**
  Replace duplicate sidebar blocks with `<?php require_once __DIR__ . '/partials/user_sidebar.php'; ?>`.

---

### Task 5: Verification & Visual Polish

**Files:**
- Verify: `tests/test_sidebar_and_header_consistency.php`

- [ ] **Step 1: Run automated test suite**
  Execute `php tests/test_sidebar_and_header_consistency.php` and confirm all assertions pass.

- [ ] **Step 2: Live browser visual audit with Puppeteer**
  - Navigate to Dashboard, Calendar, Reserve, and Settings.
  - Measure header top bounding box coordinate across all 4 pages to confirm pixel-level alignment.
  - Capture and inspect screenshots for dark and light modes.

