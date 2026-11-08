# 3-Step Room Reservation Wizard Design Specification

## 1. Overview & Objective
Transform the room reservation experience on `app/views/reserve_view.php` from a single monolithic form into an intuitive, elegant 3-step wizard with an interactive progress tracker, fluid step transitions, integrated in-page review, and `/ui-ux-pro-max` visual standards—strictly adhering to ClassSpace's dark/light slate color palette (`--background-slate: #000C12`, `--surface-slate: #0B171E`, `--border-slate: #263941`, `--accent-sky: #38bdf8`).

---

## 2. Architecture & Step Breakdown

```
[ Step 1: Space & Purpose ] ──── [ Step 2: Date & Schedule ] ──── [ Step 3: Review & Confirm ]
      Purpose / Subject                  One-time vs Weekly             Summary Receipt Card
      Building & Room Select             Date or Day-of-Week            Verified Conflict-Free Badge
      Live Room Preview Card             Time Slot (Start / End)        Direct Back-to-Edit Links
                                         Conflict & AI Suggestions      Final Submission Button
```

### Step 1: Space & Purpose (`#wizard-step-1`)
- **Reschedule Banner**: Shown when `$reschedulePendingId > 0` (`Rescheduling Pending Reservation #ID`).
- **Reservation Subject**: Text input `#reservation-subject` (`maxlength="150"`, required).
- **Location Selectors**:
  - Building dropdown (`#hall-select`)
  - Room dropdown (`#room-select`)
- **Live Room Preview Card (`#room-preview`)**:
  - Dynamically renders building photo banner, room title, building pill, seating capacity, and air conditioning chip as soon as a room is selected.
- **Navigation Controls**:
  - `[ Continue to Schedule → ]` (`#btn-step1-next`). Validates that both subject and room are chosen before proceeding.

### Step 2: Date & Schedule (`#wizard-step-2`)
- **Reservation Type**: Segmented radio toggle (`One-time` vs `Weekly (recurring)`).
- **Schedule Pickers**:
  - *One-time*: Date picker (`#schedule-date`, `min="YYYY-MM-DD"`).
  - *Weekly*: Day of week dropdown (`#schedule-dow`) + Semester Duration picker (`#schedule-until`) with active semester badge (`<i class="fas fa-graduation-cap"></i> 1st Semester`).
- **Time Grid**:
  - Start time (`#schedule-start`) & End time (`#schedule-end`).
- **Conflict Warning & AI Suggestions**:
  - Automated real-time conflict checking (`checkConflicts()`).
  - If a conflict is found: displays `#conflict-warning` and renders AI alternative room / slot recommendation cards (`#ai-suggestions-container`).
  - 1-click apply buttons (`Use This Room` / `Use This Slot`) automatically resolve the conflict and update fields.
- **Navigation Controls**:
  - `[ ← Back to Space ]` (`#btn-step2-back`).
  - `[ Proceed to Review → ]` (`#btn-step2-next`). Validates start/end times and verifies no active conflict before proceeding.

### Step 3: Integrated In-Page Review & Confirmation (`#wizard-step-3`)
- Replaces the legacy popup modal with an in-page **Reservation Confirmation Card** (`.reservation-summary-card`):
  - **Banner / Visual**: Building thumbnail with room title, building pill, and capacity badge.
  - **Summary Breakdown**:
    - **Subject**: Entered class or activity with quick pencil edit shortcut back to Step 1.
    - **Schedule**: Formatted date (e.g. *"Monday, November 10, 2026"*) or recurrence (e.g. *"Every Wednesday until Dec 31, 2026"*).
    - **Time Slot**: Formatted 12-hour duration (e.g. *"1:30 PM – 3:00 PM (1.5 hrs)"*).
    - **Status Notice**: Verified conflict-free green badge with administrator routing disclosure.
- **Navigation Controls**:
  - `[ ← Back to Edit ]` (`#btn-step3-back`).
  - `[ Confirm and Submit Request 🚀 ]` (`#submit-btn`). Handles async POST to `../controllers/reserve_controller.php`, shows spinner, and redirects on success.

---

## 3. Visual Stepper Component (`.wizard-stepper`)

Located at the top of `.reservation-card`:
- **Progress Track**: A 2px background track (`--border-slate`) with an active progress fill (`--accent-sky`) animating width from $0\%$ to $50\%$ to $100\%$.
- **Step Pills**:
  1. `[ 1. Space & Purpose ]` (door icon)
  2. `[ 2. Date & Schedule ]` (calendar-clock icon)
  3. `[ 3. Review & Confirm ]` (clipboard-check icon)
- **Step States**:
  - **Active**: Elevated surface, glowing accent outline, bold text, pulsing step dot.
  - **Completed**: Green checkmark icon, clickable to jump back directly without losing state.
  - **Upcoming**: Muted text, disabled until prior steps pass validation.

---

## 4. State Management & Navigation Logic

```javascript
let currentStep = 1;

function goToStep(stepNumber) {
    if (stepNumber === 2 && !validateStep1()) return;
    if (stepNumber === 3 && (!validateStep1() || !validateStep2())) return;
    
    // Hide all step containers
    document.querySelectorAll('.wizard-step').forEach(step => step.classList.remove('active'));
    // Activate target step
    document.getElementById(`wizard-step-${stepNumber}`).classList.add('active');
    
    // Update Stepper UI & progress line
    updateStepper(stepNumber);
    
    if (stepNumber === 3) {
        populateReviewSummary();
    }
    currentStep = stepNumber;
}
```

### Pre-fill & Reschedule Handling
- When landing with URL parameters (`hall_id`, `room_id`, `date`, `start`, `end`, `dow`, `reschedule_pending_id`):
  - Inputs populate automatically.
  - If all Step 1 and Step 2 fields are valid and conflict-free, the stepper allows direct navigation to Step 2 or Step 3.

---

## 5. CSS Styling Standards (`/ui-ux-pro-max`)

- **Strict Color Tokens**:
  - Dark Mode: Surface `#0B171E`, Borders `#263941`, Sky Accent `#38bdf8`, Dark Accent `#0284c7`, Text High `#f8fafc`, Text Low `#94a3b8`.
  - Light Mode: Surface `#ffffff`, Borders `#e2e8f0`, Accent `#0284c7`, Text High `#0f172a`, Text Low `#64748b`.
- **Transitions**: Smooth `fade-in-slide` animation (`opacity: 0 -> 1; transform: translateY(8px) -> translateY(0); transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1)`).
- **Responsive Breakpoints**:
  - `< 768px`: Stepper labels collapse to numbers with tooltips or compact stacked layout.
  - Full-width button actions on mobile devices.

---

## 6. Verification & Automated Test Plan

1. **Integration Tests**:
   - `tests/test_reserve_view_integration.php`: Assert stepper container `#wizard-stepper`, 3 step containers `#wizard-step-1`, `#wizard-step-2`, `#wizard-step-3`, next/back buttons, and integrated review card exist.
   - `tests/test_find_alternative_integration.php` & `tests/test_reschedule_integration.php`: Verify AI recommendation cards and 1-click alternative resolution work seamlessly within the step wizard.
2. **Full Repository Regression**:
   - Run all test suites: `Get-ChildItem -Path "tests" -Filter "*.php" | ForEach-Object { php $_.FullName }` (all 13 must pass).
3. **Puppeteer UI Verification**:
   - User login (`william.braga@gmail.com`).
   - Navigate to `reserve_view.php`.
   - Verify Step 1: select hall and room, test live room preview, click Next.
   - Verify Step 2: select date and times, verify conflict checking, click Next.
   - Verify Step 3: verify populated summary receipt card, test back button, submit reservation.
