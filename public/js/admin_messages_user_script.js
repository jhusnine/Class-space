const CONTROLLER = '../controllers/admin_messages_controller.php';

let selectedUserId   = null;
let selectedUserName = null;
let allUsers         = [];
let adminId          = null;
let searchTimeout    = null;
let threadInterval   = null;
let messageFilter    = 'all';
let messageQuery     = '';
let currentMessages  = [];

document.addEventListener('DOMContentLoaded', () => {
    loadUsers();
    setupSearchHandler();
    setupReplyHandler();


    setInterval(loadUsers, 5000);
});

function loadUsers() {
    fetch(`${CONTROLLER}?action=get_users`)
        .then(res => res.json())
        .then(data => {
            if (!data.success) return;
            allUsers = data.data;
            renderUserList(allUsers);
            updateMessageBadge(data.unreadCount);
        })
        .catch(err => console.error('Error loading users:', err));
}

function renderUserList(users) {
    const container = document.getElementById('usersList');

    if (users.length === 0) {
        container.innerHTML = `
            <div class="loading-spinner">
                <i class="fas fa-inbox"></i>
                <p>No messages yet</p>
            </div>`;
        return;
    }

    const savedScroll = container.scrollTop;

    container.innerHTML = users.map(user => `
        <div class="user-item ${selectedUserId === user.account_id ? 'active' : ''}"
             onclick="selectUser(${user.account_id}, '${escapeHtml(user.account_fname)} ${escapeHtml(user.account_lname)}')">
            <div class="user-name">${escapeHtml(user.account_fname)} ${escapeHtml(user.account_lname)}</div>
            <div class="user-email">${escapeHtml(user.account_email)}</div>
            ${user.unread_count > 0
                ? `<div class="user-unread">${user.unread_count}</div>`
                : ''}
        </div>
    `).join('');

    container.scrollTop = savedScroll;
}

function selectUser(userId, userName) {
    selectedUserId   = userId;
    selectedUserName = userName;

    document.querySelectorAll('.user-item').forEach(el => el.classList.remove('active'));
    if (event && event.currentTarget) event.currentTarget.classList.add('active');

    document.getElementById('threadHeader').innerHTML = `<h2>${escapeHtml(userName)}</h2>`;
    document.getElementById('threadInput').style.display = 'flex';


    messageFilter = 'all';
    messageQuery  = '';
    currentMessages = [];
    setupFilterBar();

    if (threadInterval) clearInterval(threadInterval);
    loadThread(userId);
    threadInterval = setInterval(() => loadThread(userId), 3000);
}

function loadThread(userId) {
    fetch(`${CONTROLLER}?action=get_messages&user_id=${userId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) renderThread(data.data);
        })
        .catch(err => console.error('Error loading thread:', err));
}

function renderThread(messages) {
    currentMessages = messages;
    drawThread(false);
}

function isAdminMessage(msg) {
    return adminId !== null
        ? parseInt(msg.contact_sender) === adminId
        : parseInt(msg.contact_sender) !== parseInt(selectedUserId);
}

function drawThread(forceScroll) {
    const container = document.getElementById('threadMessages');
    const messages  = currentMessages;

    if (messages.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-comment"></i>
                <p>No messages yet</p>
            </div>`;
        return;
    }

    if (adminId === null) {
        const adminMsg = messages.find(m => parseInt(m.contact_sender) !== parseInt(selectedUserId));
        if (adminMsg) adminId = parseInt(adminMsg.contact_sender);
    }


    const q = messageQuery.toLowerCase();
    const visible = messages.filter(msg => {
        const isOwn = isAdminMessage(msg);
        if (messageFilter === 'sent'     && !isOwn) return false;
        if (messageFilter === 'received' &&  isOwn) return false;
        if (q && !String(msg.contact_content).toLowerCase().includes(q)) return false;
        return true;
    });

    if (visible.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-filter"></i>
                <p>No matching messages</p>
            </div>`;
        return;
    }

    const isAtBottom = container.scrollHeight - container.scrollTop <= container.clientHeight + 40;

    const newHtml = visible.map(msg => {
        const isOwn = isAdminMessage(msg);

        const time = new Date(msg.contact_created).toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit'
        });


        return `
            <div class="message ${isOwn ? 'received' : 'sent'}">
                <div class="message-avatar">${isOwn ? 'S' : 'U'}</div>
                <div>
                    <div class="message-bubble">
                        <div class="message-text">${escapeHtml(msg.contact_content)}</div>
                    </div>
                    <div class="message-time">${time}</div>
                </div>
            </div>`;
    }).join('');

    if (container.innerHTML !== newHtml) {
        container.innerHTML = newHtml;
        if (isAtBottom || forceScroll) {
            container.scrollTop = container.scrollHeight;
        }
    }
}



function setupFilterBar() {
    if (document.getElementById('threadFilters')) {

        document.querySelectorAll('#threadFilters .filter-tab').forEach(t =>
            t.classList.toggle('active', t.dataset.filter === 'all'));
        document.getElementById('messageSearch').value = '';
        return;
    }

    const bar = document.createElement('div');
    bar.id = 'threadFilters';
    bar.className = 'thread-filters';
    bar.innerHTML = `
        <div class="filter-tabs">
            <button type="button" class="filter-tab active" data-filter="all">All</button>
            <button type="button" class="filter-tab" data-filter="sent">Sent</button>
            <button type="button" class="filter-tab" data-filter="received">Received</button>
        </div>
        <div class="filter-search">
            <i class="fas fa-search"></i>
            <input type="text" id="messageSearch" placeholder="Search messages" autocomplete="off">
        </div>`;

    const messagesEl = document.getElementById('threadMessages');
    messagesEl.parentNode.insertBefore(bar, messagesEl);

    bar.querySelectorAll('.filter-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            messageFilter = tab.dataset.filter;
            bar.querySelectorAll('.filter-tab').forEach(t => t.classList.toggle('active', t === tab));
            drawThread(true);
        });
    });

    document.getElementById('messageSearch').addEventListener('input', e => {
        messageQuery = e.target.value.trim();
        drawThread(true);
    });
}

function setupReplyHandler() {
    const textarea  = document.getElementById('replyInput');
    const sendBtn   = document.getElementById('replyBtn');
    const charCount = document.getElementById('replyCharCount');

    textarea.addEventListener('input', () => {
        charCount.textContent = textarea.value.length;
    });

    sendBtn.addEventListener('click', sendReply);

    textarea.addEventListener('keydown', e => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendReply();
        }
    });
}

function sendReply() {
    if (!selectedUserId) {
        toast.show('Please select a user first', 'error', 2000);
        return;
    }

    const textarea = document.getElementById('replyInput');
    const text     = textarea.value.trim();

    if (!text) {
        toast.show('Message cannot be empty', 'error', 2000);
        return;
    }

    const sendBtn = document.getElementById('replyBtn');
    sendBtn.disabled = true;

    fetch(`${CONTROLLER}?action=send_message`, {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ user_id: selectedUserId, message_text: text })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            textarea.value = '';
            document.getElementById('replyCharCount').textContent = '0';
            toast.show('Reply sent!', 'success', 2000);
            loadThread(selectedUserId);
            loadUsers();
        } else {
            toast.show(data.message || 'Failed to send', 'error', 2000);
        }
    })
    .catch(() => toast.show('Connection error', 'error', 2000))
    .finally(() => { sendBtn.disabled = false; });
}

function setupSearchHandler() {
    const input = document.getElementById('userSearch');

    input.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            const q = input.value.toLowerCase().trim();
            if (!q) { renderUserList(allUsers); return; }

            renderUserList(allUsers.filter(u =>
                u.account_fname.toLowerCase().includes(q)    ||
                u.account_lname.toLowerCase().includes(q)    ||
                u.account_email.toLowerCase().includes(q)    ||
                u.account_username.toLowerCase().includes(q)
            ));
        }, 300);
    });
}

function updateMessageBadge(count) {
    const badge = document.getElementById('messageBadge');
    if (!badge) return;
    if (count > 0) {
        badge.textContent = count;
        badge.style.display = 'flex';
    } else {
        badge.style.display = 'none';
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
