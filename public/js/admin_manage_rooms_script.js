// ==========================================================================
// 1. STATE MANAGEMENT
// ==========================================================================
let allRooms           = [];   // full unfiltered list from server
let displayRows        = [];   // currently shown (after search/status filter)
let currentPage        = 1;
let perPage            = 10;
let activeStatusFilter = null; // "available" | "unavailable" | "maintenance" | null
let searchFilteredRows = null; // non-null when a search filter is active

// ==========================================================================
// 2. HELPERS & FORMATTERS
// ==========================================================================
function formatTime(t) {
    if (!t) return "—";
    const [h, m] = t.split(":");
    const hour = parseInt(h);
    return `${hour % 12 || 12}:${m} ${hour >= 12 ? "PM" : "AM"}`;
}

function statusClass(s) {
    if (!s) return "s-maintenance";
    const v = s.toLowerCase();
    if (v === "available")   return "s-available";
    if (v === "unavailable") return "s-unavailable";
    return "s-maintenance";
}

function escHtml(s) {
    if (!s) return "";
    return String(s)
        .replace(/&/g, "&amp;").replace(/</g, "&lt;")
        .replace(/>/g, "&gt;").replace(/"/g, "&quot;");
}

function escAttr(s) {
    if (!s) return "";
    return String(s).replace(/'/g, "\\'");
}

// ==========================================================================
// 3. REMOTE DATALOAD & STATS UPDATES
// ==========================================================================
function loadAllRooms() {
    fetch("../controllers/room_controller.php?action=get_all_rooms")
        .then(r => r.json())
        .then(res => {
            if (!res.success) return;
            allRooms           = res.data;
            searchFilteredRows = null;
            updateTableStats();
            applyFilters();
        })
        .catch(() => {});
}

function updateTableStats() {
    const total   = allRooms.length;
    const avail   = allRooms.filter(r => r.room_status?.toLowerCase() === "available").length;
    const unavail = allRooms.filter(r => r.room_status?.toLowerCase() === "unavailable").length;
    const maint   = allRooms.filter(r => r.room_status?.toLowerCase() === "maintenance").length;

    document.getElementById("ts-total").textContent   = total;
    document.getElementById("ts-avail").textContent   = avail;
    document.getElementById("ts-unavail").textContent = unavail;
    document.getElementById("ts-maint").textContent   = maint;
}

// ==========================================================================
// 4. FILTERING CONTEXTS
// ==========================================================================
function filterByStatus(status) {
    if (activeStatusFilter === status) {
        activeStatusFilter = null;
        document.querySelectorAll(".tstat").forEach(b => b.classList.remove("tstat-active"));
    } else {
        activeStatusFilter = status;
        document.querySelectorAll(".tstat").forEach(b => b.classList.remove("tstat-active"));
        document.getElementById("tstat-" + status).classList.add("tstat-active");
    }
    applyFilters();
}

function applyFilters() {
    const base = searchFilteredRows !== null ? searchFilteredRows : allRooms;
    displayRows = activeStatusFilter
        ? base.filter(r => r.room_status?.toLowerCase() === activeStatusFilter)
        : [...base];
    currentPage = 1;
    renderTable();
}

// ==========================================================================
// 5. TABLE ENGINE RENDERING (MODIFIED FOR SINGLE DROPDOWN CHOICE INDICATOR)
// ==========================================================================
function renderTable() {
    const tbody = document.getElementById("rooms-tbody");
    const total = displayRows.length;
    const pages = Math.max(1, Math.ceil(total / perPage));
    if (currentPage > pages) currentPage = pages;

    const start = (currentPage - 1) * perPage;
    const end   = Math.min(start + perPage, total);
    const slice = displayRows.slice(start, end);

    document.getElementById("showing-label").innerHTML = total === 0
        ? `Showing <strong>0</strong> of <strong>0</strong> rooms`
        : `Showing <strong>${start + 1} – ${end}</strong> out of <strong>${total}</strong> rooms`;

    if (slice.length === 0) {
        tbody.innerHTML = `<tr><td colspan="5" class="td-empty">No rooms found.</td></tr>`;
        renderPagination(0, 1);
        return;
    }

    tbody.innerHTML = "";
    slice.forEach((room, i) => {
        const tr = document.createElement("tr");
        const sc = statusClass(room.room_status);

        /* TINANGGAL ANG '●' SA MGA STRINGS NG OPTION PARA DROPDOWN ARROW LANG ANG GAGANA */
        const statusLabel = room.room_status
            ? room.room_status.charAt(0).toUpperCase() + room.room_status.slice(1).toLowerCase()
            : "Maintenance";

        tr.innerHTML = `
            <td class="td-index">${start + i + 1}</td>
            <td>
                <div class="cell-primary">${escHtml(room.hall_name)}</div>
            </td>
            <td>
                <div class="cell-primary">${escHtml(room.room_name)}</div>
                <div class="cell-secondary">${escHtml(room.room_type ?? "")} · ${room.room_has_ac == 1 ? "❄️ AC" : "No AC"}</div>
            </td>
            <td class="td-muted">${room.room_capacity}</td>
            <td>
                <div class="td-actions">
                    <div class="custom-status-wrap" data-room-id="${room.room_id}">
                        <button class="custom-status-btn ${sc}" onclick="toggleStatusDropdown(this, event)">
                            <span class="csd-dot"></span>
                            <span class="csd-label">${statusLabel}</span>
                            <i class="fas fa-chevron-down csd-arrow"></i>
                        </button>
                        <div class="custom-status-menu">
                            <div class="csm-item csm-available" onclick="selectStatus(this, '${room.room_id}', 'available')">
                                <span class="csd-dot"></span> Available
                            </div>
                            <div class="csm-item csm-unavailable" onclick="selectStatus(this, '${room.room_id}', 'unavailable')">
                                <span class="csd-dot"></span> Unavailable
                            </div>
                            <div class="csm-item csm-maintenance" onclick="selectStatus(this, '${room.room_id}', 'maintenance')">
                                <span class="csd-dot"></span> Maintenance
                            </div>
                        </div>
                    </div>
                    <button class="btn-view-res"
                        onclick="openModal(${room.room_id}, '${escAttr(room.room_name)}', '${escAttr(room.hall_name)}')">
                        <i class="fas fa-calendar-alt btn-icon"></i>Reservations
                    </button>
                </div>
            </td>
        `;
        tbody.appendChild(tr);
    });

    renderPagination(total, pages);
}

// ==========================================================================
// 6. PAGINATION FLOW Control
// ==========================================================================
function renderPagination(total, pages) {
    const wrap = document.getElementById("pag-btns");
    wrap.innerHTML = "";

    const prev     = document.createElement("button");
    prev.className = "pag-btn";
    prev.innerHTML = `<i class="fas fa-chevron-left pag-icon"></i>`;
    prev.disabled  = (currentPage === 1);
    prev.onclick   = () => { if (currentPage > 1) { currentPage--; renderTable(); } };
    wrap.appendChild(prev);

    getPageRange(currentPage, pages).forEach(p => {
        const btn     = document.createElement("button");
        btn.className = "pag-btn" + (p === "..." ? "" : p === currentPage ? " active" : "");
        btn.textContent = p === "..." ? "…" : p;
        btn.disabled  = (p === "...");
        if (p !== "...") btn.onclick = () => { currentPage = p; renderTable(); };
        wrap.appendChild(btn);
    });

    const next     = document.createElement("button");
    next.className = "pag-btn";
    next.innerHTML = `<i class="fas fa-chevron-right pag-icon"></i>`;
    next.disabled  = (currentPage === pages || pages === 0);
    next.onclick   = () => { if (currentPage < pages) { currentPage++; renderTable(); } };
    wrap.appendChild(next);
}

function getPageRange(cur, total) {
    if (total <= 5)         return Array.from({ length: total }, (_, i) => i + 1);
    if (cur <= 3)           return [1, 2, 3, 4, "...", total];
    if (cur >= total - 2)   return [1, "...", total - 3, total - 2, total - 1, total];
    return [1, "...", cur - 1, cur, cur + 1, "...", total];
}

function changePerPage() {
    perPage     = parseInt(document.getElementById("per-page").value);
    currentPage = 1;
    renderTable();
}

// ==========================================================================
// 7. TRANSACTION ENGINE (UPDATE STATUS) + CUSTOM DROPDOWN CONTROLS
// ==========================================================================
function toggleStatusDropdown(btn, e) {
    e.stopPropagation();
    const wrap = btn.closest(".custom-status-wrap");
    const isOpen = wrap.classList.contains("open");
    document.querySelectorAll(".custom-status-wrap.open").forEach(w => w.classList.remove("open"));
    if (!isOpen) wrap.classList.add("open");
}

function selectStatus(item, roomId, newStatus) {
    const wrap  = item.closest(".custom-status-wrap");
    const btn   = wrap.querySelector(".custom-status-btn");
    const label = btn.querySelector(".csd-label");
    const sc    = statusClass(newStatus);
    btn.className = "custom-status-btn " + sc;
    label.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
    wrap.classList.remove("open");
    updateStatus(roomId, newStatus);
}

document.addEventListener("click", () => {
    document.querySelectorAll(".custom-status-wrap.open").forEach(w => w.classList.remove("open"));
});

function updateStatus(roomId, newStatus) {
    fetch("../controllers/room_controller.php?action=update_status", {
        method:  "POST",
        headers: { "Content-Type": "application/json" },
        body:    JSON.stringify({ room_id: roomId, status: newStatus})
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            const room = allRooms.find(r => r.room_id == roomId);
            if (room) room.room_status = newStatus;
            if (searchFilteredRows) {
                const sr = searchFilteredRows.find(r => r.room_id == roomId);
                if (sr) sr.room_status = newStatus;
            }
            updateTableStats();
            applyFilters();
            toast.show("Room status updated.", "success", 2200);
        } else {
            toast.show(res.message ?? "Failed to update status.", "error", 2500);
            loadAllRooms();
        }
    })
    .catch(() => { toast.show("Network error.", "error", 2500); loadAllRooms(); });
}

// ==========================================================================
// 8. RESERVATIONS SUBSYSTEM (MODAL ACTION)
// ==========================================================================
function openModal(roomId, roomName, hallName) {
    document.getElementById("modal-title").innerHTML =
        `Reservations — <span>${escHtml(roomName)}</span> <span class="modal-hall-label">(${escHtml(hallName)})</span>`;
    document.getElementById("modal-body").innerHTML =
        `<div class="modal-loading"><div class="spinner"></div> Loading…</div>`;
    document.getElementById("res-modal").classList.add("open");

    fetch(`../controllers/admin_get_room_reservations.php?room_id=${roomId}`)
        .then(r => r.json())
        .then(res => {
            const body = document.getElementById("modal-body");
            if (!res.success || res.data.length === 0) {
                body.innerHTML = `
                    <div class="modal-empty">
                        <i class="fas fa-calendar-xmark modal-empty-icon"></i>
                        No reservations found for this room.
                    </div>`;
                return;
            }
            body.innerHTML = "";
            res.data.forEach(r => {
                const isRecurring = !r.schedule_day;
                const dayLabel    = isRecurring
                    ? `Every ${r.schedule_day_of_week}`
                    : new Date(r.schedule_day + "T00:00:00").toLocaleDateString("en-US", {
                        month: "short", day: "numeric", year: "numeric"
                    });

                const div       = document.createElement("div");
                div.className   = "res-item";
                div.innerHTML = `
                    <div class="res-info">
                        <div class="res-name">
                            ${escHtml(r.fname)} ${escHtml(r.lname)}
                            <span class="res-username">(${escHtml(r.username)})</span>
                        </div>
                        <div class="res-meta">${dayLabel} &nbsp;·&nbsp; ${formatTime(r.schedule_start)} – ${formatTime(r.schedule_end)}</div>
                    </div>
                    <span class="res-type-badge ${isRecurring ? "weekly" : "onetime"}">${isRecurring ? "Weekly" : "One-time"}</span>
                    <button class="btn-del-res"
                        onclick="deleteReservation(${r.schedule_id}, this, ${roomId}, '${escAttr(roomName)}', '${escAttr(hallName)}')">
                        <i class="fas fa-trash-alt"></i> Delete
                    </button>
                `;
                body.appendChild(div);
            });
        })
        .catch(() => {
            document.getElementById("modal-body").innerHTML =
                `<div class="modal-empty">Failed to load reservations.</div>`;
        });
}

function closeModal() {
    document.getElementById("res-modal").classList.remove("open");
}

document.getElementById("res-modal").addEventListener("click", function (e) {
    if (e.target === this) closeModal();
});

function deleteReservation(scheduleId, btn, roomId, roomName, hallName) {
    if (!confirm("Delete this reservation? This cannot be undone.")) return;
    btn.disabled     = true;
    btn.textContent  = "…";

    fetch("../controllers/admin_delete_reservation_controller.php", {
        method:  "POST",
        headers: { "Content-Type": "application/json" },
        body:    JSON.stringify({ schedule_id: scheduleId })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            toast.show("Reservation deleted.", "success", 2200);
            openModal(roomId, roomName, hallName);
        } else {
            toast.show(res.message ?? "Failed to delete.", "error", 2500);
            btn.disabled  = false;
            btn.innerHTML = `<i class="fas fa-trash-alt"></i> Delete`;
        }
    })
    .catch(() => { toast.show("Network error.", "error", 2500); });
}

// ==========================================================================
// 9. SEARCH INTEGRATION
// ==========================================================================
const input      = document.getElementById("search-input");
const resultsBox = document.getElementById("search-results");

function runSearch() {
    const q = input.value.trim();
    if (!q) { resultsBox.innerHTML = ""; return; }

    fetch("../controllers/search_controller.php?q=" + encodeURIComponent(q))
        .then(r => r.json())
        .then(res => {
            resultsBox.innerHTML = "";
            if (!res.success || !res.data.length) {
                resultsBox.innerHTML = `<div class="search-item">No results found</div>`;
                return;
            }
            res.data.forEach(item => {
                const label = item.type === "hall"
                    ? `<strong>${escHtml(item.name)}</strong> <span>(Hall)</span>`
                    : `<strong>${escHtml(item.room_name)}</strong> <span>(${escHtml(item.name)})</span>`;
                const div       = document.createElement("div");
                div.className   = "search-item";
                div.innerHTML   = label;
                div.addEventListener("click", () => {
                    input.value          = item.type === "hall" ? item.name : item.room_name;
                    resultsBox.innerHTML = "";
                    filterBySearch(item.type, item.id);
                });
                resultsBox.appendChild(div);
            });
        });
}

function triggerSearch() {
    const q          = input.value.trim();
    resultsBox.innerHTML = "";
    if (!q) {
        searchFilteredRows = null;
        applyFilters();
        return;
    }

    fetch("../controllers/search_controller.php?q=" + encodeURIComponent(q))
        .then(r => r.json())
        .then(res => {
            if (!res.success || !res.data.length) {
                searchFilteredRows = [];
                applyFilters();
                return;
            }
            const hallIds      = new Set(res.data.filter(i => i.type === "hall").map(i => i.id));
            const roomIds      = new Set(res.data.filter(i => i.type === "room").map(i => i.id));
            searchFilteredRows = allRooms.filter(r => roomIds.has(r.room_id) || hallIds.has(r.hall_id));
            applyFilters();
        });
}

function filterBySearch(type, id) {
    searchFilteredRows = type === "hall"
        ? allRooms.filter(r => r.hall_id == id)
        : allRooms.filter(r => r.room_id == id);
    applyFilters();
}

input.addEventListener("keyup", e => {
    if (e.key === "Enter") triggerSearch();
    else runSearch();
});

document.addEventListener("click", e => {
    if (!document.querySelector(".toolbar-search").contains(e.target)) {
        resultsBox.innerHTML = "";
    }
});

// ==========================================================================
// 10. SYSTEM RUNTIME LIFECYCLE INITIALIZATION
// ==========================================================================
loadAllRooms();