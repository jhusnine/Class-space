function toggleTFA(el) {
    el.classList.toggle('active');
    const isActive = el.classList.contains('active');
    el.setAttribute('aria-pressed', String(isActive));
    const msg = isActive ? 'Two-factor authentication enabled!' : 'Two-factor authentication disabled!';
    toast.show(msg, 'success', 2000);
}

function revokeSession(btn) {
    btn.disabled = true;
    btn.textContent = 'Session Revoked';
    toast.show('Session revoked successfully!', 'success', 2000);
    setTimeout(() => {
        window.location.href = 'logout.php';
    }, 2000);
}

document.addEventListener('DOMContentLoaded', () => {
    const tfaToggle = document.getElementById('tfa-toggle');
    const revokeButton = document.getElementById('revoke-session-btn');

    tfaToggle?.addEventListener('click', () => toggleTFA(tfaToggle));
    revokeButton?.addEventListener('click', () => revokeSession(revokeButton));
});
