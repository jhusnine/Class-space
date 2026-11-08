# Changelog

All notable changes to the ClassSpace project are documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased] - 2026-11-08

### Added
- **Admin Expanded Room & Campus Hall Management Module:**
  - Expanded admin room management controls with **zero new database columns**, operating strictly on existing columns in `room` (`room_id`, `hall_id`, `room_name`, `room_capacity`, `room_type`, `room_has_ac`, `room_status`) and `hall` (`hall_id`, `hall_name`, `hall_desc`).
  - Added dedicated backend controller (`app/controllers/admin_manage_rooms_controller.php`) implementing session authentication, administrative verification (`account_is_admin = 1`), HTTP 405 Method Not Allowed enforcement for mutation endpoints, CWE-209 internal error logging and generic sanitized responses, boundary validation, and atomic transaction safeguards.
  - Implemented relational safety protections: blocked hall deletion when assigned rooms exist, and cleanly purged child records in `schedule`, `pendingschedule`, and `history` prior to room deletion.
  - Added segmented tab switcher (`[ Rooms Directory ]` and `[ Campus Buildings & Halls ]`) on `app/views/admin_manage_rooms_view.php` with real-time badge counts and seamless switching.
  - Built Tab 1 (Rooms Directory): Multi-dimensional filtering by search query, building dropdown, and status chips; updated table with room thumbnails, building pills, classification badges, seating capacity & AC amenity chips, quick-status switcher, and action buttons.
  - Built Tab 2 (Campus Buildings & Halls): Responsive grid of building cards displaying dynamic campus banner photos (`plaridel.jpeg`, `melchora.jpg`, `rizal.jpg`, `balagtas.jpg`, `gym.png`, `hotel.jpeg`, `ople.jpg`, and campus fallback `login_background.jpg`), assigned room counts, descriptions, real-time search, and Create/Edit/Delete actions.
  - Built accessible modal dialogs (`#modal-room`, `#modal-hall`, `#modal-delete-confirm`) featuring a live dynamic room preview card that updates banner image, name, building badge, classification, capacity, AC, and status in real-time as the administrator modifies form fields.
  - Added comprehensive integration test suite (`tests/test_admin_room_management.php`) running 24 subrequest assertions covering authorization, HTTP 405 enforcement, room CRUD (with and without hall assignments), hall CRUD, length boundaries, and relational safety checks.

- **Admin Teacher Account Creation & Management Module:**
  - Added dedicated backend controller (`app/controllers/admin_teachers_controller.php`) handling `get_teachers`, `create_teacher`, `update_teacher`, `reset_password`, and `delete_teacher`.
  - Implemented multi-table transactional database integrity during teacher account creation, populating `account` (login credentials, role `0`, profile), `settings` (dark mode & language preferences), and `notification` (automatic system welcome notification) so no relational columns are left empty.
  - Implemented safe foreign-key cascading deletion handling MySQL `RESTRICT` constraints across `settings`, `notification`, `contacts`, `history`, `pendingschedule`, `schedule`, and orphaned `reservedetails` entries.
  - Built comprehensive admin management interface (`app/views/admin_manage_teachers_view.php`) featuring live faculty summary stats (Total Teachers, Active Bookings, Pending Requests), real-time debounced search, status filter chips, responsive faculty directory table, and 4 modal dialogs (Create, Edit, Reset Password, and Delete Confirmation).
  - Created modern stylesheet (`public/css/admin_manage_teachers_style.css`) adhering to `/ui-ux-pro-max` guidelines, with dark/light mode tokens, glassmorphism cards, glowing focus rings, avatar chips, and responsive breakpoints.
  - Implemented client controller script (`public/js/admin_manage_teachers_script.js`) with asynchronous API calls, pagination, filter tabs, cryptographically secure password generation (`window.crypto.getRandomValues`), 1-click clipboard copy, and toast notifications.
  - Added **Teachers** navigation tab with chalkboard-teacher icon across all 6 administrative views (`admin_homepage_view.php`, `admin_manage_rooms_view.php`, `admin_manage_teachers_view.php`, `admin_reports_view.php`, `admin_messages_view.php`, `admin_setting_view.php`).
  - Added subrequest-driven end-to-end integration test suite (`tests/test_admin_teacher_management.php`) testing the real controller endpoints, authorization enforcement, HTTP 405 method checking, multi-table insertion, and cascading deletion.
- **Semester Duration Helper (`includes/semester_helper.php`):**
  - Added `getCurrentSemester($date)` and `getSemesterEndDate($date)` to calculate academic semesters and boundary dates (1st Semester: Aug 1 – Dec 31, 2nd Semester: Jan 1 – May 31, Summer Term: Jun 1 – Jul 31).
  - Integrated with reservation form to pre-populate `#schedule-until` and dynamically display the active semester badge (`1st Semester (AY 2026-2027)`).
- **Support Chat Pop-up Modal (`app/views/contact_view.php`, `public/css/contact_style.css`, `public/js/contact_script.js`):**
  - Converted the static in-page support messages section into a clean pop-up modal (`#chat-modal`) triggered by clicking the **"Chat with us"** action.
  - Added animated modal overlay (`.chat-modal-overlay` with backdrop blur) and modal card (`.chat-modal-card`).
  - Added close button (`.chat-modal-close`) in the chat header, click-outside-backdrop close handler, and `Escape` key dismiss listener.
  - Added automatic opening when navigating with `#support-chat` or `#chat` hash anchors.
- **Automated Integration Test Suites:**
  - `tests/test_teacher_room_visibility.php`: Verifies that teacher/student endpoints, searches, and dropdowns filter out `maintenance` and `unavailable` rooms, while admin endpoints retain access.
  - `tests/test_weekly_schedule_semester_duration.php`: Verifies semester duration calculations, pending reservation end dates, admin approval persistence, conflict checking across semester boundaries, and opportunistic cleanup.
  - `tests/test_contact_chat_modal.php`: Verifies contact view chat modal markup, open/close/keyboard scripts, and modal styles.
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
- **Login Portal Visual Layout & Brand Polish (`app/index.php`, `public/css/login_style.css`):**
  - Updated login portal to a streamlined modern split layout with smooth atmospheric gradient transition.
  - Streamlined brand showcase panel footer to display product version cleanly (`ClassSpace v2.4`).
- **Weekly Schedule Duration Bounded to Semester:**
  - Added `schedule_end_date` to `schedule` and `pending_schedule_end_date` to `pendingschedule` (`DATE NULL`), backfilling existing weekly entries to semester end date.
  - Updated `reserve_view.php` and `reserve_script.js` to expose semester duration date input `#schedule-until` when recurring weekly is selected.
  - Updated `admin_action_controller.php` to persist `schedule_end_date` into `schedule` upon request approval.
  - Updated `cleanup_schedules.php` to opportunistically purge expired weekly schedules (`schedule_day IS NULL AND schedule_end_date < CURDATE()`).
  - Updated `calendar_controller.php`, `admin_get_pending.php`, `my_pending.php`, `admin_get_room_reservations.php`, `admin_report_controller.php`, `calendar_script.js`, `admin_homepage_script.js`, `admin_manage_rooms_script.js`, and `admin_reports_script.js` to format and display duration bounds (`Every <Day> (until YYYY-MM-DD)`).
- **User Dashboard Reservations Stat Card (`app/views/user_homepage_view.php`):**
  - Renamed stat card header from `Your Bookings` to `Your Reservations`.
- **Contact Page Layout (`app/views/contact_view.php`, `public/css/contact_style.css`):**
  - Centered and balanced `.contact-grid` and `.contact-tiles` with a clean responsive layout now that support chat is hosted inside a pop-up modal.
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

### Fixed
- **Teacher Account Management Security & Validation Hardening (`app/controllers/admin_teachers_controller.php`):**
  - Sanitized database error responses by logging raw database exceptions internally and returning generic user-safe error messages to prevent database schema information disclosure (CWE-209).
  - Fixed username sanitization ordering to ensure non-alphanumeric character inputs cannot produce blank usernames.
  - Enforced 100-character limits on email and username inputs to match MySQL procedure `spGetAccountInfo` parameter bounds, preventing permanent login lockout for newly provisioned accounts.
  - Enforced 80-character limits on first and last names matching database column definitions.
  - Enforced HTTP POST method requirement for state-altering actions (`create_teacher`, `update_teacher`, `reset_password`, `delete_teacher`), rejecting invalid HTTP verbs with 405 Method Not Allowed.
  - Filtered active schedule counts in teacher directory to current/upcoming reservations (`CURDATE()`), preventing expired reservations from being counted as active.
- **Login Button State Reset on Failure (`public/js/login_script.js`):**
  - Fixed issue where the login submit button reset to `"SIGN IN"` instead of preserving the form's `"Log In"` label upon failed authentication or server error.
  - Set loading in-flight button text to `"Logging in..."`.
- **Teacher Room Visibility Restriction:**
  - Filtered out `maintenance` and `unavailable` rooms across user-facing room list and search endpoints (`app/controllers/room_controller.php`, `app/controllers/search_controller.php`, `public/js/homepage_script.js`, `public/js/reserve_script.js`) so non-admin users only see bookable rooms.
- **Weekly Schedule Infinite Overlap Conflicts (`app/controllers/check_conflict.php`, `app/controllers/ai_suggest_controller.php`):**
  - Bounded conflict checking between weekly reservations and one-time reservations using `schedule_end_date >= :date` and `schedule_day <= :untilDate`. One-time reservations in future semesters are no longer falsely blocked by past semester weekly bookings.
- **Active Room Occupancy Calculation (`app/controllers/room_controller.php`, `app/controllers/suggestion_room_controller.php`, `app/controllers/user_stats.php`):**
  - Added `AND (sched.schedule_end_date IS NULL OR sched.schedule_end_date >= CURDATE())` to prevent expired weekly schedules from marking rooms as occupied.

### Removed
- **Login Portal Stars Particles Canvas (`app/index.php`, `public/css/login_style.css`, `public/js/login_script.js`):**
  - Removed `#stars-canvas` HTML element, `.stars-canvas` CSS positioning rules, and `initStars()` script animation loop from the sign-in page.
- **Login Portal Academic Year Tag (`app/index.php`):**
  - Removed `<span>Academic Year 2026–2027</span>` from the left brand panel footer.
- **Login Portal Active Indicator Badge (`app/index.php`):**
  - Removed `"PORTAL ACTIVE"` pulse indicator badge from the brand header.
- **Homepage Room Status Legend (`app/views/user_homepage_view.php`):**
  - Removed `.room-status-legend` container (`Available`, `Occupied / Reserved`) from the user homepage to declutter the building filter bar.
- **Sidebar Bottom Section:**
  - Removed `.sidebar-bottom`, `.sidebar-profile-card`, user avatar, and footer links from the sidebar partial (all profile information and logout options relocated to the top-right profile dropdown).
- **Obsolete Security Navigation Tab:**
  - Removed `security_view.php` from sidebar navigation across all views (Security settings are now integrated directly under the Settings page).
- **Duplicate & Conflicting CSS:**
  - Removed duplicate `.sidebar` style definitions from `public/css/messages_style.css` and `public/css/reserve_style.css`.
  - Removed conflicting `.nav-link` / `.footer-link` color overrides from `public/css/settings_style.css` that broke active link highlighting.
  - Purged unused dead CSS selectors: `.settings-eyebrow` in `settings_style.css`, `.contact-intro` & `.contact-badge` in `contact_style.css`, and `.notif-title-main` & `.notif-subtitle` in `notification_style.css`.
