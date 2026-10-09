<!-- DeepInit issues | Component: system-wide
Run ID: deepinit-20261009-130800
Input files processed: app/controllers/*.php, app/views/*.php
Generated: 2026-10-09T13:08:00+08:00 -->

# Issue Ledger & Defect Register

**Summary:** 8 verified issues identified across authentication, data integrity, access control, and scheduling logic.

## 1. Verified issues
| ISS-id | Family | Claim | Cites | Severity |
|---|---|---|---|---|
| ISS-001 | IF-1 | SQL query in cleanup_schedules.php queries schedule_day on pendingschedule instead of pending_schedule_day | `app/controllers/cleanup_schedules.php:20` | high |
| ISS-002 | IF-1 | Hardcoded database credentials bypass database_include.php in user messaging | `app/controllers/user_messages_controller.php:18` · `app/controllers/contact_messages_controller.php:30` | high |
| ISS-003 | IF-1 | Administrative view headers only verify session existence and omit account_is_admin check | `app/views/admin_homepage_view.php:4` · `app/views/admin_manage_rooms_view.php:3` · `app/views/admin_reports_view.php:2` · `app/views/admin_setting_view.php:2` | high |
| ISS-004 | IF-1 | Admin dashboard statistics endpoint omits administrator privilege check | `app/controllers/admin_stats.php:4` | medium |
| ISS-005 | IF-7 | Duplicate user messaging controller is completely unreferenced by frontend code | `app/controllers/contact_messages_controller.php:1` | low |
| ISS-006 | IF-3a | Direct variable interpolation in SQL string instead of prepared statement parameter binding | `app/controllers/user_stats.php:21` · `app/controllers/user_stats.php:25` | medium |
| ISS-007 | IF-1 | Password authentication utilizes raw unsalted SHA-256 digests | `app/controllers/login_controller.php:32` | high |
| ISS-008 | IF-3a | [RESOLVED] Weekly reservation conflict check queries only recurring slots and omits future one-time bookings | `app/controllers/check_conflict.php:154-170` | medium |

---

## ISS-001 — Non-existent column reference in pendingschedule cleanup
- **family:** IF-1
- **claim:** SQL query in `cleanup_schedules.php` specifies `WHERE schedule_day IS NOT NULL` on `pendingschedule`, but the column name in `pendingschedule` is `pending_schedule_day`. The query fails with an unknown column exception, preventing expired pending schedules from being purged.
- **severity:** high
- **citations:** `app/controllers/cleanup_schedules.php:20`

## ISS-002 — Hardcoded root credentials bypassing shared database configuration
- **family:** IF-1
- **claim:** `user_messages_controller.php` and `contact_messages_controller.php` hardcode `$user = 'root'` and `$pass = ''` rather than requiring `includes/database_include.php`. On any environment where database credentials match `classpace`/`classpace123`, user messaging fails completely.
- **severity:** high
- **citations:** `app/controllers/user_messages_controller.php:18` · `app/controllers/contact_messages_controller.php:30`

## ISS-003 — Missing administrative authorization check in view templates
- **family:** IF-1
- **claim:** Administrative view templates (`admin_homepage_view.php`, `admin_manage_rooms_view.php`, `admin_reports_view.php`, `admin_setting_view.php`) verify only `isset($_SESSION['id'])`. Any authenticated non-admin user can access administrative HTML pages directly.
- **severity:** high
- **citations:** `app/views/admin_homepage_view.php:4` · `app/views/admin_manage_rooms_view.php:3` · `app/views/admin_reports_view.php:2` · `app/views/admin_setting_view.php:2`

## ISS-004 — Missing administrator privilege check on admin stats endpoint
- **family:** IF-1
- **claim:** `admin_stats.php` verifies only that a session ID exists (`isset($_SESSION['id'])`) without checking `account_is_admin`, allowing any logged-in standard user to retrieve administrative KPI metrics.
- **severity:** medium
- **citations:** `app/controllers/admin_stats.php:4`

## ISS-005 — Orphaned dead code controller for contact messaging
- **family:** IF-7
- **claim:** `contact_messages_controller.php` (194 LOC) represents an abandoned implementation of user messaging; client script `contact_script.js:1` routes to `user_messages_controller.php`, leaving this controller uncalled and dead.
- **severity:** low
- **citations:** `app/controllers/contact_messages_controller.php:1`

## ISS-006 — SQL string interpolation instead of prepared parameter binding
- **family:** IF-3a
- **claim:** `user_stats.php` interpolates `$accountId` directly into SQL strings (`CALL spGetUserStats($accountId, 2, @count)`) rather than using prepared statement parameter binding (`?`).
- **severity:** medium
- **citations:** `app/controllers/user_stats.php:21` · `app/controllers/user_stats.php:25`

## ISS-007 — Unsalted SHA-256 password hash verification
- **family:** IF-1
- **claim:** `login_controller.php` compares password hashes using raw `hash('sha256', $pass)` without per-user salts, exposing stored passwords to rainbow table and dictionary attacks.
- **severity:** high
- **citations:** `app/controllers/login_controller.php:32`

## ISS-008 — [RESOLVED] Asymmetric weekly conflict check omits future one-time bookings
- **status:** resolved
- **resolution:** Fixed in commit `44c5077`. Hardened `check_conflict.php:154-170` to bidirectionally query both recurring entries and upcoming one-time reservations matching the weekday (`schedule_day >= ? AND LOWER(DAYNAME(schedule_day)) = ?`). Verified via `tests/test_check_conflict_weekly_fix.php`.
- **family:** IF-3a
- **claim:** `check_conflict.php` checks weekly reservations with `AND schedule_day IS NULL`, omitting any future one-time bookings already scheduled on that weekday and allowing weekly reservations to conflict with future events.
- **severity:** medium
- **citations:** `app/controllers/check_conflict.php:154-170`
