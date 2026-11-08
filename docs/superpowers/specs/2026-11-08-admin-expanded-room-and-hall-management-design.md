# Design Specification: Admin Expanded Room & Hall Management Module

**Date**: 2026-11-08  
**Author**: Antigravity  
**Status**: Approved (Brainstorming Phase Completed)  
**Target Views & Assets**:
- Backend Controller: `app/controllers/admin_manage_rooms_controller.php`
- Admin View: `app/views/admin_manage_rooms_view.php`
- Stylesheet: `public/css/admin_manage_rooms_style.css`
- Client Script: `public/js/admin_manage_rooms_script.js`
- Test Suite: `tests/test_admin_room_management.php`

---

## 1. Overview & Business Context

Administrators in ClassSpace require full operational authority to create, update, and manage campus classrooms, laboratories, and buildings. Currently, `admin_manage_rooms_view.php` only supports viewing existing reservation schedules and toggling quick room status (`available`, `unavailable`, `maintenance`).

This module expands administrator controls to provide:
1. **Full Room Catalog CRUD**: Creation, detailed editing (name, hall assignment, capacity, room classification/type, air conditioning toggle), and safe deletion of rooms.
2. **Campus Halls & Buildings Management**: A dedicated management tab displaying building banner cards with descriptions, total room counts, and full CRUD for campus halls.
3. **Zero-Schema Invariant**: Strictly uses existing columns in `room` and `hall` without any database schema modifications or migrations.
4. **Dynamic Visual Integration**: Rooms automatically inherit high-resolution campus building imagery from their associated hall (with fallback to campus grounds for standalone spaces).

---

## 2. Data Model & Attribute Mapping (Zero Schema Alterations)

The implementation strictly operates on existing database tables and columns:

### A. `room` Table Mapping
| Field | Type | Constraint | Usage in Manager |
|---|---|---|---|
| `room_id` | `INT UNSIGNED` | Primary Key, Auto-increment | Unique identifier |
| `hall_id` | `TINYINT UNSIGNED` | Foreign Key to `hall.hall_id`, Nullable | Linked building / hall, or `NULL` for standalone spaces |
| `room_name` | `VARCHAR(20)` | Max 20 chars | Room code / name (e.g. `MH101`, `PL204`, `Covered Court`) |
| `room_capacity` | `SMALLINT UNSIGNED` | Positive Integer | Seating / student capacity (e.g. `40`, `50`) |
| `room_type` | `VARCHAR(20)` | Max 20 chars | Classification (`LECTURE`, `LABORATORY`, `AUDIO-VISUAL`, `CLINIC`, `SPORTS`, `OFFICE`, `OTHER`) |
| `room_has_ac` | `TINYINT(1)` | `0` or `1` | Air conditioning toggle (`1` = AC, `0` = Non-AC) |
| `room_status` | `VARCHAR(20)` | Max 20 chars | `available`, `unavailable`, `maintenance` |

### B. `hall` Table Mapping
| Field | Type | Constraint | Usage in Manager |
|---|---|---|---|
| `hall_id` | `TINYINT UNSIGNED` | Primary Key, Auto-increment | Unique building identifier |
| `hall_name` | `VARCHAR(20)` | Unique, Max 20 chars | Building title (e.g. `Plaridel`, `Melchora`, `Rizal`, `Balagtas`, `Gymnasium`, `Ople`) |
| `hall_desc` | `VARCHAR(250)` | Nullable, Max 250 chars | Facility description / overview text |

### C. Photo & Visual Association Rule
- Rooms do not require a separate image column. When a room is assigned to a hall (e.g., `hall_name = 'Plaridel'`), the frontend dynamically renders its visual from `public/images/plaridel.jpeg`.
- If `hall_id IS NULL` (standalone facility), the interface renders `public/images/login_background.jpg` with a `"Standalone Facility"` badge.
- When an admin reassigns a room to another hall, the photo, building badge, and description context update in real time.

---

## 3. UI/UX Architecture (`admin_manage_rooms_view.php`)

### A. Segmented Tab Switcher
Located immediately below the page header:
- `[ Rooms Directory (Total: X) ]` (Active by default)
- `[ Campus Buildings & Halls (Total: Y) ]`

### B. Tab 1: Rooms Directory
- **Toolbar**:
  - Filter chips: `Total`, `Available`, `Unavailable`, `Maintenance`.
  - Building selector dropdown: `All Buildings`, `Melchora`, `Plaridel`, `Rizal`, `Balagtas`, `Gymnasium`, `Ople`, and `Standalone (No Hall)`.
  - Real-time search input: Searches across room name, hall name, and room classification.
  - Primary Action Button: `+ Add Room`.
- **Table Columns**:
  1. `#`: Sequential index / room ID.
  2. **Room**: Building thumbnail + Room Name (`MH101`) + Hall pill.
  3. **Classification**: Room Type pill (`LECTURE`, `LABORATORY`, etc.).
  4. **Capacity & Features**: Seat count (`45 seats`) + Air conditioning badge (`AC` or `Non-AC`).
  5. **Status**: Quick status dropdown pill (`Available`, `Unavailable`, `Maintenance`) with instant asynchronous update.
  6. **Actions**:
     - Edit (`.action-edit`): Opens Room Editor Modal.
     - Reservations (`.action-view`): Opens existing Reservation History Modal.
     - Delete (`.action-delete`): Opens Delete Room Confirmation Modal.

### C. Tab 2: Campus Buildings & Halls
- **Toolbar**:
  - Search bar for halls.
  - Primary Action Button: `+ Add Hall`.
- **Building Card Grid**:
  - Visual cards with:
    - Building banner image (`melchora.jpg`, `plaridel.jpeg`, etc.).
    - Building Name (`Plaridel Hall`).
    - Assigned rooms count badge (`16 rooms assigned`).
    - Description paragraph (`hall_desc`).
    - Actions: `Edit Hall` and `Delete Hall` (guarded by room assignment check).

---

## 4. Modal Specifications

### A. Room Modal (`#modal-room` - Create & Edit)
- **Title**: Dynamic (`Create New Room` / `Edit Room - <Room Name>`).
- **Fields**:
  - Room Name (`room_name`, required, `maxlength="20"`).
  - Building Assignment (`hall_id`, dropdown of all halls + `None (Standalone Facility)` option).
  - Room Classification (`room_type`, select: `LECTURE`, `LABORATORY`, `AUDIO-VISUAL`, `CLINIC`, `SPORTS`, `OFFICE`, `OTHER`).
  - Seating Capacity (`room_capacity`, number input, `min="1"`, `max="999"`).
  - Air Conditioning (`room_has_ac`, checkbox toggle: `Air Conditioned`).
  - Room Status (`room_status`, select: `available`, `unavailable`, `maintenance`).
- **Live Dynamic Preview**: Side preview box rendering the inherited hall image, room badge, and status pill updating in real time as the administrator modifies form fields.
- **Actions**: Cancel & Save (`Create Room` / `Save Changes`).

### B. Hall Modal (`#modal-hall` - Create & Edit)
- **Title**: Dynamic (`Add Campus Building` / `Edit Building - <Hall Name>`).
- **Fields**:
  - Building Name (`hall_name`, required, `maxlength="20"`).
  - Description (`hall_desc`, textarea, `maxlength="250"`, character counter).
- **Actions**: Cancel & Save (`Create Hall` / `Save Changes`).

### C. Room Deletion Modal (`#modal-delete-room`)
- Displays room name and building.
- Queries `schedule` and `pendingschedule` for active bookings (`schedule_day >= CURDATE()`).
- Warns admin if bookings exist; requires confirmation.

### D. Hall Deletion Modal (`#modal-delete-hall`)
- Enforces relational safety guard:
  - If rooms currently reference `hall_id`, deletion is blocked with a message: *"Cannot delete this building because X room(s) are currently assigned to it. Please reassign those rooms before deleting."*

---

## 5. Backend Controller Architecture (`admin_manage_rooms_controller.php`)

### A. Security & Middleware
- Safe session initialization: `if (session_status() === PHP_SESSION_NONE) session_start();`
- Admin privilege verification: Queries `account_is_admin = 1` in MySQL using `$_SESSION["id"]`.
- Method enforcement: Enforces `$_SERVER['REQUEST_METHOD'] === 'POST'` for all state-changing endpoints (`create_room`, `update_room`, `delete_room`, `create_hall`, `update_hall`, `delete_hall`), returning HTTP 405 on violation.
- Error sanitization: Internal logging with `error_log` and client-safe generic responses (CWE-209 mitigation).

### B. Supported Endpoints
1. `get_rooms`:
   - Returns all rooms joined with `hall_name` and `hall_desc`, ordered by `hall_name ASC, room_name ASC`.
2. `get_halls`:
   - Returns all halls joined with count of assigned rooms (`COUNT(r.room_id) AS room_count`).
3. `create_room`:
   - Enforces `room_name` uniqueness within the target hall.
   - Inserts into `room`.
4. `update_room`:
   - Enforces `room_name` uniqueness excluding current `room_id`.
   - Updates `room`.
5. `delete_room`:
   - In a transaction, cleans up child records in `schedule`, `pendingschedule`, `history`, and deletes from `room`.
6. `create_hall`:
   - Validates `hall_name` uniqueness.
   - Inserts into `hall`.
7. `update_hall`:
   - Validates `hall_name` uniqueness excluding current `hall_id`.
   - Updates `hall`.
8. `delete_hall`:
   - Verifies that zero rooms are assigned to `hall_id`.
   - Deletes from `hall`.

---

## 6. Verification & Automated Testing Plan

1. **Unit & Integration Suite (`tests/test_admin_room_management.php`)**:
   - Subrequest invocation with admin session mocking.
   - Tests `get_rooms` and `get_halls`.
   - Tests Room CRUD (create, update, status toggle, safe delete).
   - Tests Hall CRUD (create, update, delete rejection when rooms assigned, successful delete when empty).
   - Tests validation boundaries (name ≤ 20, desc ≤ 250, method not allowed 405, unauthorized 401/403).
2. **Regression Verification**:
   - Run existing test suites (`tests/*.php`) to verify user-side room visibility, conflict checks, and AI suggestions remain 100% green.
3. **Puppeteer UI Verification**:
   - Full browser walkthrough of Tab 1 (Rooms) and Tab 2 (Halls).
   - Testing Room and Hall modals, dynamic image previews, and form submissions.
