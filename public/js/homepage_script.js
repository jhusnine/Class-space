(function () {
    "use strict";

    /* -- Element lookups (null-safe) ----------------------------------- */
    const input      = document.getElementById("search-input");
    const resultsBox = document.getElementById("search-results");
    const searchBtn  = document.querySelector(".search-btn");

    /* -- State --------------------------------------------------------- */
    let allRoomsCache = [];
    let currentFilter = null;
    let statsData     = {};


    const PLACEHOLDER_IMAGE = "../../public/images/example.jpg";

    function buildingPhoto(hallId) {
        return `../../public/images/halls/${encodeURIComponent(hallId)}.jpg`;
    }

    function hallPhoto(hall) {
        const hallName = String(hall?.hall_name ?? "").trim().toLowerCase();
        const buildingImages = {
            gym: "../../public/images/gym.png",
            hotel: "../../public/images/hotel.jpeg",
            melchora: "../../public/images/melchora.jpg",
            plaridel: "../../public/images/plaridel.jpeg"
        };
        return buildingImages[hallName] || buildingPhoto(hall.hall_id);
    }

    function hallPlaceholder(hall) {
        const hallName = String(hall?.hall_name ?? "").trim().toLowerCase();
        return hallName === "gym" ? "../../public/images/gym.png" : PLACEHOLDER_IMAGE;
    }

    function roomPhoto(room) {
        const hallName = String(room?.hall_name ?? "").trim().toLowerCase();
        const roomName = String(room?.room_name ?? "").trim().toLowerCase();
        return hallName === "gym" || roomName === "gym"
            ? "../../public/images/gym.png"
            : `../../public/images/rooms/${encodeURIComponent(room.room_id)}.jpg`;
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

    /* Photo fallback is wired with addEventListener after rendering. */
    function photoBlock(src, alt, icon, key, placeholder, badgeHtml) {
        const focal = FOCAL_POINTS[key] ? ` style="object-position:${FOCAL_POINTS[key]}"` : "";
        return `<div class="card-photo-wrap">
                    <div class="photo-fallback" aria-hidden="true"><i class="fas ${icon}"></i></div>
                    <img class="card-photo" src="${src}" alt="${escapeHtml(alt)}" loading="lazy"${focal} data-placeholder="${escapeHtml(placeholder || "")}">
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
                document.getElementById("stat-active").textContent    = res.active;
                document.getElementById("stat-pending").textContent   = res.pending;
                document.getElementById("stat-available").textContent = res.available;
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

    /* -- Stat-card filter ---------------------------------------------- */
    function filterRooms(type) {
        if (currentFilter === type) {
            currentFilter = null;
            clearStatFilter();
            showHallsView();
            return;
        }

        currentFilter = type;
        showRoomsView();
        document.querySelectorAll(".stat-card").forEach(c => c.classList.remove("active-filter"));
        document.getElementById("card-" + type).classList.add("active-filter");

        let filtered = [];
        let title    = "";
        let label    = "";

        if (type === "available") {
            filtered = allRoomsCache.filter(r => r.computed_status === "available");
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
        document.getElementById("halls-grid").style.display    = "none";
        document.getElementById("rooms-grid").style.display    = "grid";
        document.getElementById("back-to-halls").style.display = "inline-flex";
    }

    function clearStatFilter() {
        document.querySelectorAll(".stat-card").forEach(c => c.classList.remove("active-filter"));
    }

    function updateSectionTitle(title, label) {
        document.getElementById("rooms-section-title").textContent = title;
        document.getElementById("filter-label").textContent        = label;
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
        const statusClass = {
            available:   "status-available",
            unavailable: "status-unavailable",
            maintenance: "status-maintenance"
        }[status] ?? "status-unknown";
        const edgeStatusClass = roomStatusClass(status);
        const timeRange = status === "unavailable" && room.conflict_start
            ? ` (${formatTime(room.conflict_start)} - ${formatTime(room.conflict_end)})`
            : "";
        const pendingBadge = room.has_pending
            ? `<span class="badge badge-pending">Pending</span>`
            : "";
        const activeBadge = room.is_my_active
            ? `<span class="badge badge-active">Your Reservation</span>`
            : "";
        const acChip = room.room_has_ac == 1
            ? `<span class="spec spec-on"><i class="fas fa-snowflake"></i> Air-conditioned</span>`
            : `<span class="spec spec-off"><i class="fas fa-snowflake"></i> No AC</span>`;
        const card = document.createElement("div");
        card.classList.add("room-card", edgeStatusClass);
        const floor = roomFloor(room) || floorFromRoomName(room.room_name);
        if (floor !== "" && floor !== null) card.dataset.floor = String(floor);
        const statusBadge = `<span class="card-status ${statusClass}">` +
            `${escapeHtml(capitalize(status))}${escapeHtml(timeRange)}</span>`;
        card.innerHTML = `
            ${photoBlock(roomPhoto(room), room.room_name, "fa-door-open", "rooms/" + room.room_id, PLACEHOLDER_IMAGE, statusBadge)}
            <div class="room-info">
                <h3>${escapeHtml(room.room_name)}${activeBadge}${pendingBadge}</h3>
                <p class="room-location">
                    <i class="fas fa-building"></i> ${escapeHtml(room.hall_name)}
                </p>
                <div class="room-specs">
                    <span class="spec"><i class="fas fa-users"></i> ${escapeHtml(room.room_capacity)} seats</span>
                    ${acChip}
                    <span class="spec"><i class="fas fa-tag"></i> ${escapeHtml(room.room_type)}</span>
                </div>
                <button class="btn-reserve" data-hall-id="${escapeHtml(room.hall_id)}" data-room-id="${escapeHtml(room.room_id)}"
                    ${status !== "available" ? "disabled" : ""}>
                    Quick Reserve
                </button>
            </div>
        `;
        wireImageFallbacks(card);
        const reserveButton = card.querySelector(".btn-reserve");
        if (reserveButton && status === "available") {
            reserveButton.addEventListener("click", () => {
                const hallId = encodeURIComponent(reserveButton.dataset.hallId);
                const roomId = encodeURIComponent(reserveButton.dataset.roomId);
                window.location.href = `reserve_view.php?hall_id=${hallId}&room_id=${roomId}`;
            });
        }
        target.appendChild(card);
    }

    function renderRooms(rooms) {
        const grid = document.getElementById("rooms-grid");
        grid.innerHTML = "";
        grid.classList.remove("rooms-grouped");

        if (!rooms || rooms.length === 0) {
            grid.innerHTML = `<p class="rooms-empty-msg">No rooms found for this filter.</p>`;
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
        if (sortedFloors.length === 0) {
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

    /* -- Hall tiles ---------------------------------------------------- */
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

        hallsGrid.style.display = "grid";
        roomsGrid.style.display = "none";
        backBtn.style.display   = "none";
        updateSectionTitle("Browse by Building", "");

        const halls = summarizeHalls(rooms);
        hallsGrid.innerHTML = "";

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
            hallsGrid.appendChild(tile);
        });
    }

    function showHallRooms(hallId, hallName) {
        const hallsGrid = document.getElementById("halls-grid");
        const roomsGrid = document.getElementById("rooms-grid");
        const backBtn   = document.getElementById("back-to-halls");

        hallsGrid.style.display = "none";
        roomsGrid.style.display = "grid";
        backBtn.style.display   = "inline-flex";

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
        const query = input.value.trim();
        if (query.length === 0) return;

        resultsBox.innerHTML = "";
        showRoomsView();
        const grid     = document.getElementById("rooms-grid");
        grid.innerHTML = `<p class="rooms-loading-msg">Loading...</p>`;
        currentFilter  = null;
        clearStatFilter();
        updateSectionTitle("Search Results", "");

        fetch("../controllers/search_controller.php?q=" + encodeURIComponent(query))
            .then(res => res.json())
            .then(res => {
                if (!res.success || res.data.length === 0) {
                    grid.innerHTML = `<p class="rooms-empty-msg">No results found for "${escapeHtml(query)}".</p>`;
                    allRoomsCache  = [];
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
                        grid.innerHTML = `<p class="rooms-empty-msg">No rooms found for "${escapeHtml(query)}".</p>`;
                        allRoomsCache  = [];
                        return;
                    }

                    allRoomsCache = merged;
                    updateSectionTitle(`Results for "${query}"`, `${merged.length} room(s) found`);
                    renderRooms(allRoomsCache);
                });
            })
            .catch(() => {
                grid.innerHTML = `<p class="rooms-empty-msg">Search failed. Please try again.</p>`;
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

    document.addEventListener("click", function (e) {
        const bar = document.querySelector(".search-bar");
        if (bar && resultsBox && !bar.contains(e.target)) {
            resultsBox.innerHTML = "";
        }
    });

    /* -- Load rooms for a single chosen building/room ------------------ */
    function loadFeaturedRooms(type, id) {
        showRoomsView();
        const grid     = document.getElementById("rooms-grid");
        grid.innerHTML = `<p class="rooms-loading-msg">Loading...</p>`;
        currentFilter  = null;
        clearStatFilter();
        updateSectionTitle("Rooms", "");

        fetch(`../controllers/room_controller.php?action=get_featured&type=${type}&id=${id}`)
            .then(res => res.json())
            .then(res => {
                if (!res.success || res.data.length === 0) {
                    grid.innerHTML = `<p class="rooms-empty-msg">No rooms found.</p>`;
                    allRoomsCache  = [];
                    return;
                }
                allRoomsCache = res.data;
                updateSectionTitle(
                    `${type === "hall" ? res.data[0].hall_name : res.data[0].room_name} - Rooms`,
                    ""
                );
                renderRooms(allRoomsCache);
            })
            .catch(() => {});
    }


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

        const backButton = document.getElementById("back-to-halls");
        if (backButton) {
            backButton.addEventListener("click", showHallsView);
        }
    }

    /* -- Init ---------------------------------------------------------- */
    function loadDefaultRooms() {
        const grid = document.getElementById("rooms-grid");
        grid.innerHTML = `<p class="rooms-loading-msg">Loading rooms...</p>`;

        fetch("../controllers/room_controller.php?action=get_all")
            .then(r => r.json())
            .then(res => {
                if (!res.success || res.data.length === 0) {
                    grid.innerHTML = `<p class="rooms-empty-msg">No rooms found.</p>`;
                    return;
                }
                allRoomsCache = res.data;
                renderHallTiles(allRoomsCache);
            })
            .catch(() => {
                grid.innerHTML = `<p class="rooms-empty-msg">Failed to load rooms.</p>`;
            });
    }

    initSharedMobileMenu();
    initDashboardInteractions();
    loadStats();
    loadNotifBadge();
    loadDefaultRooms();

    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get("submitted") === "1" && typeof window.toast !== "undefined") {
        window.toast.show("Reservation submitted! Waiting for admin approval.", "success", 3500);
        history.replaceState({}, "", "user_homepage_view.php");
    }

    setInterval(loadStats,      60000);
    setInterval(loadNotifBadge, 30000);

    window.filterRooms = filterRooms;
    window.showHallsView = showHallsView;
})();
