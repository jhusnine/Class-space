# Changelog

All notable changes to the ClassSpace project are documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased] - 2026-11-08

### Added
- **Unified User Sidebar Partial (`app/views/partials/user_sidebar.php`):**
  - Created a single reusable component for primary navigation across all 7 user views (`user_homepage_view.php`, `calendar_view.php`, `reserve_view.php`, `settings_view.php`, `contact_view.php`, `notifications_view.php`, and `profile_view.php`).
  - Added auto-detection of `$activeTab` based on `$_SERVER['PHP_SELF']` with support for manual view overrides.
  - Integrated mobile menu drawer toggle (`#mobile-menu-toggle`) and backdrop.
- **User Utility Bar Partial (`app/views/partials/user_utility_bar.php`):**
  - Extracted shared top-right utility navigation across all 7 user views.
  - Includes shortcuts for Contact Us (`contact_view.php`) with unread reply badges, Notifications (`notifications_view.php`) with unread badge counter, and Account Profile dropdown.
  - Implemented safe session attribute escaping using `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` with blank name fallback.
- **Top-Right Profile Dropdown Menu:**
  - Upgraded top-right profile item into an accessible dropdown trigger button (`#profile-dropdown-trigger`).
  - Added dropdown menu (`#profile-dropdown`) featuring:
    - User identity card: Avatar circle, Full Name, Email address, and dynamic Role badge (`Teacher` / `Student`).
    - Navigation actions: **View Profile** (`profile_view.php`), **Account Settings** (`settings_view.php`), and **About ClassSpace** (`settings_view.php#about-settings`).
    - Destructive action: **Log Out** (`logout.php`) with danger hover highlight.
- **Client-Side Interactivity (`public/js/toast.js`):**
  - Added `initProfileDropdown()` with toggle on click, click-outside-to-close, and keyboard `Escape` closing.
  - Implemented mutual close synchronization between profile dropdown and notification dropdown (opening one automatically closes the other).
- **CSS Component Tokens & Styles (`public/css/global.css`):**
  - Added styles for `.utility-profile-wrapper`, `.utility-profile-caret`, `.profile-dropdown`, `.profile-dropdown-header`, `.profile-dropdown-avatar`, and `.profile-dropdown-item`.
  - Added responsive viewport adaptations for screens `< 768px`.
  - Applied `scrollbar-gutter: stable;` to both `html` and `.main-content` to eliminate horizontal layout shifts when navigating between scrolling and non-scrolling views.
- **Automated Regression & Consistency Tests:**
  - `tests/test_sidebar_and_header_consistency.php`: Verifies existence and inclusion of shared partials, absence of hardcoded sidebars, presence of `.page-header` & `h1`, stable scrollbar gutters, and container padding.
  - `tests/test_profile_dropdown_integration.php`: Verifies dropdown markup, session binding, and clean sidebars across all 7 views.
- **Architecture Documentation:**
  - `docs/superpowers/specs/2026-11-07-unified-sidebar-header-layout-spec.md`
  - `docs/superpowers/plans/2026-11-07-unified-sidebar-header-layout-plan.md`
  - `docs/superpowers/specs/2026-11-08-user-utility-bar-profile-dropdown-spec.md`
  - `docs/superpowers/plans/2026-11-08-user-utility-bar-profile-dropdown-plan.md`

### Changed
- **Consumer Views Componentization:**
  - Replaced ~60 lines of duplicated sidebar markup in all 7 user views with `require __DIR__ . '/partials/user_sidebar.php';`.
  - Replaced ~25 lines of duplicated utility bar markup in all 7 user views with `require __DIR__ . '/partials/user_utility_bar.php';`.
  - Net deletion of over 250 lines of duplicate view template code.
- **Header Baseline & Layout Alignment:**
  - Elevated `.main-content` top padding from `40px` to `16px 32px 32px` in `global.css`, `homepage_style.css`, `messages_style.css`, and `reserve_style.css`.
  - Tightened `.user-utility-bar` margin to `0 0 6px`, aligning `h1` headings across all 7 views to a uniform `66px` vertical offset.
  - Adjusted `reserve_style.css` to use `margin-inline: 0` instead of `margin-inline: auto` so the Reserve page header aligns with other pages on wide displays.
- **Brand Subtitle Cleanup:**
  - Removed `"First City Providential College"` subtitle under **ClassSpace** in both user sidebar and admin views (`admin_homepage_view.php`, `admin_manage_rooms_view.php`, `admin_messages_view.php`, `admin_reports_view.php`, `admin_setting_view.php`) for a cleaner, modern collegiate brand header.
- **Test Suite Modernization:**
  - Updated `tests/test_sidebar_consistency.php` and `tests/test_homepage_redesign_smoke.php` to resolve the shared partial rather than expecting inline sidebar markup in view files.

### Removed
- **Sidebar Bottom Section:**
  - Removed `.sidebar-bottom`, `.sidebar-profile-card`, user avatar, and footer links from the sidebar partial (all profile information and logout options relocated to the top-right profile dropdown).
- **Obsolete Security Navigation Tab:**
  - Removed `security_view.php` from sidebar navigation across all views (Security settings are now integrated directly under the Settings page).
- **Duplicate & Conflicting CSS:**
  - Removed duplicate `.sidebar` style definitions from `public/css/messages_style.css` and `public/css/reserve_style.css`.
  - Removed conflicting `.nav-link` / `.footer-link` color overrides from `public/css/settings_style.css` that broke active link highlighting.
  - Purged unused dead CSS selectors: `.settings-eyebrow` in `settings_style.css`, `.contact-intro` & `.contact-badge` in `contact_style.css`, and `.notif-title-main` & `.notif-subtitle` in `notification_style.css`.
