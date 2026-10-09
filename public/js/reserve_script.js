
function initSharedMobileMenu() {
    const toggle = document.getElementById("mobile-menu-toggle");
    const backdrop = document.getElementById("mobile-menu-backdrop");
    const sidebar = document.getElementById("classspace-sidebar");
    if (!toggle || !backdrop || !sidebar) return;

    const closeMenu = () => {
        document.body.classList.remove("mobile-menu-open");
        document.documentElement.style.overflow = "";
        toggle.setAttribute("aria-expanded", "false");
        toggle.setAttribute("aria-label", "Open navigation menu");
        toggle.innerHTML = '<i class="fas fa-bars" aria-hidden="true"></i>';
        backdrop.hidden = true;
    };

    const openMenu = () => {
        document.body.classList.add("mobile-menu-open");
        document.documentElement.style.overflow = "hidden";
        toggle.setAttribute("aria-expanded", "true");
        toggle.setAttribute("aria-label", "Close navigation menu");
        toggle.innerHTML = '<i class="fas fa-xmark" aria-hidden="true"></i>';
        backdrop.hidden = false;
    };

    toggle.addEventListener("click", () => {
        document.body.classList.contains("mobile-menu-open") ? closeMenu() : openMenu();
    });
    backdrop.addEventListener("click", closeMenu);
    sidebar.querySelectorAll("a").forEach(link => link.addEventListener("click", closeMenu));
    document.addEventListener("keydown", event => {
        if (event.key === "Escape") closeMenu();
    });
}

fetch("../controllers/room_controller.php?action=get_halls")
    .then(r => r.json())
    .then(res => {
        const sel = document.getElementById("hall-select");
        if (!res.success || !res.data.length) return;
        res.data.forEach(hall => {
            const opt       = document.createElement("option");
            opt.value       = hall.hall_id;
            opt.textContent = hall.hall_name;
            sel.appendChild(opt);
        });

        const preHallId = document.getElementById("preHallId").value;
        const preRoomId = document.getElementById("preRoomId").value;

        if (preHallId) {
            document.getElementById("hall-select").value = preHallId;
            loadRooms(preRoomId);
        }
    });

function loadRooms(preselectRoomId = 0) {
    const hallId  = document.getElementById("hall-select").value;
    const roomSel = document.getElementById("room-select");
    roomSel.innerHTML = `<option value="">— Loading... —</option>`;
    roomSel.disabled  = true;
    document.getElementById("room-preview").style.display = "none";

    if (!hallId) {
        roomSel.innerHTML = `<option value="">— Select a building first —</option>`;
        return;
    }

    fetch(`../controllers/room_controller.php?hall_id=${hallId}&action=get_rooms`)
        .then(r => r.json())
        .then(res => {
            roomSel.innerHTML = `<option value="">— Select a room —</option>`;
            if (!res.success || !res.data.length) {
                roomSel.innerHTML = `<option value="">No rooms available</option>`;
                return;
            }
            res.data.forEach(room => {
                const opt               = document.createElement("option");
                opt.value               = room.room_id;
                opt.textContent         = room.room_name;
                opt.dataset.room_capacity = room.room_capacity;
                opt.dataset.type        = room.room_type;
                opt.dataset.ac          = room.room_has_ac;
                opt.dataset.status      = room.room_status;
                roomSel.appendChild(opt);
            });
            roomSel.disabled  = false;
            roomSel.addEventListener("change", showRoomPreview);

            if (preselectRoomId) {
                roomSel.value = preselectRoomId;
                showRoomPreview();
            }
        });
}

function showRoomPreview() {
    const sel     = document.getElementById("room-select");
    const opt     = sel.options[sel.selectedIndex];
    const preview = document.getElementById("room-preview");

    if (!sel.value) { preview.style.display = "none"; return; }

    const statusClass = {
        available:   "preview-status-available",
        unavailable: "preview-status-unavailable",
        maintenance: "preview-status-maintenance"
    }[opt.dataset.status?.toLowerCase()] ?? "preview-status-unknown";

    preview.innerHTML = `
        <strong class="preview-name">${opt.textContent}</strong> &nbsp;|&nbsp;
        Capacity: ${opt.dataset.room_capacity} &nbsp;|&nbsp;
        ${opt.dataset.type} &nbsp;|&nbsp;
        ${opt.dataset.ac == 1 ? "AC" : "No AC"} &nbsp;|&nbsp;
        <span class="${statusClass}">${opt.dataset.status}</span>
    `;
    preview.style.display = "block";

    checkConflicts();
}

function toggleType() {
    const type = document.querySelector('input[name="res-type"]:checked').value;
    document.getElementById("field-date").style.display = type === "one-time" ? "block" : "none";
    document.getElementById("field-dow").style.display  = type === "weekly"   ? "block" : "none";
    checkConflicts();
}

function checkConflicts() {
    const roomId  = document.getElementById("room-select").value;
    const type    = document.querySelector('input[name="res-type"]:checked').value;
    const start   = document.getElementById("schedule-start").value;
    const end     = document.getElementById("schedule-end").value;
    const date    = document.getElementById("schedule-date").value;
    const dow     = document.getElementById("schedule-dow").value;
    const warning = document.getElementById("conflict-warning");

    warning.style.display = "none";

    if (!roomId || !start || !end) return;
    if (type === "one-time" && !date) return;
    if (type === "weekly"   && !dow)  return;

    const params = new URLSearchParams({ room_id: roomId, start, end, type });
    if (type === "one-time") params.append("date", date);
    else                     params.append("dow",  dow);

    fetch(`../controllers/check_conflict.php?${params}`)
        .then(r => r.json())
        .then(res => {
            if (res.conflict) {
                document.getElementById("conflict-msg").textContent = res.message;
                warning.style.display = "block";
            }
        });
}

document.getElementById("schedule-start").addEventListener("change", checkConflicts);
document.getElementById("schedule-end").addEventListener("change",   checkConflicts);
document.getElementById("schedule-date").addEventListener("change",   checkConflicts);
document.getElementById("schedule-dow").addEventListener("change",    checkConflicts);

function submitReservation() {
    const roomId = document.getElementById("room-select").value;
    const type   = document.querySelector('input[name="res-type"]:checked').value;
    const start  = document.getElementById("schedule-start").value;
    const end    = document.getElementById("schedule-end").value;
    const date   = document.getElementById("schedule-date").value;
    const dow    = document.getElementById("schedule-dow").value;

    if (!roomId)                    return toast.show("Please select a room.", "error", 2500);
    if (!start || !end)             return toast.show("Please set start and end time.", "error", 2500);
    if (start >= end)               return toast.show("End time must be after start time.", "error", 2500);
    if (type === "one-time" && !date) return toast.show("Please select a date.", "error", 2500);
    if (type === "weekly"   && !dow)  return toast.show("Please select a day of the week.", "error", 2500);
    if (document.getElementById("conflict-warning").style.display !== "none")
        return toast.show("Please resolve the scheduling conflict first.", "error", 2500);

    const btn     = document.getElementById("submit-btn");
    btn.disabled  = true;
    btn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Submitting...`;

    fetch("../controllers/reserve_controller.php", {
        method:  "POST",
        headers: { "Content-Type": "application/json" },
        body:    JSON.stringify({
            room_id: roomId,
            type:    type,
            start:   start,
            end:     end,
            date:    type === "one-time" ? date : null,
            dow:     type === "weekly"   ? dow  : null
        })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            toast.show("Reservation submitted! Waiting for admin approval.", "success", 3000);
            setTimeout(() => window.location.href = "user_homepage_view.php?submitted=1", 1500);
        } else {
            toast.show(res.message ?? "Something went wrong.", "error", 3000);
            btn.disabled  = false;
            btn.innerHTML = `<i class="fas fa-paper-plane"></i> Submit Reservation`;
        }
    });
}
initSharedMobileMenu();


document.getElementById("hall-select")?.addEventListener("change", () => loadRooms());
document.querySelectorAll('input[name="res-type"]').forEach(input => {
    input.addEventListener("change", toggleType);
});
document.getElementById("submit-btn")?.addEventListener("click", submitReservation);
initSharedMobileMenu();
