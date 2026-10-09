function formatTime(t) {
    if (!t) return "—";
    const [h, m] = t.split(":");
    const hour = parseInt(h);
    const ampm = hour >= 12 ? "PM" : "AM";
    const h12  = hour % 12 || 12;
    return `${h12}:${m} ${ampm}`;
}

function formatDayDate(row) {
    if (row.specific_date) {
        const d = new Date(row.specific_date + "T00:00:00");
        return d.toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" });
    }
    if (row.day_of_week) {
        return `Every ${row.day_of_week}`;
    }
    return "—";
}

function statusBadgeClass(status) {
    if (status === "Approved") return "status-available";
    if (status === "Pending")  return "status-maintenance";
    if (status === "Rejected") return "status-unavailable";
    return "";
}

// ─── Load halls for the filter dropdown ────────────────────
function loadHalls() {
    fetch("../controllers/room_controller.php?action=get_halls")
        .then(r => r.json())
        .then(res => {
            if (!res.success) return;
            const sel = document.getElementById("filter-hall");
            res.data.forEach(h => {
                const opt = document.createElement("option");
                opt.value = h.hall_id;
                opt.textContent = h.hall_name;
                sel.appendChild(opt);
            });
        });
}

// ─── When hall filter changes, repopulate room filter ──────
function onHallChange() {
    const hallId  = document.getElementById("filter-hall").value;
    const roomSel = document.getElementById("filter-room");
    roomSel.innerHTML = `<option value="">All Rooms</option>`;

    if (!hallId) {
        loadReport();
        return;
    }

    fetch(`../controllers/room_controller.php?action=get_rooms&hall_id=${hallId}`)
        .then(r => r.json())
        .then(res => {
            if (!res.success) return;
            res.data.forEach(room => {
                const opt = document.createElement("option");
                opt.value = room.room_id;
                opt.textContent = room.room_name;
                roomSel.appendChild(opt);
            });
        })
        .finally(() => loadReport());
}

// ─── Fetch the report with current filters ─────────────────
function loadReport() {
    const status = document.getElementById("filter-status").value;
    const hallId = document.getElementById("filter-hall").value;
    const roomId = document.getElementById("filter-room").value;
    const date   = document.getElementById("filter-date").value;

    const params = new URLSearchParams();
    if (status) params.append("status", status);
    if (hallId) params.append("hall_id", hallId);
    if (roomId) params.append("room_id", roomId);
    if (date)   params.append("date", date);

    const tbody = document.getElementById("report-tbody");
    tbody.innerHTML = `
        <tr>
            <td colspan="7" class="loading-cell">
                <div class="spinner spinner-center"></div>
            </td>
        </tr>
    `;

    fetch(`../controllers/admin_report_controller.php?${params.toString()}`)
        .then(r => r.json())
        .then(res => {
            if (!res.success) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" class="loading-cell">
                            Failed to load report. ${res.message ? "(" + res.message + ")" : ""}
                        </td>
                    </tr>
                `;
                document.getElementById("report-count").textContent = "Could not load report.";
                return;
            }

            const data = res.data;
            document.getElementById("report-count").textContent =
                `Showing ${data.length} record(s)`;

            if (data.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" class="loading-cell">
                            No reservation records match the selected filters.
                        </td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML = "";
            data.forEach((row, i) => {
                const tr = document.createElement("tr");
                tr.innerHTML = `
                    <td>${i + 1}</td>
                    <td>${row.fname} ${row.lname} <span style="opacity:.6;">(${row.username})</span></td>
                    <td>${row.room_name}</td>
                    <td>${row.hall_name}</td>
                    <td>${formatDayDate(row)}</td>
                    <td>${formatTime(row.start_time)} – ${formatTime(row.end_time)}</td>
                    <td><span class="${statusBadgeClass(row.status)}">${row.status}</span></td>
                `;
                tbody.appendChild(tr);
            });
        })
        .catch(() => {
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="loading-cell">
                        Connection error while loading report.
                    </td>
                </tr>
            `;
            document.getElementById("report-count").textContent = "Connection error.";
        });
}

function clearFilters() {
    document.getElementById("filter-status").value = "";
    document.getElementById("filter-hall").value = "";
    document.getElementById("filter-room").innerHTML = `<option value="">All Rooms</option>`;
    document.getElementById("filter-date").value = "";
    loadReport();
}

loadHalls();
loadReport();