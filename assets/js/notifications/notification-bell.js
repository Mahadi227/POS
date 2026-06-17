/**
 * Admin notification bell + dashboard alerts widget (enterprise).
 */
window.NotificationBell = (() => {
    const POLL_MS = 20000;
    let items = [];
    let pollTimer = null;
    let filter = 'all';

    const t = (k) => (window.ADMIN_I18N || window.NOTIF_I18N || {})[k] || k;

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s ?? '';
        return d.innerHTML;
    }

    function apiBase() {
        return window.ADMIN_CONFIG?.api?.base || '../../api/v1/index.php';
    }

    function formatDate(iso) {
        if (!iso) return '';
        if (typeof AdminAPI?.formatDate === 'function') return AdminAPI.formatDate(iso);
        try {
            return new Date(iso).toLocaleString(window.ADMIN_CONFIG?.locale || undefined);
        } catch {
            return iso;
        }
    }

    function severityIcon(s) {
        return { critical: 'report', error: 'error_outline', warning: 'warning_amber', success: 'check_circle' }[s] || 'notifications';
    }

    function severityClass(s) {
        return { warning: 'ad-notif--warn', error: 'ad-notif--error', critical: 'ad-notif--critical' }[s] || 'ad-notif--info';
    }

    function moduleIcon(mod) {
        return {
            cash_register: 'payments',
            warehouse: 'warehouse',
            inventory: 'inventory_2',
            pos: 'point_of_sale',
            accounting: 'receipt_long',
            users: 'people',
            system: 'settings',
        }[mod] || 'notifications';
    }

    function filteredItems() {
        if (filter === 'unread') return items.filter((n) => !n.is_read);
        if (filter === 'critical') {
            return items.filter((n) => n.priority === 'critical' || n.severity === 'critical' || n.severity === 'error');
        }
        return items;
    }

    function updateBadge(count) {
        const badge = document.getElementById('adminNotifBadge');
        const label = document.getElementById('adminNotifUnreadLabel');
        if (badge) {
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : String(count);
                badge.hidden = false;
            } else {
                badge.hidden = true;
            }
        }
        if (label) {
            if (count > 0) {
                label.textContent = `${count} ${t('unread')}`;
                label.hidden = false;
            } else {
                label.hidden = true;
            }
        }
    }

    function renderDropdown() {
        const list = document.getElementById('adminNotifList');
        if (!list) return;
        const visible = filteredItems();

        if (!visible.length) {
            list.innerHTML = `<li class="ad-notif-empty">${esc(t('notif_empty'))}</li>`;
            return;
        }

        list.innerHTML = visible.slice(0, 20).map((n) => {
            const href = n.action_url || '../notifications/notification_center.php';
            const cat = (n.category || n.module || '').replace(/_/g, ' ');
            return `<li class="ad-notif-item ${severityClass(n.severity)}${n.is_read ? '' : ' is-unread'}" data-id="${n.id}">
                <a href="${esc(href)}" class="ad-notif-link" data-notif-id="${n.id}">
                    <span class="material-icons-round ad-notif-icon">${severityIcon(n.severity)}</span>
                    <span class="ad-notif-body">
                        <span class="ad-notif-meta">
                            <span class="ad-notif-cat">${esc(cat)}</span>
                            ${!n.is_read ? '<span class="ad-notif-dot"></span>' : ''}
                        </span>
                        <strong>${esc(n.title || n.message)}</strong>
                        <small>${esc(n.message)}</small>
                        <small class="ad-notif-time">${esc(formatDate(n.created_at))}</small>
                    </span>
                </a>
            </li>`;
        }).join('');
    }

    function renderWidget() {
        const root = document.getElementById('notifAlertsWidget');
        if (!root) return;

        const alerts = items.filter((n) => {
            if (n.is_read) return false;
            return n.severity === 'warning' || n.severity === 'error' || n.severity === 'critical'
                || n.priority === 'high' || n.priority === 'critical';
        }).slice(0, 6);

        if (!alerts.length) {
            root.innerHTML = `<p class="ad-empty-row">${esc(t('notif_empty'))}</p>`;
            return;
        }

        root.innerHTML = `<ul class="ad-notif-alerts">${alerts.map((n) => {
            const href = n.action_url || '../notifications/notification_center.php';
            return `<li class="${severityClass(n.severity)}">
                <span class="material-icons-round">${moduleIcon(n.module)}</span>
                <div class="ad-notif-alerts__text">
                    <strong>${esc(n.title || n.message)}</strong>
                    <small>${esc(formatDate(n.created_at))}</small>
                </div>
                <a href="${esc(href)}" class="ad-notif-alerts__link material-icons-round" title="${esc(t('view_all'))}">chevron_right</a>
            </li>`;
        }).join('')}</ul>`;
    }

    async function fetchList() {
        try {
            let res;
            if (typeof AdminAPI?.getNotifications === 'function') {
                res = await AdminAPI.getNotifications({ limit: 40 });
            } else {
                const url = `${apiBase()}?request=notifications/list&limit=40`;
                const r = await fetch(url, { credentials: 'same-origin' });
                res = await r.json();
            }

            if (res.status === 'success') {
                items = res.data || [];
                const unread = res.unread_count ?? items.filter((n) => !n.is_read).length;
                updateBadge(unread);
                renderDropdown();
                renderWidget();
                if (window.NotificationOffline) await NotificationOffline.saveAll(items);
                maybeBrowserNotify(items);
            }
        } catch (e) {
            if (window.NotificationOffline) {
                items = await NotificationOffline.getAll();
                updateBadge(items.filter((n) => !n.is_read).length);
                renderDropdown();
                renderWidget();
            }
        }
    }

    let lastBrowserId = null;
    function maybeBrowserNotify(list) {
        if (!('Notification' in window) || Notification.permission !== 'granted') return;
        const unread = list.filter((n) => !n.is_read);
        const latest = unread.find((n) => n.severity === 'critical' || n.priority === 'critical');
        if (!latest || latest.id === lastBrowserId) return;
        new Notification(latest.title || 'RetailPOS', { body: latest.message, tag: 'retailpos-' + latest.id });
        lastBrowserId = latest.id;
    }

    async function markAllRead() {
        if (typeof AdminAPI?.markAllNotificationsRead === 'function') {
            await AdminAPI.markAllNotificationsRead();
        } else {
            await fetch(`${apiBase()}?request=notifications/mark-all-read`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: '{}',
            });
        }
        fetchList();
    }

    async function markRead(id) {
        if (!id) return;
        if (typeof AdminAPI?.markNotificationsRead === 'function') {
            await AdminAPI.markNotificationsRead([id]);
        }
        const item = items.find((n) => n.id === id);
        if (item) item.is_read = true;
        updateBadge(items.filter((n) => !n.is_read).length);
        renderDropdown();
        renderWidget();
    }

    function isMobileView() {
        return window.matchMedia('(max-width: 768px)').matches;
    }

    function openPanel() {
        const btn = document.getElementById('adminNotifBtn');
        const panel = document.getElementById('adminNotifPanel');
        const backdrop = document.getElementById('adminNotifBackdrop');
        if (!btn || !panel) return;
        panel.classList.add('is-open');
        panel.classList.remove('is-expanded');
        btn.setAttribute('aria-expanded', 'true');
        if (isMobileView()) {
            document.body.classList.add('ad-notif-open');
            if (backdrop) {
                backdrop.classList.add('is-visible');
                backdrop.setAttribute('aria-hidden', 'false');
            }
        }
        fetchList();
    }

    function closePanel() {
        const btn = document.getElementById('adminNotifBtn');
        const panel = document.getElementById('adminNotifPanel');
        const backdrop = document.getElementById('adminNotifBackdrop');
        if (!btn || !panel) return;
        panel.classList.remove('is-open', 'is-dragging', 'is-expanded');
        panel.style.transform = '';
        panel.style.transition = '';
        btn.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('ad-notif-open');
        if (backdrop) {
            backdrop.classList.remove('is-visible');
            backdrop.setAttribute('aria-hidden', 'true');
            backdrop.style.opacity = '';
        }
    }

    function bindSheetGestures(panel) {
        const SWIPE_CLOSE_PX = 72;
        const SWIPE_EXPAND_PX = 56;
        const SWIPE_VELOCITY = 0.4;
        let sheetState = 'half';
        let startY = 0;
        let startX = 0;
        let currentY = 0;
        let startTime = 0;
        let dragging = false;
        let canSwipe = false;

        const swipeZone = panel.querySelector('.ad-notif-panel__head') || panel;
        const list = panel.querySelector('.ad-notif-list');

        function snapHalf() {
            sheetState = 'half';
            panel.classList.remove('is-expanded');
            panel.style.transform = '';
            panel.style.transition = '';
        }

        function snapFull() {
            sheetState = 'full';
            panel.classList.add('is-expanded');
            panel.style.transform = '';
            panel.style.transition = '';
        }

        function resetDrag() {
            dragging = false;
            canSwipe = false;
            panel.classList.remove('is-dragging');
            panel.style.transition = '';
            const backdrop = document.getElementById('adminNotifBackdrop');
            if (backdrop) {
                backdrop.style.opacity = '';
                backdrop.style.transition = '';
            }
            if (panel.classList.contains('is-open')) {
                panel.style.transform = '';
            }
        }

        function animateSnap() {
            panel.style.transition = 'transform 0.24s cubic-bezier(0.4, 0, 0.2, 1)';
            panel.style.transform = '';
            const backdrop = document.getElementById('adminNotifBackdrop');
            if (backdrop) {
                backdrop.style.transition = 'opacity 0.24s ease';
                backdrop.style.opacity = '';
            }
            setTimeout(() => {
                panel.style.transition = '';
                if (backdrop) backdrop.style.transition = '';
            }, 240);
        }

        function onTouchStart(e) {
            if (!isMobileView() || !panel.classList.contains('is-open')) return;
            const touch = e.touches[0];
            const target = e.target;
            const onHeader = swipeZone.contains(target) || target.closest('.ad-notif-panel__handle');
            const listAtTop = !list || list.scrollTop <= 0;
            if (!onHeader && !listAtTop) return;

            startY = touch.clientY;
            startX = touch.clientX;
            currentY = 0;
            startTime = Date.now();
            dragging = true;
            canSwipe = true;
            panel.classList.add('is-dragging');
        }

        function onTouchMove(e) {
            if (!dragging || !canSwipe) return;
            const touch = e.touches[0];
            const deltaY = touch.clientY - startY;
            const deltaX = touch.clientX - startX;

            if (Math.abs(deltaX) > Math.abs(deltaY) && Math.abs(deltaY) < 10) return;

            currentY = deltaY;
            e.preventDefault();

            if (sheetState === 'full' && deltaY < 0) {
                panel.style.transform = `translateY(${deltaY * 0.25}px)`;
                return;
            }

            if (sheetState === 'half' && deltaY < 0) {
                const pull = Math.max(deltaY, -120);
                panel.style.transform = `translateY(${pull}px)`;
                return;
            }

            if (deltaY > 0) {
                panel.style.transform = `translateY(${deltaY}px)`;
                const backdrop = document.getElementById('adminNotifBackdrop');
                if (backdrop) {
                    backdrop.style.opacity = String(Math.max(0.12, 1 - deltaY / 300));
                }
            }
        }

        function onTouchEnd() {
            if (!dragging || !canSwipe) return;
            const elapsed = Math.max(1, Date.now() - startTime);
            const velocity = currentY / elapsed;

            if (sheetState === 'half') {
                if (currentY <= -SWIPE_EXPAND_PX || (currentY < -20 && velocity < -SWIPE_VELOCITY)) {
                    snapFull();
                    animateSnap();
                } else if (currentY >= SWIPE_CLOSE_PX || (currentY > 24 && velocity > SWIPE_VELOCITY)) {
                    panel.style.transition = 'transform 0.22s ease';
                    panel.style.transform = 'translateY(100%)';
                    const backdrop = document.getElementById('adminNotifBackdrop');
                    if (backdrop) {
                        backdrop.style.transition = 'opacity 0.22s ease';
                        backdrop.style.opacity = '0';
                    }
                    sheetState = 'half';
                    setTimeout(closePanel, 220);
                } else {
                    snapHalf();
                    animateSnap();
                }
            } else if (sheetState === 'full') {
                if (currentY >= SWIPE_CLOSE_PX * 1.6 || (currentY > 80 && velocity > SWIPE_VELOCITY * 1.2)) {
                    panel.style.transition = 'transform 0.22s ease';
                    panel.style.transform = 'translateY(100%)';
                    const backdrop = document.getElementById('adminNotifBackdrop');
                    if (backdrop) {
                        backdrop.style.transition = 'opacity 0.22s ease';
                        backdrop.style.opacity = '0';
                    }
                    sheetState = 'half';
                    setTimeout(closePanel, 220);
                } else if (currentY >= SWIPE_CLOSE_PX || (currentY > 20 && velocity > SWIPE_VELOCITY)) {
                    snapHalf();
                    animateSnap();
                } else {
                    snapFull();
                    animateSnap();
                }
            }

            dragging = false;
            canSwipe = false;
            panel.classList.remove('is-dragging');
        }

        panel.addEventListener('touchstart', onTouchStart, { passive: true });
        panel.addEventListener('touchmove', onTouchMove, { passive: false });
        panel.addEventListener('touchend', onTouchEnd);
        panel.addEventListener('touchcancel', () => {
            resetDrag();
            if (panel.classList.contains('is-expanded')) snapFull();
            else snapHalf();
        });

        const handle = panel.querySelector('.ad-notif-panel__handle');
        handle?.addEventListener('dblclick', () => {
            if (!isMobileView() || !panel.classList.contains('is-open')) return;
            if (sheetState === 'full') snapHalf();
            else snapFull();
        });
    }

    function bindUi() {
        const btn = document.getElementById('adminNotifBtn');
        const panel = document.getElementById('adminNotifPanel');
        const backdrop = document.getElementById('adminNotifBackdrop');
        if (!btn || !panel) return;

        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            if (panel.classList.contains('is-open')) {
                closePanel();
            } else {
                openPanel();
            }
        });

        backdrop?.addEventListener('click', closePanel);
        document.getElementById('adminNotifClose')?.addEventListener('click', (e) => {
            e.preventDefault();
            closePanel();
        });

        document.addEventListener('click', (e) => {
            if (isMobileView()) return;
            if (!e.target.closest('.ad-notif-wrap')) {
                closePanel();
            }
        });
        panel.addEventListener('click', (e) => e.stopPropagation());

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && panel.classList.contains('is-open')) {
                closePanel();
            }
        });

        window.addEventListener('resize', () => {
            if (!isMobileView() && backdrop) {
                backdrop.classList.remove('is-visible');
                document.body.classList.remove('ad-notif-open');
            }
        });

        document.getElementById('adminNotifMarkRead')?.addEventListener('click', (e) => {
            e.preventDefault();
            markAllRead();
        });

        panel.querySelectorAll('.ad-notif-tab').forEach((tab) => {
            tab.addEventListener('click', () => {
                panel.querySelectorAll('.ad-notif-tab').forEach((b) => b.classList.remove('is-active'));
                tab.classList.add('is-active');
                filter = tab.dataset.filter || 'all';
                renderDropdown();
            });
        });

        document.getElementById('adminNotifList')?.addEventListener('click', (e) => {
            const link = e.target.closest('[data-notif-id]');
            if (link) markRead(parseInt(link.dataset.notifId, 10));
        });

        if ('Notification' in window && Notification.permission === 'default') {
            btn.addEventListener('dblclick', () => Notification.requestPermission());
        }

        bindSheetGestures(panel);
    }

    function start() {
        if (!document.getElementById('adminNotifBtn')) return;
        bindUi();
        fetchList();
        pollTimer = setInterval(fetchList, POLL_MS);
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') fetchList();
        });
        document.addEventListener('store-switched', fetchList);
        window.addEventListener('online', () => {
            if (window.NotificationOffline) NotificationOffline.syncWithServer().then(fetchList);
        });
    }

    function stop() {
        if (pollTimer) clearInterval(pollTimer);
    }

    return { start, stop, fetchList, markAllRead, openPanel, closePanel };
})();

document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('adminNotifBtn')) NotificationBell.start();
});
