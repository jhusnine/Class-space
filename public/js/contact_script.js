const CONTROLLER = '../../app/controllers/user_messages_controller.php';

let adminId = null;
let currentUserId = null;
let pollInterval = null;
let allMessages  = [];
let activeFilter = 'all';   // 'all' | 'sent' | 'received'
let searchQuery  = '';
let threadSig = '';   // only re-render when the thread actually changed

document.addEventListener('DOMContentLoaded', () => {
    initChat();

    setupFilters();
});

async function initChat() {
    // Load messages immediately, then poll every 3 s
    await loadMessages();
    pollInterval = setInterval(loadMessages, 3000);
}

// ── Load & Render Thread ─────────────────────────────────────────────────────
async function loadMessages() {
    try {
        const res  = await fetch(`${CONTROLLER}?action=get_messages`);
        const data = await res.json();

        if (!data.success) return;

        // Capture IDs on first load
        if (adminId === null) adminId = data.adminId;

        renderThread(data.data);

        await markSupportReplyNotificationsRead();
    } catch (err) {
        console.error('Failed to load messages:', err);
    }
}

async function markSupportReplyNotificationsRead() {
    try {
        const response = await fetch('../controllers/mark_notif_read.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'contact_replies' })
        });
        const result = await response.json();
        if (response.ok && result.success && typeof window.loadNotifBadge === 'function') {
            window.loadNotifBadge();
        }
    } catch (_) {
        // A transient badge/read request failure must not block the chat itself.
    }
}

function isOutgoing(msg) {
    return parseInt(msg.contact_sender) !== parseInt(adminId);
}

function getFilteredMessages() {
    return allMessages.filter(m => {
        if (activeFilter === 'sent' && !isOutgoing(m)) return false;
        if (activeFilter === 'received' && isOutgoing(m)) return false;
        return !searchQuery || String(m.contact_content).toLowerCase().includes(searchQuery);
    });
}

function setupFilters() {
    document.getElementById('chatFilters').addEventListener('click', e => {
        const chip = e.target.closest('.chip');
        if (!chip) return;
        activeFilter = chip.dataset.filter;
        document.querySelectorAll('#chatFilters .chip')
            .forEach(c => c.classList.toggle('active', c === chip));
        threadSig = '';
        renderThread();
    });

    let t = null;
    document.getElementById('chatSearch').addEventListener('input', e => {
        clearTimeout(t);
        t = setTimeout(() => {
            searchQuery = e.target.value.toLowerCase().trim();
            threadSig = '';
            renderThread();
        }, 200);
    });
}

function renderThread(incoming) {
    const stream = document.getElementById('chat-stream');
    if (!stream) return;

    if (incoming) allMessages = incoming;

    const last = allMessages[allMessages.length - 1];
    const sig  = allMessages.length + ':' + (last ? last.contact_id : 0) + '|' + activeFilter + '|' + searchQuery;
    if (sig === threadSig) return;
    const firstRender = threadSig === '';
    threadSig = sig;

    if (allMessages.length === 0) {
        stream.innerHTML = `
            <div class="empty-chat-state">
                <i class="fas fa-comments"></i>
                <p>No messages yet. Type below to contact support.</p>
            </div>`;
        return;
    }

    const messages = getFilteredMessages();
    if (messages.length === 0) {
        stream.innerHTML = `
            <div class="empty-chat-state">
                <i class="fas fa-magnifying-glass"></i>
                <p>No messages match your filter.</p>
            </div>`;
        return;
    }

    const myInitial = stream.dataset.initial || 'U';
    const wasAtBottom = stream.scrollHeight - stream.scrollTop <= stream.clientHeight + 40;

    const toDate = m => new Date(String(m.contact_created).replace(' ', 'T'));
    const isOut  = isOutgoing;

    let html = '';
    let lastDay = '';

    messages.forEach((msg, i) => {
        const date = toDate(msg);
        const day  = date.toDateString();
        const out  = isOut(msg);

        if (day !== lastDay) {
            html += `<div class="day-divider"><span>${escapeHtml(dayLabel(date))}</span></div>`;
            lastDay = day;
        }

        // Same sender, same day => same run. Avatar + time show on the last bubble of a run.
        const next    = messages[i + 1];
        const endsRun = !next || toDate(next).toDateString() !== day || isOut(next) !== out;

        const time = date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });

        // "[SUBJECT] text" becomes a small label above the text
        let subject = '';
        let text    = String(msg.contact_content);
        const m = text.match(/^\[([^\]]{1,60})\]\s*([\s\S]*)$/);
        if (m) { subject = m[1]; text = m[2]; }

        const avatar = `<div class="msg-avatar ${endsRun ? '' : 'is-hidden'}">${out ? escapeHtml(myInitial) : 'S'}</div>`;
        const col = `
            <div class="msg-col">
                <div class="msg-box">
                    ${subject ? `<div class="msg-subject">${escapeHtml(subject)}</div>` : ''}
                    <div class="msg-text">${escapeHtml(text)}</div>
                </div>
                ${endsRun ? `<div class="msg-time">${escapeHtml(time)}</div>` : ''}
            </div>`;

        html += `
            <div class="chat-row ${out ? 'outgoing' : 'incoming'} ${endsRun ? '' : 'in-run'}">
                ${out ? col + avatar : avatar + col}
            </div>`;
    });

    stream.innerHTML = html;
    if (firstRender || wasAtBottom) stream.scrollTop = stream.scrollHeight;
}

function dayLabel(date) {
    const today = new Date();
    const yest  = new Date(); yest.setDate(today.getDate() - 1);
    if (date.toDateString() === today.toDateString()) return 'Today';
    if (date.toDateString() === yest.toDateString())  return 'Yesterday';
    return date.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
}

// ── Chat Footer Input (right panel) ──────────────────────────────────────────
async function sendMessageFromInput() {
    const input = document.getElementById('reply-message-input');
    const text  = input.value.trim();
    if (!text) return;

    try {
        const res  = await fetch(`${CONTROLLER}?action=send_message`, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ message_text: text })
        });
        const data = await res.json();

        if (data.success) {
            input.value = '';
            threadSig = '';   // force a render + scroll to the new message
            await markSupportReplyNotificationsRead();
            await loadMessages();
        } else {
            toast.show(data.message || 'Failed to send.', 'error', 2000);
        }
    } catch (err) {
        console.error(err);
        toast.show('Connection error.', 'error', 2000);
    }
}

function checkEnterKey(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessageFromInput();
    }
}

// ── XSS Helper ────────────────────────────────────────────────────────────────
function escapeHtml(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}
