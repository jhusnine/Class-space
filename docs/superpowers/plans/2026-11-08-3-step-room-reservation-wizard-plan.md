# 3-Step Room Reservation Wizard Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Transform the room reservation page (`app/views/reserve_view.php`) from a single long form with a popup modal into a 3-step wizard featuring an interactive progress stepper, fluid transitions, and an integrated in-page review card, adhering to `/ui-ux-pro-max` guidelines while strictly maintaining ClassSpace's established slate & sky blue palette.

**Architecture:** A 3-step client-side wizard architecture inside `.reservation-card` on `app/views/reserve_view.php` coordinated by `public/js/reserve_script.js` and styled by `public/css/reserve_style.css`. The 3 steps separate Space & Purpose (Step 1), Date & Schedule with AI CSP Conflict Resolution (Step 2), and an Integrated In-Page Review Receipt (Step 3).

**Tech Stack:** PHP 8+, Vanilla JavaScript (ES6+ async/fetch), CSS (Glassmorphic dark/light tokens), FontAwesome 6, PHPUnit/CLI test runner, Puppeteer.

## Global Constraints
- **Strict Color Tokens**: Maintain ClassSpace's established color scheme: Dark mode (`--background-slate: #000C12`, `--surface-slate: #0B171E`, `--border-slate: #263941`, `--accent-sky: #38bdf8`), Light mode (`--background-slate: #f8fafc`, `--surface-slate: #ffffff`, `--border-slate: #e2e8f0`, `--accent-sky: #0284c7`). No rogue color schemes.
- **Zero Schema Changes**: Operates strictly on existing `reserve_controller.php` payload structure (`subject`, `room_id`, `type`, `start`, `end`, `date`, `dow`, `until`, `pending_id`).
- **Complete Feature Parity**: Retain URL parameter pre-filling (`hall_id`, `room_id`, `date`, `dow`, `start`, `end`, `until`, `auto_check`, `reschedule_pending_id`), automated conflict checking, and ClassSpace AI recommendation cards with 1-click alternative resolution.
- **Integrated In-Page Review**: Eliminate the disruptive popup modal `#booking-review` in favor of an in-page confirmation receipt in Step 3.

---

### Task 1: Wizard Markup & Stepper Skeleton (`app/views/reserve_view.php`) & Integration Test

**Files:**
- Modify: `tests/test_reserve_view_integration.php`
- Modify: `app/views/reserve_view.php`

**Interfaces:**
- Produces DOM containers:
  - Stepper: `#wizard-stepper`, `.stepper-progress-bar`, `.step-item[data-step="1"]`, `[data-step="2"]`, `[data-step="3"]`
  - Step 1: `#wizard-step-1` with `#reservation-subject`, `#hall-select`, `#room-select`, `#room-preview`, `#btn-step1-next`
  - Step 2: `#wizard-step-2` with `input[name="res-type"]`, `#schedule-date`, `#schedule-dow`, `#schedule-until`, `#schedule-start`, `#schedule-end`, `#conflict-warning`, `#ai-suggestions-container`, `#btn-step2-back`, `#btn-step2-next`
  - Step 3: `#wizard-step-3` with `#reservation-summary-card`, summary fields (`#summary-subject`, `#summary-building`, `#summary-room`, `#summary-schedule`, `#summary-time`), `#btn-step3-back`, and final submission `#submit-btn`.

- [ ] **Step 1: Update integration test `tests/test_reserve_view_integration.php`**

Add assertions asserting the existence of `#wizard-stepper`, `#wizard-step-1`, `#wizard-step-2`, `#wizard-step-3`, `#btn-step1-next`, `#btn-step2-next`, `#reservation-summary-card`, and the removal of the old `#booking-review` modal.

- [ ] **Step 2: Run test to verify it fails (RED)**

Run: `php tests/test_reserve_view_integration.php`  
Expected: FAIL (stepper and wizard step containers do not exist yet).

- [ ] **Step 3: Update `app/views/reserve_view.php`**

- Add `.wizard-stepper` directly under `.card-header` in `.reservation-card`.
- Wrap existing subject and room controls inside `<div class="wizard-step active" id="wizard-step-1">`.
  - Add navigation bar with `<button type="button" class="btn-primary btn-wizard-next" id="btn-step1-next">Continue to Schedule <i class="fas fa-arrow-right"></i></button>`.
- Wrap schedule, conflict, and AI containers inside `<div class="wizard-step" id="wizard-step-2" style="display:none;">`.
  - Add navigation bar with `<button type="button" class="btn-secondary btn-wizard-back" id="btn-step2-back"><i class="fas fa-arrow-left"></i> Back to Space</button>` and `<button type="button" class="btn-primary btn-wizard-next" id="btn-step2-next">Proceed to Review <i class="fas fa-arrow-right"></i></button>`.
- Create `<div class="wizard-step" id="wizard-step-3" style="display:none;">`.
  - Add `#reservation-summary-card` containing visual banner, summary list (`#summary-subject`, `#summary-building`, `#summary-room`, `#summary-schedule`, `#summary-time`), and verified notice.
  - Add navigation bar with `<button type="button" class="btn-secondary btn-wizard-back" id="btn-step3-back"><i class="fas fa-arrow-left"></i> Back to Edit</button>` and `<button type="button" class="btn-primary submit-btn" id="submit-btn"><i class="fas fa-paper-plane"></i> Confirm & Submit Reservation</button>`.
- Remove legacy modal dialog `#booking-review`.

- [ ] **Step 4: Check PHP syntax & Run test (GREEN)**

Run: `php -l app/views/reserve_view.php`  
Expected: `No syntax errors detected`.  
Run: `php tests/test_reserve_view_integration.php`  
Expected: PASS (Exit 0).

- [ ] **Step 5: Commit**

```bash
git add app/views/reserve_view.php tests/test_reserve_view_integration.php
git commit -m "feat(ui): add 3-step wizard markup and progress stepper to reserve view"
```

---

### Task 2: Stylesheet Polish for Stepper & Wizard Steps (`public/css/reserve_style.css`)

**Files:**
- Modify: `public/css/reserve_style.css`

**Interfaces:**
- Consumes: Markup selectors from Task 1.
- Produces: CSS rules for `.wizard-stepper`, `.stepper-progress-bar`, `.step-item`, `.wizard-step`, `.wizard-nav-actions`, `.reservation-summary-card`, and responsive breakpoints.

- [ ] **Step 1: Style the Progress Stepper Component**

Add CSS for:
- `.wizard-stepper`: flex container with relative positioning, margin bottom 28px, padding 4px.
- `.stepper-track`: horizontal track with background `var(--border-slate)` and height 2px.
- `.stepper-progress-bar`: active colored fill with background `var(--accent-sky)`, box-shadow glow, and `transition: width 0.3s cubic-bezier(0.16, 1, 0.3, 1)`.
- `.step-item`: flex items with step pill styling, icon, label, and cursor pointer.
- `.step-item.active`: elevated surface, border-color `var(--accent-sky)`, text `var(--text-high)`.
- `.step-item.completed`: text `var(--text-low)`, green checkmark indicator `color: #10b981`.

- [ ] **Step 2: Style Wizard Step Containers & Animation**

Add CSS for:
- `.wizard-step`: display none when inactive.
- `.wizard-step.active`: display block with keyframe animation `fadeSlideIn` (`opacity: 0 -> 1; transform: translateY(8px) -> translateY(0)` over 0.25s).
- `.wizard-nav-actions`: flex container at bottom of each step, justifying back button to start and next/submit button to end, border-top 1px solid `var(--border-slate)`, padding-top 20px, margin-top 24px.

- [ ] **Step 3: Style Step 3 Reservation Summary Receipt Card**

Add CSS for:
- `.reservation-summary-card`: glassmorphic surface (`var(--surface-slate)` or `rgba(56, 189, 248, 0.03)`), border 1px solid `var(--border-slate)`, border-radius 14px, overflow hidden.
- `.summary-banner`: image banner with building photo, overlay gradient, room title, and capacity badge.
- `.summary-details-grid`: 2-column or list view with `.summary-row`, `.summary-label`, `.summary-value`, and `.summary-edit-link`.
- `.summary-status-box`: verified conflict-free badge with green checkmark and administrator routing reminder.

- [ ] **Step 4: Add Responsive Media Queries & Dark/Light Theme Tokens**

- Tablet & Mobile (`< 768px`): collapse stepper labels to numbers/compact mode, stack action buttons full-width.
- Ensure all components look sharp in both dark mode and `body.light-mode`.

- [ ] **Step 5: Verify CSS & Commit**

Run: token validation check or `git diff`.
```bash
git add public/css/reserve_style.css
git commit -m "style(reserve): add progress stepper, wizard step animations, and summary card styles"
```

---

### Task 3: Client-Side Stepper State Engine & Transitions (`public/js/reserve_script.js`)

**Files:**
- Modify: `public/js/reserve_script.js`

**Interfaces:**
- Consumes: Endpoints `room_controller.php`, `check_conflict.php`, `ai_suggest_controller.php`, `reserve_controller.php`.
- Produces: `goToStep(step)`, `validateStep1()`, `validateStep2()`, `populateReviewSummary()`, `submitReservation()`.

- [ ] **Step 1: Implement Wizard Navigation State Machine**

Add `let currentWizardStep = 1;` and `goToStep(stepNumber)`:
- If moving to Step 2: call `validateStep1()`. If false, stop and show toast.
- If moving to Step 3: call `validateStep1()` and `validateStep2()`. If false, stop and show toast.
- Update active classes on `#wizard-step-1`, `#wizard-step-2`, `#wizard-step-3`.
- Update stepper progress bar width ($0\% \rightarrow 50\% \rightarrow 100\%$) and step pill states (`active`, `completed`).

- [ ] **Step 2: Implement Step 1 & Step 2 Validation Functions**

- `validateStep1()`: verifies `#reservation-subject` is non-empty, `#room-select` has a valid selection and is not storage or under maintenance.
- `validateStep2()`: verifies start time and end time exist with `start < end`, date/dow present, and `#conflict-warning` is not visible.

- [ ] **Step 3: Implement Step 3 In-Page Summary Population**

`populateReviewSummary()`:
- Formats room name, building name, and resolves building image via `getBuildingImage()`.
- Formats schedule date (one-time) or recurrence with semester end date (weekly).
- Formats start and end times to 12-hour AM/PM.
- Populates `#summary-subject`, `#summary-building`, `#summary-room`, `#summary-schedule`, `#summary-time`, and banner image.

- [ ] **Step 4: Wire Navigation Buttons & Stepper Clicks**

- `#btn-step1-next` clicks -> `goToStep(2)`.
- `#btn-step2-back` clicks -> `goToStep(1)`.
- `#btn-step2-next` clicks -> `goToStep(3)`.
- `#btn-step3-back` clicks -> `goToStep(2)`.
- Quick edit buttons on summary card jump to Step 1 or Step 2.
- Stepper pill clicks: allow clicking step 1 from step 2 or 3; allow clicking step 2 from step 3.
- `#submit-btn` submits directly to `reserve_controller.php` with payload and handles redirect.

- [ ] **Step 5: Pre-fill & Reschedule Flow Integration**

- When arriving with URL pre-fills (`hall_id`, `room_id`, `reschedule_pending_id`, `auto_check=1`):
  - Populate inputs as before.
  - If reschedule is active or room is pre-selected, enable smooth navigation to Step 2/3.
  - If an alternative recommendation is applied (`applySuggestion`), maintain stepper state and auto-recheck conflicts.

- [ ] **Step 6: Syntax Check with `node -c` & Commit**

Run: `node -c public/js/reserve_script.js`  
Expected: Exit 0.
```bash
git add public/js/reserve_script.js
git commit -m "feat(script): add 3-step wizard navigation, validation, and in-page review population"
```

---

### Task 4: End-to-End Regression & Puppeteer Visual Verification

**Files:**
- Modify: `CHANGELOG.md`

- [ ] **Step 1: Run Full Test Suite**

Run: `Get-ChildItem -Path "tests" -Filter "*.php" | ForEach-Object { php $_.FullName }`  
Expected: All test suites PASS (Exit 0).

- [ ] **Step 2: Puppeteer Browser Walkthrough**

- Log in to ClassSpace (`william.braga@gmail.com`).
- Navigate to `http://localhost:8000/app/views/reserve_view.php`.
- Verify Step 1: Stepper at 0%, enter subject, choose Hall and Room, verify live room preview card, click Next.
- Verify Step 2: Stepper at 50%, select date & time, verify conflict checking and AI suggestion card rendering, click Next.
- Verify Step 3: Stepper at 100%, verify summary receipt card with room banner, schedule, and time, test clicking back to edit, submit reservation.
- Capture screenshots for evidence.

- [ ] **Step 3: Update `CHANGELOG.md` & Commit**

Update `CHANGELOG.md` under `## [Unreleased] - 2026-11-08`.
```bash
git add CHANGELOG.md
git commit -m "docs(changelog): record 3-step room reservation wizard updates"
```
