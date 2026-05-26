/**
 * app.js — Core utilities
 * Toast notifications, confirm modal, sidebar, CSRF helper, nav highlights
 */

'use strict';

// ─── CSRF ───────────────────────────────────────────────────────────────────

window.getCsrfToken = function () {
    return document.querySelector('meta[name="csrf-token"]')?.content
        ?? window.APP?.csrfToken
        ?? '';
};

// ─── Toast ───────────────────────────────────────────────────────────────────

window.toast = (function () {
    function show(message, type = 'info', duration = 4000) {
        const container = document.getElementById('toastContainer');
        if (!container) return;

        const toast = document.createElement('div');
        toast.className = 'toast toast--' + type;
        toast.setAttribute('role', 'alert');

        const icons = {
            success: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>',
            error:   '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
            info:    '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
            warning: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
        };

        toast.innerHTML = `
            <span class="toast-icon">${icons[type] || icons.info}</span>
            <span class="toast-message">${message}</span>
            <button class="toast-close" aria-label="Fechar">×</button>
        `;

        toast.querySelector('.toast-close').addEventListener('click', () => dismiss(toast));
        container.appendChild(toast);

        // Animate in
        requestAnimationFrame(() => toast.classList.add('toast--visible'));

        if (duration > 0) {
            setTimeout(() => dismiss(toast), duration);
        }
    }

    function dismiss(toast) {
        toast.classList.remove('toast--visible');
        toast.addEventListener('transitionend', () => toast.remove(), { once: true });
        setTimeout(() => toast.remove(), 500); // fallback
    }

    return {
        success: (msg, d) => show(msg, 'success', d),
        error:   (msg, d) => show(msg, 'error', d),
        info:    (msg, d) => show(msg, 'info', d),
        warning: (msg, d) => show(msg, 'warning', d),
    };
})();

// ─── Confirm Modal ───────────────────────────────────────────────────────────

window.confirm2 = function (message, title = 'Confirmar acção') {
    return new Promise(resolve => {
        const modal   = document.getElementById('confirmModal');
        const titleEl = document.getElementById('confirmTitle');
        const msgEl   = document.getElementById('confirmMessage');
        const okBtn   = document.getElementById('confirmOk');
        const cancelBtn = document.getElementById('confirmCancel');

        if (!modal) {
            resolve(window.confirm(message));
            return;
        }

        titleEl.textContent = title;
        msgEl.textContent   = message;
        modal.hidden = false;

        function cleanup() {
            modal.hidden = true;
            okBtn.removeEventListener('click', onOk);
            cancelBtn.removeEventListener('click', onCancel);
        }

        function onOk()     { cleanup(); resolve(true);  }
        function onCancel() { cleanup(); resolve(false); }

        okBtn.addEventListener('click', onOk);
        cancelBtn.addEventListener('click', onCancel);
    });
};

// ─── Sidebar Toggle ───────────────────────────────────────────────────────────

(function () {
    const toggle  = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const layout  = document.querySelector('.app-layout');

    if (!toggle || !sidebar) return;

    const COLLAPSED_KEY = 'sidebar_collapsed';
    const isCollapsed   = localStorage.getItem(COLLAPSED_KEY) === 'true';

    if (isCollapsed) {
        sidebar.classList.add('sidebar--collapsed');
        layout?.classList.add('layout--sidebar-collapsed');
    }

    toggle.addEventListener('click', () => {
        const collapsed = sidebar.classList.toggle('sidebar--collapsed');
        layout?.classList.toggle('layout--sidebar-collapsed', collapsed);
        localStorage.setItem(COLLAPSED_KEY, String(collapsed));
    });

    // Close sidebar on mobile when clicking outside
    document.addEventListener('click', (e) => {
        if (window.innerWidth < 768) {
            if (!sidebar.contains(e.target) && !toggle.contains(e.target)) {
                sidebar.classList.remove('sidebar--open');
            }
        }
    });

    // Mobile: toggle open (not collapse)
    if (window.innerWidth < 768) {
        toggle.addEventListener('click', () => {
            sidebar.classList.toggle('sidebar--open');
        });
    }
})();

// ─── User Menu Dropdown ───────────────────────────────────────────────────────

(function () {
    const trigger  = document.getElementById('userMenuTrigger');
    const dropdown = document.getElementById('userMenuDropdown');

    if (!trigger || !dropdown) return;

    trigger.addEventListener('click', (e) => {
        e.stopPropagation();
        const isOpen = dropdown.classList.toggle('open');
        trigger.setAttribute('aria-expanded', String(isOpen));
    });

    document.addEventListener('click', () => {
        dropdown.classList.remove('open');
        trigger.setAttribute('aria-expanded', 'false');
    });
})();

// ─── Active Nav Highlighting ──────────────────────────────────────────────────

(function () {
    const path  = window.location.pathname;
    document.querySelectorAll('.sidebar-item').forEach(link => {
        const href = link.getAttribute('href');
        if (!href) return;
        // Exact match or prefix match for sub-sections
        if (path === href || (href !== '/' && path.startsWith(href))) {
            link.classList.add('active');
        } else {
            link.classList.remove('active');
        }
    });
})();

// ─── Keyboard Shortcuts ───────────────────────────────────────────────────────

document.addEventListener('keydown', (e) => {
    // Escape closes modals/lightbox
    if (e.key === 'Escape') {
        const uploadModal = document.getElementById('uploadModal');
        const lightbox    = document.getElementById('lightbox');
        const confirmModal= document.getElementById('confirmModal');

        if (confirmModal && !confirmModal.hidden) {
            document.getElementById('confirmCancel')?.click();
        } else if (uploadModal && !uploadModal.hidden) {
            document.getElementById('closeUploadModal')?.click();
        } else if (lightbox && !lightbox.hidden) {
            document.getElementById('lightboxClose')?.click();
        }
    }
});

// ─── Navigation progress bar ─────────────────────────────────────────────────
// Shows a thin animated bar at the top during page-to-page navigation
// so the user sees instant feedback instead of a blank wait.
(function () {
    const bar = document.createElement('div');
    bar.id = 'nav-progress';
    document.body.prepend(bar);

    let raf, timer;

    function start() {
        clearTimeout(timer);
        cancelAnimationFrame(raf);
        bar.style.transition = 'none';
        bar.style.width = '0';
        bar.classList.add('nav-progress--active');
        raf = requestAnimationFrame(() => {
            bar.style.transition = 'width 8s cubic-bezier(0.05, 0.6, 0.4, 1)';
            bar.style.width = '82%';
        });
    }

    function finish() {
        cancelAnimationFrame(raf);
        bar.style.transition = 'width 0.15s ease';
        bar.style.width = '100%';
        timer = setTimeout(() => {
            bar.style.transition = 'opacity 0.2s ease, width 0s 0.2s';
            bar.classList.remove('nav-progress--active');
            bar.style.width = '0';
        }, 200);
    }

    // Start on any navigation click
    document.addEventListener('click', function (e) {
        const link = e.target.closest('a[href]');
        if (!link) return;
        const href = link.href;
        // Skip: hash links, external links, blank target, javascript:, download
        if (!href || link.origin !== location.origin) return;
        if (link.target === '_blank' || link.download) return;
        if (link.pathname === location.pathname && link.search === location.search) return;
        start();
    });

    // Start on form submit (full-page POSTs)
    document.addEventListener('submit', function (e) {
        if (e.defaultPrevented) return;
        start();
    });

    // Complete on page show (including back/forward)
    window.addEventListener('pageshow', finish);
})();

// ─── Form submit — loading state ─────────────────────────────────────────────
// Gives instant visual feedback on any form that does a full-page POST.
// Add data-no-loading to a form to opt out.

document.addEventListener('submit', function (e) {
    const form = e.target;
    if (form.dataset.noLoading !== undefined) return;
    const btn = form.querySelector('[type="submit"]:not([data-no-loading])');
    if (!btn || btn.disabled) return;
    const original = btn.innerHTML;
    btn.disabled   = true;
    btn.innerHTML  = `<svg class="spinner" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:middle;margin-right:6px"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>A aguardar…`;
    // Restore if the page comes back (e.g. validation error via redirect)
    window.addEventListener('pageshow', () => {
        btn.disabled  = false;
        btn.innerHTML = original;
    }, { once: true });
});

// ─── Open upload modal from triggers ─────────────────────────────────────────

document.getElementById('openUploadModal')?.addEventListener('click', () => {
    const modal = document.getElementById('uploadModal');
    if (modal) modal.hidden = false;
});

document.getElementById('emptyStateUpload')?.addEventListener('click', () => {
    const modal = document.getElementById('uploadModal');
    if (modal) modal.hidden = false;
});

document.getElementById('closeUploadModal')?.addEventListener('click', () => {
    const modal = document.getElementById('uploadModal');
    if (modal) modal.hidden = true;
});

document.getElementById('cancelUpload')?.addEventListener('click', () => {
    const modal = document.getElementById('uploadModal');
    if (modal) modal.hidden = true;
});
