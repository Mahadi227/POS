/**
 * Notification Center UI
 */
(() => {
    const t = (k) => (window.NOTIF_I18N || {})[k] || k;
    let tab = 'all';
    let items = [];

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s ?? '';
        return d.innerHTML;
    }

    function severityIcon(s) {
        return { critical: 'report', error: 'error_outline', warning: 'warning_amber', success: 'check_circle' }[s] || 'notifications';
    }

    async function load() {
        const filters = { limit: 80 };
        if (tab === 'unread') filters.unread = 1;
        if (tab === 'archived') filters.archived = 1;
        if (tab === 'pinned') filters.pinned = 1;
        const search = document.getElementById('notifSearch')?.value?.trim();
        const category = document.getElementById('notifCategory')?.value;
        const priority = document.getElementById('notifPriority')?.value;
        if (search) filters.search = search;
        if (category) filters.category = category;
        if (priority) filters.priority = priority;

        try {
            let res;
            if (!navigator.onLine && window.NotificationOffline) {
                items = await NotificationOffline.getAll();
            } else {
                res = await NotificationAPI.list(filters);
                if (res.status === 'success') {
                    items = res.data || [];
                    if (window.NotificationOffline) await NotificationOffline.saveAll(items);
                    const badge = document.getElementById('unreadTabBadge');
                    if (badge) {
                        const c = res.unread_count || 0;
                        badge.textContent = c;
                        badge.hidden = c === 0;
                    }
                }
            }
            render();
        } catch (e) {
            console.warn('Notification load failed', e);
        }
    }

    function render() {
        const list = document.getElementById('notifList');
        const empty = document.getElementById('notifEmpty');
        if (!list) return;

        if (!items.length) {
            list.innerHTML = '';
            empty?.classList.remove('hidden');
            return;
        }
        empty?.classList.add('hidden');

        list.innerHTML = items.map((n) => `
            <li class="notif-item notif-item--${n.severity}${n.is_read ? '' : ' is-unread'}${n.is_pinned ? ' is-pinned' : ''}" data-id="${n.id}">
                <span class="material-icons-round notif-item__icon">${severityIcon(n.severity)}</span>
                <div class="notif-item__body">
                    <strong>${esc(n.title)}</strong>
                    <p>${esc(n.message)}</p>
                    <small>${esc(n.created_at)} · ${esc(n.category)}</small>
                </div>
                <div class="notif-item__actions">
                    ${!n.is_read ? `<button type="button" class="notif-icon-btn" data-action="read" title="${esc(t('mark_read'))}"><span class="material-icons-round">done</span></button>` : ''}
                    <button type="button" class="notif-icon-btn" data-action="pin" title="${esc(t('pin'))}"><span class="material-icons-round">${n.is_pinned ? 'push_pin' : 'push_pin'}</span></button>
                    <button type="button" class="notif-icon-btn" data-action="archive" title="${esc(t('archive'))}"><span class="material-icons-round">archive</span></button>
                </div>
            </li>`).join('');
    }

    async function handleAction(e) {
        const btn = e.target.closest('[data-action]');
        if (!btn) return;
        const li = btn.closest('.notif-item');
        const id = parseInt(li?.dataset.id || '0', 10);
        if (!id) return;
        const action = btn.dataset.action;
        if (action === 'read') await NotificationAPI.markRead([id]);
        if (action === 'pin') await NotificationAPI.pin(id, !li.classList.contains('is-pinned'));
        if (action === 'archive') await NotificationAPI.archive([id]);
        load();
    }

    async function loadCategories() {
        const res = await NotificationAPI.meta();
        const sel = document.getElementById('notifCategory');
        if (res.status === 'success' && sel) {
            (res.data?.categories || []).forEach((c) => {
                const opt = document.createElement('option');
                opt.value = c.slug;
                opt.textContent = document.documentElement.lang === 'fr' ? c.name_fr : c.name_en;
                sel.appendChild(opt);
            });
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.notif-tab').forEach((btn) => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.notif-tab').forEach((b) => b.classList.remove('is-active'));
                btn.classList.add('is-active');
                tab = btn.dataset.tab || 'all';
                load();
            });
        });
        document.getElementById('notifSearch')?.addEventListener('input', () => load());
        document.getElementById('notifCategory')?.addEventListener('change', () => load());
        document.getElementById('notifPriority')?.addEventListener('change', () => load());
        document.getElementById('notifList')?.addEventListener('click', handleAction);
        document.getElementById('notifMarkAllRead')?.addEventListener('click', async () => {
            await NotificationAPI.markAllRead();
            load();
        });
        loadCategories();
        load();
        if (window.NotificationOffline) NotificationOffline.syncWithServer();
        setInterval(load, 30000);
    });
})();
