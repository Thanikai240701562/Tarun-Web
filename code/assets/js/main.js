/**
 * TeamForge Core UI JavaScript Framework
 * Manages UI interactions, navigation, toasts, and authenticated layout state
 */

document.addEventListener('DOMContentLoaded', async () => {
    // 1. Mobile Sidebar Toggle
    const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
    const appSidebar = document.querySelector('.app-sidebar');
    const sidebarOverlay = document.querySelector('.sidebar-overlay');

    if (sidebarToggleBtn && appSidebar) {
        sidebarToggleBtn.addEventListener('click', () => {
            appSidebar.classList.toggle('show');
            if (sidebarOverlay) {
                sidebarOverlay.classList.toggle('show');
            }
        });
    }

    if (sidebarOverlay && appSidebar) {
        sidebarOverlay.addEventListener('click', () => {
            appSidebar.classList.remove('show');
            sidebarOverlay.classList.remove('show');
        });
    }

    // 2. Active Sidebar Link Highlighting
    const currentPath = window.location.pathname.split('/').pop() || 'index.html';
    document.querySelectorAll('.app-sidebar .nav-link-custom').forEach(link => {
        const href = link.getAttribute('href');
        if (href && (href === currentPath || href.endsWith('/' + currentPath))) {
            link.classList.add('active');
        }
    });

    // 3. User Session State Sync (Sidebar & Topbar)
    if (window.api && document.querySelector('.app-sidebar')) {
        try {
            const authState = await window.api.checkAuth();
            if (authState && authState.logged_in && authState.user) {
                const u = authState.user;
                const userNameEl = document.getElementById('sidebarUserName');
                const userEmailEl = document.getElementById('sidebarUserEmail');
                const userAvatarEl = document.getElementById('sidebarUserAvatar');
                const topbarAvatarEl = document.getElementById('topbarUserAvatar');

                if (userNameEl) userNameEl.textContent = u.full_name || 'Student';
                if (userEmailEl) userEmailEl.textContent = u.email || '';
                
                const initial = (u.full_name ? u.full_name.charAt(0) : 'U').toUpperCase();
                if (userAvatarEl) userAvatarEl.textContent = initial;
                if (topbarAvatarEl) topbarAvatarEl.textContent = initial;

                // Show Admin section if faculty_admin
                const adminMenu = document.getElementById('sidebarAdminCategory');
                const adminLinks = document.querySelectorAll('.sidebar-admin-link');
                if (u.role === 'faculty_admin') {
                    if (adminMenu) adminMenu.style.display = 'block';
                    adminLinks.forEach(el => el.style.display = 'flex');
                } else {
                    if (adminMenu) adminMenu.style.display = 'none';
                    adminLinks.forEach(el => el.style.display = 'none');
                }
            }
        } catch (e) {
            console.warn('[Session] Session check skipped:', e);
        }
    }

    // 4. Initialize Bootstrap Tooltips
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
    }
});

/**
 * Toast Notification Helper
 */
window.tfToast = function(message, type = 'info') {
    let container = document.getElementById('tf-toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'tf-toast-container';
        container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
    }

    const toastId = 'toast-' + Math.random().toString(36).substring(2, 9);
    const bgClass = type === 'success' ? 'bg-success' : (type === 'error' || type === 'danger' ? 'bg-danger' : (type === 'warning' ? 'bg-warning' : 'bg-primary'));
    
    const toastHtml = `
        <div id="${toastId}" class="toast align-items-center text-white ${bgClass} border-0 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body d-flex align-items-center gap-2">
                    <i class="bi bi-info-circle-fill"></i>
                    <span>${message}</span>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    `;

    container.insertAdjacentHTML('beforeend', toastHtml);
    const toastEl = document.getElementById(toastId);
    if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
        const bsToast = new bootstrap.Toast(toastEl, { delay: 4000 });
        bsToast.show();
        toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
    } else {
        setTimeout(() => toastEl.remove(), 4000);
    }
};
