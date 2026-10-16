(function () {
    "use strict";

    /* -- Element lookups (null-safe) ----------------------------------- */
    const input      = document.getElementById("search-input");
    const resultsBox = document.getElementById("search-results");
    const searchBtn  = document.querySelector(".search-btn");

    /* -- State --------------------------------------------------------- */
    let allRoomsCache   = [];
    let currentFilter   = null;
    let currentBuilding = "all";
    let vacantOnly      = false;
    let statsData       = {};

    const PLACEHOLDER_IMAGE = "../../public/images/example.jpg";

    const BUILDING_IMAGES = {
        gym: "../../public/images/gym.png",
        hotel: "../../public/images/hotel.jpeg",
        melchora: "../../public/images/melchora.jpg",
        plaridel: "../../public/images/plaridel.jpeg"
    };

    const ROOM_IMAGES = {
        "bh 101": "../../public/images/BH%20101.jpg",
        "bh 102": "../../public/images/BH%20102.jpg",
        "bh 103": "../../public/images/BH%20103.jpg",
        "bh 104": "../../public/images/BH%20104.jpg",
        "bh 201": "../../public/images/BH%20201.jpg",
        "bh 202": "../../public/images/BH%20202.jpg",
        "bh 203": "../../public/images/BH%20203.jpg",
        "bh 204": "../../public/images/BH%20204.jpg",
        "bh 205": "../../public/images/BH%20205.jpg",
        "bh 206": "../../public/images/BH%20206.jpg",
        "bh 207": "../../public/images/BH%20207.jpg",
        "bh 301": "../../public/images/BH%20301.jpg",
        "bh 302": "../../public/images/BH%20302.jpg",
        "bh 303": "../../public/images/BH%20303.jpg",
        "bh 304": "../../public/images/BH%20304.jpg",
        "bh 305": "../../public/images/BH%20305.jpg",
        "bh 306": "../../public/images/BH%20306.jpg",
        "bh 307": "../../public/images/BH%20307.jpg",
        "bh 401": "../../public/images/BH%20401.jpg",
        "bh 402": "../../public/images/BH%20402.jpg",
        "bh 403": "../../public/images/BH%20403.jpg",
        "bh 404": "../../public/images/BH%20404.jpg",
        "bh 405": "../../public/images/BH%20405.jpg",
        "bh 406": "../../public/images/BH%20406.jpg",
        "bh 407": "../../public/images/BH%20407.jpg",
        "bh 501": "../../public/images/BH%20501.jpg",
        "bh 502": "../../public/images/BH%20502.jpg",
        "bh 503": "../../public/images/BH%20503.jpg",
        "bh 504": "../../public/images/BH%20504.jpg",
        "bh 505": "../../public/images/BH%20505.jpg",
        "bh 506": "../../public/images/BH%20506.jpg",
        "bh 507": "../../public/images/BH%20507.jpg",
        "ople 102": "../../public/images/OPLE%20102.jpeg",
        "ople 201": "../../public/images/OPLE%20201.jpeg",
        "ople 202": "../../public/images/OPLE%20202.jpeg",
        "ople 301": "../../public/images/OPLE%20301.jpeg",
        "ople 302": "../../public/images/OPLE%20302.jpeg",
        "ople 303": "../../public/images/OPLE%20303.jpeg",
        "ople 304": "../../public/images/OPLE%20304.jpeg",
        "ople 305": "../../public/images/OPLE%20305.jpeg",
        "ople 401": "../../public/images/OPLE%20401.jpeg",
        "ople 402": "../../public/images/OPLE%20402.jpeg",
        "ople 403": "../../public/images/OPLE%20403.jpeg",
        "ople 404": "../../public/images/OPLE%20404.jpeg",
        "ople 405": "../../public/images/OPLE%20405.jpeg",
        "ople 501": "../../public/images/OPLE%20501.jpeg",
        "ople 502": "../../public/images/OPLE%20502.jpeg",
        "ople 503": "../../public/images/OPLE%20503.jpeg",
        "ople psychology testingsimulation room": "../../public/images/PSYCHOLOGY%20TESTINGSIMULATION%20ROOM.jpeg",
        "ople psychological research laboratory": "../../public/images/OPLE%20PSYCHOLOGICAL%20RESEARCH%20LABORATORY.jpeg"
    };

    function buildingImageForName(value) {
        const hallName = String(value ?? "").trim().toLowerCase();
        const imageKey = Object.keys(BUILDING_IMAGES).find(key => hallName.includes(key));
        return imageKey ? BUILDING_IMAGES[imageKey] : null;
    }

    function hallPhoto(hall) {
        return buildingImageForName(hall?.hall_name) || PLACEHOLDER_IMAGE;
    }

    function hallPlaceholder(hall) {
        const hallName = String(hall?.hall_name ?? "").trim().toLowerCase();
        return hallName === "gym" ? "../../public/images/gym.png" : PLACEHOLDER_IMAGE;
    }

    function isBlasOplePhotoRoom(room) {
        const hallName = String(room?.hall_name ?? "").trim().toLowerCase().replace(/[^a-z0-9]+/g, " ").trim();
        const roomName = String(room?.room_name ?? "").trim().toLowerCase();
        const hallMatchesOple = hallName.includes("ople") || hallName === "oh" || hallName.startsWith("oh ");
        const nameIdentifiesOple = roomName.startsWith("ople ") || roomName.startsWith("oh ") || roomName === "ople" || roomName === "oh";
        return hallMatchesOple || (!hallName && nameIdentifiesOple);
    }

    function roomPhoto(room) {
        const hallName = String(room?.hall_name ?? "").trim().toLowerCase();
        const roomName = String(room?.room_name ?? "").trim().toLowerCase();
        const bhCodeMatch = roomName.match(/\bbh[\s-]*(\d{3})\b/i);
        const isBalagtasHall = hallName.includes("balagtas") || hallName === "bh" || hallName.startsWith("bh ");
        const balagtasRoomMatch = isBalagtasHall ? roomName.match(/(?:^|\D)(\d{3})(?:\D|$)/) : null;
        const bhCode = bhCodeMatch?.[1] || balagtasRoomMatch?.[1];

        if (bhCode) {
            const roomImage = ROOM_IMAGES[`bh ${bhCode}`];
            if (roomImage) return roomImage;
        }

        if (isBlasOplePhotoRoom(room)) {
            const normalizedRoomLabel = `${roomName} ${String(room?.room_type ?? "").toLowerCase()}`
                .replace(/[^a-z0-9]+/g, " ")
                .trim();
            const compactRoomLabel = normalizedRoomLabel.replace(/\s+/g, "");

            if (compactRoomLabel.includes("psychologytestingsimulationroom")) {
                return ROOM_IMAGES["ople psychology testingsimulation room"];
            }
            if (compactRoomLabel.includes("psychologicalresearchlaboratory")) {
                return ROOM_IMAGES["ople psychological research laboratory"];
            }

            const roomCodeMatch = roomName.match(/\b(?:ople|oh)[\s-]*(\d{3})\b/i) || roomName.match(/\b(\d{3})\b/);
            if (roomCodeMatch) {
                const roomImage = ROOM_IMAGES[`ople ${roomCodeMatch[1]}`];
                if (roomImage) return roomImage;
            }
        }

        if (hallName.includes("gym") || roomName === "gym") {
            return BUILDING_IMAGES.gym;
        }

        // Named facade assets remain reserved for their matching buildings.
        return buildingImageForName(hallName) || PLACEHOLDER_IMAGE;
    }

    function roomStatusClass(status) {
        if (status === "available" || status === "vacant") return "room-status-available";
        if (status === "maintenance") return "room-status-maintenance";
        return "room-status-unavailable";
    }

    function roomFloor(room) {
        const rawFloor = room?.floor ?? room?.floor_number ?? room?.room_floor ?? "";
        const match = String(rawFloor).match(/\b([1-5])(?:st|nd|rd|th|f)?\b/i);
        return match ? Number(match[1]) : "";
    }

    const FOCAL_POINTS = {
        // "halls/1": "center 40%",
        // "rooms/5": "center 30%",
    };

    /* -- Helpers ------------------------------------------------------- */
    function escapeHtml(value) {
        return String(value ?? "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#39;");
    }

    function formatTime(timeStr) {
        if (!timeStr) return "";
        const [h, m] = timeStr.split(":");
        const hour = parseInt(h, 10);
        return `${hour % 12 || 12}:${m} ${hour >= 12 ? "PM" : "AM"}`;
    }

    /* Photo block returning modern 16:9 .card-media frame with fallback & location tag */
    function photoBlock(src, alt, icon, key, placeholder, badgeHtml, locationTag) {
        const focal = FOCAL_POINTS[key] ? ` style="object-position:${FOCAL_POINTS[key]}"` : "";
        return `<div class="card-media">
                    <div class="photo-fallback" aria-hidden="true"><i class="fas ${icon}"></i></div>
                    <img class="card-photo" src="${src}" alt="${escapeHtml(alt)}" loading="lazy"${focal} data-placeholder="${escapeHtml(placeholder || "")}">
                    <div class="card-media-scrim"></div>
                    ${locationTag ? `<span class="building-loc-tag">${escapeHtml(locationTag)}</span>` : ""}
                    ${badgeHtml || ""}
                </div>`;
    }

    function wireImageFallbacks(root) {
        root.querySelectorAll("img.card-photo").forEach(img => {
            img.addEventListener("error", function handleImageError() {
                const placeholder = img.dataset.placeholder;
                if (placeholder && !img.dataset.fallbackUsed) {
                    img.dataset.fallbackUsed = "1";
                    img.src = placeholder;
                    return;
                }
                img.style.display = "none";
                img.removeEventListener("error", handleImageError);
            });
        });
    }

    /* -- Stats --------------------------------------------------------- */
    function loadStats() {
        fetch("../controllers/user_stats.php")
            .then(r => r.json())
            .then(res => {
                if (!res.success) return;
                statsData = res;
                const activeEl    = document.getElementById("stat-active");
                const pendingEl   = document.getElementById("stat-pending");
                const availableEl = document.getElementById("stat-available");
                if (activeEl)    activeEl.textContent    = res.active;
                if (pendingEl)   pendingEl.textContent   = res.pending;
                if (availableEl) availableEl.textContent = res.available;
            })
            .catch(() => {});
    }

    function loadNotifBadge() {
        const badge = document.getElementById("notif-badge");
        if (!badge) return;

        fetch("../controllers/notifications_controller.php")
            .then(r => r.json())
            .then(res => {
                if (!res.success || !res.unread) return;
                badge.textContent   = res.unread > 9 ? "9+" : res.unread;
                badge.style.display = "inline-block";
            })
            .catch(() => {});
    }

    const BUILDING_FILTERS = {
        plaridel: { aliases: ["plaridel"], title: "Plaridel Hall" },
        melchora: { aliases: ["melchora"], title: "Melchora Aquino Hall" },
        gym: { aliases: ["gym"], title: "FCPC Gymnasium" },
        hotel: { aliases: ["hotel"], title: "FCPC Star Hotel" },
        balagtas: { aliases: ["balagtas", "bh"], title: "Balagtas Hall" },
        rizal: { aliases: ["rizal"], title: "Rizal Hall" },
        "blas-ople": { aliases: ["blas ople", "blas-ople", "ople"], title: "Blas Ople Hall" }
    };

    function roomMatchesBuilding(room, key) {
        const config = BUILDING_FILTERS[key];
        if (!config) return false;
        const hall = String(room?.hall_name || "").trim().toLowerCase().replace(/[^a-z0-9]+/g, " ").trim();
        const roomCode = String(room?.room_name || "").match(/\bOH[\s-]?\d{3}\b/i);
        if (key === "blas-ople" && roomCode) return true;
        return config.aliases.some(alias => {
            if (alias === "bh") return hall === "bh" || hall.startsWith("bh ");
            return hall.includes(alias.replace(/-/g, " "));
        });
    }

    function starHotelFloorLabel(floor) {
        const normalizedFloor = Number(floor);
        if (normalizedFloor === 1) return "Reception Desk";
        if (normalizedFloor === 3) return "Star Hotel";
        return null;
    }

    function updateBuildingCounts(rooms) {
        const list = rooms || allRoomsCache;
        const elAll = document.getElementById("count-all");
        if (elAll) elAll.textContent = list.length;

        Object.keys(BUILDING_FILTERS).forEach(key => {
            const count = list.filter(room => roomMatchesBuilding(room, key)).length;
            const badge = document.getElementById(`count-${key}`);
            if (badge) badge.textContent = count;
        });

        // Occupancy calculation
        const occupied = list.filter(r => {
            const s = (r.computed_status || "").toLowerCase();
            return s !== "available" && s !== "vacant";
        }).length;
        const occupancyRate = list.length > 0 ? Math.round((occupied / list.length) * 100) : 0;

        const elOcc  = document.getElementById("stat-occupancy");
        const elFill = document.getElementById("occupancy-fill");
        if (elOcc)  elOcc.textContent  = `${occupancyRate}%`;
        if (elFill) elFill.style.width = `${occupancyRate}%`;
    }

    /* -- Building Filter Pills ----------------------------------------- */
    function filterByBuilding(buildingKey) {
        currentBuilding = buildingKey || "all";

        document.querySelectorAll("#building-filter-pills .pill-tab").forEach(tab => {
            tab.classList.toggle("active", tab.dataset.building === currentBuilding);
        });

        let filtered = allRoomsCache;
        if (currentBuilding !== "all") {
            filtered = filtered.filter(room => roomMatchesBuilding(room, currentBuilding));
        }

        if (vacantOnly) {
            filtered = filtered.filter(r => {
                const status = (r.computed_status || "").toLowerCase();
                return status === "available" || status === "vacant";
            });
        }

        const title = currentBuilding === "all"
            ? "Campus Rooms & Spaces"
            : (BUILDING_FILTERS[currentBuilding]?.title || "Campus Rooms");
        updateSectionTitle(title, `Showing ${filtered.length} space(s)`);
        showRoomsView();
        renderRooms(filtered);
    }

    /* -- Stat-card filter ---------------------------------------------- */
    function filterRooms(type) {
        if (currentFilter === type) {
            currentFilter = null;
            clearStatFilter();
            filterByBuilding(currentBuilding);
            return;
        }

        currentFilter = type;
        showRoomsView();
        document.querySelectorAll(".stat-card").forEach(c => c.classList.remove("active-filter"));
        const cardTarget = document.getElementById("card-" + type);
        if (cardTarget) cardTarget.classList.add("active-filter");

        let filtered = [];
        let title    = "";
        let label    = "";

        if (type === "available") {
            filtered = allRoomsCache.filter(r => r.computed_status === "available" || r.computed_status === "vacant");
            title    = "Available Rooms";
            label    = `Showing ${filtered.length} available room(s)`;
        } else if (type === "active") {
            filtered = allRoomsCache.filter(r => r.is_my_active);
            title    = "My Active Reservations";
            label    = `Showing ${filtered.length} room(s) with your active reservations`;
        } else if (type === "pending") {
            filtered = allRoomsCache.filter(r => r.has_pending);
            title    = "Rooms with Pending Requests";
            label    = `Showing ${filtered.length} room(s) with pending requests`;
        }

        updateSectionTitle(title, label);
        renderRooms(filtered);
    }

    function showRoomsView() {
        const hallsGrid = document.getElementById("halls-grid");
        const roomsGrid = document.getElementById("rooms-grid");
        const backBtn   = document.getElementById("back-to-halls");
        if (hallsGrid) hallsGrid.style.display = "none";
        if (roomsGrid) roomsGrid.style.display = "grid";
        if (backBtn)   backBtn.style.display   = "none";
    }

    function clearStatFilter() {
        document.querySelectorAll(".stat-card").forEach(c => c.classList.remove("active-filter"));
    }

    function updateSectionTitle(title, label) {
        const titleEl = document.getElementById("rooms-section-title");
        const labelEl = document.getElementById("filter-label");
        if (titleEl) titleEl.textContent = title;
        if (labelEl) labelEl.textContent = label;
    }

    /* -- Room card renderer -------------------------------------------- */
    function floorFromRoomName(roomName) {
        const name = String(roomName ?? "");
        const explicitFloor = name.match(/\b([1-5])(?:st|nd|rd|th)?\s*(?:floor|f)\b/i);
        if (explicitFloor) return Number(explicitFloor[1]);
        const match = name.match(/(\d{3})\b/);
        if (!match) return null;
        const number = Number(match[1]);
        const floor = Math.floor(number / 100);
        return floor >= 1 && floor <= 5 ? floor : null;
    }

    function roomFloorForDisplay(room) {
        const normalizedRoomLabel = `${room?.room_name ?? ""} ${room?.room_type ?? ""}`
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, " ")
            .trim();

        if (roomMatchesBuilding(room, "plaridel")) {
            if (normalizedRoomLabel.includes("cisco lab")) return 1;
            if (normalizedRoomLabel.includes("function hall")) return 5;
        }
        if (roomMatchesBuilding(room, "hotel")) {
            if (normalizedRoomLabel.includes("reception desk")) return 1;
            if (normalizedRoomLabel.includes("star hotel")) return 3;
        }
        if (roomMatchesBuilding(room, "balagtas")) {
            if (normalizedRoomLabel.includes("broadcasting room")) return 2;
            if (normalizedRoomLabel.includes("cultural hall")) return 5;
        }

        return roomFloor(room) || floorFromRoomName(room?.room_name);
    }

    function roomNumber(roomName) {
        const match = String(roomName ?? "").match(/(\d{3})\b/);
        return match ? Number(match[1]) : Number.POSITIVE_INFINITY;
    }

    function renderRoomCard(room, target) {
        const status = (room.computed_status || "unknown").toLowerCase();
        const isAvailable = status === "available" || status === "vacant";
        const isMaintenance = status === "maintenance";
        const isInSession = status === "unavailable" && Boolean(room.conflict_start);
        const edgeStatusClass = roomStatusClass(status);
        const dataStatus = isAvailable ? "vacant" : isMaintenance ? "maintenance" : "busy";
        const floor = roomFloorForDisplay(room);

        const timeRange = isInSession
            ? ` (${formatTime(room.conflict_start)} - ${formatTime(room.conflict_end)})`
            : "";
        const statusLabel = isAvailable ? "AVAILABLE" : isMaintenance ? "MAINTENANCE" : isInSession ? "IN SESSION" : "UNAVAILABLE";
        const statusPillClass = isAvailable ? "vacant" : isMaintenance ? "maintenance" : isInSession ? "in-session" : "unavailable";
        const statusIcon = isAvailable ? "fa-circle-check" : isMaintenance ? "fa-screwdriver-wrench" : isInSession ? "fa-clock" : "fa-ban";

        const statusPill = `<span class="status-pill ${statusPillClass}">` +
            `<i class="fas ${statusIcon}" aria-hidden="true"></i> ` +
            `${statusLabel}${escapeHtml(timeRange)}</span>`;

        const activeBadge = room.is_my_active
            ? `<span class="active-user-badge"><i class="fas fa-user-check"></i> Your Active Booking</span>`
            : "";
        const pendingBadge = room.has_pending
            ? `<span class="active-user-badge" style="background:#D97706;"><i class="fas fa-hourglass-half"></i> Pending Approval</span>`
            : "";

        const acChip = room.room_has_ac == 1
            ? `<span class="amenity-tag"><i class="fas fa-snowflake"></i> Air-conditioned</span>`
            : `<span class="amenity-tag"><i class="fas fa-wind"></i> Standard Airflow</span>`;

        const showAvailabilityDetail = isAvailable || isInSession;
        const windowLabel = isAvailable ? "Free until" : "Session ends";
        const windowTime  = isAvailable
            ? (room.next_conflict_start ? formatTime(room.next_conflict_start) : "Open all day")
            : isInSession ? formatTime(room.conflict_end) : "";

        const scheduleHref = `reserve_view.php?hall_id=${encodeURIComponent(room.hall_id)}&room_id=${encodeURIComponent(room.room_id)}`;

        const card = document.createElement("div");
        card.className = `room-card ${edgeStatusClass}${room.is_my_active ? ' user-active-space' : ''}`;
        card.dataset.status = dataStatus;
        if (floor !== "" && floor !== null) card.dataset.floor = String(floor);

        card.innerHTML = `
                <div class="card-media">
                    <div class="photo-fallback" aria-hidden="true"><i class="fas fa-door-open"></i></div>
                    <img class="card-photo" src="${roomPhoto(room)}" alt="${escapeHtml(room.room_name)}" loading="lazy" data-placeholder="${escapeHtml(PLACEHOLDER_IMAGE)}">
                    <div class="card-media-scrim"></div>
                    ${activeBadge || pendingBadge}
                    ${statusPill}
            </div>
            <div class="card-content">
                <div class="room-title-row">
                    <h3 class="room-code">${escapeHtml(room.room_name)}</h3>
                    <span class="room-capacity"><i class="fas fa-users" aria-hidden="true"></i> ${escapeHtml(room.room_capacity)} seats</span>
                </div>
                <div class="room-type-name">${escapeHtml(room.room_type || 'Lecture Space')}</div>
                <div class="amenities-list">
                    ${acChip}
                </div>
                ${showAvailabilityDetail ? `<div class="schedule-window-bar">
                    <span class="window-label">${windowLabel}</span>
                    <span class="window-time">${windowTime}</span>
                </div>` : ''}
                <div class="card-actions-row">
                    <a href="${scheduleHref}" class="btn-view-schedule" title="Full schedule and multi-room booking">
                        <i class="far fa-calendar-days"></i> Schedule
                    </a>
                    <button type="button" class="btn-card-reserve ${!isAvailable ? 'btn-disabled' : ''}"
                        data-hall-id="${escapeHtml(room.hall_id)}"
                        data-room-id="${escapeHtml(room.room_id)}"
                        aria-label="${isAvailable ? `Quick Reserve ${escapeHtml(room.room_name)}` : `${statusLabel}: ${escapeHtml(room.room_name)}`}"
                        title="${isAvailable ? 'Quick Reserve' : statusLabel}"
                        ${!isAvailable ? 'disabled' : ''}>
                        ${isAvailable ? '<i class="fas fa-bolt" aria-hidden="true"></i> Reserve' : isMaintenance ? 'Maintenance' : isInSession ? 'Occupied' : 'Unavailable'}
                    </button>
                </div>
            </div>
        `;

        wireImageFallbacks(card);

        const reserveBtn = card.querySelector(".btn-card-reserve");
        if (reserveBtn && isAvailable) {
            reserveBtn.addEventListener("click", () => openQuickReserveDrawer(room));
        }

        target.appendChild(card);
    }

    function createFloorRail(grid, label) {
        const rail = document.createElement("div");
        rail.className = "floor-rail";

        const previous = document.createElement("button");
        previous.type = "button";
        previous.className = "floor-rail-arrow floor-rail-arrow-prev";
        previous.setAttribute("aria-label", `Scroll ${label} rooms left`);
        previous.innerHTML = '<i class="fas fa-chevron-left" aria-hidden="true"></i>';
        previous.hidden = true;

        const next = document.createElement("button");
        next.type = "button";
        next.className = "floor-rail-arrow floor-rail-arrow-next";
        next.setAttribute("aria-label", `Scroll ${label} rooms right`);
        next.innerHTML = '<i class="fas fa-chevron-right" aria-hidden="true"></i>';
        next.hidden = true;

        const syncButtons = () => {
            const maxScroll = Math.max(0, grid.scrollWidth - grid.clientWidth);
            const hasOverflow = maxScroll > 2;
            rail.classList.toggle("has-overflow", hasOverflow);
            previous.hidden = !hasOverflow;
            next.hidden = !hasOverflow;
            previous.disabled = grid.scrollLeft <= 2;
            next.disabled = grid.scrollLeft >= maxScroll - 2;
        };

        previous.addEventListener("click", () => grid.scrollBy({ left: -grid.clientWidth * 0.85, behavior: "smooth" }));
        next.addEventListener("click", () => grid.scrollBy({ left: grid.clientWidth * 0.85, behavior: "smooth" }));
        grid.addEventListener("scroll", syncButtons, { passive: true });
        rail._syncFloorRail = syncButtons;
        rail.append(previous, grid, next);
        requestAnimationFrame(syncButtons);
        return rail;
    }

    function syncFloorRailControls() {
        document.querySelectorAll(".floor-rail").forEach(rail => rail._syncFloorRail?.());
    }
    window.addEventListener("resize", syncFloorRailControls, { passive: true });

    function renderRooms(rooms) {
        const grid = document.getElementById("rooms-grid");
        if (!grid) return;
        grid.innerHTML = "";
        grid.classList.remove("rooms-grouped");

        if (!rooms || rooms.length === 0) {
            grid.innerHTML = `<p class="rooms-empty-msg">No rooms found matching your criteria.</p>`;
            return;
        }

        const floorGroups = new Map();
        const ungrouped = [];

        rooms.forEach((room, index) => {
            const floor = roomFloorForDisplay(room);
            if (floor === "" || floor === null) {
                ungrouped.push({ room, index });
                return;
            }
            if (!floorGroups.has(Number(floor))) floorGroups.set(Number(floor), []);
            floorGroups.get(Number(floor)).push({ room, index });
        });

        const sortedFloors = [...floorGroups.keys()].sort((a, b) => a - b);
        if (sortedFloors.length <= 1) {
            rooms.forEach(room => renderRoomCard(room, grid));
            return;
        }

        grid.classList.add("rooms-grouped");
        sortedFloors.forEach(floor => {
            const section = document.createElement("section");
            section.className = "floor-section";
            const headingId = `floor-heading-${floor}`;
            const roomsId = `floor-rooms-${floor}`;
            const suffix = floor === 1 ? "st" : floor === 2 ? "nd" : floor === 3 ? "rd" : "th";
            const floorRooms = floorGroups.get(floor);
            const isStarHotelOnlyGroup = floorRooms.every(item => roomMatchesBuilding(item.room, "hotel"));
            const headingLabel = isStarHotelOnlyGroup ? starHotelFloorLabel(floor) : null;
            const roomCount = floorRooms.length;
            section.setAttribute("aria-labelledby", headingId);
            const floorButton = document.createElement("h3");
            floorButton.className = "floor-heading";
            floorButton.id = headingId;
            floorButton.innerHTML = `<span>${headingLabel || `${floor}${suffix} Floor`}</span><span class="floor-room-count">${roomCount} ${roomCount === 1 ? "Room" : "Rooms"}</span>`;
            const floorGrid = document.createElement("div");
            floorGrid.className = "floor-rooms-grid";
            floorGrid.id = roomsId;
            floorGrid.setAttribute("aria-label", `${headingLabel || `${floor}${suffix} Floor`} rooms`);
            floorGroups.get(floor)
                .sort((a, b) => roomNumber(a.room.room_name) - roomNumber(b.room.room_name) || a.index - b.index)
                .forEach(item => renderRoomCard(item.room, floorGrid));
            section.appendChild(floorButton);
            section.appendChild(createFloorRail(floorGrid, headingLabel || `${floor}${suffix} Floor`));
            grid.appendChild(section);
        });

        if (ungrouped.length) {
            const section = document.createElement("section");
            section.className = "floor-section floor-section-other";
            section.setAttribute("aria-labelledby", "floor-heading-other");
            section.innerHTML = `<h3 class="floor-heading" id="floor-heading-other"><span>Other Rooms / Facilities</span><span class="floor-room-count">${ungrouped.length} ${ungrouped.length === 1 ? "Room" : "Rooms"}</span></h3>`;
            const otherGrid = document.createElement("div");
            otherGrid.className = "floor-rooms-grid";
            ungrouped.sort((a, b) => a.index - b.index)
                .forEach(item => renderRoomCard(item.room, otherGrid));
            section.appendChild(createFloorRail(otherGrid, "Other Rooms / Facilities"));
            grid.appendChild(section);
        }
    }

    /* -- Hall tiles (Preserved for compatibility) ---------------------- */
    function summarizeHalls(rooms) {
        const map = new Map();
        rooms.forEach(room => {
            if (!map.has(room.hall_id)) {
                map.set(room.hall_id, {
                    hall_id:   room.hall_id,
                    hall_name: room.hall_name,
                    total:     0,
                    available: 0
                });
            }
            const entry = map.get(room.hall_id);
            entry.total++;
            if (room.computed_status?.toLowerCase() === "available") entry.available++;
        });
        return [...map.values()].sort((a, b) => a.hall_name.localeCompare(b.hall_name));
    }

    function renderHallTiles(rooms) {
        const hallsGrid = document.getElementById("halls-grid");
        const roomsGrid = document.getElementById("rooms-grid");
        const backBtn   = document.getElementById("back-to-halls");

        if (hallsGrid) hallsGrid.style.display = "grid";
        if (roomsGrid) roomsGrid.style.display = "none";
        if (backBtn)   backBtn.style.display   = "none";
        updateSectionTitle("Browse by Building", "");

        const halls = summarizeHalls(rooms);
        if (hallsGrid) hallsGrid.innerHTML = "";

        halls.forEach(hall => {
            const tile = document.createElement("div");
            tile.classList.add("hall-tile");
            tile.setAttribute("tabindex", "0");
            tile.setAttribute("role", "button");
            tile.setAttribute("aria-label", `View rooms in ${hall.hall_name}`);
            tile.innerHTML = `
                ${photoBlock(hallPhoto(hall), hall.hall_name, "fa-building", "halls/" + hall.hall_id, hallPlaceholder(hall), "")}
                <div class="hall-info">
                    <h3>${escapeHtml(hall.hall_name)}</h3>
                    <p>${hall.total} room(s) in this building</p>
                    <p class="hall-meta">
                        <i class="fas fa-door-open"></i>
                        <span class="status-available">${hall.available} available</span>
                    </p>
                    <button class="btn-reserve">View Rooms</button>
                </div>
            `;
            wireImageFallbacks(tile);
            const openHall = () => showHallRooms(hall.hall_id, hall.hall_name);
            tile.addEventListener("click", openHall);
            tile.addEventListener("keydown", event => {
                if (event.key === "Enter" || event.key === " ") {
                    event.preventDefault();
                    openHall();
                }
            });
            if (hallsGrid) hallsGrid.appendChild(tile);
        });
    }

    function showHallRooms(hallId, hallName) {
        const hallsGrid = document.getElementById("halls-grid");
        const roomsGrid = document.getElementById("rooms-grid");
        const backBtn   = document.getElementById("back-to-halls");

        if (hallsGrid) hallsGrid.style.display = "none";
        if (roomsGrid) roomsGrid.style.display = "grid";
        if (backBtn)   backBtn.style.display   = "inline-flex";

        const filtered = allRoomsCache.filter(r => r.hall_id === hallId);
        updateSectionTitle(hallName, `${filtered.length} room(s)`);
        renderRooms(filtered);
    }

    function showHallsView() {
        currentFilter = null;
        clearStatFilter();
        renderHallTiles(allRoomsCache);
    }

    /* -- Search -------------------------------------------------------- */
    function runSearch() {
        if (!input || !resultsBox) return;
        const query = input.value.trim();
        if (query.length === 0) { resultsBox.innerHTML = ""; return; }

        fetch("../controllers/search_controller.php?q=" + encodeURIComponent(query))
            .then(res => res.json())
            .then(res => {
                resultsBox.innerHTML = "";
                if (!res.success || res.data.length === 0) {
                    resultsBox.innerHTML = `<div class="search-item">No results found</div>`;
                    return;
                }
                res.data.forEach(item => {
                    const display = item.type === "hall"
                        ? `<strong>${escapeHtml(item.name)}</strong> <span>(Building)</span>`
                        : `<strong>${escapeHtml(item.room_name)}</strong> <span>(${escapeHtml(item.name)})</span>`;

                    const div = document.createElement("div");
                    div.classList.add("search-item");
                    div.setAttribute("role", "option");
                    div.setAttribute("tabindex", "0");
                    div.innerHTML = display;
                    const selectResult = () => {
                        input.value          = item.type === "hall" ? item.name : item.room_name;
                        resultsBox.innerHTML = "";
                        loadFeaturedRooms(item.type, item.id);
                    };
                    div.addEventListener("click", selectResult);
                    div.addEventListener("keydown", event => {
                        if (event.key === "Enter" || event.key === " ") {
                            event.preventDefault();
                            selectResult();
                        }
                    });
                    resultsBox.appendChild(div);
                });
            })
            .catch(() => {});
    }

    function triggerSearch() {
        if (!input || !resultsBox) return;
        const query = input.value.trim();
        if (query.length === 0) return;

        resultsBox.innerHTML = "";
        showRoomsView();
        const grid = document.getElementById("rooms-grid");
        if (grid) grid.innerHTML = `<p class="rooms-loading-msg">Searching campus rooms...</p>`;
        currentFilter = null;
        clearStatFilter();
        updateSectionTitle("Search Results", "");

        fetch("../controllers/search_controller.php?q=" + encodeURIComponent(query))
            .then(res => res.json())
            .then(res => {
                if (!res.success || res.data.length === 0) {
                    if (grid) grid.innerHTML = `<p class="rooms-empty-msg">No results found for "${escapeHtml(query)}".</p>`;
                    return;
                }

                const fetches = res.data.map(item =>
                    fetch(`../controllers/room_controller.php?action=get_featured&type=${item.type}&id=${item.id}`)
                        .then(r => r.json())
                        .then(r => r.success ? r.data : [])
                );

                Promise.all(fetches).then(results => {
                    const seen   = new Set();
                    const merged = [];
                    results.flat().forEach(room => {
                        if (!seen.has(room.room_id)) {
                            seen.add(room.room_id);
                            merged.push(room);
                        }
                    });

                    if (merged.length === 0) {
                        if (grid) grid.innerHTML = `<p class="rooms-empty-msg">No rooms found for "${escapeHtml(query)}".</p>`;
                        return;
                    }

                    updateSectionTitle(`Results for "${query}"`, `${merged.length} space(s) found`);
                    renderRooms(merged);
                });
            })
            .catch(() => {
                if (grid) grid.innerHTML = `<p class="rooms-empty-msg">Search failed. Please try again.</p>`;
            });
    }

    if (input) {
        input.addEventListener("keyup", e => {
            if (e.key === "Enter") { triggerSearch(); }
            else                   { runSearch(); }
        });
    }

    if (searchBtn) {
        searchBtn.addEventListener("click", triggerSearch);
    }

    document.addEventListener("keydown", function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === "k") {
            e.preventDefault();
            if (input) input.focus();
        }
    });

    document.addEventListener("click", function (e) {
        const bar = document.querySelector(".search-bar");
        if (bar && resultsBox && !bar.contains(e.target)) {
            resultsBox.innerHTML = "";
        }
    });

    /* -- Load rooms for a single chosen building/room ------------------ */
    function loadFeaturedRooms(type, id) {
        showRoomsView();
        const grid = document.getElementById("rooms-grid");
        if (grid) grid.innerHTML = `<p class="rooms-loading-msg">Loading...</p>`;
        currentFilter = null;
        clearStatFilter();
        updateSectionTitle("Rooms", "");

        fetch(`../controllers/room_controller.php?action=get_featured&type=${type}&id=${id}`)
            .then(res => res.json())
            .then(res => {
                if (!res.success || res.data.length === 0) {
                    if (grid) grid.innerHTML = `<p class="rooms-empty-msg">No rooms found.</p>`;
                    return;
                }
                const featuredRooms = res.data;
                updateSectionTitle(
                    `${type === "hall" ? res.data[0].hall_name : res.data[0].room_name} - Rooms`,
                    ""
                );
                renderRooms(featuredRooms);
            })
            .catch(() => {});
    }

    /* -- Quick Reserve Drawer Interactions ----------------------------- */
    function openQuickReserveDrawer(room) {
        const drawer   = document.getElementById("quick-reserve-drawer");
        const backdrop = document.getElementById("drawer-backdrop");
        if (!drawer || !backdrop) return;

        const titleEl    = document.getElementById("drawer-title");
        const subtitleEl = document.getElementById("drawer-room-subtitle");
        const roomIdEl   = document.getElementById("drawer-room-id");
        const hallIdEl   = document.getElementById("drawer-hall-id");
        const dateEl     = document.getElementById("drawer-date");
        const advLink    = document.getElementById("drawer-advanced-link");

        if (titleEl)    titleEl.textContent    = `Quick Reserve: ${room.room_name || 'Room'}`;
        if (subtitleEl) subtitleEl.textContent = `${room.hall_name || 'Campus'} • Capacity: ${room.room_capacity || 0} seats`;
        if (roomIdEl)   roomIdEl.value         = room.room_id;
        if (hallIdEl)   hallIdEl.value         = room.hall_id;

        if (dateEl) {
            const today = new Date().toISOString().split("T")[0];
            dateEl.min = today;
            if (!dateEl.value) {
                dateEl.value = today;
            }
        }

        if (advLink) {
            advLink.href = `reserve_view.php?hall_id=${encodeURIComponent(room.hall_id)}&room_id=${encodeURIComponent(room.room_id)}`;
        }

        drawer.classList.add("drawer-open");
        drawer.setAttribute("aria-hidden", "false");
        backdrop.classList.add("active");
        document.body.style.overflow = "hidden";
    }

    function closeQuickReserveDrawer() {
        const drawer   = document.getElementById("quick-reserve-drawer");
        const backdrop = document.getElementById("drawer-backdrop");
        if (!drawer || !backdrop) return;

        drawer.classList.remove("drawer-open");
        drawer.setAttribute("aria-hidden", "true");
        backdrop.classList.remove("active");
        document.body.style.overflow = "";
    }

    function initQuickReserveDrawer() {
        const closeBtn = document.getElementById("drawer-close-btn");
        const backdrop = document.getElementById("drawer-backdrop");
        const form     = document.getElementById("drawer-reserve-form");

        if (closeBtn) closeBtn.addEventListener("click", closeQuickReserveDrawer);
        if (backdrop) backdrop.addEventListener("click", closeQuickReserveDrawer);

        document.addEventListener("keydown", (e) => {
            if (e.key === "Escape") {
                const drawer = document.getElementById("quick-reserve-drawer");
                if (drawer && drawer.classList.contains("drawer-open")) {
                    closeQuickReserveDrawer();
                }
            }
        });

        if (form) {
            form.addEventListener("submit", (e) => {
                e.preventDefault();
                const submitBtn = document.getElementById("drawer-submit-btn");
                const roomId    = document.getElementById("drawer-room-id")?.value;
                const date      = document.getElementById("drawer-date")?.value;
                const startTime = document.getElementById("drawer-start-time")?.value;
                const endTime   = document.getElementById("drawer-end-time")?.value;
                const purpose   = document.getElementById("drawer-purpose")?.value;

                if (!roomId || !date || !startTime || !endTime) {
                    window.toast.show("Please fill out all required fields.", "error", 3000);
                    return;
                }

                if (submitBtn) {
                    submitBtn.disabled    = true;
                    submitBtn.textContent = "Reserving...";
                }

                fetch("../controllers/reserve_controller.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({
                        room_id: parseInt(roomId, 10),
                        start: startTime,
                        end: endTime,
                        type: "one-time",
                        date: date,
                        purpose: purpose
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (submitBtn) {
                        submitBtn.disabled    = false;
                        submitBtn.textContent = "Confirm Quick Booking";
                    }

                    if (data.success) {
                        closeQuickReserveDrawer();
                        form.reset();
                        window.toast.show(data.message || "Reservation submitted! Waiting for admin approval.", "success", 4000);
                        if (typeof window.loadNotifBadge === "function") window.loadNotifBadge();
                        loadStats();
                        loadDefaultRooms();
                    } else {
                        const msg = data.message || "Unable to complete reservation due to schedule conflict.";
                        window.toast.show(msg, "error", 4500);
                    }
                })
                .catch(() => {
                    if (submitBtn) {
                        submitBtn.disabled    = false;
                        submitBtn.textContent = "Confirm Quick Booking";
                    }
                    window.toast.show("Server error during booking. Please try again.", "error", 4000);
                });
            });
        }
    }

    /* -- Interactions Init --------------------------------------------- */
    function initDashboardInteractions() {
        document.querySelectorAll(".stat-card[data-filter]").forEach(card => {
            const activate = () => filterRooms(card.dataset.filter);
            card.addEventListener("click", activate);
            card.addEventListener("keydown", event => {
                if (event.key === "Enter" || event.key === " ") {
                    event.preventDefault();
                    activate();
                }
            });
        });

        // Building filter pills
        document.querySelectorAll("#building-filter-pills .pill-tab").forEach(tab => {
            tab.addEventListener("click", () => {
                const building = tab.dataset.building;
                filterByBuilding(building);
            });
        });

        // Vacant only toggle
        const vacantToggle = document.getElementById("toggle-vacant-only");
        if (vacantToggle) {
            vacantToggle.addEventListener("click", () => {
                vacantOnly = !vacantOnly;
                vacantToggle.classList.toggle("active", vacantOnly);
                filterByBuilding(currentBuilding);
            });
        }

        const backButton = document.getElementById("back-to-halls");
        if (backButton) {
            backButton.addEventListener("click", showHallsView);
        }
    }

    /* -- Default Rooms Loader (Direct Collegiate Catalog) -------------- */
    function loadDefaultRooms() {
        const grid = document.getElementById("rooms-grid");
        if (grid) grid.innerHTML = `<p class="rooms-loading-msg">Loading campus rooms...</p>`;

        fetch("../controllers/room_controller.php?action=get_all")
            .then(r => r.json())
            .then(res => {
                if (!res.success || res.data.length === 0) {
                    if (grid) grid.innerHTML = `<p class="rooms-empty-msg">No rooms found.</p>`;
                    return;
                }
                allRoomsCache = res.data;
                updateBuildingCounts(allRoomsCache);
                showRoomsView();
                renderRooms(allRoomsCache);
            })
            .catch(() => {
                if (grid) grid.innerHTML = `<p class="rooms-empty-msg">Failed to load rooms.</p>`;
            });
    }

    initDashboardInteractions();
    initQuickReserveDrawer();
    loadStats();
    loadNotifBadge();
    loadDefaultRooms();

    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get("submitted") === "1" && typeof window.toast !== "undefined") {
        window.toast.show("Reservation submitted! Waiting for admin approval.", "success", 3500);
        history.replaceState({}, "", "user_homepage_view.php");
    }

    setInterval(loadStats, 60000);

    window.filterRooms              = filterRooms;
    window.filterByBuilding         = filterByBuilding;
    window.showHallsView            = showHallsView;
    window.openQuickReserveDrawer   = openQuickReserveDrawer;
    window.closeQuickReserveDrawer  = closeQuickReserveDrawer;
})();
