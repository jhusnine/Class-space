/**
 * ClassSpace Admin - Manage Rooms & Buildings Controller Script
 * Handles asynchronous data loading, segmented tab switching, real-time filtering,
 * dynamic live preview card updater, room and hall CRUD modals, and toast alerts.
 */

// Global State
let allRooms            = [];
let allHalls            = [];
let displayRows         = [];
let currentPage         = 1;
let perPage             = 10;
let activeStatusFilter  = null;
let currentDeleteAction = null;

// ==========================================
// Utility Functions
// ==========================================

function formatTime(t) {
    if (!t) return "—";
    const parts = t.split(":");
    if (parts.length < 2) return t;
    const hour = parseInt(parts[0], 10);
    const m = parts[1];
    return `${hour % 12 || 12}:${m} ${hour >= 12 ? "PM" : "AM"}`;
}

function statusClass(s) {
    if (!s) return "s-maintenance";
    const v = String(s).toLowerCase();
    if (v === "available")   return "s-available";
    if (v === "unavailable") return "s-unavailable";
    return "s-maintenance";
}

function escHtml(s) {
    if (s === null || s === undefined) return "";
    return String(s)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function escAttr(s) {
    if (s === null || s === undefined) return "";
    return String(s)
        .replace(/&/g, "&amp;")
        .replace(/'/g, "&#039;")
        .replace(/"/g, "&quot;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;");
}

/**
 * Dynamic Campus Image Resolver
 * Resolves building photo based on hall name, falling back to campus login background.
 */
function getBuildingImage(hallName) {
    if (!hallName) return "../../public/images/login_background.jpg";
    const name = String(hallName).toLowerCase();
    if (name.includes("plaridel")) return "../../public/images/plaridel.jpeg";
    if (name.includes("melchora")) return "../../public/images/melchora.jpg";
    if (name.includes("rizal")) return "../../public/images/rizal.jpg";
    if (name.includes("balagtas")) return "../../public/images/balagtas.jpg";
    if (name.includes("gym")) return "../../public/images/gym.png";
    if (name.includes("hotel")) return "../../public/images/hotel.jpeg";
    if (name.includes("ople")) return "../../public/images/ople.jpg";
    return "../../public/images/login_background.jpg";
}

// ==========================================
// Tab Switching & Badges
// ==========================================

function switchTab(tab) {
    const btnRooms = document.getElementById("tab-btn-rooms");
    const btnHalls = document.getElementById("tab-btn-halls");
    const contentRooms = document.getElementById("tab-rooms-content");
    const contentHalls = document.getElementById("tab-halls-content");

    if (tab === "rooms") {
        btnRooms?.classList.add("active");
        btnRooms?.setAttribute("aria-selected", "true");
        btnHalls?.classList.remove("active");
        btnHalls?.setAttribute("aria-selected", "false");

        if (contentRooms) {
            contentRooms.classList.add("active");
            contentRooms.style.display = "block";
        }
        if (contentHalls) {
            contentHalls.classList.remove("active");
            contentHalls.style.display = "none";
        }
    } else {
        btnHalls?.classList.add("active");
        btnHalls?.setAttribute("aria-selected", "true");
        btnRooms?.classList.remove("active");
        btnRooms?.setAttribute("aria-selected", "false");

        if (contentHalls) {
            contentHalls.classList.add("active");
            contentHalls.style.display = "block";
        }
        if (contentRooms) {
            contentRooms.classList.remove("active");
            contentRooms.style.display = "none";
        }
        renderHallsGrid();
    }
}

function updateTabBadges() {
    const r1 = document.getElementById("tab-rooms-badge");
    const r2 = document.getElementById("tab-badge-rooms");
    if (r1) r1.textContent = allRooms.length;
    if (r2) r2.textContent = allRooms.length;

    const h1 = document.getElementById("tab-halls-badge");
    const h2 = document.getElementById("tab-badge-halls");
    if (h1) h1.textContent = allHalls.length;
    if (h2) h2.textContent = allHalls.length;
}

// ==========================================
// Data Fetching: Rooms & Halls
// ==========================================

function loadAllRooms() {
    fetch("../controllers/admin_manage_rooms_controller.php?action=get_rooms")
        .then(r => r.json())
        .then(res => {
            if (!res.success) {
                if (window.toast) toast.show("Failed to load rooms.", "error");
                return;
            }
            allRooms = res.data || [];
            updateTableStats();
            updateTabBadges();
            applyFilters();
        })
        .catch(() => {
            const tbody = document.getElementById("rooms-tbody");
            if (tbody) {
                tbody.innerHTML = `<tr><td colspan="6" class="td-empty">Failed to load rooms. Please check your connection.</td></tr>`;
            }
        });
}

function loadAllHalls() {
    fetch("../controllers/admin_manage_rooms_controller.php?action=get_halls")
        .then(r => r.json())
        .then(res => {
            if (!res.success) {
                if (window.toast) toast.show("Failed to load campus buildings.", "error");
                return;
            }
            allHalls = res.data || [];
            updateHallsStats();
            updateTabBadges();
            populateHallDropdowns();
            renderHallsGrid();
        })
        .catch(() => {
            const grid = document.getElementById("halls-grid");
            if (grid) {
                grid.innerHTML = `<div class="td-empty" style="grid-column: 1 / -1; padding: 48px; text-align: center;">Failed to load campus buildings.</div>`;
            }
        });
}

function updateTableStats() {
    const total   = allRooms.length;
    const avail   = allRooms.filter(r => (r.room_status || "").toLowerCase() === "available").length;
    const unavail = allRooms.filter(r => (r.room_status || "").toLowerCase() === "unavailable").length;
    const maint   = allRooms.filter(r => (r.room_status || "").toLowerCase() === "maintenance").length;

    const elTotal = document.getElementById("ts-total");
    const elAvail = document.getElementById("ts-avail");
    const elUnavail = document.getElementById("ts-unavail");
    const elMaint = document.getElementById("ts-maint");

    if (elTotal) elTotal.textContent = total;
    if (elAvail) elAvail.textContent = avail;
    if (elUnavail) elUnavail.textContent = unavail;
    if (elMaint) elMaint.textContent = maint;
}

function updateHallsStats() {
    const totalEl = document.getElementById("hs-total");
    if (totalEl) totalEl.textContent = allHalls.length;
}

function populateHallDropdowns() {
    const filterSelect = document.getElementById("filter-hall");
    const modalSelect = document.getElementById("room-hall-select");

    const currentFilter = filterSelect ? filterSelect.value : "";
    const currentModal = modalSelect ? modalSelect.value : "";

    if (filterSelect) {
        filterSelect.innerHTML = `
            <option value="">All Buildings</option>
            <option value="standalone">Standalone (No Hall)</option>
        `;
        allHalls.forEach(h => {
            const opt = document.createElement("option");
            opt.value = h.hall_id;
            opt.textContent = h.hall_name;
            filterSelect.appendChild(opt);
        });
        filterSelect.value = currentFilter;
    }

    if (modalSelect) {
        modalSelect.innerHTML = `<option value="">None (Standalone Facility)</option>`;
        allHalls.forEach(h => {
            const opt = document.createElement("option");
            opt.value = h.hall_id;
            opt.textContent = h.hall_name;
            modalSelect.appendChild(opt);
        });
        modalSelect.value = currentModal;
    }
}

// ==========================================
// Filtering & Table Rendering
// ==========================================

function filterByStatus(status) {
    if (activeStatusFilter === status || !status) {
        activeStatusFilter = null;
        document.querySelectorAll(".tstat").forEach(b => b.classList.remove("tstat-active"));
    } else {
        activeStatusFilter = status;
        document.querySelectorAll(".tstat").forEach(b => b.classList.remove("tstat-active"));
        document.getElementById("tstat-" + status)?.classList.add("tstat-active");
    }
    applyFilters();
}

function triggerSearch() {
    applyFilters();
}

function applyFilters() {
    let result = [...allRooms];

    if (activeStatusFilter) {
        result = result.filter(r => (r.room_status || "").toLowerCase() === activeStatusFilter);
    }

    const hallSelect = document.getElementById("filter-hall");
    const hallVal = hallSelect ? hallSelect.value : "";
    if (hallVal === "standalone") {
        result = result.filter(r => !r.hall_id || !r.hall_name);
    } else if (hallVal) {
        result = result.filter(r => String(r.hall_id) === String(hallVal));
    }

    const q = (document.getElementById("search-input")?.value || "").trim().toLowerCase();
    if (q) {
        result = result.filter(r => {
            const rName = (r.room_name || "").toLowerCase();
            const hName = (r.hall_name || "standalone facility").toLowerCase();
            const rType = (r.room_type || "").toLowerCase();
            return rName.includes(q) || hName.includes(q) || rType.includes(q);
        });
    }

    displayRows = result;
    currentPage = 1;
    renderTable();
}

function renderTable() {
    const tbody = document.getElementById("rooms-tbody");
    if (!tbody) return;

    const total = displayRows.length;
    const pages = Math.max(1, Math.ceil(total / perPage));
    if (currentPage > pages) currentPage = pages;

    const start = (currentPage - 1) * perPage;
    const end   = Math.min(start + perPage, total);
    const slice = displayRows.slice(start, end);

    const showingLabel = document.getElementById("showing-label");
    if (showingLabel) {
        showingLabel.innerHTML = total === 0
            ? `Showing <strong>0</strong> of <strong>0</strong> rooms`
            : `Showing <strong>${start + 1} – ${end}</strong> out of <strong>${total}</strong> rooms`;
    }

    if (slice.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" class="td-empty">No rooms found.</td></tr>`;
        renderPagination(0, 1);
        return;
    }

    tbody.innerHTML = "";
    slice.forEach((room, i) => {
        const tr = document.createElement("tr");
        const sc = statusClass(room.room_status);

        const statusLabel = room.room_status
            ? room.room_status.charAt(0).toUpperCase() + room.room_status.slice(1).toLowerCase()
            : "Maintenance";

        tr.innerHTML = `
            <td class="td-index">${start + i + 1}</td>
            <td>
                <div class="room-cell-wrapper">
                    <div class="room-thumb-wrapper">
                        <img src="${escAttr(getBuildingImage(room.hall_name))}" alt="${escAttr(room.hall_name || 'Campus')}" class="room-thumb-img" loading="lazy">
                    </div>
                    <div class="room-meta-group">
                        <span class="room-name-title">${escHtml(room.room_name)}</span>
                        <span class="room-hall-pill">
                            <i class="fas fa-building"></i> ${escHtml(room.hall_name || 'Standalone Facility')}
                        </span>
                    </div>
                </div>
            </td>
            <td>
                <span class="room-type-badge">${escHtml(room.room_type || 'LECTURE')}</span>
            </td>
            <td>
                <div style="display: flex; flex-direction: column; gap: 4px;">
                    <span class="capacity-chip"><i class="fas fa-users"></i> ${escHtml(room.room_capacity)} seats</span>
                    ${room.room_has_ac == 1 
                        ? `<span class="amenity-chip-ac"><i class="fas fa-snowflake"></i> AC</span>` 
                        : `<span class="amenity-chip-noac"><i class="fas fa-fan"></i> Non-AC</span>`}
                </div>
            </td>
            <td>
                <div class="custom-status-wrap" data-room-id="${room.room_id}">
                    <button type="button" class="custom-status-btn ${sc}" onclick="toggleStatusDropdown(this, event)">
                        <span class="csd-dot"></span>
                        <span class="csd-label">${statusLabel}</span>
                        <i class="fas fa-chevron-down csd-arrow"></i>
                    </button>
                    <div class="custom-status-menu">
                        <div class="csm-item csm-available" onclick="selectStatus(this, ${room.room_id}, 'available')">
                            <span class="csd-dot"></span> Available
                        </div>
                        <div class="csm-item csm-unavailable" onclick="selectStatus(this, ${room.room_id}, 'unavailable')">
                            <span class="csd-dot"></span> Unavailable
                        </div>
                        <div class="csm-item csm-maintenance" onclick="selectStatus(this, ${room.room_id}, 'maintenance')">
                            <span class="csd-dot"></span> Maintenance
                        </div>
                    </div>
                </div>
            </td>
            <td>
                <div class="action-buttons-wrap">
                    <button type="button" class="action-btn action-edit btn-edit-room" title="Edit Room" onclick="openEditRoomModal(${room.room_id})">
                        <i class="fas fa-pen"></i>
                    </button>
                    <button type="button" class="btn-view-res" onclick="openModal(${room.room_id})" title="View Reservations">
                        <i class="fas fa-calendar-alt btn-icon"></i> Reservations
                    </button>
                    <button type="button" class="action-btn action-delete btn-delete-room" title="Delete Room" onclick="openDeleteRoomModal(${room.room_id})">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
            </td>
        `;
        tbody.appendChild(tr);
    });

    renderPagination(total, pages);
}

function renderPagination(total, pages) {
    const wrap = document.getElementById("pag-btns");
    if (!wrap) return;
    wrap.innerHTML = "";

    const prev     = document.createElement("button");
    prev.type      = "button";
    prev.className = "pag-btn";
    prev.innerHTML = `<i class="fas fa-chevron-left pag-icon"></i>`;
    prev.disabled  = (currentPage === 1);
    prev.onclick   = () => { if (currentPage > 1) { currentPage--; renderTable(); } };
    wrap.appendChild(prev);

    getPageRange(currentPage, pages).forEach(p => {
        const btn     = document.createElement("button");
        btn.type      = "button";
        btn.className = "pag-btn" + (p === "..." ? "" : p === currentPage ? " active" : "");
        btn.textContent = p === "..." ? "…" : p;
        btn.disabled  = (p === "...");
        if (p !== "...") btn.onclick = () => { currentPage = p; renderTable(); };
        wrap.appendChild(btn);
    });

    const next     = document.createElement("button");
    next.type      = "button";
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
    const perPageSelect = document.getElementById("per-page");
    if (perPageSelect) {
        perPage = parseInt(perPageSelect.value, 10) || 10;
    }
    currentPage = 1;
    renderTable();
}

// ==========================================
// Quick Status Dropdown Handlers
// ==========================================

function toggleStatusDropdown(btn, e) {
    e.stopPropagation();
    const wrap = btn.closest(".custom-status-wrap");
    if (!wrap) return;
    const isOpen = wrap.classList.contains("open");
    document.querySelectorAll(".custom-status-wrap.open").forEach(w => w.classList.remove("open"));
    if (!isOpen) wrap.classList.add("open");
}

function selectStatus(item, roomId, newStatus) {
    const wrap  = item.closest(".custom-status-wrap");
    if (wrap) {
        const btn   = wrap.querySelector(".custom-status-btn");
        if (btn) {
            const label = btn.querySelector(".csd-label");
            const sc    = statusClass(newStatus);
            btn.className = "custom-status-btn " + sc;
            if (label) label.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
        }
        wrap.classList.remove("open");
    }
    updateStatus(roomId, newStatus);
}

function updateStatus(roomId, newStatus) {
    fetch("../controllers/admin_manage_rooms_controller.php?action=update_status", {
        method:  "POST",
        headers: { "Content-Type": "application/json" },
        body:    JSON.stringify({ room_id: parseInt(roomId, 10), status: newStatus })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            const room = allRooms.find(r => r.room_id == roomId);
            if (room) room.room_status = newStatus;
            updateTableStats();
            applyFilters();
            if (window.toast) toast.show("Room status updated.", "success", 2200);
        } else {
            if (window.toast) toast.show(res.message ?? "Failed to update status.", "error", 2500);
            loadAllRooms();
        }
    })
    .catch(() => {
        if (window.toast) toast.show("Network error.", "error", 2500);
        loadAllRooms();
    });
}

// ==========================================
// Live Dynamic Room Preview Card Updater
// ==========================================

function updateRoomPreview() {
    const nameInput    = document.getElementById("room-name-input");
    const hallSelect   = document.getElementById("room-hall-select");
    const typeSelect   = document.getElementById("room-type-select");
    const capInput     = document.getElementById("room-capacity-input");
    const acCheckbox   = document.getElementById("room-ac-input");
    const statusSelect = document.getElementById("room-status-select");

    const previewTitle    = document.getElementById("room-preview-title");
    const previewHall     = document.getElementById("room-preview-hall");
    const previewType     = document.getElementById("room-preview-type");
    const previewCap      = document.getElementById("room-preview-capacity");
    const previewAc       = document.getElementById("room-preview-ac");
    const previewStatus   = document.getElementById("room-preview-status");
    const previewImg      = document.getElementById("room-preview-img");

    const name = (nameInput?.value || "").trim() || "Room Name";
    if (previewTitle) previewTitle.textContent = name;

    const hallId = hallSelect ? hallSelect.value : "";
    let hallName = "";
    let hallLabel = "None (Standalone Facility)";
    if (hallId) {
        const foundHall = allHalls.find(h => String(h.hall_id) === String(hallId));
        if (foundHall) {
            hallName = foundHall.hall_name;
            hallLabel = foundHall.hall_name;
        } else if (hallSelect.selectedOptions?.[0]) {
            hallLabel = hallSelect.selectedOptions[0].textContent;
            hallName = hallLabel;
        }
    }

    if (previewHall) {
        previewHall.innerHTML = `<i class="fas fa-building"></i> ${escHtml(hallLabel)}`;
    }

    if (previewImg) {
        previewImg.src = getBuildingImage(hallName);
    }

    const type = typeSelect?.value || "LECTURE";
    if (previewType) previewType.textContent = type;

    const cap = parseInt(capInput?.value, 10);
    const capText = isNaN(cap) || cap < 0 ? "0" : cap;
    if (previewCap) {
        previewCap.innerHTML = `<i class="fas fa-chair"></i> ${capText} seats`;
    }

    const hasAc = !!acCheckbox?.checked;
    if (previewAc) {
        previewAc.style.display = hasAc ? "inline-flex" : "none";
    }

    const status = (statusSelect?.value || "available").toLowerCase();
    if (previewStatus) {
        previewStatus.className = `preview-status-pill status-${status}`;
        previewStatus.textContent = status.charAt(0).toUpperCase() + status.slice(1);
    }
}

// ==========================================
// Room CRUD Modals
// ==========================================

function openCreateRoomModal() {
    const form = document.getElementById("form-room");
    if (form) form.reset();
    document.getElementById("room-id").value = "";
    const titleText = document.getElementById("modal-room-title-text");
    if (titleText) titleText.textContent = "Create New Room";

    const capInput = document.getElementById("room-capacity-input");
    if (capInput) capInput.value = "40";
    const typeSelect = document.getElementById("room-type-select");
    if (typeSelect) typeSelect.value = "LECTURE";
    const statusSelect = document.getElementById("room-status-select");
    if (statusSelect) statusSelect.value = "available";
    const acCheckbox = document.getElementById("room-ac-input");
    if (acCheckbox) acCheckbox.checked = false;

    updateRoomPreview();
    document.getElementById("modal-room")?.classList.add("open");
}

function openEditRoomModal(roomId) {
    const room = allRooms.find(r => r.room_id == roomId);
    if (!room) {
        if (window.toast) toast.show("Room not found.", "error");
        return;
    }

    document.getElementById("room-id").value = room.room_id;
    document.getElementById("room-name-input").value = room.room_name || "";
    document.getElementById("room-hall-select").value = room.hall_id ? String(room.hall_id) : "";
    document.getElementById("room-type-select").value = room.room_type || "LECTURE";
    document.getElementById("room-capacity-input").value = room.room_capacity || 40;
    document.getElementById("room-status-select").value = (room.room_status || "available").toLowerCase();
    document.getElementById("room-ac-input").checked = room.room_has_ac == 1;

    const titleText = document.getElementById("modal-room-title-text");
    if (titleText) titleText.textContent = `Edit Room - ${room.room_name}`;

    updateRoomPreview();
    document.getElementById("modal-room")?.classList.add("open");
}

function openDeleteRoomModal(roomId) {
    const room = allRooms.find(r => r.room_id == roomId);
    if (!room) return;

    const heading = document.getElementById("delete-entity-heading");
    const desc = document.getElementById("delete-desc");
    const warning = document.getElementById("delete-extra-warning");

    if (heading) heading.textContent = `Are you sure you want to delete room "${room.room_name}"?`;
    if (desc) desc.textContent = "This action cannot be undone and will permanently remove this room record.";
    if (warning) {
        warning.style.display = "block";
        warning.innerHTML = `<i class="fas fa-exclamation-triangle"></i> Notice: Any active, recurring, or pending reservations scheduled in this room will also be permanently deleted.`;
    }

    currentDeleteAction = () => {
        const btn = document.getElementById("btn-delete-confirm");
        if (btn) btn.disabled = true;

        fetch("../controllers/admin_manage_rooms_controller.php?action=delete_room", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ room_id: room.room_id })
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                if (window.toast) toast.show(res.message || "Room deleted successfully.", "success", 2500);
                closeAllModals();
                loadAllRooms();
                loadAllHalls();
            } else {
                if (window.toast) toast.show(res.message || "Failed to delete room.", "error", 3500);
            }
        })
        .catch(() => {
            if (window.toast) toast.show("Network error while deleting room.", "error", 3500);
        })
        .finally(() => {
            if (btn) btn.disabled = false;
            currentDeleteAction = null;
        });
    };

    document.getElementById("modal-delete-confirm")?.classList.add("open");
}

// ==========================================
// Campus Buildings & Halls Directory
// ==========================================

function renderHallsGrid() {
    const grid = document.getElementById("halls-grid");
    if (!grid) return;

    const q = (document.getElementById("hall-search-input")?.value || "").trim().toLowerCase();
    let halls = allHalls;
    if (q) {
        halls = allHalls.filter(h => 
            (h.hall_name || "").toLowerCase().includes(q) ||
            (h.hall_desc || "").toLowerCase().includes(q)
        );
    }

    if (halls.length === 0) {
        grid.innerHTML = `
            <div class="td-empty" style="grid-column: 1 / -1; padding: 48px; text-align: center;">
                No campus buildings found.
            </div>`;
        return;
    }

    grid.innerHTML = halls.map(hall => {
        const img = getBuildingImage(hall.hall_name);
        const count = parseInt(hall.room_count, 10) || 0;
        const countLabel = count === 1 ? "1 room" : `${count} rooms`;
        const desc = hall.hall_desc ? escHtml(hall.hall_desc) : "No facility description provided.";
        return `
            <div class="hall-card" data-hall-id="${hall.hall_id}">
                <div class="hall-card-banner">
                    <img src="${escAttr(img)}" alt="${escAttr(hall.hall_name)}" class="hall-card-img" loading="lazy">
                    <span class="hall-card-badge">
                        <i class="fas fa-door-open"></i> ${countLabel}
                    </span>
                </div>
                <div class="hall-card-body">
                    <h3 class="hall-card-title">${escHtml(hall.hall_name)}</h3>
                    <p class="hall-card-desc">${desc}</p>
                    <div class="hall-card-actions">
                        <button type="button" class="btn-secondary btn-hall-edit" onclick="openEditHallModal(${hall.hall_id})">
                            <i class="fas fa-pen"></i> Edit
                        </button>
                        <button type="button" class="btn-danger btn-hall-delete" onclick="openDeleteHallModal(${hall.hall_id})">
                            <i class="fas fa-trash-alt"></i> Delete
                        </button>
                    </div>
                </div>
            </div>
        `;
    }).join("");
}

function openCreateHallModal() {
    const form = document.getElementById("form-hall");
    if (form) form.reset();
    document.getElementById("hall-id").value = "";
    const titleText = document.getElementById("modal-hall-title-text");
    if (titleText) titleText.textContent = "Add Campus Building";
    const descCount = document.getElementById("hall-desc-count");
    if (descCount) descCount.textContent = "0";

    document.getElementById("modal-hall")?.classList.add("open");
}

function openEditHallModal(hallId) {
    const hall = allHalls.find(h => h.hall_id == hallId);
    if (!hall) {
        if (window.toast) toast.show("Building not found.", "error");
        return;
    }

    document.getElementById("hall-id").value = hall.hall_id;
    document.getElementById("hall-name-input").value = hall.hall_name || "";
    document.getElementById("hall-desc-input").value = hall.hall_desc || "";

    const descCount = document.getElementById("hall-desc-count");
    if (descCount) descCount.textContent = (hall.hall_desc || "").length;

    const titleText = document.getElementById("modal-hall-title-text");
    if (titleText) titleText.textContent = `Edit Building - ${hall.hall_name}`;

    document.getElementById("modal-hall")?.classList.add("open");
}

function openDeleteHallModal(hallId) {
    const hall = allHalls.find(h => h.hall_id == hallId);
    if (!hall) return;

    const count = parseInt(hall.room_count, 10) || 0;
    if (count > 0) {
        if (window.toast) {
            toast.show(
                `Cannot delete this building because ${count} room(s) are currently assigned to it. Please reassign those rooms before deleting.`,
                "warning",
                4500
            );
        }
        return;
    }

    const heading = document.getElementById("delete-entity-heading");
    const desc = document.getElementById("delete-desc");
    const warning = document.getElementById("delete-extra-warning");

    if (heading) heading.textContent = `Are you sure you want to delete building "${hall.hall_name}"?`;
    if (desc) desc.textContent = "This action cannot be undone and will permanently remove this campus building.";
    if (warning) warning.style.display = "none";

    currentDeleteAction = () => {
        const btn = document.getElementById("btn-delete-confirm");
        if (btn) btn.disabled = true;

        fetch("../controllers/admin_manage_rooms_controller.php?action=delete_hall", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ hall_id: hall.hall_id })
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                if (window.toast) toast.show(res.message || "Building deleted successfully.", "success", 2500);
                closeAllModals();
                loadAllHalls();
                loadAllRooms();
            } else {
                if (window.toast) toast.show(res.message || "Failed to delete building.", "error", 3500);
            }
        })
        .catch(() => {
            if (window.toast) toast.show("Network error while deleting building.", "error", 3500);
        })
        .finally(() => {
            if (btn) btn.disabled = false;
            currentDeleteAction = null;
        });
    };

    document.getElementById("modal-delete-confirm")?.classList.add("open");
}

// ==========================================
// Modal Controllers & Backdrop
// ==========================================

function closeAllModals() {
    document.querySelectorAll(".modal-overlay").forEach(m => {
        m.classList.remove("open");
        m.classList.remove("active");
    });
    currentDeleteAction = null;
}

function closeModal() {
    closeAllModals();
}

// ==========================================
// Reservations History Modal
// ==========================================

function openModal(roomId, fallbackRoomName, fallbackHallName) {
    const room = allRooms.find(r => r.room_id == roomId);
    const roomName = room?.room_name || fallbackRoomName || "Room";
    const hallName = room?.hall_name || fallbackHallName || "";

    const modalTitle = document.getElementById("modal-title");
    if (modalTitle) {
        modalTitle.innerHTML =
            `Reservations — <span>${escHtml(roomName)}</span> <span class="modal-hall-label">(${escHtml(hallName || "Standalone Facility")})</span>`;
    }
    const modalBody = document.getElementById("modal-body");
    if (modalBody) {
        modalBody.innerHTML = `<div class="modal-loading"><div class="spinner"></div> Loading…</div>`;
    }

    document.getElementById("res-modal")?.classList.add("open");

    fetch(`../controllers/admin_get_room_reservations.php?room_id=${roomId}`)
        .then(r => r.json())
        .then(res => {
            const body = document.getElementById("modal-body");
            if (!body) return;
            if (!res.success || !res.data || res.data.length === 0) {
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
                const untilText   = r.schedule_end_date ? ` (until ${r.schedule_end_date})` : '';
                const dayLabel    = isRecurring
                    ? `Every ${r.schedule_day_of_week}${untilText}`
                    : new Date(r.schedule_day + "T00:00:00").toLocaleDateString("en-US", {
                        month: "short", day: "numeric", year: "numeric"
                    });

                const div     = document.createElement("div");
                div.className = "res-item";
                div.innerHTML = `
                    <div class="res-info">
                        <div class="res-name">
                            ${escHtml(r.fname)} ${escHtml(r.lname)}
                            <span class="res-username">(${escHtml(r.username)})</span>
                        </div>
                        <div class="res-meta">${escHtml(r.subject || "No subject")} &nbsp;·&nbsp; ${dayLabel} &nbsp;·&nbsp; ${formatTime(r.schedule_start)} – ${formatTime(r.schedule_end)}</div>
                    </div>
                    <span class="res-type-badge ${isRecurring ? "weekly" : "onetime"}">${isRecurring ? "Weekly" : "One-time"}</span>
                    <button type="button" class="btn-del-res"
                        onclick="deleteReservation(${r.schedule_id}, this, ${roomId})">
                        <i class="fas fa-trash-alt"></i> Delete
                    </button>
                `;
                body.appendChild(div);
            });
        })
        .catch(() => {
            const body = document.getElementById("modal-body");
            if (body) {
                body.innerHTML = `<div class="modal-empty">Failed to load reservations.</div>`;
            }
        });
}

function deleteReservation(scheduleId, btn, roomId) {
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
            if (window.toast) toast.show("Reservation deleted.", "success", 2200);
            openModal(roomId);
        } else {
            if (window.toast) toast.show(res.message ?? "Failed to delete.", "error", 2500);
            btn.disabled  = false;
            btn.innerHTML = `<i class="fas fa-trash-alt"></i> Delete`;
        }
    })
    .catch(() => {
        if (window.toast) toast.show("Network error.", "error", 2500);
        btn.disabled  = false;
        btn.innerHTML = `<i class="fas fa-trash-alt"></i> Delete`;
    });
}

// ==========================================
// Event Listeners & Initialization
// ==========================================

function initEventListeners() {
    // Tab switching
    document.getElementById("tab-btn-rooms")?.addEventListener("click", () => switchTab("rooms"));
    document.getElementById("tab-btn-halls")?.addEventListener("click", () => switchTab("halls"));

    // Quick filter total
    document.getElementById("tstat-total")?.addEventListener("click", () => filterByStatus(null));

    // Hall filter dropdown
    document.getElementById("filter-hall")?.addEventListener("change", applyFilters);

    // Search room input
    const searchInput = document.getElementById("search-input");
    if (searchInput) {
        searchInput.addEventListener("input", applyFilters);
        searchInput.addEventListener("keyup", e => {
            if (e.key === "Enter") applyFilters();
        });
    }

    // Modal open buttons
    document.getElementById("btn-open-create-room")?.addEventListener("click", openCreateRoomModal);
    document.getElementById("btn-open-create-hall")?.addEventListener("click", openCreateHallModal);

    // Live preview inputs in room modal
    ["room-name-input", "room-hall-select", "room-type-select", "room-capacity-input", "room-status-select", "room-ac-input"].forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        el.addEventListener("input", updateRoomPreview);
        el.addEventListener("change", updateRoomPreview);
    });

    // Form room submit
    document.getElementById("form-room")?.addEventListener("submit", function(e) {
        e.preventDefault();
        const roomId = document.getElementById("room-id").value;
        const roomName = document.getElementById("room-name-input").value.trim();
        const hallIdRaw = document.getElementById("room-hall-select").value;
        const roomType = document.getElementById("room-type-select").value;
        const roomCapacity = parseInt(document.getElementById("room-capacity-input").value, 10);
        const roomStatus = document.getElementById("room-status-select").value;
        const roomHasAc = document.getElementById("room-ac-input").checked ? 1 : 0;

        if (!roomName) {
            if (window.toast) toast.show("Room name is required.", "error");
            return;
        }

        const submitBtn = document.getElementById("btn-submit-room");
        if (submitBtn) submitBtn.disabled = true;

        const action = roomId ? "update_room" : "create_room";
        const payload = {
            room_name: roomName,
            hall_id: hallIdRaw ? parseInt(hallIdRaw, 10) : null,
            room_capacity: roomCapacity,
            room_type: roomType,
            room_has_ac: roomHasAc,
            room_status: roomStatus
        };
        if (roomId) payload.room_id = parseInt(roomId, 10);

        fetch(`../controllers/admin_manage_rooms_controller.php?action=${action}`, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload)
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                if (window.toast) toast.show(res.message || "Room saved successfully.", "success", 2500);
                closeAllModals();
                loadAllRooms();
                loadAllHalls();
            } else {
                if (window.toast) toast.show(res.message || "Failed to save room.", "error", 3500);
            }
        })
        .catch(() => {
            if (window.toast) toast.show("Network error while saving room.", "error", 3500);
        })
        .finally(() => {
            if (submitBtn) submitBtn.disabled = false;
        });
    });

    // Form hall submit
    document.getElementById("form-hall")?.addEventListener("submit", function(e) {
        e.preventDefault();
        const hallId = document.getElementById("hall-id").value;
        const hallName = document.getElementById("hall-name-input").value.trim();
        const hallDesc = document.getElementById("hall-desc-input").value.trim();

        if (!hallName) {
            if (window.toast) toast.show("Building name is required.", "error");
            return;
        }

        const submitBtn = document.getElementById("btn-submit-hall");
        if (submitBtn) submitBtn.disabled = true;

        const action = hallId ? "update_hall" : "create_hall";
        const payload = {
            hall_name: hallName,
            hall_desc: hallDesc
        };
        if (hallId) payload.hall_id = parseInt(hallId, 10);

        fetch(`../controllers/admin_manage_rooms_controller.php?action=${action}`, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload)
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                if (window.toast) toast.show(res.message || "Building saved successfully.", "success", 2500);
                closeAllModals();
                loadAllHalls();
                loadAllRooms();
            } else {
                if (window.toast) toast.show(res.message || "Failed to save building.", "error", 3500);
            }
        })
        .catch(() => {
            if (window.toast) toast.show("Network error while saving building.", "error", 3500);
        })
        .finally(() => {
            if (submitBtn) submitBtn.disabled = false;
        });
    });

    // Hall search & desc counter
    document.getElementById("hall-search-input")?.addEventListener("input", renderHallsGrid);
    document.getElementById("btn-search-hall")?.addEventListener("click", renderHallsGrid);
    document.getElementById("hall-desc-input")?.addEventListener("input", e => {
        const counter = document.getElementById("hall-desc-count");
        if (counter) counter.textContent = e.target.value.length;
    });

    // Delete confirmation button
    document.getElementById("btn-delete-confirm")?.addEventListener("click", () => {
        if (typeof currentDeleteAction === "function") {
            currentDeleteAction();
        }
    });

    // Close overlays on outside click
    document.querySelectorAll(".modal-overlay").forEach(overlay => {
        overlay.addEventListener("click", function(e) {
            if (e.target === this) {
                closeAllModals();
            }
        });
    });

    // Close on Escape key
    document.addEventListener("keydown", e => {
        if (e.key === "Escape") {
            closeAllModals();
        }
    });

    // Close status dropdown when clicking outside
    document.addEventListener("click", () => {
        document.querySelectorAll(".custom-status-wrap.open").forEach(w => w.classList.remove("open"));
    });
}

// Window global bindings for inline HTML handlers
window.filterByStatus       = filterByStatus;
window.triggerSearch        = triggerSearch;
window.changePerPage        = changePerPage;
window.closeAllModals       = closeAllModals;
window.closeModal           = closeModal;
window.openModal            = openModal;
window.deleteReservation    = deleteReservation;
window.toggleStatusDropdown = toggleStatusDropdown;
window.selectStatus         = selectStatus;
window.updateStatus         = updateStatus;
window.openCreateRoomModal  = openCreateRoomModal;
window.openEditRoomModal    = openEditRoomModal;
window.openDeleteRoomModal  = openDeleteRoomModal;
window.openCreateHallModal  = openCreateHallModal;
window.openEditHallModal    = openEditHallModal;
window.openDeleteHallModal  = openDeleteHallModal;
window.getBuildingImage     = getBuildingImage;
window.switchTab            = switchTab;

// Boot up
if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => {
        initEventListeners();
        loadAllRooms();
        loadAllHalls();
    });
} else {
    initEventListeners();
    loadAllRooms();
    loadAllHalls();
}
