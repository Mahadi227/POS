/**
 * Admin — real-time cash register notifications (polling)
 */
window.AdminNotifications = (() => {
    const STORAGE_KEY = 'admin_cr_notifications_seen';
    const POLL_MS = 30000;
    let pollTimer = null;
    let items = [];

    function i18n() {
        return window.ADMIN_I18N || {};
    }

    function t(key) {
        return i18n()[key] || key;
    }

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s ?? '';
        return d.innerHTML;
    }

    function getSeenAt() {
        return localStorage.getItem(STORAGE_KEY) || '';
    }

    function setSeenAt(iso) {
        localStorage.setItem(STORAGE_KEY, iso || new Date().toISOString());
    }

    function severityIcon(severity) {
        if (severity === 'warning') return 'warning_amber';
        if (severity === 'error') return 'error_outline';
        return 'notifications';
    }

    function severityClass(severity) {
        if (severity === 'warning') return 'ad-notif--warn';
        if (severity === 'error') return 'ad-notif--error';
        return 'ad-notif--info';
    }

    function unreadCount() {
        const seen = getSeenAt();
        if (!seen) return items.length;
        return items.filter((n) => n.created_at > seen).length;
    }

    function updateBadge() {
        const badge = document.getElementById('adminNotifBadge');
        const count = unreadCount();
        if (!badge) return;
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : String(count);
            badge.hidden = false;
        } else {
            badge.hidden = true;
        }
    }

    function renderDropdown() {
        const list = document.getElementById('adminNotifList');
        if (!list) return;

        if (!items.length) {
            list.innerHTML = `<li class="ad-notif-empty">${esc(t('cr_notif_empty'))}</li>`;
            return;
        }

        list.innerHTML = items.slice(0, 20).map((n) => {
            const isUnread = !getSeenAt() || n.created_at > getSeenAt();
            const href = n.register_id
                ? `cash_registers/register_details.php?id=${n.register_id}`
                : 'cash_registers/dashboard.php';
            return `<li class="ad-notif-item ${severityClass(n.severity)}${isUnread ? ' is-unread' : ''}">
                <a href="${href}" class="ad-notif-link">
                    <span class="material-icons-round ad-notif-icon">${severityIcon(n.severity)}</span>
                    <span class="ad-notif-body">
                        <strong>${esc(n.message)}</strong>
                        <small>${esc(AdminAPI.formatDate(n.created_at))}${n.register_name ? ' · ' + esc(n.register_name) : ''}</small>
                    </span>
                </a>
            </li>`;
        }).join('');
    }

    function renderWidget() {
        const root = document.getElementById('crAlertsWidget');
        if (!root) return;

        const alerts = items.filter((n) => n.severity === 'warning' || n.action === 'cash_difference').slice(0, 5);
        if (!alerts.length) {
            root.innerHTML = `<p class="ad-empty-row">${esc(t('cr_notif_empty'))}</p>`;
            return;
        }

        root.innerHTML = `<ul class="ad-cr-alerts">${alerts.map((n) => `
            <li class="${severityClass(n.severity)}">
                <span class="material-icons-round">${severityIcon(n.severity)}</span>
                <div><strong>${esc(n.message)}</strong><small>${esc(AdminAPI.formatDate(n.created_at))}</small></div>
            </li>`).join('')}</ul>
            <a href="cash_registers/reconciliation.php" class="ad-inline-link">${esc(t('cr_nav_reconciliation'))}</a>`;
    }

    async function fetchNotifications() {
        if (typeof AdminAPI?.getCashRegisterNotifications !== 'function') return;
        try {
            const res = await AdminAPI.getCashRegisterNotifications();
            if (res.status === 'success') {
                items = res.data || [];
                updateBadge();
                renderDropdown();
                renderWidget();
            }
        } catch (e) {
            console.warn('Notifications poll failed', e);
        }
    }

    function bindUi() {
        const btn = document.getElementById('adminNotifBtn');
        const panel = document.getElementById('adminNotifPanel');
        if (!btn || !panel) return;

        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const open = panel.classList.toggle('is-open');
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open) {
                setSeenAt(new Date().toISOString());
                updateBadge();
                panel.querySelectorAll('.ad-notif-item').forEach((el) => el.classList.remove('is-unread'));
            }
        });

        document.addEventListener('click', () => {
            panel.classList.remove('is-open');
            btn.setAttribute('aria-expanded', 'false');
        });
        panel.addEventListener('click', (e) => e.stopPropagation());

        document.getElementById('adminNotifMarkRead')?.addEventListener('click', () => {
            setSeenAt(new Date().toISOString());
            updateBadge();
            panel.querySelectorAll('.ad-notif-item').forEach((el) => el.classList.remove('is-unread'));
        });
    }

    function start() {
        bindUi();
        fetchNotifications();
        pollTimer = setInterval(fetchNotifications, POLL_MS);
        document.addEventListener('store-switched', fetchNotifications);
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') fetchNotifications();
        });
    }

    function stop() {
        if (pollTimer) clearInterval(pollTimer);
    }

    return { start, stop, fetchNotifications };
})();

document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('adminNotifBtn')) {
        AdminNotifications.start();
    }
});
