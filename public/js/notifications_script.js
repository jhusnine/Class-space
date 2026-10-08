const ICONS = {
    approved: 'fa-check',
    pending: 'fa-clock',
    declined: 'fa-times'
};

let allNotifs = [];
let activeTab = 'all';
let searchTerm = '';

function loadNotifications() {
    fetch('../controllers/notifications_controller.php')  
        .then(r => r.json())
        .then(res => {
            if (!res.success) {
                renderError();
                return;
            }
            allNotifs = res.data || [];
            renderList();
        })
        .catch(renderError);
}

function renderList() {
    const list = document.getElementById('notifications-list');

    let items = allNotifs;

    if (activeTab !== 'all') {
        items = items.filter(n => n.notif_type === activeTab);
    }

    if (searchTerm) {
        const q = searchTerm.toLowerCase();
        items = items.filter(n =>
            n.notif_title.toLowerCase().includes(q) ||
            n.notif_message.toLowerCase().includes(q)
        );
    }

    if (!items.length) {
        list.innerHTML = `
            <div class="notif-empty">
                <i class="fas fa-bell-slash notif-empty-icon"></i>
                <div class="notif-empty-title">No notifications</div>
                <div class="notif-empty-text">Nothing to show for the selected filter.</div>
            </div>
        `;
        return;
    }

    list.innerHTML = '';

    items.forEach(n => {
        const isRead = n.notif_is_read == 1;
        const readCls = isRead ? 'read' : '';
        const dot = isRead ? '' : '<span class="notif-unread-dot"></span>';
        const icon = ICONS[n.notif_type] ?? 'fa-info-circle';

        const div = document.createElement('div');
        div.className = `notification-item ${n.notif_type} ${readCls}`;
        div.dataset.id = n.notif_id;

        div.innerHTML = `
            <div class="notif-icon">
                <i class="fas ${icon}"></i>
            </div>

            <div class="notif-body">
                <div class="notif-title">
                    ${dot}
                    ${escapeHtml(n.notif_title)}
                </div>

                <div class="notif-desc">
                    ${escapeHtml(n.notif_message)}
                </div>

                <div class="notif-time">
                    ${formatDate(n.notif_created)}
                </div>
            </div>
        `;

        div.addEventListener('click', () => markRead(n.notif_id, div));
        list.appendChild(div);
    });
}

function renderError() {
    document.getElementById('notifications-list').innerHTML = `
        <div class="notif-empty notif-error">
            <i class="fas fa-exclamation-triangle"></i>
            <div class="notif-empty-title error">Could not load notifications.</div>
        </div>
    `;
}

function setTab(btn, filter) {
    document.querySelectorAll('.notif-tab').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
    activeTab = filter;
    renderList();
}

document.addEventListener('DOMContentLoaded', () => {
    const search = document.getElementById('search-input');
    if (search) {
        search.addEventListener('input', function () {
            searchTerm = this.value.trim();
            renderList();
        });
    }

    loadNotifications();
});

function markRead(notifId, el) {
    if (el.classList.contains('read')) return;

    fetch('../controllers/mark_notif_read.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ notif_id: notifId })
    });

    el.classList.add('read');

    const dot = el.querySelector('.notif-unread-dot');
    if (dot) dot.remove();

    const n = allNotifs.find(x => x.notif_id == notifId);
    if (n) n.notif_is_read = 1;
}

function markAllRead() {
    fetch('../controllers/mark_notif_read.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ notif_id: 'all' })
    })
    .then(() => {
        allNotifs.forEach(n => n.notif_is_read = 1);
        renderList();
        toast.show('All notifications marked as read.', 'success', 2000);
    })
    .catch(() =>
        toast.show('Could not update notifications.', 'error', 2500)
    );
}

function escapeHtml(str) {
    const d = document.createElement('div');
    d.appendChild(document.createTextNode(str ?? ''));
    return d.innerHTML;
}

function formatDate(dt) {
    if (!dt) return '';
    const d = new Date(dt);
    if (isNaN(d)) return dt;

    return d.toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit'
    });
}