const START_HOUR = 7;
const END_HOUR   = 21;
const DAYS       = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
const MONTHS     = ['January','February','March','April','May','June',
                    'July','August','September','October','November','December'];

let weekOffset   = 0;
let allSchedules = [];
let myPending    = [];
const hallMap    = new Map();

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function getWeekStart(offset = 0) {
    const now = new Date();
    const day = now.getDay();
    const sun = new Date(now);
    sun.setDate(now.getDate() - day + (offset * 7));
    sun.setHours(0, 0, 0, 0);
    return sun;
}

function dateStr(d) {
    return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
}

function fmt12(timeStr) {
    if (!timeStr) return '';
    const [h, m] = timeStr.split(':');
    const hour = parseInt(h);
    return `${hour % 12 || 12}:${m}${hour >= 12 ? 'pm' : 'am'}`;
}

// ─── Load data then build filters ─────────────────────────
function loadAll() {
    const p1 = fetch('../controllers/calendar_controller.php')
        .then(r => r.json())
        .then(res => { if (res.success) allSchedules = res.data; });

    const p2 = fetch('../controllers/my_pending.php')
        .then(r => r.json())
        .then(res => { if (res.success) myPending = res.data; })
        .catch(() => { myPending = []; });

    Promise.all([p1, p2]).then(() => {
        loadHallFilter();
        renderWeek();
    });
}

// ─── Populate Hall dropdown from room_controller.php?action=get_halls ────────────
function loadHallFilter() {
    fetch('../controllers/room_controller.php?action=get_halls')
        .then(r => r.json())
        .then(res => {
            if (!res.success) return;
            const sel = document.getElementById('filter-hall');
            res.data.forEach(h => {
                hallMap.set(h.hall_name.toLowerCase(), h.hall_id);
                const opt = document.createElement('option');
                opt.value       = h.hall_name;
                opt.dataset.id  = h.hall_id;
                opt.textContent = h.hall_name;
                sel.appendChild(opt);
            });
        });
}

// ─── When a hall is chosen, populate the Room dropdown ────
document.getElementById('filter-hall').addEventListener('change', function () {
    const roomSel = document.getElementById('filter-room');
    roomSel.innerHTML = '<option value="">All Rooms</option>';

    const selectedOpt = this.options[this.selectedIndex];
    const hallId = selectedOpt.dataset.id;
    if (!hallId) { applyFilters(); return; }

    fetch(`../controllers/room_controller.php?hall_id=${hallId}&action=get_rooms`)
        .then(r => r.json())
        .then(res => {
            if (!res.success) return;
            res.data.forEach(room => {
                const opt = document.createElement('option');
                opt.value       = room.room_name;
                opt.textContent = room.room_name;
                roomSel.appendChild(opt);
            });
        })
        .finally(() => applyFilters());
});

// ─── Render week ──────────────────────────────────────────
// ─── Compute side-by-side lanes for time-overlapping events (per day) ───
function computeDayLayouts(list) {
    const layout = new Map();
    const items = list.filter(ev => {
        const h = Number(ev.schedule_start.split(':')[0]);
        return h >= START_HOUR && h <= END_HOUR;
    }).map(ev => {
        const [sh, sm] = ev.schedule_start.split(':').map(Number);
        const [eh, em] = ev.schedule_end.split(':').map(Number);
        return { ev, start: sh * 60 + sm, end: eh * 60 + em };
    }).sort((a, b) => a.start - b.start || a.end - b.end);

    let cluster = [];
    let clusterEnd = -1;

    const flush = () => {
        if (!cluster.length) return;
        const laneEnds = [];
        cluster.forEach(item => {
            let lane = laneEnds.findIndex(end => end <= item.start);
            if (lane === -1) { lane = laneEnds.length; laneEnds.push(item.end); }
            else { laneEnds[lane] = item.end; }
            item.lane = lane;
        });
        const total = laneEnds.length;
        cluster.forEach(item => layout.set(item.ev, { lane: item.lane, lanes: total }));
        cluster = [];
        clusterEnd = -1;
    };

    items.forEach(item => {
        if (cluster.length && item.start >= clusterEnd) flush();
        cluster.push(item);
        clusterEnd = Math.max(clusterEnd, item.end);
    });
    flush();

    return layout;
}

function renderWeek() {
    const weekStart = getWeekStart(weekOffset);
    const days = Array.from({length: 7}, (_, i) => {
        const d = new Date(weekStart);
        d.setDate(weekStart.getDate() + i);
        return d;
    });

    const wEnd = days[6];
    document.getElementById('week-label').textContent =
        `${MONTHS[weekStart.getMonth()].slice(0,3)} ${weekStart.getDate()} – ${MONTHS[wEnd.getMonth()].slice(0,3)} ${wEnd.getDate()}, ${wEnd.getFullYear()}`;

    const filterHall = document.getElementById('filter-hall').value;
    const filterRoom = document.getElementById('filter-room').value;

    function matchFilters(s) {
        if (filterHall && s.hall_name !== filterHall) return false;
        if (filterRoom && s.room_name !== filterRoom) return false;
        return true;
    }
    
    const approved = allSchedules.filter(matchFilters);
    const pending  = myPending.filter(matchFilters);

    const eventsByDay = Array.from({length: 7}, () => []);
    const DOW_MAP = {sunday:0,monday:1,tuesday:2,wednesday:3,thursday:4,friday:5,saturday:6};

    function addEvents(list, cssClass) {
        list.forEach(s => {
            if (s.schedule_day) {
                const d = new Date(s.schedule_day + 'T00:00:00');
                days.forEach((day, idx) => {
                    if (dateStr(day) === dateStr(d)) {
                        eventsByDay[idx].push({ ...s, cssClass, calendarDate: dateStr(day) });
                    }
                });
            } else if (s.schedule_day_of_week) {
                const dowIdx = DOW_MAP[s.schedule_day_of_week.toLowerCase()];
                if (dowIdx !== undefined && days[dowIdx]) {
                    eventsByDay[dowIdx].push({ ...s, cssClass, calendarDate: dateStr(days[dowIdx]) });
                }
            }
        });
    }

    addEvents(approved, 'approved');
    addEvents(pending,  'pending');

    // Detect double-bookings per day where multiple reservations share the exact same room with overlapping time ranges
    eventsByDay.forEach(dayEvents => detectDayConflicts(dayEvents));

    // Lay out events that overlap in time side-by-side (per day) so they never
    // stack on top of each other, even when they belong to different rooms.
    const dayLayouts = eventsByDay.map(computeDayLayouts);

    const grid = document.getElementById('cal-grid');
    grid.innerHTML = '';

    const hours = [];
    for (let h = START_HOUR; h <= END_HOUR; h++) hours.push(h);

    // Corner
    const corner = document.createElement('div');
    corner.classList.add('cal-corner');
    grid.appendChild(corner);

    const today = new Date(); today.setHours(0, 0, 0, 0);

    // Day headers
    days.forEach(d => {
        const hdr = document.createElement('div');
        hdr.classList.add('cal-day-header');
        if (d.getTime() === today.getTime()) hdr.classList.add('today');
        hdr.innerHTML = `
            <div class="day-name">${DAYS[d.getDay()]}</div>
            <div class="day-date">${d.getDate()}</div>
            <div class="day-full-date">${MONTHS[d.getMonth()].slice(0,3)} ${d.getDate()}, ${d.getFullYear()}</div>
        `;
        grid.appendChild(hdr);
    });

    // Time rows
    hours.forEach(h => {
        const lbl = document.createElement('div');
        lbl.classList.add('time-label');
        lbl.innerHTML = `<span>${h % 12 || 12} ${h >= 12 ? 'pm' : 'am'}</span>`;
        grid.appendChild(lbl);

        days.forEach((_, dayIdx) => {
            const cell = document.createElement('div');
            cell.classList.add('cal-cell');
            grid.appendChild(cell);

            eventsByDay[dayIdx].forEach(ev => {
                const [evH, evM] = ev.schedule_start.split(':').map(Number);
                if (evH !== h) return;

                const [endH, endM] = ev.schedule_end.split(':').map(Number);
                const durationMins = (endH * 60 + endM) - (evH * 60 + evM);
                const topPct   = (evM / 60) * 100;
                const heightPx = Math.max(24, (durationMins / 60) * 60);

                const block = document.createElement('div');
                block.classList.add('cal-event', ev.cssClass);
                if (ev.isConflict) {
                    block.classList.add('has-conflict');
                }
                block.style.top    = `${topPct}%`;
                block.style.height = `${heightPx}px`;

                // Place overlapping events side-by-side instead of stacking them
                const li = dayLayouts[dayIdx].get(ev);
                if (li && li.lanes > 1) {
                    block.style.left  = `calc(${(li.lane / li.lanes) * 100}% + 3px)`;
                    block.style.right = `calc(${((li.lanes - 1 - li.lane) / li.lanes) * 100}% + 3px)`;
                }
                block.innerHTML = `
                    ${ev.isConflict ? `<div class="conflict-badge-pill" title="Double-booking conflict detected"><i class="fas fa-exclamation-triangle"></i> Conflict</div>` : ''}
                    <div class="ev-room">${escapeHtml(ev.room_name)}</div>
                    <div class="ev-hall">${escapeHtml(ev.hall_name)}</div>
                    <div class="ev-time">${fmt12(ev.schedule_start)} – ${fmt12(ev.schedule_end)}</div>
                    ${ev.room_type ? `<div class="ev-subject">${escapeHtml(ev.room_type)}</div>` : ''}
                `;

                block.addEventListener('click', (e) => {
                    e.stopPropagation();
                    openInspectorModal(ev);
                });

                cell.appendChild(block);
            });
        });
    });
}

function detectDayConflicts(events) {
    if (!events || events.length < 2) return;
    for (let i = 0; i < events.length; i++) {
        const evA = events[i];
        if (!evA.room_name || !evA.schedule_start || !evA.schedule_end) continue;
        const [aStartH, aStartM] = evA.schedule_start.split(':').map(Number);
        const [aEndH, aEndM] = evA.schedule_end.split(':').map(Number);
        const aStart = aStartH * 60 + (aStartM || 0);
        const aEnd = aEndH * 60 + (aEndM || 0);

        for (let j = i + 1; j < events.length; j++) {
            const evB = events[j];
            if (!evB.room_name || !evB.schedule_start || !evB.schedule_end) continue;
            if (evA.room_name.trim().toLowerCase() === evB.room_name.trim().toLowerCase()) {
                const [bStartH, bStartM] = evB.schedule_start.split(':').map(Number);
                const [bEndH, bEndM] = evB.schedule_end.split(':').map(Number);
                const bStart = bStartH * 60 + (bStartM || 0);
                const bEnd = bEndH * 60 + (bEndM || 0);

                if (aStart < bEnd && aEnd > bStart) {
                    evA.isConflict = true;
                    evB.isConflict = true;
                    if (!evA.conflictsWith) evA.conflictsWith = [];
                    if (!evB.conflictsWith) evB.conflictsWith = [];
                    evA.conflictsWith.push(evB);
                    evB.conflictsWith.push(evA);
                }
            }
        }
    }
}

function openInspectorModal(ev) {
    const modal = document.getElementById('cal-inspector-modal');
    if (!modal) return;

    const titleEl      = document.getElementById('modal-event-title');
    const conflictPill = document.getElementById('modal-conflict-pill');
    const roomHallEl   = document.getElementById('modal-room-hall');
    const dateTimeEl   = document.getElementById('modal-date-time');
    const statusEl     = document.getElementById('modal-status');
    const purposeEl    = document.getElementById('modal-purpose');
    const conflictBox  = document.getElementById('modal-conflict-box');
    const conflictDesc = document.getElementById('modal-conflict-details');
    const aiBtn        = document.getElementById('modal-btn-ai-resolve');

    if (titleEl) {
        titleEl.innerHTML = ev.isConflict
            ? '<i class="fas fa-triangle-exclamation" style="color:#ef4444;"></i> Schedule Conflict Inspector'
            : '<i class="fas fa-calendar-check" style="color:var(--brand-accent);"></i> Reservation Inspector';
    }

    if (roomHallEl) {
        roomHallEl.textContent = `${ev.room_name} (${ev.hall_name || 'Building'})`;
    }

    if (dateTimeEl) {
        dateTimeEl.textContent = `${ev.calendarDate || ev.schedule_day || ev.schedule_day_of_week || 'Scheduled Date'} • ${fmt12(ev.schedule_start)} – ${fmt12(ev.schedule_end)}`;
    }

    if (statusEl) {
        const isAppr = ev.cssClass === 'approved';
        statusEl.innerHTML = `<span style="display:inline-flex; align-items:center; gap:6px; font-weight:600; color:${isAppr ? 'var(--brand-accent)' : 'var(--warning)'};"><i class="fas fa-circle" style="font-size:7px;"></i> ${isAppr ? 'Approved Schedule' : 'Pending Reservation (Yours)'}</span>`;
    }

    if (purposeEl) {
        const organizer = (ev.account_fname || ev.account_lname)
            ? `${ev.account_fname || ''} ${ev.account_lname || ''}`.trim()
            : (ev.room_type || 'ClassSpace Booking');
        purposeEl.textContent = organizer;
    }

    if (ev.isConflict && ev.conflictsWith && ev.conflictsWith.length > 0) {
        if (conflictPill) conflictPill.style.display = 'inline-flex';
        if (conflictBox) conflictBox.style.display = 'block';

        const confListHtml = ev.conflictsWith.map(other => {
            const otherStatus = other.cssClass === 'approved' ? 'Approved' : 'Pending';
            return `<li>Overlaps with <strong>${escapeHtml(other.room_name)}</strong> from <strong>${fmt12(other.schedule_start)} – ${fmt12(other.schedule_end)}</strong> (${otherStatus}).</li>`;
        }).join('');

        if (conflictDesc) {
            conflictDesc.innerHTML = `<ul style="margin: 4px 0; padding-left: 18px;">${confListHtml}</ul>`;
        }

        if (aiBtn) {
            aiBtn.style.display = 'inline-flex';

            const toH_i = (t) => {
                if (!t) return '';
                const parts = t.split(':');
                return parts.length >= 2 ? `${parts[0].padStart(2, '0')}:${parts[1].padStart(2, '0')}` : t;
            };

            const start = toH_i(ev.schedule_start);
            const end   = toH_i(ev.schedule_end);
            const hallId = ev.hall_id || (ev.hall_name ? (hallMap.get(ev.hall_name.toLowerCase()) || '') : '') || '';
            const hallName = ev.hall_name || '';
            const roomId = ev.room_id || '';
            const roomName = ev.room_name || '';

            const isWeekly = !ev.schedule_day && !!ev.schedule_day_of_week;
            const resType = isWeekly ? 'weekly' : 'one-time';
            const dow = ev.schedule_day_of_week || '';
            const targetDate = ev.schedule_day || ev.calendarDate || '';

            const q = new URLSearchParams({
                auto_check: '1',
                type: resType,
                hall_id: String(hallId),
                hall_name: hallName,
                room_id: String(roomId),
                room_name: roomName,
                start: start,
                end: end
            });

            if (isWeekly) {
                q.append('dow', dow);
            } else {
                q.append('date', targetDate);
            }

            aiBtn.href = `reserve_view.php?${q.toString()}`;
        }
    } else {
        if (conflictPill) conflictPill.style.display = 'none';
        if (conflictBox) conflictBox.style.display = 'none';
        if (aiBtn) aiBtn.style.display = 'none';
    }

    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeInspectorModal() {
    const modal = document.getElementById('cal-inspector-modal');
    if (modal) modal.style.display = 'none';
    document.body.style.overflow = '';
}

function handleModalBackdropClick(e) {
    if (e.target.id === 'cal-inspector-modal') {
        closeInspectorModal();
    }
}

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeInspectorModal();
});

window.closeInspectorModal = closeInspectorModal;
window.handleModalBackdropClick = handleModalBackdropClick;

function shiftWeek(dir) { weekOffset += dir; renderWeek(); }
function goToday()      { weekOffset = 0;    renderWeek(); }
function applyFilters() { renderWeek(); }

loadAll();
