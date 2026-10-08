const CONTROLLER = '../../app/controllers/user_messages_controller.php';

let adminId = null;
let currentUserId = null;
let pollInterval = null;

document.addEventListener('DOMContentLoaded', () => {
    initChat();

    // DITO MO ILAGAY YUNG CODE PARA SA ENTER KEY NG CONTACT FORM:
    const contactTextarea = document.getElementById('contact-message-textarea');
    const contactForm = document.getElementById('contactForm');

    if (contactTextarea && contactForm) {
        contactTextarea.addEventListener('keydown', (e) => {
            // Kung Enter ang pinindot at walang Shift key
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault(); // Pigilan ang pagbaba ng linya
                contactForm.requestSubmit(); // I-submit ang form gamit ang handleContactSubmit(e)
            }
        });
    }
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
    } catch (err) {
        console.error('Failed to load messages:', err);
    }
}

function renderThread(messages) {
    const stream = document.getElementById('chat-stream');
    if (!stream) return;

    if (messages.length === 0) {
        stream.innerHTML = `
            <div class="empty-chat-state">
                <i class="fas fa-comments"></i>
                <p>No messages yet. Send a message using the form on the left.</p>
            </div>`;
        return;
    }

    // Remember scroll position — only auto-scroll if already at bottom
    const isAtBottom = stream.scrollHeight - stream.scrollTop <= stream.clientHeight + 40;

    stream.innerHTML = messages.map(msg => {
        const isOutgoing = parseInt(msg.contact_sender) !== parseInt(adminId);
        const direction  = isOutgoing ? 'outgoing' : 'incoming';
        const time = new Date(msg.contact_created).toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit'
        });

        if (direction === 'incoming') {
            return `
                <div class="chat-row incoming">
                    <div class="msg-avatar"></div>
                    <div class="msg-col">
                        <div class="msg-box">${escapeHtml(msg.contact_content)}</div>
                        <div class="msg-time">${time}</div>
                    </div>
                </div>`;
        } else {
            return `
                <div class="chat-row outgoing">
                    <div class="msg-col">
                        <div class="msg-box">${escapeHtml(msg.contact_content)}</div>
                        <div class="msg-time">${time}</div>
                    </div>
                    <div class="msg-avatar"></div>
                </div>`;
        }
    }).join('');

    if (isAtBottom) {
        stream.scrollTop = stream.scrollHeight;
    }
}

// ── Contact Form (left panel) ─────────────────────────────────────────────────
async function handleContactSubmit(e) {
    e.preventDefault();

    const subjectInput = document.querySelector('#contactForm input[placeholder="Subject"]');
    const messageInput = document.querySelector('#contactForm textarea');
    const subject = subjectInput.value.trim();
    const message = messageInput.value.trim();

    if (!subject || !message) {
        toast.show('Please fill in all fields.', 'error', 2000);
        return;
    }

    const finalMessage = `[${subject.toUpperCase()}] ${message}`;

    try {
        const res  = await fetch(`${CONTROLLER}?action=send_message`, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ message_text: finalMessage })
        });
        const data = await res.json();

        if (data.success) {
            toast.show('Your message has been sent!', 'success', 3000);
            document.getElementById('contactForm').reset();
            await loadMessages();
        } else {
            toast.show(data.message || 'Failed to send message.', 'error', 2500);
        }
    } catch (err) {
        console.error(err);
        toast.show('Connection error. Please try again.', 'error', 2500);
    }
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
    const d = document.createElement('div');
    d.appendChild(document.createTextNode(str));
    return d.innerHTML;
}
