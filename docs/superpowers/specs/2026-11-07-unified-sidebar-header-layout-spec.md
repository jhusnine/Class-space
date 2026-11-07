# Design Spec: Unified Sidebar & Header Layout Alignment

**Date:** 2026-11-07  
**Status:** Approved  
**Author:** Pair Programming Agent & User  
**Skill:** ui-ux-pro-max  

---

## 1. Overview & Problem Statement

Across the user-facing tabs of ClassSpace (`user_homepage_view.php`, `calendar_view.php`, `reserve_view.php`, `settings_view.php`, `contact_view.php`, `notifications_view.php`, `profile_view.php`), the following layout and navigation inconsistencies exist:
1. **Sidebar Divergence:** Every view file duplicates 60+ lines of raw sidebar HTML. `settings_view.php` has a different footer (missing `About Us`) and missing subtitle formatting; `notifications_view.php` has a doubled subtitle typo (`College College`); other views vary in nav labels (`Campus Dashboard` vs `Dashboard`, `Master Calendar` vs `Calendar`).
2. **Obsolete Navigation Links:** The `Security` tab link remains in several sidebars despite security settings being consolidated into `settings_view.php`.
3. **Header Shift & Vertical Drift:** Navigating between tabs causes noticeable visual jumping. Main content top padding is excessively thick (`padding: 40px`), utility bar margins push headers down, and individual page headers use disparate font sizes (`32px` vs `26px`), margins, and extra elements (e.g. `.settings-eyebrow`).

---

## 2. Goals & Non-Goals

### Goals
- Extract a single, canonical reusable PHP component for the user sidebar: `app/views/partials/user_sidebar.php`.
- Remove the `Security` tab from all sidebar navigations.
- Ensure 4 canonical navigation items: **Dashboard**, **Calendar**, **Reserve**, **Settings**.
- Standardize all views so `settings_view.php` and other pages render the exact same sidebar layout, brand header, profile card, and footer.
- Lift and standardize page headers so content does not feel sunken or overly padded, and `<h1>` titles share an identical vertical pixel baseline across tabs.
- Purge conflicting `.sidebar` declarations from individual page stylesheets (`messages_style.css`, `reserve_style.css`).

### Non-Goals
- Altering core backend business logic, database queries, or admin controller behaviors.
- Redesigning the entire settings form or calendar grid engine beyond layout and header alignment.

---

## 3. Architecture & Component Design

### 3.1 Reusable Partial: `app/views/partials/user_sidebar.php`
- **Location:** `app/views/partials/user_sidebar.php`
- **Inputs / Context:**
  - `$_SESSION` data (`fname`, `lname`, `email`).
  - `$firstInitial` helper (escaped first letter of user's first name, fallback `'U'`).
  - `$activeTab` parameter with auto-detection fallback using `basename($_SERVER['PHP_SELF'])`:
    - `user_homepage_view.php` -> `'dashboard'`
    - `calendar_view.php` -> `'calendar'`
    - `reserve_view.php` -> `'reserve'`
    - `settings_view.php` / `security_view.php` -> `'settings'`
    - Other views (`contact_view.php`, `notifications_view.php`, `profile_view.php`) -> no active nav item, but utility bar highlights active icon.
- **Markup Structure:**
  ```html
  <button type="button" class="mobile-menu-toggle" id="mobile-menu-toggle"
          aria-controls="classspace-sidebar" aria-expanded="false" aria-label="Open navigation menu">
      <i class="fas fa-bars" aria-hidden="true"></i>
  </button>
  <div class="mobile-menu-backdrop" id="mobile-menu-backdrop" hidden></div>
  <aside class="sidebar" id="classspace-sidebar">
      <div class="logo brand-header">
          <div class="brand-logo-frame">
              <img src="../../public/images/logo.png" alt="ClassSpace">
          </div>
          <div class="brand-text-block">
              <span class="brand-title">ClassSpace</span>
              <span class="brand-subtitle">First City Providential College</span>
          </div>
      </div>

      <ul class="nav-menu">
          <li class="nav-item">
              <a href="user_homepage_view.php" class="nav-link <?= $activeTab === 'dashboard' ? 'active' : '' ?>">
                  <i class="fas fa-home" aria-hidden="true"></i>
                  <span>Dashboard</span>
              </a>
          </li>
          <li class="nav-item">
              <a href="calendar_view.php" class="nav-link <?= $activeTab === 'calendar' ? 'active' : '' ?>">
                  <i class="fas fa-calendar" aria-hidden="true"></i>
                  <span>Calendar</span>
              </a>
          </li>
          <li class="nav-item">
              <a href="reserve_view.php" class="nav-link <?= $activeTab === 'reserve' ? 'active' : '' ?>">
                  <i class="fas fa-calendar-plus" aria-hidden="true"></i>
                  <span>Reserve</span>
              </a>
          </li>
          <li class="nav-item">
              <a href="settings_view.php" class="nav-link <?= $activeTab === 'settings' ? 'active' : '' ?>">
                  <i class="fas fa-cog" aria-hidden="true"></i>
                  <span>Settings</span>
              </a>
          </li>
      </ul>

      <div class="sidebar-bottom">
          <div class="sidebar-profile-card">
              <div class="user-avatar-circle"><?= htmlspecialchars($firstInitial, ENT_QUOTES, 'UTF-8') ?></div>
              <div class="sidebar-profile-meta">
                  <div class="sidebar-profile-name" title="<?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?></div>
                  <div class="sidebar-profile-sub">Teacher</div>
              </div>
              <a href="logout.php" class="sidebar-profile-logout" id="logout" title="Log out">
                  <i class="fas fa-arrow-right-from-bracket" aria-hidden="true"></i>
              </a>
          </div>

          <div class="sidebar-footer">
              <a href="about_view.php" class="footer-link">About Us</a>
              <span class="footer-dot">&bull;</span>
              <a href="logout.php" class="footer-link">Log out</a>
          </div>
      </div>
  </aside>
  ```

---

## 4. UI/UX Pro Max Layout & Spacing Specification

### 4.1 Content Container & Header Baseline
- **Main Content Padding:**
  - Update `.main-content` in `public/css/global.css`:
    ```css
    .main-content {
        flex: 1;
        overflow-y: auto;
        padding: 24px 36px 36px;
        min-width: 0;
    }
    ```
- **Utility Bar Spacing:**
  - Reduce `.user-utility-bar` margin: `margin: 0 0 12px;`.
  - Ensures account utilities stay aligned without consuming excessive vertical space.
- **Unified Page Header (`.page-header`):**
  - Standardize `.page-header`:
    ```css
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 24px;
        min-height: 48px;
    }
    .page-header h1 {
        font-size: 26px;
        font-weight: 700;
        line-height: 1.25;
        letter-spacing: -0.02em;
        color: var(--text-high);
        margin: 0;
    }
    .page-header p {
        margin: 6px 0 0;
        color: var(--text-low);
        font-size: 13px;
        line-height: 1.45;
    }
    ```
  - In `settings_view.php`, adjust `.settings-eyebrow` or remove it from pushing the `h1` down, aligning the Settings title to the exact same vertical coordinate.
  - In `notifications_view.php`, harmonize `.notif-page-header` to match `.page-header` margins and padding.

---

## 5. CSS Conflict Elimination

- Remove competing `.sidebar` and `.nav-menu` overrides in:
  - `public/css/messages_style.css` (lines 45–105)
  - `public/css/reserve_style.css` (lines 370–400)
- Ensure all sidebar rules in `global.css` act as the single source of truth for both dark and light modes.

---

## 6. Verification & Quality Gates

1. **Automated Integration Checks:**
   - Run PHP syntax lint (`php -l`) on all modified view files and partials.
2. **Visual Consistency Audit via Puppeteer:**
   - Capture screenshots of Dashboard, Calendar, Reserve, and Settings sidebars and headers.
   - Assert `sidebar` bounding box, width, brand text, and profile layout are identical across all views.
   - Assert `h1` vertical offset (`getBoundingClientRect().top`) is consistent within 2px across pages.
3. **Functional Navigation:**
   - Verify clicking Dashboard, Calendar, Reserve, and Settings routes accurately and highlights the active item.

