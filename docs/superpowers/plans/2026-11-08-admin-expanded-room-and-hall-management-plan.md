# Admin Expanded Room & Hall Management Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Provide administrators with full operational control to create, view, edit, and delete classrooms, laboratories, and campus buildings/halls using strictly existing database columns, with dynamic photo inheritance and foreign key deletion safeguards.

**Architecture:** A dual-tab admin management interface on `admin_manage_rooms_view.php` powered by a dedicated backend controller `admin_manage_rooms_controller.php`. Rooms dynamically inherit campus building photos from `public/images/` via `hall_id` (or standard campus grounds visual when standalone). Enforces admin privileges, HTTP 405 method validation, and foreign-key checks.

**Tech Stack:** PHP 8+, MySQL (PDO with transactions), Vanilla JavaScript (ES6+ async/fetch), CSS (Glassmorphic dark/light tokens), FontAwesome 6, PHPUnit/CLI test runner, Puppeteer.

## Global Constraints
- **Zero New Columns**: Operate strictly on existing database columns in `room` (`room_id`, `hall_id`, `room_name`, `room_capacity`, `room_type`, `room_has_ac`, `room_status`) and `hall` (`hall_id`, `hall_name`, `hall_desc`). Zero schema alterations or `ALTER TABLE` statements.
- **Admin Authentication**: Every mutation strictly queries `account_is_admin = 1` from MySQL using `$_SESSION["id"]`.
- **HTTP Method Enforcement**: Mutations (`create_room`, `update_room`, `delete_room`, `create_hall`, `update_hall`, `delete_hall`) require HTTP `POST`; non-POST requests return HTTP 405 `method_not_allowed`.
- **Information Disclosure Prevention (CWE-209)**: Log raw exceptions via `error_log` internally and return generic client-safe messages.
- **Relational Integrity**: Deleting a hall is rejected if rooms still reference it; deleting a room safely cleans up associated records in an atomic transaction.

---

### Task 1: Backend Controller (`admin_manage_rooms_controller.php`) & Integration Test Suite

**Files:**
- Create: `app/controllers/admin_manage_rooms_controller.php`
- Test: `tests/test_admin_room_management.php`

**Interfaces:**
- Produces:
  - `POST /app/controllers/admin_manage_rooms_controller.php?action=create_room` -> JSON `{"success": true, "message": "...", "data": {...}}`
  - `POST /app/controllers/admin_manage_rooms_controller.php?action=update_room` -> JSON `{"success": true, "message": "..."}`
  - `POST /app/controllers/admin_manage_rooms_controller.php?action=delete_room` -> JSON `{"success": true, "message": "..."}`
  - `POST /app/controllers/admin_manage_rooms_controller.php?action=update_status` -> JSON `{"success": true, "message": "..."}`
  - `GET /app/controllers/admin_manage_rooms_controller.php?action=get_rooms` -> JSON `{"success": true, "data": [...]}`
  - `GET /app/controllers/admin_manage_rooms_controller.php?action=get_halls` -> JSON `{"success": true, "data": [...]}`
  - `POST /app/controllers/admin_manage_rooms_controller.php?action=create_hall` -> JSON `{"success": true, "message": "...", "data": {...}}`
  - `POST /app/controllers/admin_manage_rooms_controller.php?action=update_hall` -> JSON `{"success": true, "message": "..."}`
  - `POST /app/controllers/admin_manage_rooms_controller.php?action=delete_hall` -> JSON `{"success": true, "message": "..."}`

- [ ] **Step 1: Write the failing integration test**

Create `tests/test_admin_room_management.php` verifying authentication rejection, HTTP 405 method checking, room CRUD with hall linking, hall CRUD, and foreign key deletion prevention.

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/test_admin_room_management.php`  
Expected: FAIL (controller does not exist yet).

- [ ] **Step 3: Implement `app/controllers/admin_manage_rooms_controller.php`**

Write the complete controller handling:
- Session check & MySQL admin privilege check (`account_is_admin = 1`).
- Enforce POST on mutation actions (`create_room`, `update_room`, `delete_room`, `create_hall`, `update_hall`, `delete_hall`, `update_status`).
- Actions: `get_rooms`, `get_halls`, `create_room`, `update_room`, `delete_room`, `create_hall`, `update_hall`, `delete_hall`, `update_status`.
- Validate boundaries (`room_name` <= 20, `room_type` <= 20, `hall_name` <= 20, `hall_desc` <= 250).
- Atomic transactions and error sanitization.

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/test_admin_room_management.php`  
Expected: PASS (Exit 0, all controller checks pass).

- [ ] **Step 5: Commit**

```bash
git add app/controllers/admin_manage_rooms_controller.php tests/test_admin_room_management.php
git commit -m "feat(api): add admin room and hall management controller with integration tests"
```

---

### Task 2: Admin Rooms & Halls View Layout & Modals (`admin_manage_rooms_view.php`)

**Files:**
- Modify: `app/views/admin_manage_rooms_view.php`

**Interfaces:**
- Consumes: Controller endpoints from Task 1.
- Produces: Segmented tab switcher (`#tab-btn-rooms`, `#tab-btn-halls`), `#tab-rooms-content`, `#tab-halls-content`, `#modal-room`, `#modal-hall`, and `#modal-delete-room`.

- [ ] **Step 1: Write markup for Segmented Tab Switcher**

Add tab toggle header under `.page-header` in `admin_manage_rooms_view.php`:
- `[ Rooms Directory ]` (`#tab-btn-rooms`, active)
- `[ Campus Buildings & Halls ]` (`#tab-btn-halls`)

- [ ] **Step 2: Update Tab 1 (Rooms Directory) with Enhanced Column Headers & Controls**

- Add Hall filter select dropdown `#filter-hall` in toolbar next to status chips and search bar.
- Add `+ Add Room` button (`#btn-open-create-room`).
- Update table headers: `#`, `Room`, `Classification`, `Capacity & Features`, `Status`, `Actions`.

- [ ] **Step 3: Create Tab 2 (Campus Buildings & Halls) Container**

Add `#tab-halls-content` with:
- Toolbar: Search input `#hall-search-input` and `+ Add Building` button (`#btn-open-create-hall`).
- Grid container `#halls-grid` for building cards.

- [ ] **Step 4: Add Modal Dialogs with Accessibility Attributes**

Add:
- `#modal-room` (`role="dialog"` `aria-modal="true"`): Name, Hall selector (with `None - Standalone Facility`), Type select, Capacity number, AC checkbox toggle, Status select, and live dynamic preview card (`#room-preview-card`).
- `#modal-hall` (`role="dialog"` `aria-modal="true"`): Name (`maxlength="20"`), Description (`maxlength="250"`).
- `#modal-delete-confirm` (`role="dialog"` `aria-modal="true"`): Dangerous action confirmation with details.

- [ ] **Step 5: Check PHP Syntax & Commit**

Run: `php -l app/views/admin_manage_rooms_view.php`  
Expected: `No syntax errors detected`.
```bash
git add app/views/admin_manage_rooms_view.php
git commit -m "feat(ui): update admin rooms view with segmented tabs, building directory, and modals"
```

---

### Task 3: Stylesheet Polish (`admin_manage_rooms_style.css`)

**Files:**
- Modify: `public/css/admin_manage_rooms_style.css`

**Interfaces:**
- Consumes: Markup selectors from Task 2.
- Produces: Glassmorphic tab pills, building card grid, thumbnail styling, preview card, and responsive rules.

- [ ] **Step 1: Style Segmented Tab Switcher**

Add CSS for `.segmented-tabs-wrapper`, `.tab-btn`, `.tab-btn.active`, and badge counters.

- [ ] **Step 2: Style Room Directory Columns & Thumbnails**

Add CSS for `.room-thumb-wrapper`, `.room-thumb-img`, `.room-meta-group`, `.room-hall-pill`, and `.amenity-chip-ac`.

- [ ] **Step 3: Style Building Card Grid (`#halls-grid`)**

Add CSS for `.halls-grid`, `.hall-card`, `.hall-card-banner`, `.hall-card-body`, `.hall-card-title`, `.hall-room-count-badge`, and `.hall-card-actions`.

- [ ] **Step 4: Style Modal Controls & Dynamic Room Preview**

Add CSS for `#room-preview-card`, `.preview-banner`, `.preview-body`, `.form-grid`, `.form-group`, and modal action buttons.

- [ ] **Step 5: Add Responsive Media Queries**

Ensure smooth collapse on tablet (`< 900px`) and mobile (`< 680px`).

- [ ] **Step 6: Commit**

```bash
git add public/css/admin_manage_rooms_style.css
git commit -m "style(admin): add segmented tab, building card, and modal preview styles"
```

---

### Task 4: Frontend Controller Script (`admin_manage_rooms_script.js`)

**Files:**
- Modify: `public/js/admin_manage_rooms_script.js`

**Interfaces:**
- Consumes: `admin_manage_rooms_controller.php` endpoints from Task 1, DOM elements from Task 2.
- Produces: Asynchronous data loading, tab switching, real-time filtering, live preview updater, modal handlers, and toast notifications.

- [ ] **Step 1: Implement Tab Switching & State Management**

Add handlers to switch between `rooms` and `halls` tabs, updating active classes and loading corresponding datasets.

- [ ] **Step 2: Implement Room Catalog Rendering & Real-Time Filters**

- Update `renderTable()` to display building thumbnails (`getBuildingImage(room.hall_name)`), room classification badges, capacity + AC chips, quick status dropdowns, and action buttons.
- Wire search input and `#filter-hall` dropdown to filter rooms in real time.

- [ ] **Step 3: Implement Live Dynamic Preview in Room Modal**

Add event listeners on `#room-name-input`, `#room-hall-select`, `#room-type-select`, and `#room-ac-input` to immediately update `#room-preview-card` thumbnail, title, and badges as the admin types.

- [ ] **Step 4: Implement Room CRUD Form Handlers**

- `openCreateRoomModal()` & `openEditRoomModal(roomId)`.
- Submit to `action=create_room` or `action=update_room`.
- Delete room with active bookings check via `action=delete_room`.

- [ ] **Step 5: Implement Hall Directory Rendering & CRUD Handlers**

- `loadHalls()` and render `#halls-grid` building cards with photos, room counts, and descriptions.
- `openCreateHallModal()` & `openEditHallModal(hallId)`.
- Submit to `action=create_hall` or `action=update_hall`.
- Delete hall with assignment guard via `action=delete_hall`.

- [ ] **Step 6: Syntax Check with `node -c` & Commit**

Run: `node -c public/js/admin_manage_rooms_script.js`  
Expected: Exit 0.
```bash
git add public/js/admin_manage_rooms_script.js
git commit -m "feat(script): add async room and hall management with live preview and filter interactions"
```

---

### Task 5: End-to-End Verification & Puppeteer Visual Walkthrough

**Files:**
- Modify: `CHANGELOG.md`

- [ ] **Step 1: Run Full Test Suite**

Run: `Get-ChildItem -Path "tests" -Filter "*.php" | ForEach-Object { php $_.FullName }`  
Expected: All test suites PASS (Exit 0).

- [ ] **Step 2: Browser Verification via Puppeteer**

- Log into admin portal (`william.braga@gmail.com`).
- Navigate to `admin_manage_rooms_view.php`.
- Verify Tab 1 (Rooms Directory): Check building thumbnails, quick status switcher, and filters.
- Open Room Modal: Verify live dynamic preview updates.
- Switch to Tab 2 (Campus Buildings & Halls): Verify building cards grid, banner photos, and room counts.
- Test creating a room and creating/editing a hall.
- Capture screenshots for evidence.

- [ ] **Step 3: Update `CHANGELOG.md` & Final Commit**

Update `CHANGELOG.md` under `## [Unreleased] - 2026-11-08`.
```bash
git add CHANGELOG.md
git commit -m "docs(changelog): record expanded room and hall management module updates"
```
