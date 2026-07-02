/**
 * Warehouse Portal — Employee Profile (enterprise layout)
 */
document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('whProfRoot');
    if (!root) return;

    const { t, esc, money, showError, hideError, updateLastUpdated } = WarehouseUI;
    const CACHE_KEY = 'wh_profile_v1';
    const csrf = window.WH_PAGE?.csrfToken || '';
    const readOnly = !!window.WH_PAGE?.readOnly;

    const NAV = [
        ['overview', 'wh_prof_tab_overview', 'dashboard'],
        ['personal', 'wh_prof_tab_personal', 'person'],
        ['employment', 'wh_prof_tab_employment', 'work'],
        ['account', 'wh_prof_tab_account', 'manage_accounts'],
        ['performance', 'wh_prof_tab_performance', 'trending_up'],
        ['security', 'wh_prof_tab_security', 'shield'],
        ['notifications', 'wh_prof_tab_notifications', 'notifications'],
        ['preferences', 'wh_prof_tab_preferences', 'tune'],
        ['activity', 'wh_prof_tab_activity', 'history'],
        ['login', 'wh_prof_tab_login', 'login'],
    ];

    const METRIC_ICONS = {
        wh_prof_metric_receipts: 'move_to_inbox',
        wh_prof_metric_units_received: 'inventory',
        wh_prof_metric_po: 'shopping_cart',
        wh_prof_metric_dispatches: 'local_shipping',
        wh_prof_metric_deliveries: 'done_all',
        wh_prof_metric_transfers: 'swap_horiz',
        wh_prof_metric_adjustments: 'tune',
        wh_prof_metric_stock_counts: 'fact_check',
        wh_prof_metric_products: 'category',
        wh_prof_metric_inventory_value: 'payments',
        wh_prof_metric_actions: 'bolt',
    };

    const LOADING_STEPS = [
        'wh_prof_loading',
        'wh_prof_loading_account',
        'wh_prof_loading_performance',
        'wh_prof_loading_preferences',
    ];

    const state = {
        data: null,
        section: 'overview',
        loginSearchTimer: null,
        pendingSync: [],
        rendered: false,
        loadingStep: 0,
        loadingTimer: null,
    };

    const els = {
        loading: document.getElementById('whProfLoading'),
        loadingText: document.getElementById('whProfLoadingText'),
        page: document.getElementById('whProfPage'),
        root,
        offlineBadge: document.getElementById('whProfOfflineBadge'),
        toast: document.getElementById('whProfToast'),
    };

    function startLoadingSteps(refresh = false) {
        stopLoadingSteps();
        if (!els.loadingText) return;
        const steps = refresh ? ['wh_prof_refreshing', ...LOADING_STEPS] : LOADING_STEPS;
        const showStep = () => {
            els.loadingText.textContent = t(steps[state.loadingStep % steps.length]);
            state.loadingStep += 1;
        };
        showStep();
        state.loadingTimer = setInterval(showStep, 1400);
    }

    function stopLoadingSteps() {
        if (state.loadingTimer) {
            clearInterval(state.loadingTimer);
            state.loadingTimer = null;
        }
        state.loadingStep = 0;
    }

    function setLoadingVisible(visible, refresh = false) {
        if (!els.loading) return;
        els.loading.hidden = !visible;
        els.loading.setAttribute('aria-busy', visible ? 'true' : 'false');
        els.loading.classList.toggle('is-refresh', refresh);
        if (visible) startLoadingSteps(refresh);
        else stopLoadingSteps();
    }

    function toast(msg, ok = true) {
        if (!els.toast) return;
        els.toast.textContent = msg;
        els.toast.className = `wh-prof-toast show${ok ? '' : ' wh-prof-toast--error'}`;
        clearTimeout(els.toast._t);
        els.toast._t = setTimeout(() => els.toast.classList.remove('show'), 3200);
    }

    function fmtDate(val, withTime = false) {
        if (!val) return '—';
        try {
            return AdminAPI.formatDate(val, withTime ? { dateStyle: 'short', timeStyle: 'short' } : { dateStyle: 'short' });
        } catch {
            return val;
        }
    }

    function initial(name) {
        return (name || 'W').trim().charAt(0).toUpperCase();
    }

    function statusPill(status) {
        const s = String(status || '').toLowerCase();
        const ok = ['success', 'active', 'online', 'completed'].includes(s);
        const warn = ['failed', 'error', 'inactive', 'blocked'].includes(s);
        const cls = ok ? 'wh-prof-pill--ok' : warn ? 'wh-prof-pill--warn' : 'wh-prof-pill--muted';
        return `<span class="wh-prof-pill ${cls}">${esc(status || '—')}</span>`;
    }

    function sectionCard(title, body, icon = 'article') {
        return `<article class="wh-prof-card">
            <header class="wh-prof-card__head">
                <span class="material-icons-round">${icon}</span>
                <h3>${esc(title)}</h3>
            </header>
            <div class="wh-prof-card__body">${body}</div>
        </article>`;
    }

    function infoGrid(rows) {
        return `<dl class="wh-prof-info">${rows.map(([label, value, icon]) =>
            `<div class="wh-prof-info__row">
                <dt><span class="material-icons-round">${icon || 'info'}</span>${esc(label)}</dt>
                <dd>${value}</dd>
            </div>`
        ).join('')}</dl>`;
    }

    function metricCards(perf) {
        const m = perf?.metrics || {};
        const defs = [
            ['wh_prof_metric_receipts', m.receipts],
            ['wh_prof_metric_units_received', m.units_received],
            ['wh_prof_metric_po', m.purchase_orders],
            ['wh_prof_metric_dispatches', m.dispatches],
            ['wh_prof_metric_deliveries', m.deliveries_completed],
            ['wh_prof_metric_transfers', m.transfers],
            ['wh_prof_metric_adjustments', m.adjustments],
            ['wh_prof_metric_stock_counts', m.stock_counts],
            ['wh_prof_metric_products', m.products_managed],
            ['wh_prof_metric_inventory_value', m.inventory_value != null ? money(m.inventory_value) : null],
            ['wh_prof_metric_actions', m.warehouse_actions],
        ];
        const items = defs.filter(([, v]) => v != null && v !== 0);
        if (!items.length) {
            return `<div class="wh-prof-empty"><span class="material-icons-round">insights</span><p>${esc(t('no_data'))}</p></div>`;
        }
        return `<div class="wh-prof-kpi-grid">${items.map(([key, val]) =>
            `<article class="wh-prof-kpi">
                <span class="wh-prof-kpi__icon material-icons-round">${METRIC_ICONS[key] || 'analytics'}</span>
                <span class="wh-prof-kpi__label">${esc(t(key))}</span>
                <strong>${esc(String(val))}</strong>
            </article>`
        ).join('')}</div>`;
    }

    function notifToggle(name, label, checked, canEdit) {
        return `<label class="wh-prof-switch-row">
            <span>${esc(t(label))}</span>
            <span class="wh-sett-toggle">
                <input type="checkbox" name="${esc(name)}"${checked ? ' checked' : ''}${canEdit ? '' : ' disabled'}>
                <span class="wh-sett-toggle__track"></span>
            </span>
        </label>`;
    }

    function renderShell(d) {
        const u = d.user || {};
        const canEdit = d.permissions?.can_edit && !readOnly;
        const avatarHtml = u.avatar_url
            ? `<img src="${esc(u.avatar_url)}" alt="" class="wh-prof-avatar__img">`
            : `<span class="wh-prof-avatar__initial">${esc(initial(u.name))}</span>`;

        root.innerHTML = `
        ${!canEdit ? `<div class="wh-prof-readonly">${esc(t('wh_prof_readonly_banner'))}</div>` : ''}
        <aside class="wh-prof-nav">
            <div class="wh-prof-nav__user">
                <div class="wh-prof-nav__avatar" id="whProfNavAvatar">${avatarHtml}</div>
                <div class="wh-prof-nav__meta">
                    <strong id="whProfNavName">${esc(u.name || '—')}</strong>
                    <span id="whProfNavRole">${esc(u.role || '—')}</span>
                </div>
            </div>
            <nav class="wh-prof-nav__list" role="tablist">${NAV.map(([id, label, icon]) =>
                `<button type="button" class="wh-prof-nav__item${state.section === id ? ' is-active' : ''}"
                    data-section="${id}" role="tab" aria-selected="${state.section === id}">
                    <span class="material-icons-round">${icon}</span><span>${esc(t(label))}</span>
                </button>`
            ).join('')}</nav>
        </aside>
        <div class="wh-prof-main">
            <div class="wh-prof-panels" id="whProfPanels"></div>
        </div>`;

        renderPanels(d);
        bindEvents();
        state.rendered = true;
        showSection(state.section, true);
    }

    function renderPanels(d) {
        const u = d.user || {};
        const acc = d.account || {};
        const prefs = d.preferences || {};
        const notif = d.notifications || {};
        const canEdit = d.permissions?.can_edit && !readOnly;
        const avatarHtml = u.avatar_url
            ? `<img src="${esc(u.avatar_url)}" alt="" class="wh-prof-avatar__img" id="whProfAvatarImg">`
            : `<span class="wh-prof-avatar__initial" id="whProfAvatarInitial">${esc(initial(u.name))}</span>`;

        const panels = document.getElementById('whProfPanels');
        if (!panels) return;

        panels.innerHTML = `
        <section class="wh-prof-panel" data-panel="overview">
            <header class="wh-prof-hero">
                <div class="wh-prof-avatar-wrap">
                    <div class="wh-prof-avatar" id="whProfAvatar">${avatarHtml}</div>
                    ${canEdit ? `<label class="wh-prof-avatar-btn" title="${esc(t('wh_prof_upload_photo'))}">
                        <input type="file" id="whProfAvatarInput" accept="image/jpeg,image/png,image/webp" hidden>
                        <span class="material-icons-round">photo_camera</span>
                    </label>` : ''}
                </div>
                <div class="wh-prof-hero__body">
                    <h2>${esc(u.name || '—')}</h2>
                    <p class="wh-prof-hero__sub">${esc(u.email || '')}</p>
                    <div class="wh-prof-badges">
                        <span class="wh-prof-badge"><span class="material-icons-round">badge</span>${esc(u.role || '—')}</span>
                        <span class="wh-prof-badge wh-prof-badge--ok"><span class="material-icons-round">circle</span>${esc(t('wh_prof_online'))}</span>
                        <span class="wh-prof-badge${u.is_active ? ' wh-prof-badge--ok' : ' wh-prof-badge--warn'}">${esc(u.is_active ? t('account_active') : t('account_inactive'))}</span>
                    </div>
                </div>
            </header>
            ${sectionCard(t('wh_prof_tab_overview'), infoGrid([
                [t('wh_prof_employee_id'), esc(u.employee_id || '—'), 'fingerprint'],
                [t('wh_prof_warehouse'), esc(u.warehouse_name || '—'), 'warehouse'],
                [t('wh_prof_branch'), esc(u.branch_name || '—'), 'store'],
                [t('wh_prof_phone'), esc(u.phone || '—'), 'phone'],
                [t('wh_prof_member_since'), esc(fmtDate(u.member_since)), 'event'],
                [t('wh_prof_last_login'), esc(fmtDate(u.last_login, true)), 'schedule'],
            ]), 'dashboard')}
            ${sectionCard(t('wh_prof_perf_title'), metricCards(d.performance), 'trending_up')}
        </section>

        <section class="wh-prof-panel" data-panel="personal">
            ${sectionCard(t('wh_prof_section_personal'), `
                <form id="whProfPersonalForm" class="wh-prof-form">
                    <p class="wh-muted wh-prof-hint">${esc(t('wh_prof_readonly_hint'))}</p>
                    <div class="wh-prof-form-grid">
                        <label>${esc(t('wh_prof_first_name'))}<input name="first_name" value="${esc(u.first_name)}" ${canEdit ? '' : 'readonly'} required minlength="2"></label>
                        <label>${esc(t('wh_prof_last_name'))}<input name="last_name" value="${esc(u.last_name)}" ${canEdit ? '' : 'readonly'} required minlength="2"></label>
                        <label>${esc(t('wh_prof_phone'))}<input name="phone" type="tel" value="${esc(u.phone || '')}" ${canEdit ? '' : 'readonly'}></label>
                        <label>${esc(t('wh_prof_email'))}<input value="${esc(u.email)}" readonly disabled class="wh-prof-input--muted"></label>
                        <label class="wh-prof-span2">${esc(t('wh_prof_address'))}<textarea name="address" rows="2" ${canEdit ? '' : 'readonly'}>${esc(u.address || '')}</textarea></label>
                        <label class="wh-prof-span2">${esc(t('wh_prof_emergency'))}<input name="emergency_contact" value="${esc(u.emergency_contact || '')}" ${canEdit ? '' : 'readonly'}></label>
                        <label>${esc(t('wh_prof_language'))}
                            <select name="language" ${canEdit ? '' : 'disabled'}>
                                <option value="en"${u.language === 'en' ? ' selected' : ''}>English</option>
                                <option value="fr"${u.language === 'fr' ? ' selected' : ''}>Français</option>
                            </select>
                        </label>
                        <label>${esc(t('wh_prof_timezone'))}
                            <select name="timezone" ${canEdit ? '' : 'disabled'}>
                                ${['UTC', 'Africa/Abidjan', 'Africa/Lagos', 'Europe/Paris', 'America/New_York'].map((tz) =>
                                    `<option value="${tz}"${(u.timezone || 'UTC') === tz ? ' selected' : ''}>${tz}</option>`
                                ).join('')}
                            </select>
                        </label>
                    </div>
                    ${canEdit ? `<div class="wh-prof-actions">
                        <button type="submit" class="wh-btn"><span class="material-icons-round">save</span>${esc(t('wh_prof_save'))}</button>
                        ${u.avatar_url ? `<button type="button" class="wh-btn wh-btn--ghost" id="whProfRemoveAvatar"><span class="material-icons-round">delete</span>${esc(t('wh_prof_remove_photo'))}</button>` : ''}
                    </div>` : ''}
                </form>`, 'person')}
        </section>

        <section class="wh-prof-panel" data-panel="employment">
            ${sectionCard(t('wh_prof_tab_employment'), infoGrid([
                [t('wh_prof_employee_id'), esc(u.employee_id || '—'), 'fingerprint'],
                [t('wh_prof_department'), esc(u.department || '—'), 'corporate_fare'],
                [t('wh_prof_warehouse'), esc(u.warehouse_name || '—'), 'warehouse'],
                [t('wh_prof_branch'), esc(u.branch_name || '—'), 'store'],
                [t('wh_prof_role'), esc(u.role || '—'), 'badge'],
                [t('wh_prof_supervisor'), esc(u.supervisor_name || '—'), 'supervisor_account'],
                [t('wh_prof_date_joined'), esc(fmtDate(u.date_joined)), 'event'],
                [t('wh_prof_employment_status'), statusPill(u.employment_status), 'verified_user'],
                [t('wh_prof_shift'), '—', 'schedule'],
            ]), 'work')}
        </section>

        <section class="wh-prof-panel" data-panel="account">
            ${sectionCard(t('wh_prof_tab_account'), infoGrid([
                [t('wh_prof_username'), esc(u.username || u.email), 'account_circle'],
                [t('wh_prof_email'), esc(u.email), 'mail'],
                [t('wh_prof_role'), esc(u.role), 'badge'],
                [t('wh_prof_last_login'), esc(fmtDate(u.last_login, true)), 'login'],
                [t('wh_prof_last_activity'), esc(fmtDate(u.last_activity, true)), 'update'],
                [t('wh_prof_session'), `<code>${esc(acc.session_id || '—')}</code>`, 'key'],
                [t('wh_prof_device'), esc(acc.device || '—'), 'devices'],
                [t('wh_prof_browser'), esc(acc.browser || '—'), 'language'],
                [t('wh_prof_os'), esc(acc.os || '—'), 'computer'],
                [t('wh_prof_ip'), `<code>${esc(acc.ip_address || '—')}</code>`, 'public'],
            ]), 'manage_accounts')}
        </section>

        <section class="wh-prof-panel" data-panel="performance">
            ${sectionCard(t('wh_prof_perf_title'), `
                <p class="wh-prof-period">${esc(t('wh_prof_perf_period'))}</p>
                ${metricCards(d.performance)}`, 'trending_up')}
        </section>

        <section class="wh-prof-panel" data-panel="security">
            ${canEdit ? sectionCard(t('wh_prof_security_password'), `
                <form id="whProfPasswordForm" class="wh-prof-form wh-prof-form--narrow">
                    <label>${esc(t('wh_prof_current_password'))}<input type="password" name="current_password" autocomplete="current-password"></label>
                    <label>${esc(t('wh_prof_new_password'))}<input type="password" name="new_password" autocomplete="new-password" minlength="6"></label>
                    <label>${esc(t('wh_prof_confirm_password'))}<input type="password" name="confirm_password" autocomplete="new-password"></label>
                    <button type="submit" class="wh-btn"><span class="material-icons-round">lock</span>${esc(t('wh_prof_change_password'))}</button>
                </form>`, 'lock') : ''}
            <div class="wh-prof-card wh-prof-card--muted">
                <header class="wh-prof-card__head"><span class="material-icons-round">verified_user</span><h3>${esc(t('wh_prof_2fa'))}</h3></header>
                <div class="wh-prof-card__body">
                    <p class="wh-muted">${esc(t('wh_prof_2fa_hint'))}</p>
                    <label class="wh-prof-switch-row wh-prof-switch-row--disabled">
                        <span>${esc(t('wh_prof_2fa'))}</span>
                        <span class="wh-sett-toggle"><input type="checkbox" disabled><span class="wh-sett-toggle__track"></span></span>
                    </label>
                </div>
            </div>
            ${canEdit ? `<div class="wh-prof-card">
                <header class="wh-prof-card__head"><span class="material-icons-round">devices_other</span><h3>${esc(t('wh_prof_logout_devices'))}</h3></header>
                <div class="wh-prof-card__body">
                    <p class="wh-muted">${esc(t('wh_prof_logout_devices_hint'))}</p>
                    <button type="button" class="wh-btn wh-btn--ghost" id="whProfLogoutDevices"><span class="material-icons-round">logout</span>${esc(t('wh_prof_logout_devices'))}</button>
                </div>
            </div>` : ''}
        </section>

        <section class="wh-prof-panel" data-panel="notifications">
            ${sectionCard(t('wh_prof_notif_channels'), `
                <form id="whProfNotifForm" class="wh-prof-form">
                    <div class="wh-prof-switch-group">
                        ${notifToggle('email_enabled', 'wh_prof_notif_email', notif.email_enabled, canEdit)}
                        ${notifToggle('sms_enabled', 'wh_prof_notif_sms', notif.sms_enabled, canEdit)}
                        ${notifToggle('push_enabled', 'wh_prof_notif_push', notif.push_enabled, canEdit)}
                        ${notifToggle('whatsapp_enabled', 'wh_prof_notif_whatsapp', notif.whatsapp_enabled, canEdit)}
                    </div>
                    <h4 class="wh-prof-subhead">${esc(t('wh_prof_notif_warehouse'))}</h4>
                    <div class="wh-prof-switch-group">
                        ${notifToggle('warehouse_notif_dashboard', 'wh_prof_notif_dashboard', notif.dashboard_alerts, canEdit)}
                        ${notifToggle('warehouse_notif_low_stock', 'wh_prof_notif_low_stock', notif.low_stock_alerts, canEdit)}
                        ${notifToggle('warehouse_notif_transfer', 'wh_prof_notif_transfer', notif.transfer_alerts, canEdit)}
                        ${notifToggle('warehouse_notif_receiving', 'wh_prof_notif_receiving', notif.receiving_alerts, canEdit)}
                        ${notifToggle('warehouse_notif_dispatch', 'wh_prof_notif_dispatch', notif.dispatch_alerts, canEdit)}
                    </div>
                    ${canEdit ? `<div class="wh-prof-actions"><button type="submit" class="wh-btn"><span class="material-icons-round">save</span>${esc(t('wh_prof_save'))}</button></div>` : ''}
                </form>`, 'notifications')}
        </section>

        <section class="wh-prof-panel" data-panel="preferences">
            ${sectionCard(t('wh_prof_tab_preferences'), `
                <form id="whProfPrefsForm" class="wh-prof-form">
                    <div class="wh-prof-form-grid">
                        <label>${esc(t('wh_prof_pref_theme'))}
                            <select name="theme" ${canEdit ? '' : 'disabled'}>
                                <option value="light"${prefs.theme === 'light' ? ' selected' : ''}>${esc(t('wh_prof_theme_light'))}</option>
                                <option value="dark"${prefs.theme === 'dark' ? ' selected' : ''}>${esc(t('wh_prof_theme_dark'))}</option>
                                <option value="system"${prefs.theme === 'system' ? ' selected' : ''}>${esc(t('wh_prof_theme_system'))}</option>
                            </select>
                        </label>
                        <label>${esc(t('wh_prof_pref_date'))}
                            <select name="date_format" ${canEdit ? '' : 'disabled'}>
                                <option value="Y-m-d"${prefs.date_format === 'Y-m-d' ? ' selected' : ''}>YYYY-MM-DD</option>
                                <option value="d/m/Y"${prefs.date_format === 'd/m/Y' ? ' selected' : ''}>DD/MM/YYYY</option>
                                <option value="m/d/Y"${prefs.date_format === 'm/d/Y' ? ' selected' : ''}>MM/DD/YYYY</option>
                            </select>
                        </label>
                        <label>${esc(t('wh_prof_pref_time'))}
                            <select name="time_format" ${canEdit ? '' : 'disabled'}>
                                <option value="24h"${prefs.time_format === '24h' ? ' selected' : ''}>24h</option>
                                <option value="12h"${prefs.time_format === '12h' ? ' selected' : ''}>12h</option>
                            </select>
                        </label>
                        <label>${esc(t('wh_prof_pref_items'))}
                            <input type="number" name="items_per_page" min="10" max="200" value="${Number(prefs.items_per_page || 50)}" ${canEdit ? '' : 'readonly'}>
                        </label>
                        <label>${esc(t('wh_prof_pref_layout'))}
                            <select name="dashboard_layout" ${canEdit ? '' : 'disabled'}>
                                <option value="compact"${prefs.dashboard_layout === 'compact' ? ' selected' : ''}>Compact</option>
                                <option value="standard"${prefs.dashboard_layout === 'standard' ? ' selected' : ''}>Standard</option>
                                <option value="expanded"${prefs.dashboard_layout === 'expanded' ? ' selected' : ''}>Expanded</option>
                            </select>
                        </label>
                        <label>${esc(t('wh_prof_pref_wh_view'))}
                            <select name="default_warehouse_view" ${canEdit ? '' : 'disabled'}>
                                <option value="assigned"${prefs.default_warehouse_view === 'assigned' ? ' selected' : ''}>Assigned</option>
                                <option value="all"${prefs.default_warehouse_view === 'all' ? ' selected' : ''}>All</option>
                            </select>
                        </label>
                    </div>
                    ${canEdit ? `<div class="wh-prof-actions"><button type="submit" class="wh-btn"><span class="material-icons-round">save</span>${esc(t('wh_prof_save'))}</button></div>` : ''}
                </form>`, 'tune')}
        </section>

        <section class="wh-prof-panel" data-panel="activity">
            ${sectionCard(t('wh_prof_activity_title'), `
                <div id="whProfActivityWrap" class="wh-prof-table-wrap"><p class="wh-muted">${esc(t('loading'))}</p></div>`, 'history')}
        </section>

        <section class="wh-prof-panel" data-panel="login">
            ${sectionCard(t('wh_prof_login_title'), `
                <label class="wh-prof-search"><span class="material-icons-round">search</span>
                    <input type="search" id="whProfLoginSearch" placeholder="${esc(t('wh_prof_login_search'))}">
                </label>
                <div id="whProfLoginWrap" class="wh-prof-table-wrap"><p class="wh-muted">${esc(t('loading'))}</p></div>`, 'login')}
        </section>`;
    }

    function showSection(id, skipLoad = false) {
        state.section = id;
        root.querySelectorAll('.wh-prof-nav__item').forEach((btn) => {
            const active = btn.dataset.section === id;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        root.querySelectorAll('.wh-prof-panel').forEach((p) => {
            p.hidden = p.dataset.panel !== id;
            p.classList.toggle('is-active', p.dataset.panel === id);
        });
        if (!skipLoad) {
            if (id === 'activity') loadActivities();
            if (id === 'login') loadLoginHistory();
        }
    }

    function bindEvents() {
        root.querySelector('.wh-prof-nav__list')?.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-section]');
            if (!btn) return;
            showSection(btn.dataset.section || 'overview');
        });

        root.addEventListener('submit', async (e) => {
            if (e.target.id === 'whProfPersonalForm') {
                e.preventDefault();
                await saveProfile(Object.fromEntries(new FormData(e.target).entries()));
            } else if (e.target.id === 'whProfPasswordForm') {
                e.preventDefault();
                const fd = new FormData(e.target);
                const current = fd.get('current_password');
                const newPwd = fd.get('new_password');
                const confirm = fd.get('confirm_password');
                if (String(newPwd).length < 6) { toast(t('password_min_length'), false); return; }
                if (newPwd !== confirm) { toast(t('password_mismatch'), false); return; }
                if (!current) { toast(t('current_password_required'), false); return; }
                try {
                    const res = await AdminAPI.updateWarehouseProfilePassword({ current_password: current, new_password: newPwd, confirm_password: confirm, csrf_token: csrf });
                    if (res.status === 'success') { toast(res.message || t('wh_prof_saved')); e.target.reset(); }
                    else toast(res.message || t('wh_prof_error'), false);
                } catch { toast(t('connection_error'), false); }
            } else if (e.target.id === 'whProfNotifForm') {
                e.preventDefault();
                const whPayload = { csrf_token: csrf };
                const stdPayload = { csrf_token: csrf };
                e.target.querySelectorAll('input[type=checkbox]').forEach((cb) => {
                    const val = cb.checked ? 1 : 0;
                    if (cb.name.startsWith('warehouse_notif_')) whPayload[cb.name] = val;
                    else stdPayload[cb.name] = val;
                });
                try {
                    if (Object.keys(stdPayload).length > 1) await AdminAPI.updateWarehouseProfileNotifications(stdPayload);
                    await AdminAPI.updateWarehouseProfilePreferences(whPayload);
                    toast(t('wh_prof_saved'));
                } catch { toast(t('connection_error'), false); }
            } else if (e.target.id === 'whProfPrefsForm') {
                e.preventDefault();
                const payload = Object.fromEntries(new FormData(e.target).entries());
                payload.csrf_token = csrf;
                try {
                    const res = await AdminAPI.updateWarehouseProfilePreferences(payload);
                    if (res.status === 'success') {
                        toast(t('wh_prof_saved'));
                        if (window.AppTheme && payload.theme) AppTheme.applyMode(payload.theme);
                    } else toast(res.message || t('wh_prof_error'), false);
                } catch { toast(t('connection_error'), false); }
            }
        });

        root.addEventListener('click', async (e) => {
            if (e.target.closest('#whProfLogoutDevices')) {
                try {
                    const res = await AdminAPI.logoutWarehouseOtherDevices({ csrf_token: csrf });
                    toast(res.status === 'success' ? (res.message || t('wh_prof_saved')) : (res.message || t('wh_prof_error')), res.status === 'success');
                } catch { toast(t('connection_error'), false); }
            }
            if (e.target.closest('#whProfRemoveAvatar')) {
                try {
                    const res = await AdminAPI.deleteWarehouseProfileAvatar(csrf);
                    if (res.status === 'success') { toast(t('wh_prof_saved')); await load(true); }
                    else toast(res.message || t('wh_prof_error'), false);
                } catch { toast(t('connection_error'), false); }
            }
        });

        root.addEventListener('change', async (e) => {
            if (e.target.id === 'whProfAvatarInput' && e.target.files?.[0]) {
                try {
                    const res = await AdminAPI.uploadWarehouseProfileAvatar(e.target.files[0], csrf);
                    if (res.status === 'success') { toast(t('wh_prof_saved')); await load(true); }
                    else toast(res.message || t('wh_prof_error'), false);
                } catch { toast(t('connection_error'), false); }
                e.target.value = '';
            }
        });

        root.addEventListener('input', (e) => {
            if (e.target.id === 'whProfLoginSearch') {
                clearTimeout(state.loginSearchTimer);
                state.loginSearchTimer = setTimeout(loadLoginHistory, 350);
            }
        });
    }

    function renderProfile(d) {
        if (!state.rendered) {
            renderShell(d);
            return;
        }
        state.data = d;
        renderPanels(d);
        const u = d.user || {};
        const navName = document.getElementById('whProfNavName');
        const navRole = document.getElementById('whProfNavRole');
        if (navName) navName.textContent = u.name || '—';
        if (navRole) navRole.textContent = u.role || '—';
        showSection(state.section, true);
        if (state.section === 'activity') loadActivities();
        if (state.section === 'login') loadLoginHistory();
    }

    async function saveProfile(payload) {
        payload.csrf_token = csrf;
        if (!navigator.onLine) {
            state.pendingSync.push({ type: 'profile', payload, at: Date.now() });
            try { localStorage.setItem('wh_profile_pending', JSON.stringify(state.pendingSync)); } catch (_) {}
            toast(t('wh_prof_offline_cached'));
            return;
        }
        try {
            const res = await AdminAPI.updateWarehouseProfile(payload);
            if (res.status === 'success') {
                toast(t('wh_prof_saved'));
                await load(true);
            } else toast(res.message || t('wh_prof_error'), false);
        } catch { toast(t('connection_error'), false); }
    }

    async function flushOffline() {
        const raw = localStorage.getItem('wh_profile_pending');
        if (!raw || !navigator.onLine) return;
        try {
            const items = JSON.parse(raw);
            for (const item of items) {
                if (item.type === 'profile') await AdminAPI.updateWarehouseProfile(item.payload);
            }
            localStorage.removeItem('wh_profile_pending');
        } catch (_) {}
    }

    async function loadActivities() {
        const wrap = document.getElementById('whProfActivityWrap');
        if (!wrap) return;
        try {
            const res = await AdminAPI.getWarehouseProfileActivities();
            const rows = res.data || [];
            if (!rows.length) {
                wrap.innerHTML = `<div class="wh-prof-empty"><span class="material-icons-round">history</span><p>${esc(t('wh_prof_activity_empty'))}</p></div>`;
                return;
            }
            wrap.innerHTML = `<table class="wh-table wh-prof-table"><thead><tr>
                <th>${esc(t('wh_prof_activity_date'))}</th><th>${esc(t('wh_prof_activity_action'))}</th><th>${esc(t('wh_prof_activity_status'))}</th>
            </tr></thead><tbody>${rows.map((r) => `<tr>
                <td>${esc(fmtDate(r.created_at, true))}</td>
                <td><code>${esc(r.action || r.entity_type || '—')}</code></td>
                <td>${statusPill(r.status || 'success')}</td>
            </tr>`).join('')}</tbody></table>`;
        } catch {
            wrap.innerHTML = `<p class="wh-muted">${esc(t('load_error'))}</p>`;
        }
    }

    async function loadLoginHistory() {
        const wrap = document.getElementById('whProfLoginWrap');
        if (!wrap) return;
        const q = document.getElementById('whProfLoginSearch')?.value?.trim() || '';
        try {
            const res = await AdminAPI.getWarehouseProfileLoginHistory({ q, limit: 30 });
            const rows = res.data || [];
            if (!rows.length) {
                wrap.innerHTML = `<div class="wh-prof-empty"><span class="material-icons-round">login</span><p>${esc(t('wh_prof_login_empty'))}</p></div>`;
                return;
            }
            wrap.innerHTML = `<table class="wh-table wh-prof-table"><thead><tr>
                <th>${esc(t('wh_prof_login_date'))}</th><th>${esc(t('wh_prof_ip'))}</th><th>${esc(t('wh_prof_browser'))}</th>
                <th>${esc(t('wh_prof_os'))}</th><th>${esc(t('wh_prof_device'))}</th><th>${esc(t('wh_prof_login_status'))}</th>
            </tr></thead><tbody>${rows.map((r) => `<tr>
                <td>${esc(fmtDate(r.created_at, true))}</td>
                <td><code>${esc(r.ip_address || '—')}</code></td>
                <td>${esc(r.browser || '—')}</td>
                <td>${esc(r.os || r.os_name || '—')}</td>
                <td>${esc(r.device || r.device_type || '—')}</td>
                <td>${statusPill(r.status)}</td>
            </tr>`).join('')}</tbody></table>`;
        } catch {
            wrap.innerHTML = `<p class="wh-muted">${esc(t('load_error'))}</p>`;
        }
    }

    function saveCache(data) {
        try { localStorage.setItem(CACHE_KEY, JSON.stringify({ saved_at: Date.now(), data })); } catch (_) {}
    }

    function loadCache() {
        try { const raw = localStorage.getItem(CACHE_KEY); return raw ? JSON.parse(raw) : null; } catch { return null; }
    }

    async function load(silent = false) {
        const hasCachedShell = state.rendered;
        if (!silent) {
            setLoadingVisible(true, hasCachedShell);
            if (!hasCachedShell) root.hidden = true;
            else els.page?.classList.add('is-refreshing');
        }
        hideError();
        if (els.offlineBadge) els.offlineBadge.hidden = true;
        try {
            const res = await AdminAPI.getWarehouseProfile();
            if (res.status !== 'success') throw new Error(res.message || t('load_error'));
            state.data = res.data;
            renderProfile(state.data);
            root.hidden = false;
            saveCache(state.data);
            updateLastUpdated();
            await flushOffline();
        } catch (err) {
            const cached = loadCache();
            if (cached?.data) {
                state.data = cached.data;
                renderProfile(cached.data);
                root.hidden = false;
                if (els.offlineBadge) els.offlineBadge.hidden = false;
            } else if (!state.rendered) {
                showError(err.message || t('load_error'));
            }
        } finally {
            if (!silent) {
                setLoadingVisible(false);
                els.page?.classList.remove('is-refreshing');
            }
            root.hidden = !state.rendered;
        }
    }

    window.addEventListener('online', () => { flushOffline(); load(true); });
    document.addEventListener('wh:refresh', () => load(true));
    const cached = loadCache();
    if (cached?.data) {
        state.data = cached.data;
        renderProfile(cached.data);
        root.hidden = false;
        setLoadingVisible(false);
        load(true);
    } else {
        load(false);
    }
});
