function formatTime(t){
    if(!t) return "—";
    const [h,m]=t.split(":");
    const hour=parseInt(h);
    const ampm=hour>=12?"PM":"AM";
    const h12=hour%12||12;
    return `${h12}:${m} ${ampm}`;
}

function escapeHtml(value){
    return String(value ?? "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function loadStats(){
    fetch("../controllers/admin_stats.php")
        .then(r=>r.json())
        .then(res=>{
            if(!res.success) return;
            document.getElementById("stat-pending").textContent=res.pending;
            document.getElementById("stat-rooms").textContent=res.rooms;
            document.getElementById("stat-schedules").textContent=res.schedules;
        });
}

function loadPending(){
    fetch("../controllers/admin_get_pending.php")
        .then(r=>r.json())
        .then(res=>{
            const tbody=document.getElementById("pending-tbody");
            if(!tbody) return;
            const rows = Array.isArray(res.data) ? res.data : [];

            if(!res.success||rows.length===0){
                tbody.innerHTML=`
                    <tr>
                        <td colspan="9" class="pending-empty">
                            ${res.success ? "No pending requests." : "Unable to load pending requests."}
                        </td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML="";

            rows.forEach((row,i)=>{
                const isRecurring = String(row.pending_schedule_day_of_week ?? "").trim() !== "";

                const dayDisplay = isRecurring
                    ? `<span class="pending-recurring">Every ${escapeHtml(row.pending_schedule_day_of_week)}</span>`
                    : `<span>${escapeHtml(row.pending_schedule_day || "—")}</span>`;

                const tr=document.createElement("tr");

                tr.innerHTML=`
                    <td class="pending-index">${i+1}</td>
                    <td>
                        <div class="pending-user-name">${escapeHtml(row.fname)} ${escapeHtml(row.lname)}</div>
                        <div class="pending-username">${escapeHtml(row.username)}</div>
                    </td>
                    <td>${escapeHtml(row.subject || "—")}</td>
                    <td>${escapeHtml(row.room_name || "—")}</td>
                    <td>${escapeHtml(row.hall_name || "—")}</td>
                    <td>
                        <span class="pending-type">
                            ${isRecurring ? "Weekly" : "Once"}
                        </span>
                    </td>
                    <td>${dayDisplay}</td>
                    <td>${formatTime(row.pending_schedule_start)} — ${formatTime(row.pending_schedule_end)}</td>
                    <td>
                        <div class="pending-actions">
                            <button class="btn-approve" type="button" data-pending-id="${Number(row.pending_id) || 0}" data-pending-action="approve">
                                <i class="fas fa-check"></i> Approve
                            </button>
                            <button class="btn-reject" type="button" data-pending-id="${Number(row.pending_id) || 0}" data-pending-action="reject">
                                <i class="fas fa-times"></i> Reject
                            </button>
                        </div>
                    </td>
                `;

                tbody.appendChild(tr);
            });

            tbody.querySelectorAll("[data-pending-action]").forEach(button => {
                button.addEventListener("click", () => {
                    handleAction(Number(button.dataset.pendingId), button.dataset.pendingAction);
                });
            });
        })
        .catch(()=>{
            const tbody=document.getElementById("pending-tbody");
            if(tbody) tbody.innerHTML=`
                <tr>
                    <td colspan="9" class="pending-empty">Unable to load pending requests.</td>
                </tr>
            `;
        });
}

function handleAction(pendingId,action){
    fetch("../controllers/admin_action_controller.php",{
        method:"POST",
        headers:{"Content-Type":"application/json"},
        credentials:"same-origin",
        body:JSON.stringify({pending_id:pendingId,action:action})
    })
    .then(r=>r.json())
    .then(res=>{
        if(res.success){
            toast.show(
                action==="approve"
                    ? `Request approved. ${Number(res.affected_users || 0)} conflicting request(s) were declined and notified.`
                    : "Request rejected.",
                action==="approve"?"success":"error",
                2500
            );
            loadPending();
            loadStats();
        }else{
            toast.show(res.message??"Something went wrong.","error",2500);
        }
    })
    .catch(()=>toast.show("Unable to update this pending request.","error",2500));
}

loadStats();
loadPending();
