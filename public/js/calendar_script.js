const START_HOUR = 7;
const END_HOUR   = 21;
const DAYS       = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
const MONTHS     = ['January','February','March','April','May','June',
                    'July','August','September','October','November','December'];

let weekOffset   = 0;
let allSchedules = [];
let myPending    = [];

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
                    if (dateStr(day) === dateStr(d)) eventsByDay[idx].push({...s, cssClass});
                });
            } else if (s.schedule_day_of_week) {
                const dowIdx = DOW_MAP[s.schedule_day_of_week.toLowerCase()];
                if (dowIdx !== undefined) eventsByDay[dowIdx].push({...s, cssClass});
            }
        });
    }

    addEvents(approved, 'approved');
    addEvents(pending,  'pending');

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
                block.style.top    = `${topPct}%`;
                block.style.height = `${heightPx}px`;

                // Place overlapping events side-by-side instead of stacking them
                const li = dayLayouts[dayIdx].get(ev);
                if (li && li.lanes > 1) {
                    block.style.left  = `calc(${(li.lane / li.lanes) * 100}% + 3px)`;
                    block.style.right = `calc(${((li.lanes - 1 - li.lane) / li.lanes) * 100}% + 3px)`;
                }
                block.innerHTML = `
                    <div class="ev-room">${ev.room_name}</div>
                    <div class="ev-hall">${ev.hall_name}</div>
                    <div class="ev-time">${fmt12(ev.schedule_start)} – ${fmt12(ev.schedule_end)}</div>
                    ${ev.room_type ? `<div class="ev-subject">${ev.room_type}</div>` : ''}
                `;
                cell.appendChild(block);
            });
        });
    });
}

function shiftWeek(dir) { weekOffset += dir; renderWeek(); }
function goToday()      { weekOffset = 0;    renderWeek(); }
function applyFilters() { renderWeek(); }

loadAll();
