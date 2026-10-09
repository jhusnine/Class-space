<!-- DeepInit extract | Component: reservations
Run ID: deepinit-20261009-130800
Input files processed: app/controllers/reserve_controller.php, app/controllers/check_conflict.php, app/controllers/my_pending.php, app/controllers/calendar_controller.php, app/controllers/cleanup_schedules.php, app/views/reserve_view.php, app/views/calendar_view.php, public/js/reserve_script.js, public/js/calendar_script.js, public/css/reserve_style.css, public/css/calendar_style.css
Generated: 2026-10-09T13:08:00+08:00 -->

# Component Analysis: reservations

## 1. Component Overview
- **Purpose**: Powers the core institutional room reservation lifecycle, covering reservation submission, real-time pre-submission conflict checking, schedule calendar display, user pending tracking, and opportunistic cleanup of past schedules.
- **Tech Stack**: PHP 8 (PDO transactions, DateTime formatting, strict types), MySQL Stored Procedures, Vanilla JavaScript (DOM manipulation, async conflict debouncing), FullCalendar/custom calendar rendering, CSS.
- **Key Files & Entry Points**:
  - `app/controllers/reserve_controller.php`: Main reservation submission endpoint.
  - `app/controllers/check_conflict.php`: Async endpoint testing booking slots for overlapping commitments.
  - `app/controllers/ai_suggest_controller.php`: Constraint Satisfaction Problem (CSP) slot and alternative room recommendation engine.
  - `app/controllers/calendar_controller.php`: Aggregates active schedules across all rooms for calendar rendering.
  - `app/controllers/my_pending.php`: Retrieves pending reservation requests for the logged-in user.
  - `app/controllers/cleanup_schedules.php`: Deletes expired one-time schedules.
  - `app/views/reserve_view.php` & `app/views/calendar_view.php`: Frontend presentation templates.
- **Complexity**: Complex [HIGH].
- **Certainty**: [HIGH].

## 2. Features & Capabilities
- **Dual Booking Types**: Supports both single-session ('one-time') reservations bound to a specific calendar date and recurring ('weekly') reservations bound to an ongoing day-of-the-week (`reserve_controller.php:88-123`).
- **Two-Phase Conflict Detection**: Client debounces input to `check_conflict.php` before submit; server executes an authoritative re-check inside `reserve_controller.php:181-238` prior to database insertion.
- **AI-Based CSP Recommendation Engine**: When conflicts arise, calculates operating-hour free intervals (07:00–21:00) and expands across same-building and campus facilities using a penalty heuristic function, returning top-3 optimal alternatives with 1-click application (`ai_suggest_controller.php:1-240`).
- **Double-Booking Calendar Inspector**: Detects simultaneous reservations in identical rooms, renders visual conflict warning tags on calendar events, and opens an interactive modal with direct redirection to AI slot resolution (`calendar_script.js:253-370`).
- **Transactional Staging**: Submits requests into `pendingschedule` within a database transaction, atomically recording a confirmation notification (`reserve_controller.php:240-269`).
- **Calendar Visualization**: Aggregates room bookings with room details and reserver names for institutional visibility (`calendar_controller.php:15-36`).
- **Opportunistic Schedule Purging**: Automatically purges past one-time reservations (`cleanup_schedules.php:11-22`).

## 3. Workflows & Behaviors
- **`WF-reservations:001` (Submit Reservation Request)** [HIGH]:
  - Trigger: User submits the booking modal in `app/views/reserve_view.php`.
  - Steps:
    1. `public/js/reserve_script.js` dispatches JSON payload with `room_id`, `start`, `end`, `type`, `date` or `dow` to `reserve_controller.php`.
    2. `app/controllers/reserve_controller.php:21` validates user session.
    3. `app/controllers/reserve_controller.php:95` validates time bounds (`start < end` in `H:i` format).
    4. `app/controllers/reserve_controller.php:181-224` executes SQL query searching for conflicting approved bookings in `schedule`.
    5. If conflict found, aborts with HTTP 200 `{"success": false, "error": "conflict"}` (`reserve_controller.php:229-238`).
    6. Starts PDO transaction (`reserve_controller.php:240`).
    7. Inserts record into `pendingschedule` with status `'pending'` (`reserve_controller.php:243-256`).
    8. Inserts alert into `notification` table confirming submission (`reserve_controller.php:259-266`).
    9. Commits transaction and responds with `{"success": true}` (`reserve_controller.php:268-269`).
- **`WF-reservations:002` (Real-Time Conflict Pre-Check)** [HIGH]:
  - Trigger: User selects room, date/dow, or adjusts time sliders in `reserve_view.php`.
  - Steps:
    1. `public/js/reserve_script.js` debounces input and issues GET request to `check_conflict.php`.
    2. `app/controllers/check_conflict.php:119-160` checks for overlapping rows in `schedule` where `schedule_start < ? AND schedule_end > ?`.
    3. Responds with `{"conflict": false, "available": true}` or `{"conflict": true, "message": "..."}`.
- **`WF-reservations:003` (Fetch Schedule Calendar)** [HIGH]:
  - Trigger: User opens `app/views/calendar_view.php`.
  - Steps:
    1. `public/js/calendar_script.js` requests `calendar_controller.php`.
    2. `app/controllers/calendar_controller.php:15-36` selects approved bookings joined with `room`, `hall`, and `account`.
    3. Client populates calendar grid with active reservation badges.
- **`WF-reservations:004` (CSP Slot & Room Recommendation)** [HIGH]:
  - Trigger: User selects a conflicting time/room slot in `reserve_view.php` or clicks "Find Alternative with AI" in `calendar_view.php`.
  - Steps:
    1. `public/js/reserve_script.js:198-255` issues GET request to `ai_suggest_controller.php`.
    2. `app/controllers/ai_suggest_controller.php:120-220` computes operating hours free intervals (07:00–21:00) and expands across same-hall and other campus rooms meeting capacity.
    3. Evaluates penalty heuristic scoring $H(C)$ and constructs deterministic explanations.
    4. Client renders cards in `#ai-suggestions-container` allowing 1-click application.
- **`WF-reservations:005` (Double-Booking Calendar Inspection)** [HIGH]:
  - Trigger: Overlapping reservations exist for identical rooms on the calendar grid.
  - Steps:
    1. `public/js/calendar_script.js:253-286` identifies pairwise interval overlaps in identical rooms during client render.
    2. Tags conflicting events with `.has-conflict` and warning badge pills.
    3. Clicking an event opens `#cal-inspector-modal` detailing the overlapping reservations with a direct link to `reserve_view.php` for AI-assisted rescheduling.

## 4. Business Rules
| Rule ID | Rule Statement | Criticality | Source (`file:line`) |
|---|---|---|---|
| `BR-reservations:001` | Booking start time must be strictly earlier than end time in 24-hour format | Core | `app/controllers/reserve_controller.php:95` |
| `BR-reservations:002` | One-time reservations must provide valid `Y-m-d` date; weekly must provide valid day-of-week string | Core | `app/controllers/reserve_controller.php:102-123` |
| `BR-reservations:003` | Slot overlap is defined as `existing_start < requested_end AND existing_end > requested_start` | Core | `app/controllers/reserve_controller.php:190-196` |
| `BR-reservations:004` | One-time reservation checks conflict against both same-date bookings and recurring bookings on matching weekday | Core | `app/controllers/reserve_controller.php:181-207` |
| `BR-reservations:005` | Reservation submissions enter `pendingschedule` with status `'pending'` awaiting admin approval | Core | `app/controllers/reserve_controller.php:247` |
| `BR-reservations:006` | Client receives an immediate pending alert in `notification` upon submitting a request | Supporting | `app/controllers/reserve_controller.php:259-266` |
| `BR-reservations:007` | Past one-time schedules where `schedule_day < CURDATE()` are purged on dashboard initialization | Peripheral | `app/controllers/cleanup_schedules.php:12-15` |

## 5. Data Models
- **Entity**: `schedule`
  - Purpose: Stores approved, active room allocations and bookings.
  - Source: `app/controllers/reserve_controller.php`, `calendar_controller.php`.
  - Properties:
    | Property | Type | Required | Description |
    |---|---|---|---|
    | `schedule_id` | INT | Yes | Primary key |
    | `room_id` | INT | Yes | Foreign key to `room` |
    | `account_id` | INT | Yes | Foreign key to `account` (reserver) |
    | `schedule_day_of_week` | VARCHAR(15) | No | Weekday name for recurring reservations ('Monday'..'Sunday') |
    | `schedule_day` | DATE | No | Specific date (`YYYY-MM-DD`) for one-time bookings; NULL for weekly |
    | `schedule_start` | TIME | Yes | Start time (`HH:MM:SS`) |
    | `schedule_end` | TIME | Yes | End time (`HH:MM:SS`) |
- **Entity**: `pendingschedule`
  - Purpose: Staging table for reservation requests awaiting administrative approval or rejection.
  - Source: `app/controllers/reserve_controller.php:243-256`.
  - Properties:
    | Property | Type | Required | Description |
    |---|---|---|---|
    | `pending_id` / `pending_schedule_id` | INT | Yes | Primary key |
    | `room_id` | INT | Yes | Target room ID |
    | `account_id` | INT | Yes | Requesting user ID |
    | `pending_schedule_day_of_week` | VARCHAR(15) | No | Recurring day name |
    | `pending_schedule_day` | DATE | No | Target specific date |
    | `pending_schedule_start` | TIME | Yes | Start time |
    | `pending_schedule_end` | TIME | Yes | End time |
    | `pending_schedule_status` | ENUM/VARCHAR | Yes | Status: 'pending', 'approved', 'rejected' |

## 6. Integration Points
| Integration ID | Name | Type | Direction | Target | Source (`file:line`) |
|---|---|---|---|---|---|
| `IP-reservations:001` | Database Connection | shared-DB | In | `includes/database_include.php` | `app/controllers/reserve_controller.php:25` |
| `IP-reservations:002` | Room & Hall Catalog Lookup | shared-DB | In | `room`, `hall` tables | `app/controllers/reserve_controller.php:153-157` |
| `IP-reservations:003` | User Notification Write | shared-DB | Out | `notification` table | `app/controllers/reserve_controller.php:259` |
| `IP-reservations:004` | Stored Procedure `spGetMyPending` | API | Out | MySQL Database | `app/controllers/my_pending.php:26` |

## 7. User Roles & Access
- **Students & Faculty**: May query room conflicts, submit new reservation requests, view calendar schedules, and view their own pending bookings.
- **Unauthenticated Users**: Blocked with HTTP 401 or redirect to login.

## 8. Interfaces Exposed
- `POST app/controllers/reserve_controller.php`: Submits a reservation request.
- `GET app/controllers/check_conflict.php`: Checks availability for a room slot.
- `GET app/controllers/ai_suggest_controller.php`: Returns top-3 ranked CSP alternatives for conflicted slots.
- `GET app/controllers/calendar_controller.php`: Returns array of approved schedules.
- `GET app/controllers/my_pending.php`: Returns array of pending requests for active session.

## 9. Interfaces Consumed
| External Component | What is Imported | Import Location (`file:line`) |
|---|---|---|
| `core_shared` | `$host, $dbname, $db_user, $db_pass` | `app/controllers/reserve_controller.php:25` |
| `rooms` | `room_name, hall_name` | `app/controllers/reserve_controller.php:153` |
| `auth` | `$_SESSION['id']` | `app/controllers/reserve_controller.php:21` |

## 10. Legacy Warnings
- **Database Column Name Bug in Cleanup**: `cleanup_schedules.php:20` references `WHERE schedule_day IS NOT NULL` on `pendingschedule`, but the column name in `pendingschedule` is `pending_schedule_day` (causing an unhandled SQL error caught silently).
- **Asymmetric Weekly Conflict Check (RESOLVED)**: Previously `check_conflict.php:151-157` queried only `schedule_day IS NULL` when testing weekly reservations. Resolved in `check_conflict.php:154-170` by querying both recurring entries and upcoming one-time bookings on the matching weekday.
- **Uncoordinated Simultaneous Submissions**: Two users can submit pending requests for the exact same room slot at the same time; conflict checking is repeated during admin approval to prevent double-booking.

## 11. Design Rationale
| Pattern | Location | Rationale | Evidence | Certainty |
|---|---|---|---|---|
| Dual Conflict Check | `app/controllers/reserve_controller.php:180` | Pre-checks on client input and re-verifies on submission to minimize race conditions | `/* Preserve the existing server-side overlap check. */` | [HIGH] |
| Transactional Staging | `app/controllers/reserve_controller.php:240-268` | Guarantees atomic creation of both reservation request and user notification record | `$pdo->beginTransaction() ... $pdo->commit()` | [HIGH] |
