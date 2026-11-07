<!-- DEEPINIT:START -->
# Views — Agent Context
Server-rendered PHP HTML views providing user dashboards, administrative panels, booking wizards, reports, and communication interfaces.

## Conventions & Invariants
- **Session Protection**: Views invoke `session_start()` and redirect unauthenticated requests (`!isset($_SESSION["id"])`) with `header("Location: ../index.php")`.
- **First-Paint Theme Class**: Synchronizes the `.light-mode` body class on initial server paint by inspecting `$_COOKIE['theme'] === 'light'` before emitting HTML.
- **Opportunistic Purging**: `user_homepage_view.php:2` invokes `require_once "../controllers/cleanup_schedules.php";` to remove past one-time bookings on dashboard render.
- **Client Sanitization**: All user-rendered session strings are escaped with `htmlspecialchars()` (`$fname`, `$lname`).

## Key Files
- `user_homepage_view.php`: Student and faculty dashboard with KPI metrics and quick actions.
- `admin_homepage_view.php`: Administrative control surface for approving/rejecting reservations.
- `reserve_view.php`: Interactive classroom booking form with conflict pre-checking.
- `calendar_view.php`: Institutional room allocation schedule calendar.
- `admin_reports_view.php`: Comprehensive multi-filter reservation reporting UI.
- Detailed documentation → `../../.ai/docs/components/user_portal.md`, `admin.md`, `reservations.md`
<!-- DEEPINIT:END -->
