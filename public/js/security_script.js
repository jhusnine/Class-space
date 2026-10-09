function toggleTFA(el) {
    el.classList.toggle('active');
    const isActive = el.classList.contains('active');
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