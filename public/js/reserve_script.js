
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

        const preHallId   = parseInt(document.getElementById("preHallId")?.value, 10) || 0;
        const preHallName = (document.getElementById("preHallName")?.value || '').trim().toLowerCase();
        const preRoomId   = parseInt(document.getElementById("preRoomId")?.value, 10) || 0;
        const preRoomName = (document.getElementById("preRoomName")?.value || '').trim().toLowerCase();
        const preDate     = document.getElementById("preDate")?.value;
        const preDow      = document.getElementById("preDow")?.value;
        const preType     = document.getElementById("preType")?.value;
        const preStart    = document.getElementById("preStart")?.value;
        const preEnd      = document.getElementById("preEnd")?.value;
        const autoCheck   = document.getElementById("autoCheck")?.value === "1";

        if (preType === 'weekly' || preDow) {
            const weeklyRadio = document.querySelector('input[name="res-type"][value="weekly"]');
            if (weeklyRadio) {
                weeklyRadio.checked = true;
                toggleType();
            }
            if (preDow) {
                const dowSel = document.getElementById("schedule-dow");
                if (dowSel) {
                    for (let opt of dowSel.options) {
                        if (opt.value.toLowerCase() === preDow.toLowerCase()) {
                            dowSel.value = opt.value;
                            break;
                        }
                    }
                }
            }
        } else {
            if (preDate) {
                const dateInput = document.getElementById("schedule-date");
                if (dateInput && !dateInput.value) dateInput.value = preDate;
            }
        }

        if (preStart) {
            const startInput = document.getElementById("schedule-start");
            if (startInput && !startInput.value) startInput.value = preStart;
        }
        if (preEnd) {
            const endInput = document.getElementById("schedule-end");
            if (endInput && !endInput.value) endInput.value = preEnd;
        }


        let hallMatched = false;
        if (preHallId > 0) {
            sel.value = preHallId;
            if (sel.value == preHallId) hallMatched = true;
        }
        if (!hallMatched && preHallName) {
            for (let opt of sel.options) {
                if (opt.textContent.trim().toLowerCase() === preHallName) {
                    sel.value = opt.value;
                    hallMatched = true;
                    break;
                }
            }
        }

        if (hallMatched && sel.value) {
            loadRooms(preRoomId, preRoomName, autoCheck);
        }
    });

function loadRooms(preselectRoomId = 0, preselectRoomName = '', autoCheck = false) {
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
                const roomStatus = String(room.room_status || '').toLowerCase();
                const roomType = String(room.room_type || '').toLowerCase();
                const roomName = String(room.room_name || '').toLowerCase();
                const isStorage = roomType.includes('storage') || roomName.includes('storage');
                const isReservable = roomStatus === 'available' && !isStorage;
                opt.disabled = !isReservable;
                if (!isReservable) opt.textContent += ' — Not available for reservation';
                roomSel.appendChild(opt);
            });
            roomSel.disabled  = false;
            roomSel.addEventListener("change", showRoomPreview);

            let roomMatched = false;
            if (preselectRoomId && preselectRoomId > 0) {
                roomSel.value = preselectRoomId;
                if (roomSel.value == preselectRoomId) roomMatched = true;
            }
            if (!roomMatched && preselectRoomName) {
                for (let i = 0; i < roomSel.options.length; i++) {
                    const opt = roomSel.options[i];
                    if (opt.textContent.trim().toLowerCase() === preselectRoomName.toLowerCase()) {
                        roomSel.selectedIndex = i;
                        roomMatched = true;
                        break;
                    }
                }
            }

            if (roomMatched && roomSel.value) {
                showRoomPreview();
                if (autoCheck) {
                    checkConflicts(true);
                }
            }
        });
}

function showRoomPreview() {
    const sel     = document.getElementById("room-select");
    const opt     = sel.options[sel.selectedIndex];
    const preview = document.getElementById("room-preview");

    if (!sel.value) { preview.style.display = "none"; return; }

    const roomStatus = String(opt.dataset.status || '').toLowerCase();
    const roomType = String(opt.dataset.type || '').toLowerCase();
    const roomName = String(opt.textContent || '').toLowerCase();
    const isStorage = roomType.includes('storage') || roomName.includes('storage');

    const statusClass = {
        available:   "preview-status-available",
        unavailable: "preview-status-unavailable",
        maintenance: "preview-status-maintenance"
    }[roomStatus] ?? "preview-status-unknown";
    const displayStatusClass = isStorage ? "preview-status-storage" : statusClass;
    const displayStatus = isStorage ? "STORAGE" : (opt.dataset.status || "Unknown");

    preview.innerHTML = `
        <strong class="preview-name">${opt.textContent}</strong> &nbsp;|&nbsp;
        Capacity: ${opt.dataset.room_capacity} &nbsp;|&nbsp;
        ${opt.dataset.type} &nbsp;|&nbsp;
        ${opt.dataset.ac == 1 ? "AC" : "No AC"} &nbsp;|&nbsp;
        <span class="${displayStatusClass}">${displayStatus}</span>
    `;
    preview.style.display = "block";

    const isReservable = roomStatus === 'available' && !isStorage;
    const submitButton = document.getElementById("submit-btn");
    const warning = document.getElementById("conflict-warning");
    const aiContainer = document.getElementById("ai-suggestions-container");

    if (submitButton) submitButton.disabled = !isReservable;
    if (!isReservable) {
        if (warning) {
            document.getElementById("conflict-msg").textContent = isStorage
                ? "Storage rooms cannot be reserved. Please select an available room."
                : "This room is unavailable for reservation. Please select an available room.";
            warning.style.display = "block";
        }
        if (aiContainer) aiContainer.style.display = "none";
        return;
    }

    checkConflicts();
}

function toggleType() {
    const type = document.querySelector('input[name="res-type"]:checked').value;
    document.getElementById("field-date").style.display = type === "one-time" ? "block" : "none";
    document.getElementById("field-dow").style.display  = type === "weekly"   ? "block" : "none";
    checkConflicts();
}

let currentSuggestions = [];
let conflictAbortController = null;
let aiAbortController = null;
let lastConflictReqId = 0;
let lastAiReqId = 0;

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function checkConflicts(forceAiFetch = false) {
    const roomId      = document.getElementById("room-select").value;
    const type        = document.querySelector('input[name="res-type"]:checked').value;
    const start       = document.getElementById("schedule-start").value;
    const end         = document.getElementById("schedule-end").value;
    const date        = document.getElementById("schedule-date").value;
    const dow         = document.getElementById("schedule-dow").value;
    const warning     = document.getElementById("conflict-warning");
    const aiContainer = document.getElementById("ai-suggestions-container");

    if (conflictAbortController) {
        conflictAbortController.abort();
    }
    if (aiAbortController) {
        aiAbortController.abort();
    }

    warning.style.display = "none";
    if (aiContainer && !forceAiFetch) aiContainer.style.display = "none";

    if (!roomId || !start || !end) return;
    if (type === "one-time" && !date) return;
    if (type === "weekly"   && !dow)  return;

    const selectedRoom = document.getElementById("room-select")?.selectedOptions?.[0];
    const selectedStatus = String(selectedRoom?.dataset.status || '').toLowerCase();
    const selectedType = String(selectedRoom?.dataset.type || '').toLowerCase();
    const selectedName = String(selectedRoom?.textContent || '').toLowerCase();
    if (selectedStatus !== "available" || selectedType.includes("storage") || selectedName.includes("storage")) {
        warning.style.display = "block";
        document.getElementById("conflict-msg").textContent = selectedType.includes("storage") || selectedName.includes("storage")
            ? "Storage rooms cannot be reserved. Please select an available room."
            : "This room is unavailable for reservation. Please select an available room.";
        if (aiContainer) aiContainer.style.display = "none";
        return;
    }

    const params = new URLSearchParams({ room_id: roomId, start, end, type });
    if (type === "one-time") params.append("date", date);
    else                     params.append("dow",  dow);
    const pendingId = parseInt(document.getElementById("reschedulePendingId")?.value, 10) || 0;
    if (pendingId > 0) params.append("pending_id", String(pendingId));

    conflictAbortController = new AbortController();
    const reqId = ++lastConflictReqId;

    if (forceAiFetch) {
        fetchAiSuggestions(params);
    }

    fetch(`../controllers/check_conflict.php?${params}`, { signal: conflictAbortController.signal })
        .then(r => r.json())
        .then(res => {
            if (reqId !== lastConflictReqId) return;
            if (res.error === "room_unavailable" || res.available === false) {
                warning.style.display = "block";
                document.getElementById("conflict-msg").textContent = res.message || "This room is unavailable for reservation.";
                if (aiContainer) aiContainer.style.display = "none";
                return;
            }
            if (res.conflict) {
                document.getElementById("conflict-msg").textContent = res.message;
                warning.style.display = "block";
                if (res.has_suggestions || forceAiFetch) {
                    fetchAiSuggestions(params);
                }
            } else if (!forceAiFetch && aiContainer) {
                aiContainer.style.display = "none";
            }
        })
        .catch(err => {
            if (err.name !== 'AbortError') {
                console.error("Conflict check error:", err);
            }
        });
}

function fetchAiSuggestions(params) {
    const aiContainer = document.getElementById("ai-suggestions-container");
    const cardsList   = document.getElementById("ai-cards-list");
    const summaryText = document.getElementById("ai-summary-text");
    if (!aiContainer || !cardsList) return;

    if (aiAbortController) {
        aiAbortController.abort();
    }
    aiAbortController = new AbortController();
    const aiReqId = ++lastAiReqId;

    aiContainer.style.display = "block";
    summaryText.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Analyzing campus facilities & open intervals via CSP engine...';
    cardsList.innerHTML = '';

    fetch(`../controllers/ai_suggest_controller.php?${params}`, { signal: aiAbortController.signal })
        .then(r => r.json())
        .then(res => {
            if (aiReqId !== lastAiReqId) return;
            if (!res.success || !res.suggestions || res.suggestions.length === 0) {
                if (res.error === "room_unavailable") {
                    aiContainer.style.display = "none";
                }
                summaryText.textContent = res.ai_summary || "No immediate alternative slots found for this date. Please consider adjusting the day or time window.";
                return;
            }

            currentSuggestions = res.suggestions;
            summaryText.textContent = res.ai_summary;

            const reschedulePendingId = parseInt(document.getElementById("reschedulePendingId")?.value, 10) || 0;

            cardsList.innerHTML = res.suggestions.map((sug, idx) => `
                <div class="ai-suggestion-card ${sug.type === 'alternative_room' ? 'alt-room' : 'alt-slot'}">
                    <div class="ai-card-header">
                        <div class="ai-card-title">
                            <span class="ai-rank-badge">#${sug.rank}</span>
                            <strong>${escapeHtml(sug.room_name)}</strong>
                            <span class="ai-hall-name">(${escapeHtml(sug.hall_name)})</span>
                        </div>
                        <span class="ai-type-pill ${sug.type}">
                            ${sug.type === 'alternative_room' ? '<i class="fas fa-door-open"></i> Alternative Room' : '<i class="fas fa-clock"></i> Alternative Time'}
                        </span>
                    </div>
                    <div class="ai-card-body">
                        <div class="ai-meta-row">
                            <span class="ai-meta-item"><i class="fas fa-calendar-day"></i> ${escapeHtml(sug.formatted_time)}</span>
                            <span class="ai-meta-item"><i class="fas fa-users"></i> ${sug.room_capacity} seats</span>
                            ${sug.room_has_ac == 1 ? '<span class="ai-meta-item ac-item"><i class="fas fa-snowflake"></i> AC</span>' : ''}
                            ${sug.room_type ? `<span class="ai-meta-item"><i class="fas fa-tag"></i> ${escapeHtml(sug.room_type)}</span>` : ''}
                        </div>
                        <p class="ai-reason-text"><i class="fas fa-lightbulb"></i> ${escapeHtml(sug.reason)}</p>
                    </div>
                    <div class="ai-card-actions" style="display:flex; gap:8px; flex-wrap:wrap;">
                        <button type="button" class="btn-apply-suggestion" onclick="applySuggestion(${idx})">
                            <i class="fas fa-check-circle"></i> Use This ${sug.type === 'alternative_room' ? 'Room' : 'Time'}
                        </button>
                        ${reschedulePendingId > 0 ? `
                            <button type="button" class="btn-apply-suggestion btn-apply-instant" onclick="applyAndSaveSuggestion(${idx})" style="background: linear-gradient(135deg, #059669, #10b981); border:1px solid #34d399; color:#fff;">
                                <i class="fas fa-bolt"></i> Instant Resolve #${reschedulePendingId}
                            </button>
                        ` : ''}
                    </div>
                </div>
            `).join('');
        })
        .catch(err => {
            if (err.name !== 'AbortError') {
                summaryText.textContent = "Could not fetch automated suggestions right now.";
            }
        });
}

function applySuggestion(index) {
    const sug = currentSuggestions[index];
    if (!sug) return;

    const reschedulePendingId = parseInt(document.getElementById("reschedulePendingId")?.value, 10) || 0;

    if (sug.type === 'alternative_room') {
        const hallSelect = document.getElementById("hall-select");
        const roomSelect = document.getElementById("room-select");

        if (hallSelect.value != sug.hall_id) {
            hallSelect.value = sug.hall_id;
            fetch(`../controllers/room_controller.php?hall_id=${sug.hall_id}&action=get_rooms`)
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        roomSelect.innerHTML = '<option value="">-- Choose a Room --</option>';
                        res.data.forEach(room => {
                            const opt = document.createElement("option");
                            opt.value = room.room_id;
                            opt.textContent = room.room_name;
                            opt.dataset.type = room.room_type;
                            opt.dataset.ac = room.room_has_ac;
                            opt.dataset.status = room.room_status;
                            opt.dataset.room_capacity = room.room_capacity;
                            const roomStatus = String(room.room_status || '').toLowerCase();
                            const roomType = String(room.room_type || '').toLowerCase();
                            const roomName = String(room.room_name || '').toLowerCase();
                            const isStorage = roomType.includes('storage') || roomName.includes('storage');
                            const isReservable = roomStatus === 'available' && !isStorage;
                            opt.disabled = !isReservable;
                            if (!isReservable) opt.textContent += ' — Not available for reservation';
                            roomSelect.appendChild(opt);
                        });
                        roomSelect.value = sug.room_id;
                        showRoomPreview();
                        checkConflicts();
                        const msg = reschedulePendingId > 0
                            ? `Selected ${sug.room_name}! Click "Update & Resolve" below to save.`
                            : `Switched to recommended room: ${sug.room_name} (${sug.formatted_time})`;
                        toast.show(msg, "success", 3500);
                    }
                });
            return;
        } else {
            roomSelect.value = sug.room_id;
            showRoomPreview();
        }
    } else if (sug.type === 'alternative_slot') {
        document.getElementById("schedule-start").value = sug.start;
        document.getElementById("schedule-end").value   = sug.end;
    }

    const msg = reschedulePendingId > 0
        ? `Selected slot! Click "Update & Resolve" below to save.`
        : `Applied recommendation: ${sug.room_name} (${sug.formatted_time})`;
    toast.show(msg, "success", 3500);
    checkConflicts();
}

function applyAndSaveSuggestion(index) {
    applySuggestion(index);
    setTimeout(() => submitReservation(), 350);
}

document.getElementById("schedule-start").addEventListener("change", checkConflicts);
document.getElementById("schedule-end").addEventListener("change",   checkConflicts);
document.getElementById("schedule-date").addEventListener("change",   checkConflicts);
document.getElementById("schedule-dow").addEventListener("change",    checkConflicts);

function getReservationReviewData() {
    const subject = document.getElementById("reservation-subject")?.value.trim() || "";
    const roomId = document.getElementById("room-select").value;
    const type   = document.querySelector('input[name="res-type"]:checked').value;
    const start  = document.getElementById("schedule-start").value;
    const end    = document.getElementById("schedule-end").value;
    const date   = document.getElementById("schedule-date").value;
    const dow    = document.getElementById("schedule-dow").value;
    const reschedulePendingId = parseInt(document.getElementById("reschedulePendingId")?.value, 10) || 0;
    const selectedRoom = document.getElementById("room-select")?.selectedOptions?.[0];
    const selectedStatus = String(selectedRoom?.dataset.status || '').toLowerCase();
    const selectedType = String(selectedRoom?.dataset.type || '').toLowerCase();
    const selectedName = String(selectedRoom?.textContent || '').toLowerCase();

    if (!subject)                   return toast.show("Please enter the reservation subject.", "error", 2500);
    if (!roomId)                    return toast.show("Please select a room.", "error", 2500);
    if (selectedStatus !== "available" || selectedType.includes("storage") || selectedName.includes("storage"))
        return toast.show("This room is not available for reservation.", "error", 2500);
    if (!start || !end)             return toast.show("Please set start and end time.", "error", 2500);
    if (start >= end)               return toast.show("End time must be after start time.", "error", 2500);
    if (type === "one-time" && !date) return toast.show("Please select a date.", "error", 2500);
    if (type === "weekly"   && !dow)  return toast.show("Please select a day of the week.", "error", 2500);
    if (document.getElementById("conflict-warning").style.display !== "none")
        return toast.show("Please resolve the scheduling conflict first.", "error", 2500);

    const hallSelect = document.getElementById("hall-select");
    const roomSelect = document.getElementById("room-select");
    const hallName = hallSelect.selectedOptions[0]?.textContent?.trim() || "—";
    const roomName = roomSelect.selectedOptions[0]?.textContent?.replace(/\s+—\s+Not available for reservation$/i, '').trim() || "—";
    const schedule = type === "one-time"
        ? new Date(`${date}T00:00:00`).toLocaleDateString(undefined, {year: "numeric", month: "long", day: "numeric"})
        : `Every ${dow}`;

    return { subject, roomId, type, start, end, date, dow, reschedulePendingId, hallName, roomName, schedule };
}

function openBookingReview() {
    const reviewData = getReservationReviewData();
    if (!reviewData || typeof reviewData !== "object") return;

    document.getElementById("review-subject").textContent = reviewData.subject;
    document.getElementById("review-building").textContent = reviewData.hallName;
    document.getElementById("review-room").textContent = reviewData.roomName;
    document.getElementById("review-schedule").textContent = reviewData.schedule;
    document.getElementById("review-time").textContent = `${reviewData.start} – ${reviewData.end}`;

    const review = document.getElementById("booking-review");
    const mainContent = document.querySelector(".main-content");
    review._returnFocus = document.activeElement;
    review._mainContent = mainContent;
    review._mainScrollTop = mainContent ? mainContent.scrollTop : 0;
    review.hidden = false;
    review.setAttribute("aria-hidden", "false");
    document.body.style.overflow = "hidden";
    if (mainContent) mainContent.style.overflow = "hidden";
    review.querySelector(".booking-review-dialog")?.focus();
    document.getElementById("review-confirm")?.focus();
}

function closeBookingReview() {
    const review = document.getElementById("booking-review");
    if (!review) return;
    review.hidden = true;
    review.setAttribute("aria-hidden", "true");
    if (review._mainContent) {
        review._mainContent.style.overflow = "";
        review._mainContent.scrollTop = review._mainScrollTop || 0;
    }
    document.body.style.overflow = "";
    review._returnFocus?.focus?.({ preventScroll: true });
}

function submitReservationRequest() {
    const reviewData = getReservationReviewData();
    if (!reviewData || typeof reviewData !== "object") return;

    const { subject, roomId, type, start, end, date, dow, reschedulePendingId } = reviewData;

    const btn     = document.getElementById("submit-btn");
    closeBookingReview();
    btn.disabled  = true;
    btn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${reschedulePendingId > 0 ? 'Updating...' : 'Submitting...'}`;

    const payload = {
        subject: subject,
        room_id: roomId,
        type:    type,
        start:   start,
        end:     end,
        date:    type === "one-time" ? date : null,
        dow:     type === "weekly"   ? dow  : null
    };

    if (reschedulePendingId > 0) {
        payload.pending_id = reschedulePendingId;
    }

    fetch("../controllers/reserve_controller.php", {
        method:  "POST",
        headers: { "Content-Type": "application/json" },
        body:    JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            if (res.action === 'updated' || reschedulePendingId > 0) {
                toast.show(res.message || "Reservation updated! Redirecting to calendar...", "success", 2500);
                setTimeout(() => window.location.href = "calendar_view.php?rescheduled=1", 1200);
            } else {
                toast.show("Reservation submitted! Waiting for admin approval.", "success", 3000);
                setTimeout(() => window.location.href = "user_homepage_view.php?submitted=1", 1500);
            }
        } else {
            toast.show(res.message ?? "Something went wrong.", "error", 3000);
            btn.disabled  = false;
            btn.innerHTML = reschedulePendingId > 0
                ? `<i class="fas fa-clipboard-check"></i> Review Updated Reservation`
                : `<i class="fas fa-clipboard-check"></i> Review Reservation Details`;
        }
    })
    .catch(() => {
        toast.show("Network error. Please try again.", "error", 3000);
        btn.disabled = false;
        btn.innerHTML = reschedulePendingId > 0
            ? `<i class="fas fa-clipboard-check"></i> Review Updated Reservation`
            : `<i class="fas fa-clipboard-check"></i> Review Reservation Details`;
    });
}

function submitReservation() {
    openBookingReview();
}
window.applySuggestion = applySuggestion;
window.applyAndSaveSuggestion = applyAndSaveSuggestion;
initSharedMobileMenu();


document.getElementById("hall-select")?.addEventListener("change", () => loadRooms());
document.querySelectorAll('input[name="res-type"]').forEach(input => {
    input.addEventListener("change", toggleType);
});
document.getElementById("submit-btn")?.addEventListener("click", submitReservation);
document.getElementById("review-confirm")?.addEventListener("click", submitReservationRequest);
document.getElementById("review-edit")?.addEventListener("click", closeBookingReview);
document.getElementById("review-close")?.addEventListener("click", closeBookingReview);
document.querySelector('[data-review-close="true"]')?.addEventListener("click", closeBookingReview);
document.addEventListener("keydown", event => {
    if (event.key === "Escape") closeBookingReview();
});
initSharedMobileMenu();
