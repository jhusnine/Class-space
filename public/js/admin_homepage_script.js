function formatTime(t){
    if(!t) return "—";
    const [h,m]=t.split(":");
    const hour=parseInt(h);
    const ampm=hour>=12?"PM":"AM";
    const h12=hour%12||12;
    return `${h12}:${m} ${ampm}`;
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

            if(!res.success||res.data.length===0){
                tbody.innerHTML=`
                    <tr>
                        <td colspan="8" class="pending-empty">
                            No pending requests.
                        </td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML="";

            res.data.forEach((row,i)=>{
                const isRecurring = row.pending_schedule_day_of_week !== null &&
                                    row.pending_schedule_day_of_week !== undefined &&
                                    row.pending_schedule_day_of_week !== "";

                const dayDisplay = isRecurring
                    ? `<span class="pending-recurring">Every ${row.pending_schedule_day_of_week}</span>`
                    : `<span>${row.pending_schedule_day}</span>`;

                const tr=document.createElement("tr");

                tr.innerHTML=`
                    <td class="pending-index">${i+1}</td>
                    <td>
                        <div class="pending-user-name">${row.fname} ${row.lname}</div>
                        <div class="pending-username">${row.username}</div>
                    </td>
                    <td>${row.room_name}</td>
                    <td>${row.hall_name}</td>
                    <td>
                        <span class="pending-type">
                            ${isRecurring ? "Weekly" : "Once"}
                        </span>
                    </td>
                    <td>${dayDisplay}</td>
                    <td>${formatTime(row.pending_schedule_start)} — ${formatTime(row.pending_schedule_end)}</td>
                    <td>
                        <div class="pending-actions">
                            <button class="btn-approve" onclick="handleAction(${row.pending_id},'approve')">
                                <i class="fas fa-check"></i> Approve
                            </button>
                            <button class="btn-reject" onclick="handleAction(${row.pending_id},'reject')">
                                <i class="fas fa-times"></i> Reject
                            </button>
                        </div>
                    </td>
                `;

                tbody.appendChild(tr);
            });
        });
}

function handleAction(pendingId,action){
    fetch("../controllers/admin_action_controller.php",{
        method:"POST",
        headers:{"Content-Type":"application/json"},
        body:JSON.stringify({pending_id:pendingId,action:action})
    })
    .then(r=>r.json())
    .then(res=>{
        if(res.success){
            toast.show(
                action==="approve"?"Request approved!":"Request rejected.",
                action==="approve"?"success":"error",
                2500
            );
            loadPending();
            loadStats();
        }else{
            toast.show(res.message??"Something went wrong.","error",2500);
        }
    });
}

loadStats();
loadPending();