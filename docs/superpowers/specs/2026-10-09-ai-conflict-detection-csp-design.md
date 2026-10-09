# Design Specification: AI Conflict Detection & CSP Recommendation Engine

- **Project**: ClassSpace (Web-Based Room Allocation and Reservation System)
- **Feature**: AI-Based Conflict Detection & Constraint Satisfaction Slot/Room Recommendation
- **Date**: 2026-10-09
- **Status**: Approved (Brainstorming Phase Complete)
- **Author**: Antigravity & Development Team

---

## 1. Context & Motivation

### 1.1 Academic & System Requirements
As established in the institutional research manuscript (*ClassSpace: Chapter I–III*), the system’s core identity is:
> *"Web-Based Room Allocation and Reservation System with Artificial Intelligence-Based Conflict Detection and Administrative Approval"*

Chapter 3 specifies two distinct algorithmic processes:
1. **Conflict Detection Algorithm**: A deterministic interval-overlap screening gate preventing double-booking before submission to the administrative queue.
2. **Constraint Satisfaction–Based Slot Suggestion Algorithm (CSP)**: An automated heuristic recommender that, upon detecting a collision, identifies open 30-minute intervals between 7:00 AM and 9:00 PM, searches compatible rooms in the same hall, and ranks optimal alternatives.

### 1.2 Current State & Gaps
* **Dead-End Conflict Alerts**: When a collision occurs, `check_conflict.php` only returns `"conflict": true, "message": "This room is already booked..."`. The user receives no assistance or alternatives.
* **Randomized Suggestion Placeholder**: `suggestion_room_controller.php` currently executes `ORDER BY RAND() LIMIT 12`, lacking any constraint satisfaction intelligence.
* **Asymmetric Overlap Defect ([GitHub Issue #8](https://github.com/jhusnine/Class-space/issues/8))**: `check_conflict.php` does not check weekly recurring requests against upcoming one-time bookings.
* **Silent Calendar Overlaps**: `calendar_script.js` lays out time-overlapping events side-by-side using CSS grid without flagging double-booked collisions for the same room.

---

## 2. Theoretical Formulation: Constraint Satisfaction Problem (CSP)

The recommendation engine models classroom scheduling as a formal CSP tuple:

$$\text{CSP} = \langle X, D, C \rangle$$

### 2.1 Variables ($X$)
* $X_{\text{room}} \in \text{room\_id}$: Recommended candidate room.
* $X_{\text{start}}$: Recommended start time.
* $X_{\text{end}} = X_{\text{start}} + \text{requested\_duration}$: Recommended end time.
* $X_{\text{date}}$: Reservation date (or day of week for recurring).

### 2.2 Domains ($D$)
* **Time Domain ($D(X_{\text{start}})$)**: Discrete 30-minute intervals within institutional operating hours (07:00 to 21:00):
  $$\{ \text{07:00}, \text{07:30}, \text{08:00}, \dots, \text{21:00} - \text{duration} \}$$
* **Room Domain ($D(X_{\text{room}})$)**: All active rooms in the database:
  $$\{ r \in \text{rooms} \mid r.\text{room\_status} = \text{'available'} \}$$
  Scoped to the same hall first; relaxed campus-wide if candidates $< 2$.

### 2.3 Hard Constraints ($C_{\text{hard}}$)
1. **Operating Hours**: $07:00 \le X_{\text{start}} < X_{\text{end}} \le 21:00$.
2. **Operational Status**: $\text{room\_status}(X_{\text{room}}) = \text{'available'}$.
3. **Capacity Threshold**: $\text{capacity}(X_{\text{room}}) \ge \text{target\_capacity}$.
4. **Collision Freedom**: For every schedule $s \in (\text{schedule} \cup \text{pendingschedule})$ for $X_{\text{room}}$ on $X_{\text{date}}$:
   $$\neg (s.\text{start} < X_{\text{end}} \land s.\text{end} > X_{\text{start}})$$

### 2.4 Soft Constraints & Heuristic Penalty Function
When multiple candidates satisfy all hard constraints, they are scored using a weighted penalty function:

$$\text{Penalty} = (\Delta_{\text{time}} \times 1.0) + (\text{DiffHall} \times 60) + (\Delta_{\text{cap}} \times 0.5) + (\text{TypeMismatch} \times 40)$$

Where:
* $\Delta_{\text{time}} = |X_{\text{start}} - \text{RequestedStart}|$ in minutes.
* $\text{DiffHall} = 1$ if room is in a different building/hall, $0$ if same hall.
* $\Delta_{\text{cap}} = \text{capacity}(X_{\text{room}}) - \text{target\_capacity}$ (excess seat penalty).
* $\text{TypeMismatch} = 1$ if room amenities (AC, room type) do not match, $0$ otherwise.

The candidate with the **lowest penalty score** is ranked **#1**.

---

## 3. System Architecture & Endpoints

### 3.1 Backend Components

#### A. Upgraded `app/controllers/check_conflict.php`
* Fixes the asymmetric weekly-vs-one-time check ([Issue #8](https://github.com/jhusnine/Class-space/issues/8)).
* Adds `"has_suggestions": true` to the JSON payload whenever a conflict is detected to signal the frontend.

#### B. New Endpoint `app/controllers/ai_suggest_controller.php`
* **Method**: `GET`
* **Query Parameters**:
  * `room_id` (int, required)
  * `date` (YYYY-MM-DD, required if `type=one-time`)
  * `dow` (string, required if `type=weekly`)
  * `start` (HH:MM, required)
  * `end` (HH:MM, required)
  * `type` (`one-time` | `weekly`, required)
* **Response Contract**:
```json
{
  "success": true,
  "conflict_detected": true,
  "original_request": {
    "room_id": 14,
    "room_name": "Room 201",
    "hall_name": "Main Building",
    "time": "13:00 - 15:00",
    "date": "2026-10-15"
  },
  "ai_summary": "Room 201 is occupied from 1:00 PM – 2:30 PM. We evaluated campus rooms across operating hours and found 2 optimal alternatives.",
  "suggestions": [
    {
      "rank": 1,
      "type": "alternative_room",
      "room_id": 16,
      "room_name": "Room 203",
      "hall_name": "Main Building",
      "room_type": "Lecture",
      "room_has_ac": 1,
      "room_capacity": 45,
      "start": "13:00",
      "end": "15:00",
      "formatted_time": "1:00 PM – 3:00 PM",
      "reason": "Exact time match in the same hall with air conditioning and 45 seats."
    },
    {
      "rank": 2,
      "type": "alternative_slot",
      "room_id": 14,
      "room_name": "Room 201",
      "hall_name": "Main Building",
      "start": "15:00",
      "end": "17:00",
      "formatted_time": "3:00 PM – 5:00 PM",
      "reason": "Same room, opens immediately after the conflicting reservation ends."
    }
  ]
}
```

---

## 4. Frontend UI Integration

### 4.1 Reservation Form (`app/views/reserve_view.php` & `public/js/reserve_script.js`)
* When `checkConflicts()` detects an overlap, an animated **"ClassSpace AI Assistant"** container (`#ai-suggestions-container`) renders beneath the warning banner.
* Displays ranked suggestion cards featuring room metadata, time badges, and AI reasoning.
* **1-Click Apply Action**:
  * Clicking **"Apply Suggestion"** automatically updates the form inputs (swaps Hall & Room dropdowns or changes Start & End times).
  * Triggers a success toast notification: `"Applied AI suggestion: Room 203 (1:00 PM – 3:00 PM)"`.
  * Re-triggers conflict validation, clearing the warning banner and enabling submission.

### 4.2 Schedule Calendar (`app/views/calendar_view.php` & `public/js/calendar_script.js`)
* **Double-Booking Detection**: Scans the rendered events per day. If two events occupy the **same room** with overlapping hours, they receive a red border and a `⚠️ Double-Booked` badge.
* **Quick AI Inspector**: Clicking on any booked or conflicted event opens a modal with a **"Find Alternative with AI"** button, allowing instant redirection to `reserve_view.php` with pre-filled parameters.

---

## 5. Verification & Acceptance Criteria

1. **CSP Free-Gap Verification**:
   - Booking a room with an existing reservation from 10:00 to 12:00 must correctly identify 07:00–10:00 and 12:00–21:00 as candidate intervals.
2. **Alternative Room Discovery**:
   - Conflicted request for a 40-seat AC room must prioritize 40+ seat AC rooms in the same hall over distant halls or non-AC rooms.
3. **1-Click Apply**:
   - Clicking "Apply Suggestion" in `reserve_view.php` updates the form, re-checks availability, and enables the submit button without page reload.
4. **Performance**:
   - CSP solver execution completes in $< 15\text{ms}$ on local MySQL without external network dependencies.
5. **Issue #8 Resolution**:
   - Recurring weekly checks correctly detect upcoming one-time bookings on the same weekday.
