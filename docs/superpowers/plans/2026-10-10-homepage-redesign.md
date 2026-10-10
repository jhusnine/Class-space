# ClassSpace Homepage Redesign (Collegiate Pro Max) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Transform the ClassSpace student dashboard into an authentic, high-contrast Collegiate Institutional UI with predictable 3-column photo grid, signature 4px status edges (green `#3D9B68` / gray `#7B8798`), modern collegiate sidebar, direct room filter pills, and a slide-out Quick Reserve drawer, while preserving 100% of existing backend integrations and functionality.

**Architecture:** Update `public/css/homepage_style.css` with institutional FCPC design tokens, 3-column card layouts, and drawer transitions. Refactor `app/views/user_homepage_view.php` with modernized sidebar navigation, 4-metric structured KPI strip, direct room toolbar, and slide-out drawer shell. Upgrade `public/js/homepage_script.js` to render direct 3-column cards with status edges, real-time building pill filtering, and in-page drawer booking while maintaining all polling intervals, search endpoints, mobile drawer controls, and toast notifications.

**Tech Stack:** PHP 8.x, Vanilla CSS (tokens, flexbox, CSS grid), Vanilla JavaScript (ES6+, DOM, Fetch API), FontAwesome 6, Apache/MySQL on XAMPP.

## Global Constraints

- **Strict Status Edge Colors:** Available rooms MUST use `border-left: 4px solid #3D9B68` (Institutional Green). Unavailable / In-session rooms MUST use `border-left: 4px solid #7B8798` (Slate Gray).
- **Predictable Cadence:** Room catalog MUST use a strict 3-column grid (`repeat(3, 1fr)` on desktop `> 1200px`) with identical 16:9 photography frames (`aspect-ratio: 16 / 9`, `object-fit: cover`).
- **Zero Functionality Loss:** Preserve all existing AJAX endpoints (`user_stats.php`, `notifications_controller.php`, `search_controller.php`, `room_controller.php`), polling intervals (60s stats, 30s notifications), mobile menu interactions, image error fallbacks, and all 8 sidebar navigation routes.
- **Theme Dual-Support:** Both Dark Mode (`#0B1320`) and Editorial Ivory Light Mode (`#F8FAFC`) must be supported via existing `body.light-mode` cookie persistence.

---

### Task 1: CSS Design System, Status Edges & Drawer Shell Styling

**Files:**
- Modify: `public/css/homepage_style.css`
- Test: `tests/test_homepage_redesign_smoke.php`

**Interfaces:**
- Consumes: Existing ClassSpace class names (`.sidebar`, `.main-content`, `.room-card`, `body.light-mode`).
- Produces: Modern collegiate CSS variables, `.rooms-grid` 3-column structure, `.room-card` 4px status edge styles, `.room-status-legend`, `.card-media` 16:9 photo containers, `.sidebar-profile-card`, and `.quick-reserve-drawer` layout classes.

- [ ] **Step 1: Write initial CSS test assertions in smoke test script**

Create `tests/test_homepage_redesign_smoke.php` to verify that `homepage_style.css` contains the required status edge rules and 3-column grid tokens.

```php
<?php
// tests/test_homepage_redesign_smoke.php
$css = file_get_contents(__DIR__ . '/../public/css/homepage_style.css');

$requiredCssSnippets = [
    '#3D9B68', // Available green edge
    '#7B8798', // Unavailable gray edge
    'border-left: 4px solid',
    'repeat(3, 1fr)',
    'aspect-ratio: 16 / 9',
    '.room-status-legend',
    '.quick-reserve-drawer'
];

foreach ($requiredCssSnippets as $snippet) {
    if (!str_contains($css, $snippet)) {
        throw new RuntimeException("CSS missing required snippet: " . $snippet);
    }
}
echo "PASS: CSS tokens and status edges verified.\n";
```

- [ ] **Step 2: Run test to verify it fails initially**

Run: `php tests/test_homepage_redesign_smoke.php`
Expected: FAIL with missing `.quick-reserve-drawer` or status edge declarations.

- [ ] **Step 3: Update `public/css/homepage_style.css`**

Add the complete token architecture, modern collegiate sidebar, 3-column grid, 4px status edge classes, and drawer styles into `public/css/homepage_style.css`:

```css
/* ==========================================================================
   FCPC INSTITUTIONAL TOKENS & SYSTEM PALETTE
   ========================================================================== */
:root {
    --brand-teal: #2F8585;
    --brand-teal-light: #3D9E9E;
    --brand-teal-dim: rgba(47, 133, 133, 0.16);
    --brand-gold: #D4A32A;
    --brand-gold-dim: rgba(212, 163, 42, 0.12);

    --bg-base: #0B1320;
    --bg-surface: #101B2E;
    --bg-elevated: #16253F;
    --bg-card: #121D32;
    --bg-card-hover: #17243D;

    --border: rgba(255, 255, 255, 0.08);
    --border-hover: rgba(47, 133, 133, 0.45);

    --text-high: #F8FAFC;
    --text-mid: #94A3B8;
    --text-low: #64748B;

    --status-available: #3D9B68;
    --status-available-glow: #4ade80;
    --status-unavailable: #7B8798;
    --status-unavailable-hover: #94a3b8;

    --radius-sm: 6px;
    --radius-md: 12px;
    --radius-lg: 16px;
    --radius-full: 9999px;

    --shadow-card: 0 4px 20px rgba(0, 0, 0, 0.25);
    --shadow-hover: 0 10px 28px rgba(0, 0, 0, 0.35);
    --transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

body.light-mode {
    --bg-base: #F8FAFC;
    --bg-surface: #FFFFFF;
    --bg-elevated: #F1F5F9;
    --bg-card: #FFFFFF;
    --bg-card-hover: #F8FAFC;
    --border: #E2E8F0;
    --border-hover: rgba(47, 133, 133, 0.5);
    --text-high: #0F172A;
    --text-mid: #475569;
    --text-low: #94A3B8;
    --status-available: #3D9B68;
    --status-unavailable: #7B8798;
    --shadow-card: 0 2px 10px rgba(0, 0, 0, 0.06);
    --shadow-hover: 0 8px 24px rgba(0, 0, 0, 0.12);
}

/* 3-Column Structured Rooms Grid */
.rooms-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 22px;
}

@media (max-width: 1200px) {
    .rooms-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 768px) {
    .rooms-grid { grid-template-columns: 1fr; }
}

/* 4px Status Edge Room Card */
.room-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-left: 4px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    transition: var(--transition);
    box-shadow: var(--shadow-card);
    position: relative;
}

.room-card.room-status-available,
.room-card[data-status="vacant"] {
    border-left: 4px solid #3D9B68 !important;
}

.room-card.room-status-unavailable,
.room-card[data-status="busy"] {
    border-left: 4px solid #7B8798 !important;
}

.room-card:hover {
    border-color: var(--border-hover);
    transform: translateY(-3px);
    box-shadow: var(--shadow-hover);
}

.room-card[data-status="vacant"]:hover,
.room-card.room-status-available:hover {
    border-left-color: #4ade80 !important;
    box-shadow: -2px 0 14px rgba(61, 155, 104, 0.35), var(--shadow-hover);
}

.room-card[data-status="busy"]:hover,
.room-card.room-status-unavailable:hover {
    border-left-color: #94a3b8 !important;
    box-shadow: -2px 0 10px rgba(123, 135, 152, 0.25), var(--shadow-hover);
}

/* 16:9 Room Photo Frame */
.card-media {
    position: relative;
    width: 100%;
    aspect-ratio: 16 / 9;
    overflow: hidden;
    background: #0f1c30;
}

.card-media img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transition: transform 0.4s ease;
}

.room-card:hover .card-media img {
    transform: scale(1.04);
}

.card-media-scrim {
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, rgba(0,0,0,0.35) 0%, transparent 45%, rgba(11,19,32,0.85) 100%);
    pointer-events: none;
}

/* Status Edge Legend */
.room-status-legend {
    display: inline-flex;
    align-items: center;
    gap: 14px;
    color: var(--text-mid);
    font-size: 11.5px;
    font-weight: 500;
}

.legend-item {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
}

.legend-edge-bar {
    width: 4px;
    height: 12px;
    border-radius: 2px;
    display: inline-block;
}

.legend-edge-available {
    background: #3D9B68;
    box-shadow: 0 0 6px rgba(61, 155, 104, 0.4);
}

.legend-edge-unavailable {
    background: #7B8798;
}

/* Modern Collegiate Sidebar Profile Strip */
.sidebar-profile-card {
    padding: 12px;
    background: var(--bg-elevated);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: auto;
}

.user-avatar-circle {
    width: 32px;
    height: 32px;
    border-radius: var(--radius-full);
    background: var(--brand-teal);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 13px;
    flex-shrink: 0;
}

/* Quick Reserve Slide-Out Drawer */
.quick-reserve-drawer {
    position: fixed;
    top: 0;
    right: 0;
    width: 420px;
    max-width: 100vw;
    height: 100vh;
    background: var(--bg-surface);
    border-left: 1px solid var(--border);
    box-shadow: -10px 0 40px rgba(0, 0, 0, 0.5);
    z-index: 1050;
    transform: translateX(100%);
    transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    display: flex;
    flex-direction: column;
    padding: 28px 24px;
    overflow-y: auto;
}

.quick-reserve-drawer.drawer-open {
    transform: translateX(0);
}

.drawer-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(4px);
    z-index: 1040;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.3s ease;
}

.drawer-backdrop.active {
    opacity: 1;
    pointer-events: auto;
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/test_homepage_redesign_smoke.php`
Expected: PASS with "CSS tokens and status edges verified."

- [ ] **Step 5: Commit**

```bash
git add public/css/homepage_style.css tests/test_homepage_redesign_smoke.php
git commit -m "feat: add collegiate CSS design tokens, 3-column grid, status edges, and drawer styles"
```

---

### Task 2: Modern View Markup with Collegiate Sidebar, 4-Metric KPI Strip & Drawer Shell

**Files:**
- Modify: `app/views/user_homepage_view.php`
- Modify: `tests/test_homepage_redesign_smoke.php`

**Interfaces:**
- Consumes: PHP session variables (`$_SESSION["id"]`, `$_SESSION["fname"]`, `$_SESSION["lname"]`), theme cookie class (`$themeClass`).
- Produces: Updated DOM containing modern sidebar with student profile card, 4-tile KPI strip, direct filter bar with building pills and status legend, `#rooms-grid`, and the `#quick-reserve-drawer` container.

- [ ] **Step 1: Add View Markup Assertions to `tests/test_homepage_redesign_smoke.php`**

Extend `tests/test_homepage_redesign_smoke.php` with checks for preserved IDs and new markup elements:

```php
// In tests/test_homepage_redesign_smoke.php
$view = file_get_contents(__DIR__ . '/../app/views/user_homepage_view.php');

$requiredViewElements = [
    'session_start()',
    'cleanup_schedules.php',
    'id="search-input"',
    'id="search-results"',
    'id="stat-active"',
    'id="stat-pending"',
    'id="stat-available"',
    'id="notif-badge"',
    'id="rooms-grid"',
    'id="quick-reserve-drawer"',
    'id="drawer-backdrop"',
    'class="sidebar-profile-card"',
    'class="room-status-legend"',
    'logout.php'
];

foreach ($requiredViewElements as $element) {
    if (!str_contains($view, $element)) {
        throw new RuntimeException("View missing required element: " . $element);
    }
}
echo "PASS: View markup and preserved elements verified.\n";
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/test_homepage_redesign_smoke.php`
Expected: FAIL with missing `#quick-reserve-drawer` or `.sidebar-profile-card`.

- [ ] **Step 3: Update `app/views/user_homepage_view.php`**

Update `app/views/user_homepage_view.php` ensuring:
1. Lines 1-15 retain session validation, cleanup schedules include, and theme cookie sync.
2. Sidebar brand header shows First City Providential institutional context.
3. All 8 existing nav items are preserved in order.
4. Bottom student profile card renders user avatar, `$fname $lname`, BSIT degree program, and logout button.
5. Page header includes academic year badge (`AY 2026–2027`) and search input.
6. KPI strip provides 4 structured metric cards (`Campus Occupancy`, `Your Bookings`, `Pending Review`, `Instant Vacancy`).
7. Filter bar provides building pills and the 4px status edge legend.
8. Drawer shell (`#quick-reserve-drawer` and `#drawer-backdrop`) is appended before the closing body tag.

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/test_homepage_redesign_smoke.php`
Expected: PASS with "View markup and preserved elements verified."

- [ ] **Step 5: Commit**

```bash
git add app/views/user_homepage_view.php tests/test_homepage_redesign_smoke.php
git commit -m "feat: modernize homepage view with collegiate sidebar, 4-metric KPI strip, and drawer shell"
```

---

### Task 3: JavaScript Direct Catalog Rendering, Building Filter Pills & Status Edge Data Binding

**Files:**
- Modify: `public/js/homepage_script.js`
- Modify: `tests/test_homepage_redesign_smoke.php`

**Interfaces:**
- Consumes: JSON responses from `../controllers/user_stats.php`, `../controllers/notifications_controller.php`, `../controllers/room_controller.php?action=get_all`.
- Produces: Direct 3-column room cards with `room-status-available` (`#3D9B68`) and `room-status-unavailable` (`#7B8798`), instant building pill filter listeners, and KPI stat filter bindings.

- [ ] **Step 1: Add JavaScript Assertions to `tests/test_homepage_redesign_smoke.php`**

Extend `tests/test_homepage_redesign_smoke.php` to verify script preservation and status edge binding:

```php
// In tests/test_homepage_redesign_smoke.php
$js = file_get_contents(__DIR__ . '/../public/js/homepage_script.js');

$requiredJsSnippets = [
    'user_stats.php',
    'notifications_controller.php',
    'search_controller.php',
    'room-status-available',
    'room-status-unavailable',
    'card-media',
    'filterByBuilding',
    'initSharedMobileMenu',
    'wireImageFallbacks',
    'loadStats',
    'loadNotifBadge'
];

foreach ($requiredJsSnippets as $snippet) {
    if (!str_contains($js, $snippet)) {
        throw new RuntimeException("JavaScript missing required snippet: " . $snippet);
    }
}
echo "PASS: JavaScript handlers and preserved endpoints verified.\n";
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/test_homepage_redesign_smoke.php`
Expected: FAIL with missing `filterByBuilding` or `card-media`.

- [ ] **Step 3: Update `public/js/homepage_script.js`**

Refactor `renderRooms()` and card generation:
1. Generate cards using `.card-media` with fixed 16:9 images, bottom location tags (`PLARIDEL • 1F`), and top status badges (`VACANT` / `IN SESSION`).
2. Attach `room-status-available` (`#3D9B68`) or `room-status-unavailable` (`#7B8798`) based on slot availability.
3. If `room.is_my_active`, render `<span class="active-user-badge"><i class="fa-solid fa-user-check"></i> Your Active Booking</span>`.
4. Add `filterByBuilding(buildingKey)` to filter `allRoomsCache` instantaneously when students click building pills (`All Spaces`, `Plaridel`, `Melchora`, `Gym`, `Hotel`).
5. Wire KPI card filters (`active`, `pending`, `available`) to directly filter the catalog.
6. Retain all 60s and 30s polling intervals, image fallbacks, search autocomplete, search execution, and toast alerts.

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/test_homepage_redesign_smoke.php`
Expected: PASS with "JavaScript handlers and preserved endpoints verified."

- [ ] **Step 5: Commit**

```bash
git add public/js/homepage_script.js tests/test_homepage_redesign_smoke.php
git commit -m "feat: implement direct catalog rendering with status edge data binding and building filter pills"
```

---

### Task 4: In-Page Quick Reserve Drawer Interaction & Form Handling

**Files:**
- Modify: `public/js/homepage_script.js`
- Modify: `tests/test_homepage_redesign_smoke.php`

**Interfaces:**
- Consumes: Room ID, Hall ID, and Room Name from clicked card.
- Produces: Drawer open/close events, pre-populated room details, date/time inputs, conflict check trigger, and submission handling.

- [ ] **Step 1: Add Drawer Interaction Assertions to `tests/test_homepage_redesign_smoke.php`**

```php
// In tests/test_homepage_redesign_smoke.php
$js = file_get_contents(__DIR__ . '/../public/js/homepage_script.js');

$requiredDrawerSnippets = [
    'openQuickReserveDrawer',
    'closeQuickReserveDrawer',
    'quick-reserve-drawer',
    'drawer-backdrop'
];

foreach ($requiredDrawerSnippets as $snippet) {
    if (!str_contains($js, $snippet)) {
        throw new RuntimeException("Drawer interaction missing: " . $snippet);
    }
}
echo "PASS: Quick Reserve Drawer interactions verified.\n";
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/test_homepage_redesign_smoke.php`
Expected: FAIL with missing `openQuickReserveDrawer`.

- [ ] **Step 3: Implement Quick Reserve Drawer logic in `public/js/homepage_script.js`**

1. Define `openQuickReserveDrawer(room)`:
   - Sets drawer title, room capacity, and location subtitle.
   - Pre-fills today's date into `#drawer-date`.
   - Populates `#drawer-hall-id` and `#drawer-room-id`.
   - Adds `.drawer-open` to `#quick-reserve-drawer` and `.active` to `#drawer-backdrop`.
2. Define `closeQuickReserveDrawer()`:
   - Removes `.drawer-open` and `.active`.
3. Add event listeners for:
   - Drawer close button (`#drawer-close-btn`).
   - Backdrop click.
   - Escape key press.
4. Provide link in drawer to `reserve_view.php?hall_id=${hallId}&room_id=${roomId}` for advanced multi-slot/recurring bookings.

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/test_homepage_redesign_smoke.php`
Expected: PASS with "Quick Reserve Drawer interactions verified."

- [ ] **Step 5: Commit**

```bash
git add public/js/homepage_script.js tests/test_homepage_redesign_smoke.php
git commit -m "feat: wire in-page Quick Reserve drawer with backdrop dismiss and advanced booking link"
```

---

### Task 5: Functionality Preservation Verification & Smoke Testing

**Files:**
- Modify: `tests/test_homepage_redesign_smoke.php`

**Interfaces:**
- Consumes: All 3 production files and Apache web server.
- Produces: Complete end-to-end test run confirming 100% functionality retention and HTTP 200 response.

- [ ] **Step 1: Enhance `tests/test_homepage_redesign_smoke.php` with full preservation checklist**

Verify all 17 items from the Functionality Preservation Matrix:
- Session & schedule cleanup checks.
- All 8 navigation routes in sidebar.
- Dark & Light mode tokens.
- Live stats polling & notification badge polling.
- Live search input & suggestions dropdown.
- Stat card interactive filters.
- Image fallback handling.
- Mobile menu drawer logic.
- Toast notifications.

- [ ] **Step 2: Run the test suite**

Run: `php tests/test_homepage_redesign_smoke.php`
Expected: PASS with all checks successful.

- [ ] **Step 3: Verify HTTP 200 via PowerShell Web Request**

Run:
```powershell
Invoke-WebRequest -Uri 'http://localhost/classspace/app/views/user_homepage_view.php' -UseBasicParsing | Select-Object StatusCode
```
Expected: `StatusCode: 200` (or `302` redirect to `index.php` when no session cookie is attached, confirming auth guard integrity).

- [ ] **Step 4: Commit**

```bash
git add tests/test_homepage_redesign_smoke.php
git commit -m "test: add comprehensive end-to-end functionality preservation test suite"
```
