const toast = (() => {
    let container = null;

    function getContainer() {
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.classList.add('toast-container');
            document.body.appendChild(container);
        }
        return container;
    }

    function createCloseButton() {
        const closeButton = document.createElement('button');
        closeButton.type = 'button';
        closeButton.className = 'toast-close';
        closeButton.setAttribute('aria-label', 'Close notification');
        closeButton.textContent = '×';
        return closeButton;
    }

    function show(message, type = 'info', duration = 3500) {
        const containerElement = getContainer();
        const toastElement = document.createElement('div');
        const messageElement = document.createElement('span');
        const closeButton = createCloseButton();

        toastElement.classList.add('toast-item');
        if (type) toastElement.classList.add(`toast-${type}`);
        messageElement.className = 'toast-message';
        messageElement.textContent = String(message);

        closeButton.addEventListener('click', (event) => {
            event.stopPropagation();
            toastElement.remove();
        });

        toastElement.append(messageElement, closeButton);
        containerElement.appendChild(toastElement);
        requestAnimationFrame(() => requestAnimationFrame(() => toastElement.classList.add('toast-visible')));

        const timer = window.setTimeout(() => dismiss(toastElement), duration);
        toastElement.addEventListener('click', () => {
            window.clearTimeout(timer);
            dismiss(toastElement);
        });
    }

    function dismiss(toastElement) {
        if (!toastElement || !toastElement.isConnected) return;
        toastElement.classList.remove('toast-visible');
        toastElement.classList.add('toast-hiding');
        window.setTimeout(() => toastElement.remove(), 280);
    }

    return { show };
})();
window.toast = toast;

let latestUserNotifications = [];
let notificationCanDelete = false;
let notificationSnapshotLoaded = false;
let notificationLoadFailed = false;
let notificationRequestInFlight = false;
let notificationRefreshQueued = false;
let notificationLastLoadedAt = 0;
let notificationDropdownElements = null;

function getNotificationControllerPath(filename) {
    const basePath = window.location.pathname.includes('index') ? 'controllers' : '../controllers';
    return `${basePath}/${filename}`;
}

async function softDeleteNotification(notifId) {
    const response = await fetch(getNotificationControllerPath('mark_notif_read.php'), {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'delete', notif_id: notifId })
    });

    const result = await response.json();
    if (!response.ok || !result.success) {
        if (result.error === 'database_migration_required') {
            throw new Error('Notification removal is not available until the database update is applied.');
        }
        throw new Error('Could not remove this notification.');
    }
    return result;
}
window.softDeleteNotification = softDeleteNotification;

function formatNotificationTime(value) {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);
    return date.toLocaleString('en-US', {
        month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit'
    });
}

function createNotificationIcon(type) {
    const icons = { approved: 'fa-check', pending: 'fa-clock', declined: 'fa-times', cancelled: 'fa-ban' };
    const icon = document.createElement('i');
    icon.className = `fas ${icons[type] || 'fa-circle-info'}`;
    icon.setAttribute('aria-hidden', 'true');
    return icon;
}

function renderNotificationDropdown() {
    const elements = notificationDropdownElements;
    if (!elements) return;

    const { list } = elements;
    list.replaceChildren();

    if (!notificationSnapshotLoaded) {
        const loading = document.createElement('div');
        loading.className = 'notification-dropdown-empty';
        loading.textContent = 'Loading notifications…';
        list.appendChild(loading);
        return;
    }
    if (notificationLoadFailed) {
        const error = document.createElement('div');
        error.className = 'notification-dropdown-empty';
        error.textContent = 'Notifications could not be loaded.';
        list.appendChild(error);
        return;
    }

    const items = latestUserNotifications.slice(0, 4);
    if (!items.length) {
        const empty = document.createElement('div');
        empty.className = 'notification-dropdown-empty';
        empty.textContent = 'You are all caught up.';
        list.appendChild(empty);
        return;
    }

    items.forEach((notification) => {
        const type = String(notification.notif_type || 'info').toLowerCase();
        const safeType = type.replace(/[^a-z0-9_-]/g, '');
        const row = document.createElement('article');
        row.className = `notification-dropdown-item type-${safeType}`;
        if (Number(notification.notif_is_read) !== 1) row.classList.add('is-unread');

        const iconWrap = document.createElement('span');
        iconWrap.className = 'notification-dropdown-icon';
        iconWrap.appendChild(createNotificationIcon(type));

        const content = document.createElement('a');
        content.className = 'notification-dropdown-content';
        content.href = 'notifications_view.php';

        const title = document.createElement('span');
        title.className = 'notification-dropdown-title';
        title.textContent = String(notification.notif_title || 'Notification');
        const message = document.createElement('span');
        message.className = 'notification-dropdown-message';
        message.textContent = String(notification.notif_message || '');
        const time = document.createElement('span');
        time.className = 'notification-dropdown-time';
        time.textContent = formatNotificationTime(notification.notif_created);
        content.append(title, message, time);
        row.append(iconWrap, content);

        if (notificationCanDelete) {
            const removeButton = document.createElement('button');
            removeButton.type = 'button';
            removeButton.className = 'notification-dropdown-delete';
            removeButton.setAttribute('aria-label', 'Remove this notification from your list');
            removeButton.title = 'Remove from your notifications';
            removeButton.innerHTML = '<i class="fas fa-trash" aria-hidden="true"></i>';
            removeButton.addEventListener('click', async (event) => {
                event.preventDefault();
                event.stopPropagation();
                removeButton.disabled = true;
                try {
                    await softDeleteNotification(notification.notif_id);
                    latestUserNotifications = latestUserNotifications.filter(
                        (item) => String(item.notif_id) !== String(notification.notif_id)
                    );
                    renderNotificationDropdown();
                    loadNotifBadge();
                    toast.show('Notification removed. The database record was retained.', 'success', 3000);
                } catch (error) {
                    removeButton.disabled = false;
                    toast.show(error.message, 'error', 3000);
                }
            });
            row.appendChild(removeButton);
        }
        list.appendChild(row);
    });

    if (!notificationCanDelete) {
        const note = document.createElement('div');
        note.className = 'notification-dropdown-note';
        note.textContent = 'Notification removal is temporarily unavailable.';
        list.appendChild(note);
    }
}

function initNotificationDropdown() {
    const trigger = document.querySelector('.user-utility-bar .utility-icon-link[href="notifications_view.php"]');
    if (!trigger || trigger.dataset.dropdownReady === 'true') return;

    const actions = trigger.parentElement;
    if (!actions) return;
    trigger.dataset.dropdownReady = 'true';
    trigger.setAttribute('aria-haspopup', 'true');
    trigger.setAttribute('aria-expanded', 'false');
    trigger.setAttribute('aria-controls', 'notification-dropdown');

    const panel = document.createElement('section');
    panel.id = 'notification-dropdown';
    panel.className = 'notification-dropdown';
    panel.setAttribute('role', 'region');
    panel.setAttribute('aria-label', 'Recent notifications');
    panel.hidden = true;
    panel.innerHTML = `
        <div class="notification-dropdown-header">
            <strong>Notifications</strong>
            <button type="button" class="notification-dropdown-close" aria-label="Close notifications">
                <i class="fas fa-xmark" aria-hidden="true"></i>
            </button>
        </div>
        <div class="notification-dropdown-list" id="notification-dropdown-list"></div>
        <a class="notification-dropdown-footer" href="notifications_view.php">See all notifications</a>
    `;
    actions.appendChild(panel);

    const list = panel.querySelector('#notification-dropdown-list');
    const closeButton = panel.querySelector('.notification-dropdown-close');
    notificationDropdownElements = { panel, trigger, list };

    const setOpen = (isOpen) => {
        panel.hidden = !isOpen;
        trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        if (isOpen && (
            !notificationSnapshotLoaded
            || notificationLoadFailed
            || Date.now() - notificationLastLoadedAt > 30000
        )) {
            renderNotificationDropdown();
            loadNotifBadge();
        }
    };

    trigger.addEventListener('click', (event) => {
        event.preventDefault();
        setOpen(panel.hidden);
    });
    closeButton.addEventListener('click', () => setOpen(false));
    panel.querySelector('.notification-dropdown-footer').addEventListener('click', () => setOpen(false));

    document.addEventListener('click', (event) => {
        if (!panel.hidden && !panel.contains(event.target) && !trigger.contains(event.target)) setOpen(false);
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !panel.hidden) {
            setOpen(false);
            trigger.focus();
        }
    });
    renderNotificationDropdown();
}

function updateContactReplyBadge(count) {
    const contactLink = document.querySelector('.user-utility-bar .utility-icon-link[href="contact_view.php"]');
    if (!contactLink) return;

    let badge = contactLink.querySelector('.contact-reply-badge');
    if (!badge) {
        badge = document.createElement('span');
        badge.id = 'contact-reply-badge';
        badge.className = 'notif-badge contact-reply-badge';
        badge.setAttribute('aria-live', 'polite');
        badge.setAttribute('aria-label', 'Unread support replies');
        contactLink.appendChild(badge);
    }

    const unread = Math.max(0, Number(count) || 0);
    badge.textContent = unread > 9 ? '9+' : (unread > 0 ? String(unread) : '');
    badge.style.display = unread > 0 ? 'inline-flex' : 'none';
}

function loadNotifBadge() {
    const badge = document.getElementById('notif-badge');
    if (!badge) return;
    if (notificationRequestInFlight) {
        notificationRefreshQueued = true;
        return;
    }

    notificationRequestInFlight = true;
    notificationRefreshQueued = false;
    notificationLoadFailed = false;
    fetch(getNotificationControllerPath('notifications_controller.php'), {
        credentials: 'same-origin',
        cache: 'no-store'
    })
        .then((response) => response.json())
        .then((result) => {
            if (!result.success) throw new Error('Could not load notifications.');
            const unread = Math.max(0, Number(result.unread) || 0);
            badge.textContent = unread > 9 ? '9+' : (unread > 0 ? String(unread) : '');
            badge.style.display = unread > 0 ? 'inline-flex' : 'none';
            latestUserNotifications = Array.isArray(result.data) ? result.data : [];
            notificationCanDelete = result.can_delete === true;
            updateContactReplyBadge(result.unread_contact_replies);
            notificationSnapshotLoaded = true;
            notificationLastLoadedAt = Date.now();
            renderNotificationDropdown();
        })
        .catch(() => {
            notificationSnapshotLoaded = true;
            notificationLoadFailed = true;
            renderNotificationDropdown();
        })
        .finally(() => {
            notificationRequestInFlight = false;
            if (notificationRefreshQueued) {
                notificationRefreshQueued = false;
                loadNotifBadge();
            }
        });
}

window.loadNotifBadge = loadNotifBadge;
loadNotifBadge();
window.setInterval(loadNotifBadge, 30000);

function initSharedMobileMenu() {
    const toggle   = document.getElementById("mobile-menu-toggle");
    const backdrop = document.getElementById("mobile-menu-backdrop");
    const sidebar  = document.getElementById("classspace-sidebar");
    if (!toggle || !backdrop || !sidebar) return;
    if (toggle.dataset.menuBound === "true") return;
    toggle.dataset.menuBound = "true";

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

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => {
        initSharedMobileMenu();
        initNotificationDropdown();
    });
} else {
    initSharedMobileMenu();
    initNotificationDropdown();
}
