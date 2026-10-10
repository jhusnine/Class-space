# Room Photos 404 and Visual Variety Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Eliminate all browser console 404 network errors and provide visually diverse, authentic photography across all campus rooms and buildings on the dashboard.

**Architecture:** Replace the broken per-room 404 request pattern (`rooms/{id}.jpg`) with a zero-404 hierarchical asset resolver in `homepage_script.js`. The resolver prioritizes specialized room types (Computer Labs, Crime/Forensic Labs, AI Data Center, Nursing Skills, Library, Function Halls), then building identity halls (Plaridel, Melchora, Rizal, Balagtas, Ople, Hotel, Gym), with graceful curated fallback.

**Tech Stack:** Vanilla JavaScript (ES6+), PHP 8+, SVG / JPEG visual assets, PHPUnit/Smoke Test Suite, Puppeteer.

## Global Constraints
- Preserve all 17 items in the Functionality Preservation Matrix in `tests/test_homepage_redesign_smoke.php`.
- Do not remove `wireImageFallbacks` or `fallbackUsed` (required by the test contract).
- Zero console 404 errors when the user homepage loads.
- Visual authenticity: Cards must display distinct, appropriate photography reflecting their room type or building instead of a single identical stock photo.

---

### Task 1: Establish Diverse Room & Building Asset Library

**Files:**
- Create: `public/images/rooms/` directory
- Create: `public/images/types/computer_lab.jpg`
- Create: `public/images/types/science_lab.jpg`
- Create: `public/images/types/lecture_hall.jpg`
- Create: `public/images/types/library.jpg`
- Create: `public/images/types/data_center.jpg`
- Create: `public/images/types/event_hall.jpg`
- Create: `public/images/halls/balagtas.jpg`
- Create: `public/images/halls/ople.jpg`
- Create: `public/images/halls/rizal.jpg`
- Test: `tests/test_image_assets_smoke.php`

**Interfaces:**
- Produces: Standardized asset paths under `public/images/` and `public/images/types/` for halls and room categories.

- [ ] **Step 1: Write test verifying required asset files exist**

```php
<?php
// tests/test_image_assets_smoke.php
$requiredAssets = [
    'public/images/example.jpg',
    'public/images/gym.png',
    'public/images/hotel.jpeg',
    'public/images/melchora.jpg',
    'public/images/plaridel.jpeg',
    'public/images/types/computer_lab.jpg',
    'public/images/types/science_lab.jpg',
    'public/images/types/lecture_hall.jpg',
    'public/images/types/library.jpg',
    'public/images/types/data_center.jpg',
    'public/images/types/event_hall.jpg'
];

foreach ($requiredAssets as $relPath) {
    $fullPath = __DIR__ . '/../' . $relPath;
    if (!file_exists($fullPath)) {
        throw new RuntimeException("Missing asset: " . $relPath);
    }
}
echo "PASS: All required image assets exist.\n";
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/test_image_assets_smoke.php`
Expected: FAIL with "Missing asset: public/images/types/computer_lab.jpg"

- [ ] **Step 3: Generate/create the high-resolution category and building images**

Create `public/images/types/` and `public/images/rooms/` directory. Generate high-quality assets using `generate_image` or high-fidelity curated photography for each academic room archetype (Computer Lab, Forensic/Science Lab, Modern Lecture Hall, University Library, AI Data Center, Campus Event Hall, and missing Hall facades).

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/test_image_assets_smoke.php`
Expected: PASS with "PASS: All required image assets exist."

- [ ] **Step 5: Commit assets**

```bash
git add public/images/ tests/test_image_assets_smoke.php
git commit -m "feat: add authentic campus room category and building photo assets"
```

---

### Task 2: Implement Zero-404 Hierarchical Photo Resolver

**Files:**
- Modify: `public/js/homepage_script.js:15-55`
- Test: `tests/test_homepage_redesign_smoke.php`

**Interfaces:**
- Consumes: `room.room_type`, `room.hall_name`, `room.room_name`
- Produces: `roomPhoto(room)` returning a valid, non-404 URL matching room type or building hall

- [ ] **Step 1: Write test verifying zero-404 resolver logic**

Add test assertions in `tests/test_homepage_redesign_smoke.php` checking that `roomPhoto` maps specialized room types (Computer Labs, Crime Labs, Data Center, Event Halls) and building halls (Plaridel, Melchora, Rizal, Balagtas, Ople, Hotel, Gym) without unverified 404 URLs.

- [ ] **Step 2: Run test to verify current behavior**

Run: `php tests/test_homepage_redesign_smoke.php`
Expected: Current tests pass, but asset mapping assertions require the new resolver logic.

- [ ] **Step 3: Update `roomPhoto(room)` in `public/js/homepage_script.js`**

Implement the hierarchical mapping:
```javascript
const HALL_PHOTOS = {
    gym: "../../public/images/gym.png",
    hotel: "../../public/images/hotel.jpeg",
    melchora: "../../public/images/melchora.jpg",
    plaridel: "../../public/images/plaridel.jpeg",
    rizal: "../../public/images/types/lecture_hall.jpg",
    balagtas: "../../public/images/types/lecture_hall.jpg",
    ople: "../../public/images/types/science_lab.jpg",
    conference: "../../public/images/types/event_hall.jpg"
};

const TYPE_PHOTOS = {
    "computer lab": "../../public/images/types/computer_lab.jpg",
    "cisco lab": "../../public/images/types/computer_lab.jpg",
    "it department": "../../public/images/types/computer_lab.jpg",
    "ai data center": "../../public/images/types/data_center.jpg",
    "crime lab": "../../public/images/types/science_lab.jpg",
    "forensic room": "../../public/images/types/science_lab.jpg",
    "firing range room": "../../public/images/types/science_lab.jpg",
    "psychology testing r": "../../public/images/types/lecture_hall.jpg",
    "nursing skills": "../../public/images/types/science_lab.jpg",
    "library": "../../public/images/types/library.jpg",
    "event": "../../public/images/types/event_hall.jpg",
    "function hall": "../../public/images/types/event_hall.jpg",
    "reception": "../../public/images/hotel.jpeg",
    "lecture": "../../public/images/types/lecture_hall.jpg"
};

function roomPhoto(room) {
    const rawType = String(room?.room_type ?? "").trim().toLowerCase();
    const rawHall = String(room?.hall_name ?? "").trim().toLowerCase();
    const rawName = String(room?.room_name ?? "").trim().toLowerCase();

    if (rawHall === "gym" || rawName === "gym") {
        return "../../public/images/gym.png";
    }

    // 1. Specialized Room Type
    if (TYPE_PHOTOS[rawType]) {
        return TYPE_PHOTOS[rawType];
    }

    // 2. Hall Building Photo
    if (HALL_PHOTOS[rawHall]) {
        return HALL_PHOTOS[rawHall];
    }

    // 3. Fallback to generic example
    return PLACEHOLDER_IMAGE;
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/test_homepage_redesign_smoke.php`
Expected: PASS

- [ ] **Step 5: Commit changes**

```bash
git add public/js/homepage_script.js
git commit -m "fix: implement zero-404 hierarchical room photo resolver"
```

---

### Task 3: Synchronize Mockup Prototypes

**Files:**
- Modify: `mockups/iteration_1_pro_max_dark.html`
- Modify: `mockups/iteration_1_pro_max_light.html`
- Modify: `mockups/option_1_collegiate.html`

**Interfaces:**
- Produces: Prototypes showing matching diverse room photos across Computer Lab, Multimedia Hall, Forensic Room, and Lecture Halls.

- [ ] **Step 1: Update mockups to reference the new diverse room category assets**
Replace identical image paths with the corresponding room category photos (`types/computer_lab.jpg`, `types/science_lab.jpg`, `types/lecture_hall.jpg`, `melchora.jpg`, `plaridel.jpeg`).

- [ ] **Step 2: Commit mockup updates**

```bash
git add mockups/
git commit -m "docs: synchronize prototype mockups with diverse room photography"
```

---

### Task 4: End-to-End Verification with Headless Browser

**Files:**
- Test: `tests/test_console_errors_e2e.js` (Puppeteer script)

**Interfaces:**
- Consumes: Live PHP server on localhost / mock HTML page
- Produces: 0 console errors, 0 failed network requests, verified distinct image URLs

- [ ] **Step 1: Write Puppeteer script testing for console 404s and image variety**
Create script to listen to `response` and `console` events, asserting that no requests return HTTP 404 and that rendered card images contain at least 4 distinct image URLs.

- [ ] **Step 2: Execute E2E verification**
Run: `node tests/test_console_errors_e2e.js`
Expected: 0 console errors, 0 HTTP 404 responses, diverse image distribution confirmed.

- [ ] **Step 3: Run the full PHP smoke test suite**
Run: `php tests/test_homepage_redesign_smoke.php`
Expected: All 6 sections and all 17 matrix items pass with exit code 0.

- [ ] **Step 4: Commit verification script**

```bash
git add tests/test_console_errors_e2e.js
git commit -m "test: add e2e test verifying zero 404s and image diversity"
```
