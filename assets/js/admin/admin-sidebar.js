/**
 * Admin sidebar — mobile drawer only; desktop keeps a static sidebar
 */
(() => {
    if (window.__ADMIN_SIDEBAR_INIT__) return;
    window.__ADMIN_SIDEBAR_INIT__ = true;

    const MOBILE_MQ = window.matchMedia('(max-width: 768px)');

    function closeLabel() {
        const lang = document.documentElement.lang || '';
        return lang.startsWith('fr') ? 'Fermer le menu' : 'Close menu';
    }

    function initAdminSidebar() {
        const sidebar = document.querySelector('.admin-layout .sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const menuBtn = document.getElementById('mobileMenuBtn');
        if (!sidebar || !overlay) return;

        let closeBtn = null;

        function ensureCloseButton() {
            const header = sidebar.querySelector('.sidebar-header');
            if (!header) return;

            if (!MOBILE_MQ.matches) {
                closeBtn?.remove();
                closeBtn = null;
                return;
            }

            if (closeBtn && header.contains(closeBtn)) return;

            closeBtn = document.createElement('button');
            closeBtn.type = 'button';
            closeBtn.className = 'sidebar-close-btn';
            closeBtn.id = 'sidebarCloseBtn';
            closeBtn.setAttribute('aria-label', closeLabel());
            closeBtn.innerHTML = '<span class="material-icons-round">close</span>';
            header.appendChild(closeBtn);
            closeBtn.addEventListener('click', closeSidebar);
        }

        function resetDesktopState() {
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
            document.body.classList.remove('sidebar-open');
            menuBtn?.setAttribute('aria-expanded', 'false');
        }

        function setOpen(open) {
            if (!MOBILE_MQ.matches) {
                resetDesktopState();
                return;
            }

            sidebar.classList.toggle('open', open);
            overlay.classList.toggle('active', open);
            document.body.classList.toggle('sidebar-open', open);
            menuBtn?.setAttribute('aria-expanded', open ? 'true' : 'false');
        }

        function toggleSidebar() {
            if (!MOBILE_MQ.matches) return;
            setOpen(!sidebar.classList.contains('open'));
        }

        function closeSidebar() {
            if (!MOBILE_MQ.matches) {
                resetDesktopState();
                return;
            }
            setOpen(false);
        }

        function syncLayout() {
            ensureCloseButton();
            if (!MOBILE_MQ.matches) resetDesktopState();
        }

        menuBtn?.addEventListener('click', toggleSidebar);
        overlay.addEventListener('click', closeSidebar);

        sidebar.querySelectorAll('.nav-link').forEach((link) => {
            link.addEventListener('click', () => {
                if (MOBILE_MQ.matches) closeSidebar();
            });
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && MOBILE_MQ.matches && sidebar.classList.contains('open')) {
                closeSidebar();
            }
        });

        if (typeof MOBILE_MQ.addEventListener === 'function') {
            MOBILE_MQ.addEventListener('change', syncLayout);
        } else {
            MOBILE_MQ.addListener(syncLayout);
        }

        window.addEventListener('resize', syncLayout);
        syncLayout();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAdminSidebar);
    } else {
        initAdminSidebar();
    }
})();
