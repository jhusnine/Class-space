/**
 * ClassSpace Admin - Teacher Account Management Script
 * Handles CRUD operations, filtering, search, pagination, and modal interactions.
 */

let allTeachers = [];
let filteredTeachers = [];
let currentPage = 1;
let perPage = 10;
let currentFilter = 'all'; // 'all', 'active', 'pending'
let searchQuery = '';

// Active target for edit/reset/delete operations
let selectedTeacher = null;

document.addEventListener('DOMContentLoaded', () => {
    initEventListeners();
    loadTeachers();
});

function initEventListeners() {
    // Search input
    const searchInput = document.getElementById('teacher-search-input');
    const clearBtn = document.getElementById('clear-search-btn');

    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            searchQuery = e.target.value.trim().toLowerCase();
            if (clearBtn) {
                clearBtn.style.display = searchQuery ? 'block' : 'none';
            }
            currentPage = 1;
            applyFilters();
        });
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', () => {
            searchInput.value = '';
            searchQuery = '';
            clearBtn.style.display = 'none';
            currentPage = 1;
            applyFilters();
            searchInput.focus();
        });
    }

    // Filter Chips
    const statTotal = document.getElementById('tstat-total');
    const statActive = document.getElementById('tstat-active');
    const statPending = document.getElementById('tstat-pending');

    if (statTotal) statTotal.addEventListener('click', () => setFilter('all'));
    if (statActive) statActive.addEventListener('click', () => setFilter('active'));
    if (statPending) statPending.addEventListener('click', () => setFilter('pending'));

    // Open Create Modal
    const btnOpenCreate = document.getElementById('btn-open-create-modal');
    if (btnOpenCreate) {
        btnOpenCreate.addEventListener('click', openCreateModal);
    }

    // Modal forms
    const formCreate = document.getElementById('form-create-teacher');
    if (formCreate) {
        formCreate.addEventListener('submit', handleCreateSubmit);
    }

    const formEdit = document.getElementById('form-edit-teacher');
    if (formEdit) {
        formEdit.addEventListener('submit', handleEditSubmit);
    }

    const formReset = document.getElementById('form-reset-password');
    if (formReset) {
        formReset.addEventListener('submit', handleResetSubmit);
    }

    const btnConfirmDelete = document.getElementById('btn-confirm-delete');
    if (btnConfirmDelete) {
        btnConfirmDelete.addEventListener('click', handleDeleteConfirm);
    }

    // Email to Username auto-fill suggestion
    const createEmail = document.getElementById('create-email');
    const createUsername = document.getElementById('create-username');
    if (createEmail && createUsername) {
        createEmail.addEventListener('blur', () => {
            if (!createUsername.value.trim() && createEmail.value.includes('@')) {
                const prefix = createEmail.value.split('@')[0].replace(/[^a-zA-Z0-9_]/g, '').toLowerCase();
                createUsername.value = prefix;
            }
        });
    }

    // Global Escape key to close modals
    window.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeAllModals();
        }
    });

    // Close on overlay backdrop click
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                closeAllModals();
            }
        });
    });
}

/**
 * Fetch teachers from the backend API.
 */
function loadTeachers() {
    const tbody = document.getElementById('teachers-tbody');
    if (tbody) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="loading-cell" style="text-align: center; padding: 40px; color: var(--text-low);">
                    <i class="fas fa-circle-notch fa-spin" style="font-size: 22px; margin-bottom: 8px;"></i>
                    <div>Loading faculty & teacher accounts…</div>
                </td>
            </tr>
        `;
    }

    fetch('../controllers/admin_teachers_controller.php?action=get_teachers')
        .then(res => res.json())
        .then(res => {
            if (!res.success) {
                showToast(res.message || 'Failed to fetch teachers.', 'error');
                return;
            }

            allTeachers = res.data || [];
            updateStatsBar(res.stats || {});
            applyFilters();
        })
        .catch(err => {
            console.error('Error loading teachers:', err);
            showToast('Network error while loading teachers.', 'error');
        });
}

/**
 * Update top stat counters.
 */
function updateStatsBar(stats) {
    const totalEl = document.getElementById('ts-total');
    const activeEl = document.getElementById('ts-active');
    const pendingEl = document.getElementById('ts-pending');

    if (totalEl) totalEl.textContent = stats.total_teachers ?? allTeachers.length;
    if (activeEl) activeEl.textContent = stats.with_bookings ?? 0;
    if (pendingEl) pendingEl.textContent = stats.total_pending ?? 0;
}

/**
 * Set current active filter.
 */
function setFilter(filter) {
    currentFilter = filter;
    document.querySelectorAll('.table-stats .tstat').forEach(el => el.classList.remove('is-selected'));

    if (filter === 'all') document.getElementById('tstat-total')?.classList.add('is-selected');
    if (filter === 'active') document.getElementById('tstat-active')?.classList.add('is-selected');
    if (filter === 'pending') document.getElementById('tstat-pending')?.classList.add('is-selected');

    currentPage = 1;
    applyFilters();
}

/**
 * Filter teachers based on search query and active tab.
 */
function applyFilters() {
    filteredTeachers = allTeachers.filter(t => {
        // Tab filter
        if (currentFilter === 'active' && parseInt(t.active_schedules || 0) <= 0) {
            return false;
        }
        if (currentFilter === 'pending' && parseInt(t.pending_schedules || 0) <= 0) {
            return false;
        }

        // Search query
        if (searchQuery) {
            const fullName = `${t.account_fname || ''} ${t.account_lname || ''}`.toLowerCase();
            const username = (t.account_username || '').toLowerCase();
            const email = (t.account_email || '').toLowerCase();
            if (!fullName.includes(searchQuery) && !username.includes(searchQuery) && !email.includes(searchQuery)) {
                return false;
            }
        }

        return true;
    });

    renderTable();
}

/**
 * Render paginated table rows.
 */
function renderTable() {
    const tbody = document.getElementById('teachers-tbody');
    const showingLabel = document.getElementById('showing-label');
    const pagBtns = document.getElementById('pag-btns');

    if (!tbody) return;

    if (filteredTeachers.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6">
                    <div class="empty-state">
                        <div class="empty-state-icon"><i class="fas fa-users-slash"></i></div>
                        <h3>No teachers found</h3>
                        <p>${searchQuery ? 'No faculty members matched your search.' : 'No teacher accounts registered yet.'}</p>
                        ${!searchQuery ? '<button class="btn-primary" onclick="openCreateModal()"><i class="fas fa-plus"></i> Add Teacher</button>' : ''}
                    </div>
                </td>
            </tr>
        `;
        if (showingLabel) showingLabel.innerHTML = 'Showing <strong>0</strong> of <strong>0</strong> teachers';
        if (pagBtns) pagBtns.innerHTML = '';
        return;
    }

    const totalPages = Math.ceil(filteredTeachers.length / perPage) || 1;
    if (currentPage > totalPages) currentPage = totalPages;

    const startIdx = (currentPage - 1) * perPage;
    const endIdx = Math.min(startIdx + perPage, filteredTeachers.length);
    const pageRows = filteredTeachers.slice(startIdx, endIdx);

    tbody.innerHTML = pageRows.map(teacher => {
        const initials = getInitials(teacher.account_fname, teacher.account_lname);
        const activeSched = parseInt(teacher.active_schedules || 0);
        const pendingSched = parseInt(teacher.pending_schedules || 0);

        let activityHtml = '';
        if (activeSched > 0) {
            activityHtml += `<span class="activity-badge activity-active" title="${activeSched} active schedules"><i class="fas fa-calendar-check"></i> ${activeSched} active</span> `;
        }
        if (pendingSched > 0) {
            activityHtml += `<span class="activity-badge activity-pending" title="${pendingSched} pending requests"><i class="fas fa-clock"></i> ${pendingSched} pending</span>`;
        }
        if (activeSched === 0 && pendingSched === 0) {
            activityHtml = `<span class="activity-none">No bookings</span>`;
        }

        const darkModeLabel = parseInt(teacher.setting_dark_mode) === 1 ? 'Dark' : 'Light';
        const langLabel = (teacher.setting_language || 'en').toUpperCase();

        return `
            <tr id="teacher-row-${teacher.account_id}">
                <td class="td-id">#${teacher.account_id}</td>
                <td>
                    <div class="teacher-profile-cell">
                        <div class="teacher-avatar" style="background: ${getAvatarColor(teacher.account_id)};">
                            ${escapeHtml(initials)}
                        </div>
                        <div class="teacher-meta">
                            <span class="teacher-name">${escapeHtml(teacher.account_fname)} ${escapeHtml(teacher.account_lname)}</span>
                            <span class="teacher-username">@${escapeHtml(teacher.account_username)}</span>
                        </div>
                    </div>
                </td>
                <td>
                    <span class="teacher-email">${escapeHtml(teacher.account_email)}</span>
                </td>
                <td class="td-settings">
                    <span class="badge-chip badge-dark"><i class="fas ${parseInt(teacher.setting_dark_mode) === 1 ? 'fa-moon' : 'fa-sun'}"></i> ${escapeHtml(darkModeLabel)}</span>
                    <span class="badge-chip badge-lang"><i class="fas fa-globe"></i> ${escapeHtml(langLabel)}</span>
                </td>
                <td class="td-activity">
                    ${activityHtml}
                </td>
                <td class="th-actions">
                    <div class="action-buttons-wrap">
                        <button type="button" class="action-btn action-edit" title="Edit Teacher" aria-label="Edit ${escapeHtml(teacher.account_fname)} ${escapeHtml(teacher.account_lname)}" onclick="openEditModal(${teacher.account_id})">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button type="button" class="action-btn action-key" title="Reset Password" aria-label="Reset password for ${escapeHtml(teacher.account_fname)} ${escapeHtml(teacher.account_lname)}" onclick="openResetPasswordModal(${teacher.account_id})">
                            <i class="fas fa-key"></i>
                        </button>
                        <button type="button" class="action-btn action-delete" title="Delete Teacher" aria-label="Delete teacher ${escapeHtml(teacher.account_fname)} ${escapeHtml(teacher.account_lname)}" onclick="openDeleteModal(${teacher.account_id})">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');

    // Update Showing label
    if (showingLabel) {
        showingLabel.innerHTML = `Showing <strong>${startIdx + 1}–${endIdx}</strong> of <strong>${filteredTeachers.length}</strong> teachers`;
    }

    renderPagination(totalPages);
}

/**
 * Render pagination controls.
 */
function renderPagination(totalPages) {
    const pagBtns = document.getElementById('pag-btns');
    if (!pagBtns) return;

    if (totalPages <= 1) {
        pagBtns.innerHTML = '';
        return;
    }

    let html = `
        <button class="pag-btn" onclick="goToPage(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}>
            <i class="fas fa-chevron-left"></i>
        </button>
    `;

    for (let p = 1; p <= totalPages; p++) {
        if (p === 1 || p === totalPages || (p >= currentPage - 1 && p <= currentPage + 1)) {
            html += `
                <button class="pag-btn ${p === currentPage ? 'active' : ''}" onclick="goToPage(${p})">
                    ${p}
                </button>
            `;
        } else if (p === currentPage - 2 || p === currentPage + 2) {
            html += `<span style="padding: 0 4px; color: var(--text-low); display: flex; align-items: center;">…</span>`;
        }
    }

    html += `
        <button class="pag-btn" onclick="goToPage(${currentPage + 1})" ${currentPage === totalPages ? 'disabled' : ''}>
            <i class="fas fa-chevron-right"></i>
        </button>
    `;

    pagBtns.innerHTML = html;
}

function goToPage(page) {
    const totalPages = Math.ceil(filteredTeachers.length / perPage) || 1;
    if (page < 1 || page > totalPages) return;
    currentPage = page;
    renderTable();
}

function changePerPage() {
    const sel = document.getElementById('per-page');
    if (sel) {
        perPage = parseInt(sel.value) || 10;
        currentPage = 1;
        renderTable();
    }
}

// ==========================================
// CREATE TEACHER
// ==========================================

function openCreateModal() {
    const form = document.getElementById('form-create-teacher');
    if (form) form.reset();

    const passInput = document.getElementById('create-password');
    if (passInput) passInput.value = generateSecurePassword();

    document.getElementById('modal-create-teacher')?.classList.add('active');
    document.getElementById('create-fname')?.focus();
}

function handleCreateSubmit(e) {
    e.preventDefault();

    const submitBtn = document.getElementById('btn-submit-create');
    const fname = document.getElementById('create-fname')?.value.trim();
    const lname = document.getElementById('create-lname')?.value.trim();
    const email = document.getElementById('create-email')?.value.trim();
    const username = document.getElementById('create-username')?.value.trim();
    const password = document.getElementById('create-password')?.value;
    const darkMode = document.getElementById('create-darkmode')?.value;
    const language = document.getElementById('create-language')?.value;

    if (!fname || !lname || !email || !password) {
        showToast('Please fill in all required fields.', 'error');
        return;
    }

    if (password.length < 6) {
        showToast('Password must be at least 6 characters.', 'error');
        return;
    }

    setButtonLoading(submitBtn, true);

    const payload = {
        action: 'create_teacher',
        fname,
        lname,
        email,
        username,
        password,
        dark_mode: parseInt(darkMode) || 0,
        language: language || 'en'
    };

    fetch('../controllers/admin_teachers_controller.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(res => {
        setButtonLoading(submitBtn, false);
        if (res.success) {
            showToast(res.message || 'Teacher account created successfully!', 'success');
            closeAllModals();
            loadTeachers();
        } else {
            showToast(res.message || res.error || 'Failed to create teacher account.', 'error');
        }
    })
    .catch(err => {
        setButtonLoading(submitBtn, false);
        console.error('Error creating teacher:', err);
        showToast('An unexpected server error occurred.', 'error');
    });
}

// ==========================================
// EDIT TEACHER
// ==========================================

function openEditModal(teacherId) {
    const teacher = allTeachers.find(t => parseInt(t.account_id) === parseInt(teacherId));
    if (!teacher) return;

    selectedTeacher = teacher;

    document.getElementById('edit-account-id').value = teacher.account_id;
    document.getElementById('edit-fname').value = teacher.account_fname || '';
    document.getElementById('edit-lname').value = teacher.account_lname || '';
    document.getElementById('edit-email').value = teacher.account_email || '';
    document.getElementById('edit-username').value = teacher.account_username || '';
    document.getElementById('edit-darkmode').value = teacher.setting_dark_mode ?? 0;
    document.getElementById('edit-language').value = teacher.setting_language ?? 'en';

    document.getElementById('modal-edit-teacher')?.classList.add('active');
    document.getElementById('edit-fname')?.focus();
}

function handleEditSubmit(e) {
    e.preventDefault();

    const submitBtn = document.getElementById('btn-submit-edit');
    const teacherId = document.getElementById('edit-account-id')?.value;
    const fname = document.getElementById('edit-fname')?.value.trim();
    const lname = document.getElementById('edit-lname')?.value.trim();
    const email = document.getElementById('edit-email')?.value.trim();
    const username = document.getElementById('edit-username')?.value.trim();
    const darkMode = document.getElementById('edit-darkmode')?.value;
    const language = document.getElementById('edit-language')?.value;

    if (!fname || !lname || !email || !username) {
        showToast('All fields are required.', 'error');
        return;
    }

    setButtonLoading(submitBtn, true);

    const payload = {
        action: 'update_teacher',
        account_id: parseInt(teacherId),
        fname,
        lname,
        email,
        username,
        dark_mode: parseInt(darkMode) || 0,
        language: language || 'en'
    };

    fetch('../controllers/admin_teachers_controller.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(res => {
        setButtonLoading(submitBtn, false);
        if (res.success) {
            showToast(res.message || 'Teacher updated successfully.', 'success');
            closeAllModals();
            loadTeachers();
        } else {
            showToast(res.message || 'Failed to update teacher.', 'error');
        }
    })
    .catch(err => {
        setButtonLoading(submitBtn, false);
        console.error('Error updating teacher:', err);
        showToast('An unexpected server error occurred.', 'error');
    });
}

// ==========================================
// RESET PASSWORD
// ==========================================

function openResetPasswordModal(teacherId) {
    const teacher = allTeachers.find(t => parseInt(t.account_id) === parseInt(teacherId));
    if (!teacher) return;

    selectedTeacher = teacher;

    document.getElementById('reset-account-id').value = teacher.account_id;
    document.getElementById('reset-teacher-name').textContent = `${teacher.account_fname} ${teacher.account_lname} (@${teacher.account_username})`;

    const passInput = document.getElementById('reset-password');
    if (passInput) passInput.value = generateSecurePassword();

    document.getElementById('modal-reset-password')?.classList.add('active');
    passInput?.focus();
}

function handleResetSubmit(e) {
    e.preventDefault();

    const submitBtn = document.getElementById('btn-submit-reset');
    const teacherId = document.getElementById('reset-account-id')?.value;
    const password = document.getElementById('reset-password')?.value;

    if (!password || password.length < 6) {
        showToast('Password must be at least 6 characters.', 'error');
        return;
    }

    setButtonLoading(submitBtn, true);

    const payload = {
        action: 'reset_password',
        account_id: parseInt(teacherId),
        password
    };

    fetch('../controllers/admin_teachers_controller.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(res => {
        setButtonLoading(submitBtn, false);
        if (res.success) {
            showToast(res.message || 'Password successfully reset.', 'success');
            closeAllModals();
        } else {
            showToast(res.message || 'Failed to reset password.', 'error');
        }
    })
    .catch(err => {
        setButtonLoading(submitBtn, false);
        console.error('Error resetting password:', err);
        showToast('An unexpected server error occurred.', 'error');
    });
}

// ==========================================
// DELETE TEACHER
// ==========================================

function openDeleteModal(teacherId) {
    const teacher = allTeachers.find(t => parseInt(t.account_id) === parseInt(teacherId));
    if (!teacher) return;

    selectedTeacher = teacher;

    const nameEl = document.getElementById('delete-teacher-name');
    const warnEl = document.getElementById('delete-active-warnings');

    if (nameEl) {
        nameEl.textContent = `${teacher.account_fname} ${teacher.account_lname} (${teacher.account_email})`;
    }

    const activeSched = parseInt(teacher.active_schedules || 0);
    const pendingSched = parseInt(teacher.pending_schedules || 0);

    if (warnEl) {
        if (activeSched > 0 || pendingSched > 0) {
            warnEl.innerHTML = `
                <div style="margin-top: 8px; font-weight: 600; color: var(--danger-red);">
                    <i class="fas fa-exclamation-triangle"></i> This teacher currently has:
                </div>
                <ul class="danger-detail-list">
                    ${activeSched > 0 ? `<li><strong>${activeSched}</strong> active room schedule(s)</li>` : ''}
                    ${pendingSched > 0 ? `<li><strong>${pendingSched}</strong> pending reservation request(s)</li>` : ''}
                </ul>
                <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">
                    All associated schedules, reservation details, chat messages, and notifications will be permanently purged.
                </div>
            `;
        } else {
            warnEl.innerHTML = `
                <div style="font-size: 12px; color: var(--text-low); margin-top: 6px;">
                    This teacher has no active reservations. Their account, settings, and notifications will be safely removed.
                </div>
            `;
        }
    }

    document.getElementById('modal-delete-teacher')?.classList.add('active');
}

function handleDeleteConfirm() {
    if (!selectedTeacher) return;

    const btn = document.getElementById('btn-confirm-delete');
    setButtonLoading(btn, true);

    const payload = {
        action: 'delete_teacher',
        account_id: parseInt(selectedTeacher.account_id)
    };

    fetch('../controllers/admin_teachers_controller.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(res => {
        setButtonLoading(btn, false);
        if (res.success) {
            showToast(res.message || 'Teacher account deleted.', 'success');
            closeAllModals();
            loadTeachers();
        } else {
            showToast(res.message || 'Failed to delete teacher.', 'error');
        }
    })
    .catch(err => {
        setButtonLoading(btn, false);
        console.error('Error deleting teacher:', err);
        showToast('An unexpected server error occurred.', 'error');
    });
}

// ==========================================
// MODAL & UI UTILITIES
// ==========================================

function closeAllModals() {
    document.querySelectorAll('.modal-overlay').forEach(m => m.classList.remove('active'));
    selectedTeacher = null;
}

function togglePasswordVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;

    const isPassword = input.type === 'password';
    input.type = isPassword ? 'text' : 'password';

    const icon = btn.querySelector('i');
    if (icon) {
        icon.className = isPassword ? 'fas fa-eye-slash' : 'fas fa-eye';
    }
}

function fillGeneratedPassword(inputId) {
    const input = document.getElementById(inputId);
    if (input) {
        input.value = generateSecurePassword();
        input.type = 'text';
        showToast('Generated strong password.', 'info', 2000);
    }
}

function copyPassword(inputId) {
    const input = document.getElementById(inputId);
    if (!input || !input.value) return;

    navigator.clipboard.writeText(input.value)
        .then(() => showToast('Password copied to clipboard!', 'success', 2500))
        .catch(() => {
            input.select();
            document.execCommand('copy');
            showToast('Password copied to clipboard!', 'success', 2500);
        });
}

function generateSecurePassword() {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$%&*';
    if (window.crypto && window.crypto.getRandomValues) {
        const randBuffer = new Uint32Array(10);
        window.crypto.getRandomValues(randBuffer);
        let pass = 'Class#';
        for (let i = 0; i < 8; i++) {
            pass += chars.charAt(randBuffer[i] % chars.length);
        }
        const suffix = 10 + (randBuffer[9] % 90);
        return pass + suffix;
    }

    // Fallback if crypto is unavailable
    let pass = 'Class#';
    for (let i = 0; i < 8; i++) {
        pass += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    return pass + Math.floor(10 + Math.random() * 90);
}

function setButtonLoading(btn, isLoading) {
    if (!btn) return;
    if (isLoading) {
        btn.dataset.originalText = btn.innerHTML;
        btn.innerHTML = `<i class="fas fa-circle-notch fa-spin"></i> Processing…`;
        btn.disabled = true;
    } else {
        if (btn.dataset.originalText) {
            btn.innerHTML = btn.dataset.originalText;
        }
        btn.disabled = false;
    }
}

function getInitials(fname, lname) {
    const f = (fname || '').trim().charAt(0).toUpperCase();
    const l = (lname || '').trim().charAt(0).toUpperCase();
    return (f + l) || 'T';
}

function getAvatarColor(id) {
    const palette = [
        'linear-gradient(135deg, rgba(56, 189, 248, 0.25), rgba(14, 165, 233, 0.45))',
        'linear-gradient(135deg, rgba(16, 185, 129, 0.25), rgba(5, 150, 105, 0.45))',
        'linear-gradient(135deg, rgba(139, 92, 246, 0.25), rgba(109, 40, 217, 0.45))',
        'linear-gradient(135deg, rgba(245, 158, 11, 0.25), rgba(217, 119, 6, 0.45))',
        'linear-gradient(135deg, rgba(236, 72, 153, 0.25), rgba(190, 24, 93, 0.45))',
        'linear-gradient(135deg, rgba(99, 102, 241, 0.25), rgba(67, 56, 202, 0.45))'
    ];
    return palette[parseInt(id || 0) % palette.length];
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function showToast(message, type = 'info', duration = 3500) {
    if (window.toast && typeof window.toast.show === 'function') {
        window.toast.show(message, type, duration);
    } else {
        alert(message);
    }
}

