# Design Specification: ClassSpace Homepage Redesign (Collegiate Pro Max)

**Date:** 2026-10-10  
**Status:** Approved by User (Ready for Implementation Planning)  
**Author:** Pair Programming (User + Antigravity)  
**Target Codebase Files:**
- [`app/views/user_homepage_view.php`](file:///c:/xampp/htdocs/classspace/app/views/user_homepage_view.php)
- [`public/css/homepage_style.css`](file:///c:/xampp/htdocs/classspace/public/css/homepage_style.css)
- [`public/js/homepage_script.js`](file:///c:/xampp/htdocs/classspace/public/js/homepage_script.js)

---

## 1. Problem Statement & Audit Findings

The ClassSpace student dashboard previously exhibited visual traits typical of generic AI-generated templates:
1. **Cyber-cyan token drift (`#38bdf8`)**: Ignored First City Providential College's authentic institutional teal (`#2F8585`) and gold (`#D4A32A`).
2. **Cookie-cutter 3-stat cards**: Gradient-topped metric tiles without actionable context or hierarchy.
3. **Cumbersome 2-level drilldown**: Users were forced to click into building tiles first, then view rooms, requiring "Back to Buildings" navigation.
4. **Abstract icon placeholders**: Did not showcase real campus facilities (Plaridel Hall, Melchora Hall, College Gymnasium, FCPC Training Hotel).
5. **Hidden room status**: Required reading text badges instead of rapid visual peripheral scanning.
6. **Disconnected reservation flow**: Room booking abruptly navigated off-page without quick booking capability.

---

## 2. Approved Architectural Decisions

| Area | Decision | Details |
| :--- | :--- | :--- |
| **Grid System** | Predictable 3-Column Grid | Strict `repeat(3, 1fr)` on desktop (`> 1200px`), 2 columns on tablet (`768px–1200px`), 1 column on mobile (`< 768px`). Identical dimensions across all cards. |
| **Room Photography** | 16:9 Scrimmed Photo Containers | High-resolution campus photography with bottom gradient scrim (`rgba(11, 19, 32, 0.85)`) for high-contrast building badges (`PLARIDEL • 1F`). |
| **Status Edge System** | 4px Signature Status Edge | **Green (`#3D9B68`)** for Available rooms, **Gray (`#7B8798`)** for Unavailable / In-session rooms, accompanied by a visual legend in the toolbar. |
| **Sidebar Navigation** | Modern Executive Collegiate Sidebar | Institutional FCPC brand header, active nav pill highlights, and bottom student profile card (`Avatar + Name + Program + Quick Logout`). |
| **Room Discovery** | Direct Catalog with Filter Pills | Replaces the 2-step drilldown. Students immediately see all campus rooms with instant building filter pills (`All Spaces`, `Plaridel`, `Melchora`, `Gym`, `Hotel`). |
| **Reservation UX** | Hybrid Slide-Out Drawer | Clicking "Book Space" opens an in-page Quick Reserve Drawer for instant bookings, with an option to navigate to `reserve_view.php` for complex bookings. |
| **Theme System** | Dark Mode + Editorial Ivory Light Mode | Dark Flagship (`#0B1320`) by default, with complete Editorial Ivory (`#F8FAFC`) light mode synchronized with existing `body.light-mode` cookie. |

---

## 3. Component Specifications

### 3.1 Modern Collegiate Sidebar
- **Brand Header**:
  - Logo (`../../public/images/logo.png`) enclosed in a subtle teal container (`background: rgba(47, 133, 133, 0.16)`).
  - Title: **ClassSpace** (15px, font-weight 700).
  - Subtitle: **First City Providential** (11px uppercase, letter-spacing 0.04em, `#64748B`).
- **Nav Menu**:
  - Links: Homepage, Calendar, Notifications (with unread badge), Account Profile, Security, Settings.
  - Active State: Background `rgba(47, 133, 133, 0.16)`, border `rgba(47, 133, 133, 0.3)`, text `#F8FAFC`.
- **Bottom Student Profile Card**:
  - Displays user avatar circle with first initial (`$_SESSION["fname"][0]`).
  - Student full name (`$fname $lname`).
  - Academic role/degree ("Student • BSIT").
  - Quick logout icon button (`logout.php`) with tooltip.

### 3.2 Page Header & 4-Tile Structured KPI Strip
- **Header**:
  - Title: "San Jose Del Monte Campus" with academic year pill badge (`AY 2026–2027`).
  - Subtitle: "Real-time room occupancy, schedules, and reservations across all academic facilities."
  - Search Bar: Search input with shortcut hint pill (`Ctrl+K`).
- **KPI Strip (4 Columns)**:
  1. **Campus Occupancy**: Live occupancy percentage (e.g., 67%) with a visual progress bar.
  2. **Your Bookings**: Count of active reservations with current room/time details.
  3. **Pending Review**: Count of pending requests under admin processing.
  4. **Instant Vacancy**: Direct highlight of an immediately available room with a "Quick Claim" link.

### 3.3 Filter Toolbar & Status Legend
- **Filter Pills**:
  - Pill buttons: `All Spaces (42)`, `Plaridel Hall (14)`, `Melchora Hall (12)`, `Gymnasium (4)`, `Training Hotel (8)`.
  - Active pill highlighted in FCPC Teal (`#2F8585`).
- **Secondary Controls**:
  - Instant toggles: `Vacant Only`, `Labs Only`.
  - **Signature Status Edge Legend**:
    - Available: `<span class="legend-edge-bar legend-edge-available"></span> Available` (`#3D9B68`)
    - Unavailable: `<span class="legend-edge-bar legend-edge-unavailable"></span> Unavailable` (`#7B8798`)

### 3.4 3-Column Structured Room Card Shell
- **Physical Edge**:
  - `border-left: 4px solid var(--border)`
  - Available: `border-left-color: #3D9B68 !important;` (hover: `#4ade80` with `-2px 0 14px rgba(61, 155, 104, 0.35)` glow)
  - Unavailable: `border-left-color: #7B8798 !important;` (hover: `#94a3b8`)
- **16:9 Media Box**:
  - Image rendered with `aspect-ratio: 16 / 9`, `object-fit: cover`.
  - Building/Floor badge at bottom-left: `PLARIDEL • 1F`.
  - Status pill at top-right: `VACANT` (green badge) or `IN SESSION` (amber badge).
- **Active User Booking Card**:
  - Rendered with an anchor badge: `<span class="active-user-badge"><i class="fa-solid fa-user-check"></i> Your Active Booking</span>`.
  - Highlight border `box-shadow: 0 0 0 1px rgba(47, 133, 133, 0.35)`.
- **Card Content Body**:
  - Room Code (`RM 101`) & Capacity (`55 Seats`).
  - Room Type Subtitle (`General Lecture Hall`, `Computer Lab 1`, etc.).
  - Amenity Badges cluster (`Projector`, `Air Conditioned`, `High-Speed Wi-Fi`).
  - Availability Window: `Free until 2:30 PM` or `Clears at 1:00 PM`.
  - Actions Row:
    - Secondary: "Timetable / Schedule" (opens schedule details).
    - Primary: "Book Space" (triggers slide-out Quick Reserve Drawer).

### 3.5 Slide-Out Quick Reserve Drawer
- Slides in from right side on `transform: translateX(0)` with smooth backdrop blur.
- Displays selected room title, location, capacity, and current availability status.
- Compact booking form:
  - Date selector (defaults to today).
  - Time slot selection (Start & End time with instant conflict check preview).
  - Purpose of reservation input.
  - "Confirm Quick Booking" submit button (calls existing reservation endpoint).
  - Link: "Need recurring or multi-room booking? Go to full reservation page &rarr;".

---

## 4. Design Tokens (CSS Variables)

```css
:root {
    /* Brand Foundation */
    --brand-teal: #2F8585;
    --brand-teal-light: #3D9E9E;
    --brand-teal-dim: rgba(47, 133, 133, 0.16);
    --brand-gold: #D4A32A;
    --brand-gold-dim: rgba(212, 163, 42, 0.12);

    /* Surfaces (Dark Mode Flagship) */
    --bg-base: #0B1320;
    --bg-surface: #101B2E;
    --bg-elevated: #16253F;
    --bg-card: #121D32;
    --bg-card-hover: #17243D;

    /* Borders */
    --border: rgba(255, 255, 255, 0.08);
    --border-hover: rgba(47, 133, 133, 0.45);

    /* Text */
    --text-high: #F8FAFC;
    --text-mid: #94A3B8;
    --text-low: #64748B;

    /* Status Edge Tokens */
    --status-available: #3D9B68;
    --status-available-glow: #4ade80;
    --status-unavailable: #7B8798;
    --status-unavailable-hover: #94a3b8;

    /* Geometry & Shadows */
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
```

---

## 5. Exhaustive Functionality Preservation Matrix

The user has explicitly mandated: **"be sure to not remove any existing functionalities."** The following matrix details every existing feature and proves how it is retained in the new architecture:

| Subsystem / Feature | Existing Implementation | Redesign Architecture | Preservation Guarantee |
| :--- | :--- | :--- | :--- |
| **Session & Auth Checks** | `session_start()`, check `$_SESSION["id"]`, redirect to `../index.php` | Preserved identically in `user_homepage_view.php` | 100% Unchanged |
| **Schedule Auto-Cleanup** | `require_once "../controllers/cleanup_schedules.php";` | Preserved on line 2 of `user_homepage_view.php` | 100% Unchanged |
| **Theme Cookie Detection** | `$_COOKIE["theme"] === "light" ? "light-mode" : ""` | Preserved in PHP on `<body>` element | 100% Unchanged |
| **Stats Counters** | `loadStats()` polls `../controllers/user_stats.php` every 60s | Polling kept at 60s; updates `Active`, `Pending`, `Available` numbers in the new KPI strip | 100% Preserved |
| **Notification Badges** | `loadNotifBadge()` polls `../controllers/notifications_controller.php` every 30s | Polling kept at 30s; updates `#notif-badge` with `res.unread` count | 100% Preserved |
| **Live Search Autocomplete** | `runSearch()` queries `../controllers/search_controller.php?q=...` | Same endpoint and logic; dropdown rendered beneath search pill | 100% Preserved |
| **Enter/Click Search Execution** | `triggerSearch()` fetches featured rooms via `room_controller.php` | Same query handler, error handling, and results view | 100% Preserved |
| **Click-Outside Search Dismissal** | Event listener on `document` closes `#search-results` | Preserved identically in `homepage_script.js` | 100% Preserved |
| **Stat-Card Interactive Filtering** | Clicking `#card-active`, `#card-pending`, `#card-available` filters rooms by `is_my_active`, `has_pending`, `computed_status` | Filter handlers kept; clicking KPI cards triggers real-time filtering in the room catalog | 100% Preserved |
| **Floor & Group Sorter** | `floorFromRoomName()`, `roomNumber()`, grouping by `1st`, `2nd`, `3rd` floor | Retained in room data processing and floor pill filtering | 100% Preserved |
| **Conflict Time Ranges** | Appends `(${formatTime(conflict_start)} - ${formatTime(conflict_end)})` to unavailable rooms | Displayed in card availability window: "Occupied until X:XX PM" | 100% Preserved |
| **Air-Conditioning Chips** | `room_has_ac == 1` shows AC chip; `== 0` shows No AC | Rendered in card amenity cluster with snowflake icon | 100% Preserved |
| **Pending / Active Badges** | `has_pending` and `is_my_active` conditional badges | Rendered with dedicated highlight badges (`Your Reservation`, `Pending`) | 100% Preserved |
| **Image Error Fallback** | `wireImageFallbacks()` switches failed images to placeholder | Fallback handler retained for all 16:9 photo elements | 100% Preserved |
| **Mobile Drawer Navigation** | `#mobile-menu-toggle`, `#mobile-menu-backdrop`, `initSharedMobileMenu()` | Preserved with full ARIA attributes, escape-key handler, and backdrop dismiss | 100% Preserved |
| **Reservation Toast Alert** | Reads `?submitted=1`, calls `window.toast.show()`, cleans URL via `history.replaceState` | Preserved identically at script boot | 100% Preserved |
| **All Navigation Routes** | Links to `calendar_view.php`, `notifications_view.php`, `profile_view.php`, `security_view.php`, `settings_view.php`, `about_view.php`, `contact_view.php`, `logout.php` | All 8 navigation destinations preserved in the modern collegiate sidebar | 100% Preserved |

---

## 6. Implementation Plan Outline

1. **Step 1: CSS Token & Shell System**
   - Update [`public/css/homepage_style.css`](file:///c:/xampp/htdocs/classspace/public/css/homepage_style.css) with all new variables, 3-column room grid, 4px status edges, 16:9 media boxes, status legend, and drawer animation rules.
2. **Step 2: View Markup Updates**
   - Update [`app/views/user_homepage_view.php`](file:///c:/xampp/htdocs/classspace/app/views/user_homepage_view.php) with:
     - Modernized collegiate sidebar (FCPC logo, active pills, bottom profile card, all 8 navigation links preserved).
     - New page header with AY badge & search input (retaining `#search-input`, `#search-results`, `.search-btn`).
     - 4-metric structured KPI strip (retaining `#card-active`, `#card-pending`, `#card-available`, `#stat-active`, `#stat-pending`, `#stat-available`).
     - Direct room filter bar with building tabs and status legend.
     - Slide-out Quick Reserve Drawer container.
3. **Step 3: JavaScript Dynamic Data Binding**
   - Update [`public/js/homepage_script.js`](file:///c:/xampp/htdocs/classspace/public/js/homepage_script.js):
     - Preserve all fetch endpoints, intervals, search handlers, image error fallbacks, and toast alerts.
     - Bind room data directly into the 3-column room card template with `room-status-available` (`#3D9B68`) and `room-status-unavailable` (`#7B8798`).
     - Wire building filter tabs (`All Spaces`, `Plaridel`, `Melchora`, `Gym`, `Hotel`) for real-time filtering without page reloads.
     - Wire slide-out drawer open/close and quick booking submission while preserving `reserve_view.php` link.
4. **Step 4: Verification & Smoke Test**
   - Verify page loads with HTTP 200.
   - Verify dark and light mode toggle.
   - Verify all 8 sidebar navigation links.
   - Verify building filtering and vacancy toggling.
   - Verify live search input and dropdown suggestions.
   - Verify stat card filters (`active`, `pending`, `available`).
   - Verify drawer open/close and booking flow.
