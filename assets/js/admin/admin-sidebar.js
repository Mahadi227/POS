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

            sidebar.classList.remove('is-dragging');
            sidebar.style.transform = '';
            sidebar.style.transition = '';
            overlay.style.opacity = '';
            overlay.style.transition = '';
            overlay.style.pointerEvents = '';

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

        bindSidebarSwipe({ sidebar, overlay, isMobile: () => MOBILE_MQ.matches, setOpen, isOpen: () => sidebar.classList.contains('open') });
    }

    function bindSidebarSwipe({ sidebar, overlay, isMobile, setOpen, isOpen }) {
        const EDGE_PX = 32;
        const OPEN_PX = 64;
        const CLOSE_PX = 56;
        const VELOCITY = 0.35;

        let startX = 0;
        let startY = 0;
        let currentX = 0;
        let startTime = 0;
        let dragging = false;
        let mode = null; // 'open' | 'close'

        function blocked() {
            return !isMobile() || document.body.classList.contains('ad-notif-open');
        }

        function clearDrag() {
            dragging = false;
            mode = null;
            currentX = 0;
            sidebar.classList.remove('is-dragging');
            sidebar.style.transition = '';
            overlay.style.transition = '';
            if (!isOpen()) {
                sidebar.style.transform = '';
                overlay.style.opacity = '';
                overlay.classList.remove('active');
            } else {
                sidebar.style.transform = '';
                overlay.style.opacity = '';
            }
        }

        function onTouchStart(e) {
            if (blocked()) return;
            const touch = e.touches[0];
            const x = touch.clientX;
            const y = touch.clientY;
            const open = isOpen();
            const onSidebar = sidebar.contains(e.target);
            const fromEdge = !open && x <= EDGE_PX;
            const onOverlay = open && e.target === overlay;

            if (!fromEdge && !onSidebar && !onOverlay) return;
            if (open && onSidebar && e.target.closest('.nav-menu') && e.target.closest('.nav-menu').scrollTop > 0) {
                return;
            }

            startX = x;
            startY = y;
            currentX = 0;
            startTime = Date.now();
            dragging = true;
            mode = open ? 'close' : 'open';
            sidebar.classList.add('is-dragging');
        }

        function onTouchMove(e) {
            if (!dragging || blocked()) return;
            const touch = e.touches[0];
            const deltaX = touch.clientX - startX;
            const deltaY = touch.clientY - startY;

            if (Math.abs(deltaY) > Math.abs(deltaX) && Math.abs(deltaX) < 14) return;

            currentX = deltaX;

            if (mode === 'open' && deltaX > 0) {
                e.preventDefault();
                const w = sidebar.offsetWidth + 12;
                const pull = Math.min(deltaX, w);
                sidebar.style.transform = `translateX(calc(-100% - 12px + ${pull}px))`;
                overlay.classList.add('active');
                overlay.style.pointerEvents = 'none';
                overlay.style.opacity = String(Math.min(1, pull / w));
            } else if (mode === 'close' && deltaX < 0) {
                e.preventDefault();
                const pull = Math.max(deltaX, -sidebar.offsetWidth);
                sidebar.style.transform = `translateX(${pull}px)`;
                const w = sidebar.offsetWidth;
                overlay.style.opacity = String(Math.max(0, 1 + pull / w));
            }
        }

        function onTouchEnd() {
            if (!dragging || blocked()) return;
            const elapsed = Math.max(1, Date.now() - startTime);
            const velocity = currentX / elapsed;

            if (mode === 'open') {
                if (currentX >= OPEN_PX || (currentX > 24 && velocity > VELOCITY)) {
                    setOpen(true);
                } else {
                    clearDrag();
                }
            } else if (mode === 'close') {
                if (currentX <= -CLOSE_PX || (currentX < -20 && velocity < -VELOCITY)) {
                    setOpen(false);
                } else {
                    setOpen(true);
                }
            }

            dragging = false;
            mode = null;
            sidebar.classList.remove('is-dragging');
            overlay.style.pointerEvents = '';
        }

        document.addEventListener('touchstart', onTouchStart, { passive: true });
        document.addEventListener('touchmove', onTouchMove, { passive: false });
        document.addEventListener('touchend', onTouchEnd);
        document.addEventListener('touchcancel', clearDrag);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAdminSidebar);
    } else {
        initAdminSidebar();
    }
})();
