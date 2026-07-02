/**

 * Warehouse notifications page — warehouse/inventory alerts only, scoped API

 */

document.addEventListener('DOMContentLoaded', () => {

    const listEl = document.getElementById('whNotifList');

    if (!listEl) return;



    const {

        t, esc, showError, hideError, setMigrationHint, updateLastUpdated,

        notifIcon, notifSeverityTone, refreshNotifBadge,

    } = WarehouseUI;



    const state = {

        tab: 'all',

        page: 1,

        limit: 25,

        items: [],

        total: 0,

        unread: 0,

        stats: { total: 0, unread: 0, critical: 0, today: 0 },

        searchTimer: null,

        categoryIcons: {},

    };



    const els = {

        loading: document.getElementById('whNotifLoading'),

        empty: document.getElementById('whNotifEmpty'),

        emptyText: document.getElementById('whNotifEmptyText'),

        search: document.getElementById('whNotifSearch'),

        module: document.getElementById('whNotifModule'),

        category: document.getElementById('whNotifCategory'),

        priority: document.getElementById('whNotifPriority'),

        markAll: document.getElementById('whNotifMarkAllBtn'),

        refresh: document.getElementById('whNotifRefreshBtn'),

        unreadBadge: document.getElementById('whNotifUnreadBadge'),

        heroMeta: document.getElementById('whNotifHeroMeta'),

        statTotal: document.getElementById('whNotifStatTotal'),

        statUnread: document.getElementById('whNotifStatUnread'),

        statCritical: document.getElementById('whNotifStatCritical'),

        statToday: document.getElementById('whNotifStatToday'),

        pagination: document.getElementById('whNotifPagination'),

        prev: document.getElementById('whNotifPrev'),

        next: document.getElementById('whNotifNext'),

        pageMeta: document.getElementById('whNotifPageMeta'),

    };



    function priorityLabel(priority) {

        const map = {

            low: t('wh_notif_priority_low'),

            normal: t('wh_notif_priority_normal'),

            high: t('wh_notif_priority_high'),

            critical: t('wh_notif_priority_critical'),

        };

        return map[priority] || priority || '—';

    }



    function categoryLabel(slug) {

        if (!slug) return '—';

        return slug.replace(/_/g, ' ');

    }



    function moduleLabel(module) {

        if (module === 'warehouse') return t('wh_notif_module_wh');

        if (module === 'inventory') return t('wh_notif_module_inventory');

        return module || '—';

    }



    function buildFilters() {

        const filters = {

            limit: state.limit,

            offset: (state.page - 1) * state.limit,

        };

        if (state.tab === 'unread') filters.unread = 1;

        if (state.tab === 'archived') filters.archived = 1;

        if (state.tab === 'pinned') filters.pinned = 1;

        const mod = els.module?.value?.trim();

        if (mod) filters.module = mod;

        const cat = els.category?.value?.trim();

        if (cat) filters.category = cat;

        const pri = els.priority?.value?.trim();

        if (pri) filters.priority = pri;

        const q = els.search?.value?.trim();

        if (q) filters.search = q;

        return filters;

    }



    function setLoading(on) {

        if (els.loading) els.loading.hidden = !on;

        if (on) listEl.innerHTML = '';

    }



    function updateHeaderBadge() {

        if (els.unreadBadge) {

            els.unreadBadge.textContent = String(state.unread);

            els.unreadBadge.hidden = state.unread <= 0;

        }

        refreshNotifBadge();

    }



    function updateStats(stats) {

        const s = stats || state.stats;

        if (els.statTotal) { els.statTotal.textContent = String(s.total ?? state.total); els.statTotal.classList.remove('is-loading'); }

        if (els.statUnread) { els.statUnread.textContent = String(s.unread ?? state.unread); els.statUnread.classList.remove('is-loading'); }

        if (els.statCritical) { els.statCritical.textContent = String(s.critical ?? 0); els.statCritical.classList.remove('is-loading'); }

        if (els.statToday) { els.statToday.textContent = String(s.today ?? 0); els.statToday.classList.remove('is-loading'); }

        if (els.heroMeta) {

            const scope = window.WH_PAGE?.warehouseName;

            const scopeText = scope ? `${scope} · ` : '';

            els.heroMeta.textContent = `${scopeText}${s.total ?? state.total} ${t('records')} · ${s.unread ?? state.unread} ${t('wh_notif_stat_unread').toLowerCase()}`;

        }

        updateHeaderBadge();

    }



    function updatePagination() {

        const hasMore = state.page * state.limit < state.total;

        const show = state.total > 0 || state.page > 1;

        if (els.pagination) els.pagination.hidden = !show;

        if (els.prev) els.prev.disabled = state.page <= 1;

        if (els.next) els.next.disabled = !hasMore;

        if (els.pageMeta) {

            const pages = Math.max(1, Math.ceil(state.total / state.limit));

            els.pageMeta.textContent = `${t('records')}: ${state.total} · ${state.page}/${pages}`;

        }

    }



    function renderList(items) {

        if (!items.length) {

            listEl.innerHTML = '';

            if (els.empty) {

                els.empty.hidden = false;

                if (els.emptyText) {

                    els.emptyText.textContent = state.tab === 'unread' ? t('wh_notif_empty_unread') : t('wh_notif_empty');

                }

            }

            return;

        }

        if (els.empty) els.empty.hidden = true;



        listEl.innerHTML = items.map((n) => {

            const unread = !n.is_read;

            const pinned = n.is_pinned;

            const tone = notifSeverityTone(n.severity, n.priority);

            const icon = notifIcon(n, state.categoryIcons);

            const link = n.action_url ? `<a href="${esc(n.action_url)}" class="wh-notif-item__link">${esc(t('wh_notif_open_link'))}</a>` : '';

            const whChip = n.warehouse_name

                ? `<span class="wh-notif-chip wh-notif-chip--wh">${esc(n.warehouse_name)}</span>`

                : '';

            return `

            <li class="wh-notif-item wh-notif-item--${esc(n.severity || 'info')}${unread ? ' is-unread' : ''}${pinned ? ' is-pinned' : ''}" data-id="${n.id}">

                <div class="wh-notif-item__icon-wrap wh-notif-item__icon-wrap--${tone}" aria-hidden="true">

                    <span class="material-icons-round">${icon}</span>

                </div>

                <div class="wh-notif-item__body">

                    <div class="wh-notif-item__head">

                        <strong>${esc(n.title || '')}</strong>

                        <time>${esc(AdminAPI.formatDate(n.created_at))}</time>

                    </div>

                    <p>${esc(n.message || '')}</p>

                    <div class="wh-notif-item__meta">

                        <span class="wh-notif-chip wh-notif-chip--module">

                            <span class="material-icons-round" aria-hidden="true">${notifIcon({ module: n.module, severity: n.severity }, state.categoryIcons)}</span>

                            ${esc(moduleLabel(n.module))}

                        </span>

                        <span class="wh-notif-chip">${esc(categoryLabel(n.category))}</span>

                        <span class="wh-notif-chip wh-notif-chip--${esc(n.priority || 'normal')}">${esc(priorityLabel(n.priority))}</span>

                        ${whChip}

                        ${link}

                    </div>

                </div>

                <div class="wh-notif-item__actions">

                    ${unread ? `<button type="button" class="wh-notif-action" data-action="read" title="${esc(t('wh_notif_mark_read'))}"><span class="material-icons-round">mark_email_read</span></button>` : ''}

                    <button type="button" class="wh-notif-action${pinned ? ' is-active' : ''}" data-action="pin" title="${esc(pinned ? t('wh_notif_unpin') : t('wh_notif_pin'))}"><span class="material-icons-round">push_pin</span></button>

                    <button type="button" class="wh-notif-action" data-action="archive" title="${esc(t('wh_notif_archive'))}"><span class="material-icons-round">archive</span></button>

                </div>

            </li>`;

        }).join('');

    }



    async function loadCategories() {

        try {

            const res = await AdminAPI.getWarehouseNotificationMeta();

            const sel = els.category;

            if (res.status !== 'success' || !sel) return;

            (res.data?.categories || []).forEach((c) => {

                if (c.slug && c.icon) state.categoryIcons[c.slug] = c.icon;

                const opt = document.createElement('option');

                opt.value = c.slug;

                const lang = window.WH_CONFIG?.lang || 'en';

                opt.textContent = lang === 'fr' ? (c.name_fr || c.name_en || c.slug) : (c.name_en || c.slug);

                sel.appendChild(opt);

            });

        } catch { /* optional */ }

    }



    async function load() {

        hideError();

        setLoading(true);

        try {

            const res = await AdminAPI.getWarehouseNotifications(buildFilters());

            if (res.status !== 'success') {

                const migrationMsg = res.message?.includes('010_notifications')

                    ? t('wh_notif_migration_hint')

                    : (res.message || t('load_error'));

                throw new Error(migrationMsg);

            }

            setMigrationHint(true);

            state.items = res.data || [];

            state.total = Number(res.total ?? state.items.length);

            state.unread = Number(res.unread_count ?? res.stats?.unread ?? 0);

            state.stats = res.stats || state.stats;

            renderList(state.items);

            updateStats(state.stats);

            updatePagination();

            updateLastUpdated();

        } catch (e) {

            showError(e.message || t('load_error'));

            listEl.innerHTML = '';

            if (els.empty) els.empty.hidden = true;

            if ((e.message || '').includes('010_notifications')) {

                setMigrationHint(false);

            }

        } finally {

            setLoading(false);

        }

    }



    async function handleAction(e) {

        const btn = e.target.closest('[data-action]');

        if (!btn) return;

        const li = btn.closest('.wh-notif-item');

        const id = parseInt(li?.dataset.id || '0', 10);

        if (!id) return;

        const action = btn.dataset.action;

        try {

            if (action === 'read') await AdminAPI.markWarehouseNotificationsRead([id]);

            if (action === 'pin') await AdminAPI.pinWarehouseNotification(id, !li.classList.contains('is-pinned'));

            if (action === 'archive') await AdminAPI.archiveWarehouseNotifications([id], true);

            await load();

        } catch (err) {

            showError(err.message || t('load_error'));

        }

    }



    document.getElementById('whNotifTabs')?.addEventListener('click', (e) => {

        const tab = e.target.closest('[data-tab]');

        if (!tab) return;

        state.tab = tab.dataset.tab || 'all';

        state.page = 1;

        document.querySelectorAll('#whNotifTabs .wh-notif-tab').forEach((b) => {

            const active = b === tab;

            b.classList.toggle('is-active', active);

            b.setAttribute('aria-selected', active ? 'true' : 'false');

        });

        load();

    });



    els.search?.addEventListener('input', () => {

        clearTimeout(state.searchTimer);

        state.searchTimer = setTimeout(() => { state.page = 1; load(); }, 320);

    });

    els.module?.addEventListener('change', () => { state.page = 1; load(); });

    els.category?.addEventListener('change', () => { state.page = 1; load(); });

    els.priority?.addEventListener('change', () => { state.page = 1; load(); });



    els.markAll?.addEventListener('click', async () => {

        try {

            await AdminAPI.markAllWarehouseNotificationsRead();

            await load();

        } catch (e) {

            showError(e.message || t('load_error'));

        }

    });



    els.refresh?.addEventListener('click', load);

    els.prev?.addEventListener('click', () => { if (state.page > 1) { state.page -= 1; load(); } });

    els.next?.addEventListener('click', () => { if (state.page * state.limit < state.total) { state.page += 1; load(); } });

    listEl.addEventListener('click', handleAction);



    document.addEventListener('wh:refresh', load);



    loadCategories().then(load);

});

