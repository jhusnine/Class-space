// public/js/reserve_script.js
// ClassSpace 3-Step Room Reservation Wizard & Client-Side Engine

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

// -----------------------------------------------------------------------------
// Global Wizard State
// -----------------------------------------------------------------------------
let currentWizardStep = 1;
window.currentWizardStep = currentWizardStep;

// -----------------------------------------------------------------------------
// Hall & Room Data Fetching + URL Pre-fill Handling
// -----------------------------------------------------------------------------
fetch("../controllers/room_controller.php?action=get_halls")
    .then(r => r.json())
    .then(res => {
        const sel = document.getElementById("hall-select");
        if (!res.success || !res.data.length) return;
        res.data.forEach(hall => {
            const opt = document.createElement("option");
            opt.value = hall.hall_id;
            opt.textContent = hall.hall_name;
            sel.appendChild(opt);
        });

        const preHallId = parseInt(document.getElementById("preHallId")?.value, 10) || 0;
        const preHallName = (document.getElementById("preHallName")?.value || '').trim().toLowerCase();
        const preRoomId = parseInt(document.getElementById("preRoomId")?.value, 10) || 0;
        const preRoomName = (document.getElementById("preRoomName")?.value || '').trim().toLowerCase();
        const preDate = document.getElementById("preDate")?.value;
        const preDow = document.getElementById("preDow")?.value;
        const preType = document.getElementById("preType")?.value;
        const preStart = document.getElementById("preStart")?.value;
        const preEnd = document.getElementById("preEnd")?.value;
        const preUntil = document.getElementById("preUntil")?.value;
        const autoCheck = document.getElementById("autoCheck")?.value === "1";

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
            if (preUntil) {
                const untilInput = document.getElementById("schedule-until");
                if (untilInput) untilInput.value = preUntil;
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
    const hallId = document.getElementById("hall-select").value;
    const roomSel = document.getElementById("room-select");
    roomSel.innerHTML = `<option value="">— Loading... —</option>`;
    roomSel.disabled = true;
    const roomPreview = document.getElementById("room-preview");
    if (roomPreview) roomPreview.style.display = "none";

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
                const roomStatus = String(room.room_status || '').toLowerCase();
                if (roomStatus === 'maintenance' || roomStatus === 'unavailable') {
                    return;
                }
                const opt = document.createElement("option");
                opt.value = room.room_id;
                opt.textContent = room.room_name;
                opt.dataset.room_capacity = room.room_capacity;
                opt.dataset.type = room.room_type;
                opt.dataset.ac = room.room_has_ac;
                opt.dataset.status = room.room_status;
                const roomType = String(room.room_type || '').toLowerCase();
                const roomName = String(room.room_name || '').toLowerCase();
                const isStorage = roomType.includes('storage') || roomName.includes('storage');
                const isReservable = roomStatus === 'available' && !isStorage;
                opt.disabled = !isReservable;
                if (!isReservable) opt.textContent += ' — Not available for reservation';
                roomSel.appendChild(opt);
            });
            if (roomSel.options.length <= 1) {
                roomSel.innerHTML = `<option value="">No rooms available</option>`;
                return;
            }
            roomSel.disabled = false;
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
    const sel = document.getElementById("room-select");
    const opt = sel?.options[sel.selectedIndex];
    const preview = document.getElementById("room-preview");

    if (!sel || !sel.value) {
        if (preview) preview.style.display = "none";
        return;
    }

    const roomStatus = String(opt?.dataset.status || '').toLowerCase();
    const roomType = String(opt?.dataset.type || '').toLowerCase();
    const roomName = String(opt?.textContent || '').toLowerCase();
    const isStorage = roomType.includes('storage') || roomName.includes('storage');

    const statusClass = {
        available: "preview-status-available",
        unavailable: "preview-status-unavailable",
        maintenance: "preview-status-maintenance"
    }[roomStatus] ?? "preview-status-unknown";
    const displayStatusClass = isStorage ? "preview-status-storage" : statusClass;
    const displayStatus = isStorage ? "STORAGE" : (opt?.dataset.status || "Unknown");

    if (preview) {
        const hallSelect = document.getElementById("hall-select");
        const hallName = hallSelect?.selectedOptions?.[0]?.textContent?.trim() || "";
        const bannerUrl = getBuildingImage(hallName);
        const cleanName = opt?.textContent?.replace(/\s+—\s+Not available for reservation$/i, '') || '';

        preview.innerHTML = `
            <div class="room-preview-card-inner">
                <div class="room-preview-thumb">
                    <img src="${bannerUrl}" alt="${escapeHtml(hallName)}" class="room-preview-img">
                </div>
                <div class="room-preview-content">
                    <div class="room-preview-title-row">
                        <strong class="room-preview-name">${escapeHtml(cleanName)}</strong>
                        <span class="${displayStatusClass}">${displayStatus}</span>
                    </div>
                    <div class="room-preview-meta-row">
                        ${hallName ? `<span class="room-preview-tag"><i class="fas fa-building"></i> ${escapeHtml(hallName)}</span>` : ''}
                        <span class="room-preview-tag"><i class="fas fa-users"></i> ${escapeHtml(opt?.dataset.room_capacity)} seats</span>
                        <span class="room-preview-tag"><i class="fas fa-tag"></i> ${escapeHtml(opt?.dataset.type)}</span>
                        ${opt?.dataset.ac == 1 ? '<span class="room-preview-tag ac-tag"><i class="fas fa-snowflake"></i> AC</span>' : '<span class="room-preview-tag">No AC</span>'}
                    </div>
                </div>
            </div>
        `;
        preview.style.display = "block";
    }

    const isReservable = roomStatus === 'available' && !isStorage;
    const submitButton = document.getElementById("submit-btn");
    const warning = document.getElementById("conflict-warning");
    const aiContainer = document.getElementById("ai-suggestions-container");

    if (submitButton) submitButton.disabled = !isReservable;
    if (!isReservable) {
        if (warning) {
            const conflictMsg = document.getElementById("conflict-msg");
            if (conflictMsg) {
                conflictMsg.textContent = isStorage
                    ? "Storage rooms cannot be reserved. Please select an available room."
                    : "This room is unavailable for reservation. Please select an available room.";
            }
            warning.style.display = "block";
        }
        if (aiContainer) aiContainer.style.display = "none";
        return;
    }

    checkConflicts();
}

function toggleType() {
    const type = document.querySelector('input[name="res-type"]:checked')?.value || "one-time";
    const fieldDate = document.getElementById("field-date");
    const fieldDow = document.getElementById("field-dow");
    const semEl = document.getElementById("field-semester");

    if (fieldDate) fieldDate.style.display = type === "one-time" ? "block" : "none";
    if (fieldDow) fieldDow.style.display = type === "weekly" ? "block" : "none";
    if (semEl) semEl.style.display = type === "weekly" ? "block" : "none";

    checkConflicts();
}

// -----------------------------------------------------------------------------
// Real-Time Conflict & AI Suggestions Engine
// -----------------------------------------------------------------------------
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
    const roomSelect = document.getElementById("room-select");
    const roomId = roomSelect ? roomSelect.value : "";
    const typeRadio = document.querySelector('input[name="res-type"]:checked');
    const type = typeRadio ? typeRadio.value : "one-time";
    const startInput = document.getElementById("schedule-start");
    const endInput = document.getElementById("schedule-end");
    const dateInput = document.getElementById("schedule-date");
    const dowSelect = document.getElementById("schedule-dow");

    const start = startInput ? startInput.value : "";
    const end = endInput ? endInput.value : "";
    const date = dateInput ? dateInput.value : "";
    const dow = dowSelect ? dowSelect.value : "";
    const warning = document.getElementById("conflict-warning");
    const aiContainer = document.getElementById("ai-suggestions-container");

    if (conflictAbortController) {
        conflictAbortController.abort();
    }
    if (aiAbortController) {
        aiAbortController.abort();
    }

    if (warning) warning.style.display = "none";
    if (aiContainer && !forceAiFetch) aiContainer.style.display = "none";

    if (!roomId || !start || !end) return;
    if (type === "one-time" && !date) return;
    if (type === "weekly" && !dow) return;

    const selectedRoom = roomSelect?.selectedOptions?.[0];
    const selectedStatus = String(selectedRoom?.dataset.status || '').toLowerCase();
    const selectedType = String(selectedRoom?.dataset.type || '').toLowerCase();
    const selectedName = String(selectedRoom?.textContent || '').toLowerCase();
    if (selectedStatus !== "available" || selectedType.includes("storage") || selectedName.includes("storage")) {
        if (warning) {
            warning.style.display = "block";
            const conflictMsg = document.getElementById("conflict-msg");
            if (conflictMsg) {
                conflictMsg.textContent = selectedType.includes("storage") || selectedName.includes("storage")
                    ? "Storage rooms cannot be reserved. Please select an available room."
                    : "This room is unavailable for reservation. Please select an available room.";
            }
        }
        if (aiContainer) aiContainer.style.display = "none";
        return;
    }

    const params = new URLSearchParams({ room_id: roomId, start, end, type });
    if (type === "one-time") {
        params.append("date", date);
    } else {
        params.append("dow", dow);
        const until = document.getElementById("schedule-until")?.value;
        if (until) params.append("until", until);
    }
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
                if (warning) {
                    warning.style.display = "block";
                    const conflictMsg = document.getElementById("conflict-msg");
                    if (conflictMsg) conflictMsg.textContent = res.message || "This room is unavailable for reservation.";
                }
                if (aiContainer) aiContainer.style.display = "none";
                return;
            }
            if (res.conflict) {
                if (warning) {
                    const conflictMsg = document.getElementById("conflict-msg");
                    if (conflictMsg) conflictMsg.textContent = res.message;
                    warning.style.display = "block";
                }
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
    const cardsList = document.getElementById("ai-cards-list");
    const summaryText = document.getElementById("ai-summary-text");
    if (!aiContainer || !cardsList) return;

    if (aiAbortController) {
        aiAbortController.abort();
    }
    aiAbortController = new AbortController();
    const aiReqId = ++lastAiReqId;

    aiContainer.style.display = "block";
    if (summaryText) {
        summaryText.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Analyzing campus facilities & open intervals via CSP engine...';
    }
    cardsList.innerHTML = '';

    fetch(`../controllers/ai_suggest_controller.php?${params}`, { signal: aiAbortController.signal })
        .then(r => r.json())
        .then(res => {
            if (aiReqId !== lastAiReqId) return;
            if (!res.success || !res.suggestions || res.suggestions.length === 0) {
                if (res.error === "room_unavailable") {
                    aiContainer.style.display = "none";
                }
                if (summaryText) {
                    summaryText.textContent = res.ai_summary || "No immediate alternative slots found for this date. Please consider adjusting the day or time window.";
                }
                return;
            }

            currentSuggestions = res.suggestions;
            if (summaryText) summaryText.textContent = res.ai_summary;

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
                if (summaryText) summaryText.textContent = "Could not fetch automated suggestions right now.";
            }
        });
}

function applySuggestion(index, onComplete) {
    const sug = currentSuggestions[index];
    if (!sug) return;

    const reschedulePendingId = parseInt(document.getElementById("reschedulePendingId")?.value, 10) || 0;

    if (sug.type === 'alternative_room') {
        const hallSelect = document.getElementById("hall-select");
        const roomSelect = document.getElementById("room-select");

        if (hallSelect && hallSelect.value != sug.hall_id) {
            hallSelect.value = sug.hall_id;
            fetch(`../controllers/room_controller.php?hall_id=${sug.hall_id}&action=get_rooms`)
                .then(r => r.json())
                .then(res => {
                    if (res.success && roomSelect) {
                        roomSelect.innerHTML = '<option value="">-- Choose a Room --</option>';
                        res.data.forEach(room => {
                            const roomStatus = String(room.room_status || '').toLowerCase();
                            if (roomStatus === 'maintenance' || roomStatus === 'unavailable') {
                                return;
                            }
                            const opt = document.createElement("option");
                            opt.value = room.room_id;
                            opt.textContent = room.room_name;
                            opt.dataset.type = room.room_type;
                            opt.dataset.ac = room.room_has_ac;
                            opt.dataset.status = room.room_status;
                            opt.dataset.room_capacity = room.room_capacity;
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
                            ? `Selected ${sug.room_name}! Click "Proceed to Review" to confirm.`
                            : `Switched to recommended room: ${sug.room_name} (${sug.formatted_time})`;
                        toast.show(msg, "success", 3500);
                        if (typeof onComplete === 'function') onComplete();
                    }
                });
            return;
        } else if (roomSelect) {
            roomSelect.value = sug.room_id;
            showRoomPreview();
        }
    } else if (sug.type === 'alternative_slot') {
        const startInput = document.getElementById("schedule-start");
        const endInput = document.getElementById("schedule-end");
        if (startInput) startInput.value = sug.start;
        if (endInput) endInput.value = sug.end;
    }

    const msg = reschedulePendingId > 0
        ? `Selected slot! Click "Proceed to Review" to confirm.`
        : `Applied recommendation: ${sug.room_name} (${sug.formatted_time})`;
    toast.show(msg, "success", 3500);
    checkConflicts();
    if (typeof onComplete === 'function') onComplete();
}

function applyAndSaveSuggestion(index) {
    applySuggestion(index, () => {
        setTimeout(() => {
            const subjectInput = document.getElementById("reservation-subject");
            if (subjectInput && !subjectInput.value.trim()) {
                subjectInput.value = "Rescheduled Class/Activity";
            }
            submitReservationRequest();
        }, 300);
    });
}

// -----------------------------------------------------------------------------
// Helper Utilities: Building Banner Image & Time Formatting
// -----------------------------------------------------------------------------
function getBuildingImage(hallName) {
    const name = String(hallName || '').toLowerCase();
    if (name.includes('plaridel')) return '../../public/images/plaridel.jpeg';
    if (name.includes('melchora')) return '../../public/images/melchora.jpg';
    if (name.includes('rizal')) return '../../public/images/rizal.jpg';
    if (name.includes('balagtas')) return '../../public/images/balagtas.jpg';
    if (name.includes('gym')) return '../../public/images/gym.png';
    if (name.includes('hotel')) return '../../public/images/hotel.jpeg';
    if (name.includes('ople')) return '../../public/images/ople.jpg';
    return '../../public/images/login_background.jpg';
}

function formatTime(timeStr) {
    if (!timeStr) return '';
    const parts = timeStr.split(':');
    if (parts.length < 2) return timeStr;
    let hour = parseInt(parts[0], 10);
    const minute = parts[1].padStart(2, '0');
    if (isNaN(hour)) return timeStr;
    const period = hour >= 12 ? 'PM' : 'AM';
    hour = hour % 12;
    if (hour === 0) hour = 12;
    return `${hour}:${minute} ${period}`;
}

// -----------------------------------------------------------------------------
// Step Validations
// -----------------------------------------------------------------------------
function validateStep1() {
    const subjectInput = document.getElementById("reservation-subject");
    const subject = (subjectInput?.value || "").trim();
    if (!subject) {
        toast.show("Please enter the reservation subject.", "error", 2500);
        subjectInput?.focus();
        return false;
    }

    const roomSelect = document.getElementById("room-select");
    const roomId = roomSelect?.value || "";
    if (!roomId) {
        toast.show("Please select a room.", "error", 2500);
        roomSelect?.focus();
        return false;
    }

    const opt = roomSelect.selectedOptions?.[0];
    const roomStatus = String(opt?.dataset?.status || "").toLowerCase();
    const roomType = String(opt?.dataset?.type || "").toLowerCase();
    const roomName = String(opt?.textContent || "").toLowerCase();
    const isStorage = roomType.includes("storage") || roomName.includes("storage");

    if (opt?.disabled || roomStatus === "maintenance" || roomStatus === "unavailable" || isStorage) {
        toast.show("This room is not available for reservation.", "error", 2500);
        return false;
    }

    return true;
}

function validateStep2() {
    const typeRadio = document.querySelector('input[name="res-type"]:checked');
    const type = typeRadio ? typeRadio.value : "one-time";
    const dateInput = document.getElementById("schedule-date");
    const dowSelect = document.getElementById("schedule-dow");
    const startInput = document.getElementById("schedule-start");
    const endInput = document.getElementById("schedule-end");

    const date = dateInput?.value || "";
    const dow = dowSelect?.value || "";
    const start = startInput?.value || "";
    const end = endInput?.value || "";

    if (type === "one-time" && !date) {
        toast.show("Please select a date.", "error", 2500);
        dateInput?.focus();
        return false;
    }

    if (type === "weekly" && !dow) {
        toast.show("Please select a day of the week.", "error", 2500);
        dowSelect?.focus();
        return false;
    }

    if (!start || !end) {
        toast.show("Please set start and end time.", "error", 2500);
        if (!start) startInput?.focus();
        else endInput?.focus();
        return false;
    }

    if (start >= end) {
        toast.show("End time must be after start time.", "error", 2500);
        endInput?.focus();
        return false;
    }

    const warning = document.getElementById("conflict-warning");
    const isConflictVisible = warning && (
        warning.style.display === "block" ||
        (warning.style.display !== "none" && typeof window !== "undefined" && window.getComputedStyle && window.getComputedStyle(warning).display !== "none")
    );
    if (isConflictVisible) {
        toast.show("Please resolve the scheduling conflict before proceeding.", "error", 2500);
        return false;
    }

    return true;
}

// -----------------------------------------------------------------------------
// Stepper UI Updater
// -----------------------------------------------------------------------------
function updateStepper(stepNumber) {
    const progressBar = document.getElementById("stepper-progress-bar");
    if (progressBar) {
        const widths = { 1: "0%", 2: "50%", 3: "100%" };
        progressBar.style.width = widths[stepNumber] || "0%";
    }

    const stepIcons = { 1: "fa-door-open", 2: "fa-calendar-days", 3: "fa-clipboard-check" };

    document.querySelectorAll(".wizard-stepper .step-item").forEach(item => {
        const step = parseInt(item.dataset.step, 10);
        const circle = item.querySelector(".step-circle");
        if (!step) return;

        if (step < stepNumber) {
            item.classList.add("completed");
            item.classList.remove("active");
            item.removeAttribute("aria-current");
            if (circle) circle.innerHTML = '<i class="fas fa-check"></i>';
        } else if (step === stepNumber) {
            item.classList.add("active");
            item.classList.remove("completed");
            item.setAttribute("aria-current", "step");
            if (circle) circle.innerHTML = `<i class="fas ${stepIcons[step] || 'fa-circle'}"></i>`;
        } else {
            item.classList.remove("active", "completed");
            item.removeAttribute("aria-current");
            if (circle) circle.innerHTML = `<i class="fas ${stepIcons[step] || 'fa-circle'}"></i>`;
        }
    });
}

// -----------------------------------------------------------------------------
// Step 3 In-Page Summary Population
// -----------------------------------------------------------------------------
function populateReviewSummary() {
    const subject = document.getElementById("reservation-subject")?.value?.trim() || "—";
    const hallSelect = document.getElementById("hall-select");
    const roomSelect = document.getElementById("room-select");

    const hallName = hallSelect?.selectedOptions?.[0]?.textContent?.trim() || "—";
    const selectedOption = roomSelect?.selectedOptions?.[0];
    const roomName = selectedOption?.textContent?.replace(/\s+—\s+Not available for reservation$/i, '')?.trim() || "—";
    const capacity = selectedOption?.dataset?.room_capacity || selectedOption?.dataset?.capacity || "";
    const roomType = selectedOption?.dataset?.type || "";
    const hasAc = selectedOption?.dataset?.ac == 1;

    const type = document.querySelector('input[name="res-type"]:checked')?.value || "one-time";
    const date = document.getElementById("schedule-date")?.value || "";
    const dow = document.getElementById("schedule-dow")?.value || "";
    const until = document.getElementById("schedule-until")?.value || "";
    const start = document.getElementById("schedule-start")?.value || "";
    const end = document.getElementById("schedule-end")?.value || "";

    // Banner image resolution
    const bannerUrl = getBuildingImage(hallName);
    const banner = document.getElementById("summary-banner");
    let bannerImg = document.getElementById("summary-banner-img");
    if (!bannerImg && banner) {
        bannerImg = document.createElement("img");
        bannerImg.id = "summary-banner-img";
        bannerImg.className = "summary-banner-img";
        bannerImg.alt = hallName || "Building Banner";
        banner.insertBefore(bannerImg, banner.firstChild);
    }
    if (bannerImg) {
        bannerImg.src = bannerUrl;
        bannerImg.alt = hallName || "Building Banner";
    }
    if (banner) {
        banner.style.backgroundImage = `url("${bannerUrl}")`;
    }

    // Format schedule
    let scheduleText = "—";
    if (type === "one-time") {
        if (date) {
            scheduleText = new Date(date + 'T00:00:00').toLocaleDateString(undefined, {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        }
    } else if (type === "weekly") {
        if (until) {
            const untilFormatted = new Date(until + 'T00:00:00').toLocaleDateString(undefined, {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
            scheduleText = `Every ${dow} (until ${untilFormatted})`;
        } else {
            scheduleText = `Every ${dow}`;
        }
    }

    // Format time slot
    let timeText = "—";
    if (start && end) {
        let durationStr = "";
        const [sH, sM] = start.split(':').map(Number);
        const [eH, eM] = end.split(':').map(Number);
        if (!isNaN(sH) && !isNaN(eH)) {
            const diffMins = (eH * 60 + eM) - (sH * 60 + sM);
            if (diffMins > 0) {
                const hrs = diffMins / 60;
                durationStr = ` (${hrs % 1 === 0 ? hrs : hrs.toFixed(1)} ${hrs === 1 ? 'hr' : 'hrs'})`;
            }
        }
        timeText = `${formatTime(start)} – ${formatTime(end)}${durationStr}`;
    }

    // Populate all summary elements
    const subjectEl = document.getElementById("summary-subject");
    if (subjectEl) subjectEl.textContent = subject;

    const buildingEl = document.getElementById("summary-building");
    if (buildingEl) buildingEl.textContent = hallName;

    const roomEl = document.getElementById("summary-room");
    if (roomEl) roomEl.textContent = roomName;

    const scheduleEl = document.getElementById("summary-schedule");
    if (scheduleEl) scheduleEl.textContent = scheduleText;

    const timeEl = document.getElementById("summary-time");
    if (timeEl) timeEl.textContent = timeText;

    const buildingBadge = document.getElementById("summary-building-badge");
    if (buildingBadge) {
        buildingBadge.innerHTML = `<i class="fas fa-building"></i> ${escapeHtml(hallName)}`;
    }

    const roomTitle = document.getElementById("summary-room-title");
    if (roomTitle) {
        roomTitle.textContent = roomName;
    }

    const capBadge = document.getElementById("summary-capacity-badge");
    if (capBadge) {
        const acText = hasAc ? " • AC" : "";
        const capText = capacity ? `${capacity} Seats${acText}` : "Seating Capacity";
        capBadge.innerHTML = `<i class="fas fa-users"></i> ${escapeHtml(capText)}`;
    }

    const capEl = document.getElementById("summary-capacity");
    if (capEl) {
        capEl.textContent = capacity ? `${capacity} seats` : "—";
    }

    const typeEl = document.getElementById("summary-type");
    if (typeEl) {
        typeEl.textContent = type === "weekly" ? "Weekly (recurring)" : "One-time";
    }
}

// -----------------------------------------------------------------------------
// Wizard State Machine: Step Navigation
// -----------------------------------------------------------------------------
function goToStep(stepNumber) {
    if (stepNumber === 2 && currentWizardStep < 2) {
        if (!validateStep1()) return;
    }
    if (stepNumber === 3) {
        if (!validateStep1()) return;
        if (!validateStep2()) return;
    }

    const steps = [1, 2, 3];
    steps.forEach(num => {
        const stepEl = document.getElementById(`wizard-step-${num}`);
        if (!stepEl) return;
        if (num === stepNumber) {
            stepEl.classList.add("active");
            stepEl.style.display = "block";
        } else {
            stepEl.classList.remove("active");
            stepEl.style.display = "none";
        }
    });

    updateStepper(stepNumber);

    if (stepNumber === 3) {
        populateReviewSummary();
    }

    currentWizardStep = stepNumber;
    window.currentWizardStep = currentWizardStep;

    if (stepNumber === 1) {
        document.getElementById("reservation-subject")?.focus();
    } else if (stepNumber === 2) {
        const type = document.querySelector('input[name="res-type"]:checked')?.value;
        if (type === "weekly") {
            document.getElementById("schedule-dow")?.focus();
        } else {
            document.getElementById("schedule-date")?.focus();
        }
    } else if (stepNumber === 3) {
        document.getElementById("submit-btn")?.focus();
    }

    const card = document.querySelector(".reservation-card");
    if (card && typeof card.scrollIntoView === "function") {
        card.scrollIntoView({ behavior: "smooth", block: "start" });
    }
}

// -----------------------------------------------------------------------------
// Data Packaging & Direct Async Submission
// -----------------------------------------------------------------------------
function getReservationReviewData() {
    if (!validateStep1() || !validateStep2()) {
        return null;
    }

    const subject = document.getElementById("reservation-subject")?.value?.trim() || "";
    const roomId = document.getElementById("room-select")?.value || "";
    const type = document.querySelector('input[name="res-type"]:checked')?.value || "one-time";
    const start = document.getElementById("schedule-start")?.value || "";
    const end = document.getElementById("schedule-end")?.value || "";
    const date = document.getElementById("schedule-date")?.value || "";
    const dow = document.getElementById("schedule-dow")?.value || "";
    const until = document.getElementById("schedule-until")?.value || "";
    const reschedulePendingId = parseInt(document.getElementById("reschedulePendingId")?.value, 10) || 0;

    const hallSelect = document.getElementById("hall-select");
    const roomSelect = document.getElementById("room-select");
    const hallName = hallSelect?.selectedOptions?.[0]?.textContent?.trim() || "—";
    const selectedRoom = roomSelect?.selectedOptions?.[0];
    const roomName = selectedRoom?.textContent?.replace(/\s+—\s+Not available for reservation$/i, '')?.trim() || "—";
    const capacity = selectedRoom?.dataset?.room_capacity || selectedRoom?.dataset?.capacity || "";
    const roomType = selectedRoom?.dataset?.type || "";
    const hasAc = selectedRoom?.dataset?.ac == 1;

    const untilFormatted = until
        ? new Date(`${until}T00:00:00`).toLocaleDateString(undefined, { year: "numeric", month: "short", day: "numeric" })
        : "";
    const schedule = type === "one-time"
        ? new Date(`${date}T00:00:00`).toLocaleDateString(undefined, { weekday: "long", year: "numeric", month: "long", day: "numeric" })
        : (untilFormatted ? `Every ${dow} (until ${untilFormatted})` : `Every ${dow}`);

    return {
        subject,
        roomId,
        type,
        start,
        end,
        date,
        dow,
        until,
        reschedulePendingId,
        hallName,
        roomName,
        capacity,
        roomType,
        hasAc,
        schedule
    };
}

function submitReservationRequest() {
    const reviewData = getReservationReviewData();
    if (!reviewData || typeof reviewData !== "object") return;

    const { subject, roomId, type, start, end, date, dow, until, reschedulePendingId } = reviewData;

    const btn = document.getElementById("submit-btn");
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${reschedulePendingId > 0 ? 'Updating...' : 'Submitting...'}`;
    }

    const payload = {
        subject: subject,
        room_id: roomId,
        type: type,
        start: start,
        end: end,
        date: type === "one-time" ? date : null,
        dow: type === "weekly" ? dow : null,
        until: type === "weekly" ? until : null
    };

    if (reschedulePendingId > 0) {
        payload.pending_id = reschedulePendingId;
    }

    fetch("../controllers/reserve_controller.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload)
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
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = reschedulePendingId > 0
                        ? `<i class="fas fa-paper-plane"></i> Confirm & Update Reservation`
                        : `<i class="fas fa-paper-plane"></i> Confirm & Submit Reservation`;
                }
            }
        })
        .catch(() => {
            toast.show("Network error. Please try again.", "error", 3000);
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = reschedulePendingId > 0
                    ? `<i class="fas fa-paper-plane"></i> Confirm & Update Reservation`
                    : `<i class="fas fa-paper-plane"></i> Confirm & Submit Reservation`;
            }
        });
}

function submitReservation() {
    submitReservationRequest();
}

function openBookingReview() {
    goToStep(3);
}

function closeBookingReview() {
    // Stub for backward compatibility
}

// -----------------------------------------------------------------------------
// Global Window Exports
// -----------------------------------------------------------------------------
window.goToStep = goToStep;
window.validateStep1 = validateStep1;
window.validateStep2 = validateStep2;
window.updateStepper = updateStepper;
window.populateReviewSummary = populateReviewSummary;
window.submitReservation = submitReservation;
window.submitReservationRequest = submitReservationRequest;
window.openBookingReview = openBookingReview;
window.closeBookingReview = closeBookingReview;
window.applySuggestion = applySuggestion;
window.applyAndSaveSuggestion = applyAndSaveSuggestion;
window.getBuildingImage = getBuildingImage;
window.formatTime = formatTime;

// -----------------------------------------------------------------------------
// Event Listeners & Initialization
// -----------------------------------------------------------------------------
initSharedMobileMenu();

// Step navigation buttons
document.getElementById("btn-step1-next")?.addEventListener("click", () => goToStep(2));
document.getElementById("btn-step2-back")?.addEventListener("click", () => goToStep(1));
document.getElementById("btn-step2-next")?.addEventListener("click", () => goToStep(3));
document.getElementById("btn-step3-back")?.addEventListener("click", () => goToStep(2));

// Quick edit buttons on Step 3 Summary Card
document.querySelectorAll(".summary-edit-btn[data-target-step]").forEach(btn => {
    btn.addEventListener("click", () => {
        const targetStep = parseInt(btn.dataset.targetStep, 10);
        if (targetStep) goToStep(targetStep);
    });
});

// Stepper items click & keyboard listener
document.querySelectorAll(".wizard-stepper .step-item").forEach(item => {
    item.addEventListener("click", () => {
        const targetStep = parseInt(item.dataset.step, 10);
        if (!targetStep || targetStep === currentWizardStep) return;
        goToStep(targetStep);
    });
    item.addEventListener("keydown", (e) => {
        if (e.key === "Enter" || e.key === " ") {
            e.preventDefault();
            item.click();
        }
    });
});

// Step 3 final submission button
document.getElementById("submit-btn")?.addEventListener("click", submitReservationRequest);

// Form input change listeners for dynamic conflict re-checks
document.getElementById("hall-select")?.addEventListener("change", () => loadRooms());
document.getElementById("schedule-start")?.addEventListener("change", checkConflicts);
document.getElementById("schedule-end")?.addEventListener("change", checkConflicts);
document.getElementById("schedule-date")?.addEventListener("change", checkConflicts);
document.getElementById("schedule-dow")?.addEventListener("change", checkConflicts);
document.getElementById("schedule-until")?.addEventListener("change", checkConflicts);
document.querySelectorAll('input[name="res-type"]').forEach(input => {
    input.addEventListener("change", toggleType);
});

// Advance to Step 2 on Enter key from subject input
document.getElementById("reservation-subject")?.addEventListener("keydown", (e) => {
    if (e.key === "Enter") {
        e.preventDefault();
        goToStep(2);
    }
});

// Initialize Stepper at Step 1
updateStepper(1);
