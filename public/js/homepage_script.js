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
        plaridel: "../../public/images/plaridel.jpeg",
        balagtas: "../../public/images/balagtas.jpg",
        ople: "../../public/images/ople.jpg",
        rizal: "../../public/images/rizal.jpg",
        conference: "../../public/images/conference.jpg"
    };

    const ROOM_TYPE_IMAGES = {
        "computer lab": "../../public/images/computer_lab.jpg",
        "cisco lab": "../../public/images/computer_lab.jpg",
        "it department": "../../public/images/computer_lab.jpg",
        "ai data center": "../../public/images/computer_lab.jpg",
        "crime lab": "../../public/images/crime_lab.jpg",
        "forensic room": "../../public/images/crime_lab.jpg",
        "firing range room": "../../public/images/crime_lab.jpg",
        "nursing skills": "../../public/images/crime_lab.jpg",
        "lecture": "../../public/images/lecture_hall.jpg",
        "psychology testing r": "../../public/images/lecture_hall.jpg",
        "demo room": "../../public/images/lecture_hall.jpg",
        "engineering": "../../public/images/lecture_hall.jpg",
        "broadcasting room": "../../public/images/lecture_hall.jpg",
        "library": "../../public/images/lecture_hall.jpg",
        "event": "../../public/images/conference.jpg",
        "function hall": "../../public/images/conference.jpg",
        "reception": "../../public/images/hotel.jpeg",
        "office": "../../public/images/hotel.jpeg"
    };

    const SPECIALTY_ROOM_TYPES = {
        "computer lab": "../../public/images/computer_lab.jpg",
        "cisco lab": "../../public/images/computer_lab.jpg",
        "it department": "../../public/images/computer_lab.jpg",
        "ai data center": "../../public/images/computer_lab.jpg",
        "crime lab": "../../public/images/crime_lab.jpg",
        "forensic room": "../../public/images/crime_lab.jpg",
        "firing range room": "../../public/images/crime_lab.jpg",
        "nursing skills": "../../public/images/crime_lab.jpg",
        "event": "../../public/images/conference.jpg",
        "function hall": "../../public/images/conference.jpg",
        "reception": "../../public/images/hotel.jpeg"
    };

    function buildingPhoto(hallId) {
        return PLACEHOLDER_IMAGE;
    }

    function hallPhoto(hall) {
        const hallName = String(hall?.hall_name ?? "").trim().toLowerCase();
        return BUILDING_IMAGES[hallName] || PLACEHOLDER_IMAGE;
    }

    function hallPlaceholder(hall) {
        const hallName = String(hall?.hall_name ?? "").trim().toLowerCase();
        return hallName === "gym" ? "../../public/images/gym.png" : PLACEHOLDER_IMAGE;
    }

    function roomPhoto(room) {
        const hallName = String(room?.hall_name ?? "").trim().toLowerCase();
        const roomName = String(room?.room_name ?? "").trim().toLowerCase();
        const roomType = String(room?.room_type ?? "").trim().toLowerCase();

        if (hallName === "gym" || roomName === "gym") {
            return "../../public/images/gym.png";
        }

        // 1. Specialized Room Type image (labs, tech, forensic, event spaces)
        if (SPECIALTY_ROOM_TYPES[roomType]) {
            return SPECIALTY_ROOM_TYPES[roomType];
        }

        // 2. Building / Hall facade image for general lecture rooms
        if (BUILDING_IMAGES[hallName]) {
            return BUILDING_IMAGES[hallName];
        }

        // 3. Room Type fallback
        if (ROOM_TYPE_IMAGES[roomType]) {
            return ROOM_TYPE_IMAGES[roomType];
        }

        return PLACEHOLDER_IMAGE;
    }

    function roomStatusClass(status) {
        return status === "available" || status === "vacant"
            ? "room-status-available"
            : "room-status-unavailable";
    }

    function roomFloor(room) {
        return room?.floor ?? room?.floor_number ?? room?.room_floor ?? "";
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

    function capitalize(value) {
        const text = String(value ?? "");
        return text.charAt(0).toUpperCase() + text.slice(1);
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

    function updateBuildingCounts(rooms) {
        const list = rooms || allRoomsCache;
        const total = list.length;
        const plaridel = list.filter(r => String(r.hall_name || "").toLowerCase().includes("plaridel")).length;
        const melchora = list.filter(r => String(r.hall_name || "").toLowerCase().includes("melchora")).length;
        const gym      = list.filter(r => String(r.hall_name || "").toLowerCase().includes("gym")).length;
        const hotel    = list.filter(r => String(r.hall_name || "").toLowerCase().includes("hotel")).length;

        const elAll      = document.getElementById("count-all");
        const elPlaridel = document.getElementById("count-plaridel");
        const elMelchora = document.getElementById("count-melchora");
        const elGym      = document.getElementById("count-gym");
        const elHotel    = document.getElementById("count-hotel");

        if (elAll)      elAll.textContent      = total;
        if (elPlaridel) elPlaridel.textContent = plaridel;
        if (elMelchora) elMelchora.textContent = melchora;
        if (elGym)      elGym.textContent      = gym;
        if (elHotel)    elHotel.textContent    = hotel;

        // Occupancy calculation
        const occupied = list.filter(r => {
            const s = (r.computed_status || "").toLowerCase();
            return s !== "available" && s !== "vacant";
        }).length;
        const occupancyRate = total > 0 ? Math.round((occupied / total) * 100) : 0;

        const elOcc  = document.getElementById("stat-occupancy");
        const elFill = document.getElementById("occupancy-fill");
        if (elOcc)  elOcc.textContent  = `${occupancyRate}%`;
        if (elFill) elFill.style.width = `${occupancyRate}%`;
    }

    /* -- Building Filter Pills ----------------------------------------- */
    function filterByBuilding(buildingKey) {
        currentBuilding = buildingKey || "all";

        document.querySelectorAll("#building-filter-pills .pill-tab").forEach(tab => {
            if (tab.dataset.building === currentBuilding) {
                tab.classList.add("active");
            } else {
                tab.classList.remove("active");
            }
        });

        let filtered = allRoomsCache;
        if (currentBuilding !== "all") {
            filtered = filtered.filter(r => {
                const hall = String(r.hall_name || "").toLowerCase();
                return hall.includes(currentBuilding.toLowerCase());
            });
        }

        if (vacantOnly) {
            filtered = filtered.filter(r => {
                const s = (r.computed_status || "").toLowerCase();
                return s === "available" || s === "vacant";
            });
        }

        const titleMap = {
            all:      "Campus Rooms & Spaces",
            plaridel: "Plaridel Hall Spaces",
            melchora: "Melchora Hall Spaces",
            gym:      "Gymnasium & Athletics",
            hotel:    "Training Hotel Suites"
        };

        const title = titleMap[currentBuilding] || "Campus Rooms";
        const label = `Showing ${filtered.length} space(s)`;
        updateSectionTitle(title, label);
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
        const match = String(roomName ?? "").match(/(\d{3})\b/);
        if (!match) return null;
        const number = Number(match[1]);
        const floor = Math.floor(number / 100);
        return floor >= 1 && floor <= 5 ? floor : null;
    }

    function roomNumber(roomName) {
        const match = String(roomName ?? "").match(/(\d{3})\b/);
        return match ? Number(match[1]) : Number.POSITIVE_INFINITY;
    }

    function renderRoomCard(room, target) {
        const status = (room.computed_status || "unknown").toLowerCase();
        const isAvailable = status === "available" || status === "vacant";
        const edgeStatusClass = roomStatusClass(status);
        const dataStatus = isAvailable ? "vacant" : "busy";
        const floor = roomFloor(room) || floorFromRoomName(room.room_name);
        const hallName = (room.hall_name || "Campus").toUpperCase();
        const locationTag = floor ? `${hallName} • ${floor}F` : hallName;

        const timeRange = !isAvailable && room.conflict_start
            ? ` (${formatTime(room.conflict_start)} - ${formatTime(room.conflict_end)})`
            : "";

        const statusPill = `<span class="status-pill ${isAvailable ? 'vacant' : 'in-session'}">` +
            `<i class="fas ${isAvailable ? 'fa-circle-check' : 'fa-clock'}"></i> ` +
            `${isAvailable ? 'VACANT' : 'IN SESSION'}${escapeHtml(timeRange)}</span>`;

        const activeBadge = room.is_my_active
            ? `<span class="active-user-badge"><i class="fas fa-user-check"></i> Your Active Booking</span>`
            : "";
        const pendingBadge = room.has_pending
            ? `<span class="active-user-badge" style="background:#d97706;"><i class="fas fa-hourglass-half"></i> Pending Approval</span>`
            : "";

        const acChip = room.room_has_ac == 1
            ? `<span class="amenity-tag"><i class="fas fa-snowflake"></i> Air-conditioned</span>`
            : `<span class="amenity-tag"><i class="fas fa-wind"></i> Standard Airflow</span>`;

        const windowLabel = isAvailable ? "Free Until" : "In Session Until";
        const windowTime  = isAvailable
            ? (room.next_conflict_start ? formatTime(room.next_conflict_start) : "Open Day")
            : (room.conflict_end ? formatTime(room.conflict_end) : "Pending Schedule");

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
                <span class="building-loc-tag">${escapeHtml(locationTag)}</span>
                ${activeBadge || pendingBadge}
                ${statusPill}
            </div>
            <div class="card-content">
                <div class="room-title-row">
                    <h3 class="room-code">${escapeHtml(room.room_name)}</h3>
                    <span class="room-capacity"><i class="fas fa-users"></i> ${escapeHtml(room.room_capacity)} seats</span>
                </div>
                <div class="room-type-name">${escapeHtml(room.room_type || 'Lecture Space')}</div>
                <div class="amenities-list">
                    ${acChip}
                    <span class="amenity-tag"><i class="fas fa-building"></i> ${escapeHtml(room.hall_name)}</span>
                </div>
                <div class="schedule-window-bar">
                    <span class="window-label">${windowLabel}</span>
                    <span class="window-time">${windowTime}</span>
                </div>
                <div class="card-actions-row">
                    <a href="${scheduleHref}" class="btn-view-schedule" title="Full schedule and multi-room booking">
                        <i class="far fa-calendar-days"></i> Schedule
                    </a>
                    <button type="button" class="btn-card-reserve ${!isAvailable ? 'btn-disabled' : ''}"
                        data-hall-id="${escapeHtml(room.hall_id)}"
                        data-room-id="${escapeHtml(room.room_id)}"
                        ${!isAvailable ? 'disabled' : ''}>
                        ${isAvailable ? '<i class="fas fa-bolt"></i> Quick Reserve' : 'Occupied'}
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
            const floor = roomFloor(room) || floorFromRoomName(room.room_name);
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
            const roomCount = floorGroups.get(floor).length;
            section.setAttribute("aria-labelledby", headingId);
            const floorButton = document.createElement("h3");
            floorButton.className = "floor-heading";
            floorButton.id = headingId;
            floorButton.innerHTML = `<span>${floor}${suffix} Floor</span><span class="floor-room-count">${roomCount} ${roomCount === 1 ? "Room" : "Rooms"}</span>`;
            const floorGrid = document.createElement("div");
            floorGrid.className = "floor-rooms-grid";
            floorGrid.id = roomsId;
            floorGrid.setAttribute("aria-label", `${floor}${suffix} Floor rooms`);
            floorGroups.get(floor)
                .sort((a, b) => roomNumber(a.room.room_name) - roomNumber(b.room.room_name) || a.index - b.index)
                .forEach(item => renderRoomCard(item.room, floorGrid));
            section.appendChild(floorButton);
            section.appendChild(floorGrid);
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
            section.appendChild(otherGrid);
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

    /* -- Mobile Menu --------------------------------------------------- */
    function initSharedMobileMenu() {
        const toggle   = document.getElementById("mobile-menu-toggle");
        const backdrop = document.getElementById("mobile-menu-backdrop");
        const sidebar  = document.getElementById("classspace-sidebar");
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
                    if (typeof window.toast !== "undefined") {
                        window.toast.show("Please fill out all required fields.", "error", 3000);
                    } else {
                        alert("Please fill out all required fields.");
                    }
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
                        type: "single",
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
                        if (typeof window.toast !== "undefined") {
                            window.toast.show(data.message || "Reservation submitted! Waiting for admin approval.", "success", 4000);
                        } else {
                            alert(data.message || "Reservation submitted!");
                        }
                        loadStats();
                        loadDefaultRooms();
                    } else {
                        const msg = data.message || "Unable to complete reservation due to schedule conflict.";
                        if (typeof window.toast !== "undefined") {
                            window.toast.show(msg, "error", 4500);
                        } else {
                            alert(msg);
                        }
                    }
                })
                .catch(() => {
                    if (submitBtn) {
                        submitBtn.disabled    = false;
                        submitBtn.textContent = "Confirm Quick Booking";
                    }
                    if (typeof window.toast !== "undefined") {
                        window.toast.show("Server error during booking. Please try again.", "error", 4000);
                    } else {
                        alert("Server error during booking.");
                    }
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

    initSharedMobileMenu();
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
    setInterval(loadNotifBadge, 30000);

    window.filterRooms              = filterRooms;
    window.filterByBuilding         = filterByBuilding;
    window.showHallsView            = showHallsView;
    window.openQuickReserveDrawer   = openQuickReserveDrawer;
    window.closeQuickReserveDrawer  = closeQuickReserveDrawer;
})();
