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
        if (type) {
            toastElement.classList.add(`toast-${type}`);
        }

        messageElement.className = 'toast-message';
        messageElement.textContent = String(message);

        closeButton.addEventListener('click', (event) => {
            event.stopPropagation();
            toastElement.remove();
        });

        toastElement.append(messageElement, closeButton);
        containerElement.appendChild(toastElement);

        /* Trigger slide-in on the next frame without string evaluation. */
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                toastElement.classList.add('toast-visible');
            });
        });

        const timer = window.setTimeout(() => dismiss(toastElement), duration);

        toastElement.addEventListener('click', () => {
            window.clearTimeout(timer);
            dismiss(toastElement);
        });
    }

    function dismiss(toastElement) {
        if (!toastElement || !toastElement.isConnected) {
            return;
        }

        toastElement.classList.remove('toast-visible');
        toastElement.classList.add('toast-hiding');
        window.setTimeout(() => toastElement.remove(), 280);
    }

    return { show };
})();

function loadNotifBadge() {
    const badge = document.getElementById('notif-badge');


    if (!badge) {
        return;
    }

    let basePath = '../controllers';

    if (window.location.pathname.includes('index')) {
        basePath = 'controllers';
    }

    fetch(`${basePath}/notifications_controller.php`)
        .then((response) => response.json())
        .then((result) => {
            if (!result.success || !result.unread) {
                return;
            }

            badge.textContent = result.unread > 9 ? '9+' : String(result.unread);
            badge.style.display = 'inline-block';
        })
        .catch(() => {
        });
}

loadNotifBadge();

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
    document.addEventListener("DOMContentLoaded", initSharedMobileMenu);
} else {
    initSharedMobileMenu();
}