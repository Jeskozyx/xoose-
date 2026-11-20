document.addEventListener('DOMContentLoaded', () => {
    // Sidebar toggle (Desktop collapse/expand & Mobile drawer)
    const sidebar = document.getElementById('app-sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    const toggleBtns = document.querySelectorAll('[data-sidebar-toggle]');
    const closeBtn = document.getElementById('sidebar-close-btn');

    const isDesktop = () => window.innerWidth >= 1024;

    // Load persisted desktop state
    const savedDesktopState = localStorage.getItem('xoose_sidebar_collapsed');
    if (savedDesktopState === 'true' && isDesktop() && sidebar) {
        sidebar.classList.add('sidebar-collapsed');
    }

    const toggleSidebar = () => {
        if (!sidebar) return;
        if (isDesktop()) {
            const isCollapsed = sidebar.classList.toggle('sidebar-collapsed');
            localStorage.setItem('xoose_sidebar_collapsed', isCollapsed ? 'true' : 'false');
        } else {
            const isOpen = !sidebar.classList.contains('-translate-x-full');
            if (isOpen) {
                sidebar.classList.add('-translate-x-full');
                if (overlay) overlay.classList.add('hidden');
            } else {
                sidebar.classList.remove('-translate-x-full');
                if (overlay) overlay.classList.remove('hidden');
            }
        }
    };

    const closeMobileSidebar = () => {
        if (!isDesktop() && sidebar) {
            sidebar.classList.add('-translate-x-full');
            if (overlay) overlay.classList.add('hidden');
        }
    };

    toggleBtns.forEach((btn) => btn.addEventListener('click', toggleSidebar));
    if (closeBtn) closeBtn.addEventListener('click', closeMobileSidebar);
    if (overlay) overlay.addEventListener('click', closeMobileSidebar);

    // Shortcut Keyboard: Ctrl+B / Cmd+B untuk toggle sidebar
    document.addEventListener('keydown', (e) => {
        if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'b') {
            e.preventDefault();
            toggleSidebar();
        }
    });

    // Sidebar accordion groups (Product, Customers, Income)
    const groups = document.querySelectorAll('.sidebar-group');
    groups.forEach((group) => {
        const toggle = group.querySelector('.sidebar-toggle');
        const panel = group.querySelector('.submenu-panel');
        if (!toggle || !panel) return;

        toggle.addEventListener('click', () => {
            const isExpanded = group.classList.toggle('is-expanded');
            panel.classList.toggle('is-open', isExpanded);
            toggle.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
        });
    });

    // Dashboard Section Collapse / Expand (Folders & Workflows)
    const sectionBtns = document.querySelectorAll('.section-collapse-btn');
    sectionBtns.forEach((btn) => {
        const targetSelector = btn.dataset.target;
        const targetEl = targetSelector ? document.querySelector(targetSelector) : null;
        if (!targetEl) return;

        const label = btn.querySelector('.collapse-label');

        btn.addEventListener('click', () => {
            const isCollapsed = targetEl.classList.toggle('is-collapsed');
            btn.setAttribute('aria-expanded', isCollapsed ? 'false' : 'true');
            if (label) {
                label.textContent = isCollapsed ? 'Expand' : 'Collapse';
            }
        });
    });
});
