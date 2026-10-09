# AI Conflict Detection & CSP Recommendation Engine Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement a Constraint Satisfaction Problem (CSP) recommendation engine and AI conflict detection workflow across ClassSpace's backend controllers, reservation form, and calendar views, resolving dead-end conflict alerts and fixing the asymmetric weekly conflict bug.

**Architecture:** Layered Procedural MVC: `check_conflict.php` acts as the deterministic gatekeeper; `ai_suggest_controller.php` acts as the CSP recommendation engine finding open intervals (07:00–21:00) and peer rooms; `reserve_script.js` and `calendar_script.js` provide interactive AI recommendation cards with 1-click auto-apply.

**Tech Stack:** PHP 8.x (PDO), MySQL 8.x, Vanilla JavaScript (ES6+ async fetch), Vanilla CSS (Custom Properties tokens).

---

## Global Constraints

- Runtime: PHP 8.x with PDO MySQL; zero external Composer packages or heavy third-party dependencies.
- Operating Hours: Institutional day bounds are 07:00 to 21:00 in 30-minute intervals.
- Database Driver: Must use prepared PDO queries with parameter binding via `includes/database_include.php`.
- Styling: Vanilla CSS conforming to existing `global.css` design system tokens (light/dark theme compatibility).
- Testing: Automated CLI PHP verification scripts placed in `tests/` directory with exit code 0 on success.

---

## File Structure & Responsibilities

| File Path | Action | Responsibility |
| :--- | :---: | :--- |
| `app/controllers/check_conflict.php` | Modify | Fix asymmetric weekly-vs-one-time check ([Issue #8](https://github.com/jhusnine/Class-space/issues/8)); return `has_suggestions: true`. |
| `app/controllers/ai_suggest_controller.php` | Create | CSP engine finding free gaps in target room and available peer rooms in same hall; heuristic scoring. |
| `tests/test_check_conflict_weekly_fix.php` | Create | Automated verification for bidirectional conflict detection. |
| `tests/test_ai_suggest_controller.php` | Create | Automated verification of CSP recommendation payload and scoring order. |
| `app/views/reserve_view.php` | Modify | Add `#ai-suggestions-container` skeleton markup. |
| `public/js/reserve_script.js` | Modify | Fetch AI suggestions on conflict; implement 1-click `applySuggestion()`. |
| `public/css/reserve_style.css` | Modify | Glassmorphic card styling, AI badges, and smooth entrance animations. |
| `app/views/calendar_view.php` | Modify | Add calendar inspector modal container. |
| `public/js/calendar_script.js` | Modify | Flag double-booked same-room collisions with warning badge; inspector modal. |
| `public/css/calendar_style.css` | Modify | Styling for calendar conflict badges and inspector dialog. |

---

## Tasks

### Task 1: Fix Asymmetric Weekly Conflict Check & Attach Suggestion Signal

**Files:**
- Modify: `app/controllers/check_conflict.php`
- Create: `tests/test_check_conflict_weekly_fix.php`

**Interfaces:**
- Consumes: `$_GET['room_id']`, `$_GET['start']`, `$_GET['end']`, `$_GET['type']`, `$_GET['date']`, `$_GET['dow']`.
- Produces: JSON `{ "conflict": bool, "available": bool, "has_suggestions": bool, "message": string }`.

- [x] **Step 1: Write automated failing test script**
  Create `tests/test_check_conflict_weekly_fix.php` to simulate checking a weekly booking on a day with an existing one-time reservation in the database.
- [x] **Step 2: Run test to confirm failure**
  Execute `php tests/test_check_conflict_weekly_fix.php` and verify it fails (as weekly query currently ignores `schedule_day`).
- [x] **Step 3: Modify `app/controllers/check_conflict.php`**
  Update the weekly check query to also detect overlapping future one-time reservations (`schedule_day >= CURDATE() AND LOWER(DAYNAME(schedule_day)) = ?`). Add `'has_suggestions' => true` to conflict response payloads.
- [x] **Step 4: Run test to verify success**
  Re-run `php tests/test_check_conflict_weekly_fix.php` and confirm exit code 0.
- [x] **Step 5: Git commit**
  Commit message: `fix(reservations): bidirectional weekly conflict check and suggestion signal (#8)`

---

### Task 2: Build the CSP Recommendation Engine Controller

**Files:**
- Create: `app/controllers/ai_suggest_controller.php`
- Create: `tests/test_ai_suggest_controller.php`

**Interfaces:**
- Consumes: `$_GET['room_id']`, `$_GET['date']`, `$_GET['dow']`, `$_GET['start']`, `$_GET['end']`, `$_GET['type']`, `$_GET['capacity']`.
- Produces: JSON `{ "success": true, "conflict_detected": true, "ai_summary": string, "suggestions": array }`.

- [x] **Step 1: Write automated test for CSP suggestions**
  Create `tests/test_ai_suggest_controller.php` asserting that when room 14 is conflicted, the controller returns a 200 JSON payload with `suggestions` array containing valid alternative times or rooms.
- [x] **Step 2: Run test to verify failure**
  Execute `php tests/test_ai_suggest_controller.php` and verify failure (file does not yet exist).
- [x] **Step 3: Implement `app/controllers/ai_suggest_controller.php`**
  - Implement operating hour discretization (`07:00` to `21:00`, 30-min steps).
  - Implement Phase 1: Free gap interval detection for the conflicting room.
  - Implement Phase 2: Candidate room search within the same hall with $\text{capacity} \ge \text{target}$ and $\text{status} = \text{'available'}$.
  - Implement Phase 3: Heuristic penalty scoring function:
    $$\text{Penalty} = (\Delta_{\text{time}} \times 1.0) + (\text{DiffHall} \times 60) + (\Delta_{\text{cap}} \times 0.5) + (\text{Mismatch} \times 40)$$
  - Implement Phase 4: AI narrative generator producing contextual reasoning for each suggestion.
- [x] **Step 4: Run test to verify passing**
  Execute `php tests/test_ai_suggest_controller.php` and verify exit code 0 and valid suggestions output.
- [x] **Step 5: Git commit**
  Commit message: `feat(ai): implement CSP slot and room recommendation controller`

---

### Task 3: Integrate AI Suggestions & 1-Click Apply into Reservation View

**Files:**
- Modify: `app/views/reserve_view.php`
- Modify: `public/js/reserve_script.js`
- Modify: `public/css/reserve_style.css`

**Interfaces:**
- Consumes: JSON response from `ai_suggest_controller.php`.
- Produces: Dynamic DOM cards `#ai-suggestions-container` with click handler `applySuggestion(index)`.

- [x] **Step 1: Add HTML skeleton markup**
  Add `<div id="ai-suggestions-container" class="ai-suggestions-container" style="display:none;"></div>` in `app/views/reserve_view.php` beneath `#conflict-warning`.
- [x] **Step 2: Add styles in `public/css/reserve_style.css`**
  Add CSS tokens for `.ai-suggestions-container`, `.ai-card`, `.ai-badge`, `.btn-apply-suggestion`, with smooth entrance transitions and light/dark theme variables.
- [x] **Step 3: Update `public/js/reserve_script.js`**
  - Modify `checkConflicts()`: When `res.conflict` is true, trigger `fetch('../controllers/ai_suggest_controller.php?' + params)`.
  - Render suggestion cards inside `#ai-suggestions-container`.
  - Implement `applySuggestion(suggestion)`: Automatically update `#hall-select`, `#room-select`, `#schedule-start`, `#schedule-end`, display toast alert, and trigger `checkConflicts()`.
  - When `res.conflict` is false, hide `#ai-suggestions-container`.
- [x] **Step 4: Manual browser validation**
  Open reservation form, select an occupied slot, verify AI recommendation cards appear, click "Apply Suggestion", and verify inputs update cleanly.
- [x] **Step 5: Git commit**
  Commit message: `feat(reservations): interactive AI suggestion cards and 1-click apply`

---

### Task 4: Add Calendar Double-Booking Badges & AI Slot Inspector

**Files:**
- Modify: `app/views/calendar_view.php`
- Modify: `public/js/calendar_script.js`
- Modify: `public/css/calendar_style.css`

**Interfaces:**
- Consumes: `allSchedules` and `myPending` arrays in `calendar_script.js`.
- Produces: Visual `.conflict-tag` badges on calendar cells and `#cal-inspector-modal`.

- [x] **Step 1: Add inspector modal markup to `app/views/calendar_view.php`**
  Insert modal dialog markup with title, event details, and "Find Alternative with AI" button.
- [x] **Step 2: Add CSS in `public/css/calendar_style.css`**
  Add styling for `.cal-event.has-conflict`, `.conflict-badge-pill`, and modal styling.
- [x] **Step 3: Update `public/js/calendar_script.js`**
  - In `renderWeek()`: Detect when multiple events share the exact same `room_name` with overlapping time ranges, attaching `.has-conflict` class and a warning indicator.
  - Add click listener on events to open the inspector modal with pre-configured parameters.
  - Add "Find Alternative with AI" action that redirects to `reserve_view.php` with pre-filled room and date query params.
- [x] **Step 4: Manual browser validation**
  Navigate to `calendar_view.php`, inspect overlapping bookings, and test the AI alternative redirection.
- [x] **Step 5: Git commit**
  Commit message: `feat(calendar): visual double-booking conflict badges and AI inspector modal`

---

### Task 5: End-to-End System Verification & Audit Update

**Files:**
- Modify: `.ai/docs/issues.md`
- Modify: `.ai/docs/components/reservations.md`
- Modify: `.ai/docs/decisions.md`

- [x] **Step 1: Execute all unit and regression tests**
  Run `php tests/test_check_conflict_weekly_fix.php` and `php tests/test_ai_suggest_controller.php`.
- [x] **Step 2: Update DeepInit Documentation**
  Mark Issue #8 as RESOLVED in `.ai/docs/issues.md` and add Architectural Decision Record `ADR-006: Heuristic Constraint Satisfaction Problem Engine for Conflict Resolution` in `.ai/docs/decisions.md`.
- [x] **Step 3: Run citation verification**
  Run `python .ai/verify_citations.py` to ensure 100% citation resolution across documentation layers.
- [x] **Step 4: Git commit & push**
  Commit message: `chore(docs): mark issue 8 resolved and document CSP architecture`
