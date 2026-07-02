/**
 * Warehouse Portal — Settings
 */
document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('whSettRoot');
    if (!root) return;

    const { t, esc, showError, hideError, loadWarehouseOptions } = WarehouseUI;
    const csrf = window.WH_PAGE?.csrfToken || '';
    const canEdit = !!window.WH_PAGE?.canManage && !window.WH_PAGE?.readOnly;
    const CACHE_KEY = 'wh_settings_v1';

    const NAV = [
        ['general', 'wh_sett_nav_general', 'tune'],
        ['warehouse', 'wh_sett_nav_warehouse', 'warehouse'],
        ['inventory', 'wh_sett_nav_inventory', 'inventory_2'],
        ['transfers', 'wh_sett_nav_transfers', 'swap_horiz'],
        ['receiving', 'wh_sett_nav_receiving', 'move_to_inbox'],
        ['dispatch', 'wh_sett_nav_dispatch', 'local_shipping'],
        ['barcode', 'wh_sett_nav_barcode', 'qr_code_scanner'],
        ['notifications', 'wh_sett_nav_notifications', 'notifications'],
        ['security', 'wh_sett_nav_security', 'shield'],
        ['offline', 'wh_sett_nav_offline', 'cloud_sync'],
        ['reports', 'wh_sett_nav_reports', 'assessment'],
        ['theme', 'wh_sett_nav_theme', 'palette'],
        ['logs', 'wh_sett_nav_logs', 'history'],
    ];

    const LOADING_STEPS = [
        'wh_sett_loading',
        'wh_sett_loading_warehouse',
        'wh_sett_loading_inventory',
        'wh_sett_loading_notifications',
    ];

    const state = {
        section: 'general',
        warehouseId: window.WH_PAGE?.warehouseId || 0,
        data: null,
        draft: null,
        dirty: false,
        auditTimer: null,
        rendered: false,
        loadingStep: 0,
        loadingTimer: null,
    };

    const els = {
        loading: document.getElementById('whSettLoading'),
        loadingText: document.getElementById('whSettLoadingText'),
        page: document.getElementById('whSettPage'),
        root,
        headActions: document.getElementById('whSettHeadActions'),
        saveBtn: document.getElementById('whSettSaveBtn'),
        resetBtn: document.getElementById('whSettResetBtn'),
        cancelBtn: document.getElementById('whSettCancelBtn'),
        offlineBadge: document.getElementById('whSettOfflineBadge'),
        toast: document.getElementById('whSettToast'),
    };

    function startLoadingSteps(refresh = false) {
        stopLoadingSteps();
        if (!els.loadingText) return;
        const steps = refresh ? ['wh_sett_refreshing', ...LOADING_STEPS] : LOADING_STEPS;
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
        els.toast.className = `wh-sett-toast show${ok ? '' : ' wh-sett-toast--error'}`;
        clearTimeout(els.toast._t);
        els.toast._t = setTimeout(() => els.toast.classList.remove('show'), 3200);
    }

    function fmtDate(v) {
        if (!v) return '—';
        try { return AdminAPI.formatDate(v, { dateStyle: 'short', timeStyle: 'short' }); } catch { return v; }
    }

    function toggle(name, checked, disabled = false) {
        return `<label class="wh-sett-toggle"><input type="checkbox" name="${esc(name)}"${checked ? ' checked' : ''}${disabled ? ' disabled' : ''}><span class="wh-sett-toggle__track"></span></label>`;
    }

    function field(label, html) {
        return `<div class="wh-sett-field"><span class="wh-sett-field__label">${esc(label)}</span>${html}</div>`;
    }

    function input(name, value, type = 'text', opts = {}) {
        const ro = !canEdit || opts.readonly ? ' readonly' : '';
        const dis = !canEdit || opts.disabled ? ' disabled' : '';
        if (type === 'select') {
            return `<select name="${esc(name)}" class="wh-select"${dis}>${opts.options.map(([v, l]) =>
                `<option value="${esc(v)}"${String(value) === String(v) ? ' selected' : ''}>${esc(l)}</option>`
            ).join('')}</select>`;
        }
        if (type === 'textarea') {
            return `<textarea name="${esc(name)}" rows="${opts.rows || 2}" class="wh-sett-input"${ro}>${esc(value || '')}</textarea>`;
        }
        return `<input type="${type}" name="${esc(name)}" value="${esc(value ?? '')}" class="wh-sett-input"${ro}${opts.min != null ? ` min="${opts.min}"` : ''}${opts.max != null ? ` max="${opts.max}"` : ''}>`;
    }

    function sectionCard(title, body, sectionKey) {
        return `<article class="wh-sett-card" data-section-panel="${sectionKey}">
            <header class="wh-sett-card__head"><h3>${esc(title)}</h3></header>
            <div class="wh-sett-card__body">${body}</div>
        </article>`;
    }

    function renderGeneral(d) {
        const g = d.settings?.general || {};
        const cur = d.general || g;
        return sectionCard(t('wh_sett_nav_general'), `
            <div class="wh-sett-grid">${[
                field('Working hours', input('general.working_hours', cur.working_hours || g.working_hours)),
                field('Timezone', input('general.timezone', cur.timezone || g.timezone)),
                field('Language', input('general.language', cur.language || g.language, 'select', { options: [['en', 'English'], ['fr', 'Français']] })),
                field('Currency', input('general.currency', cur.currency || g.currency, 'text', { readonly: true })),
            ].join('')}</div>`, 'general');
    }

    function renderWarehouse(d) {
        const w = d.warehouse || {};
        const types = ['central', 'regional', 'store', 'distribution', 'cold_storage', 'temporary'];
        const mgrOpts = [['', '—'], ...(d.managers || []).map((m) => [String(m.id), m.name])];
        return sectionCard(t('wh_sett_nav_warehouse'), `
            <div class="wh-sett-grid">${[
                field('Warehouse name', input('warehouse.name', w.name)),
                field('Warehouse code', input('warehouse.warehouse_code', w.warehouse_code, 'text', { readonly: true })),
                field('Type', input('warehouse.warehouse_type', w.warehouse_type, 'select', { options: types.map((x) => [x, x]) })),
                field('Manager', input('warehouse.manager_id', w.manager_id || '', 'select', { options: mgrOpts })),
                field('Phone', input('warehouse.phone', w.phone, 'tel')),
                field('Email', input('warehouse.email', w.email, 'email')),
                field('Address', input('warehouse.address', w.address, 'textarea')),
                field('City', input('warehouse.city', w.city)),
                field('Country', input('warehouse.country', w.country)),
                field('Status', input('warehouse.status', w.status, 'select', { options: [['active', 'Active'], ['inactive', 'Inactive']] })),
            ].join('')}</div>`, 'warehouse');
    }

    function renderToggles(section, fields) {
        const s = state.draft?.settings?.[section] || {};
        return `<div class="wh-sett-toggles">${fields.map(([key, label]) =>
            `<div class="wh-sett-toggle-row"><span>${esc(label)}</span>${toggle(`${section}.${key}`, !!s[key], !canEdit)}</div>`
        ).join('')}</div>`;
    }

    function renderInventory() {
        const s = state.draft?.settings?.inventory || {};
        return sectionCard(t('wh_sett_nav_inventory'), `
            <div class="wh-sett-grid">
                ${field('Default reorder level', input('inventory.default_reorder_level', s.default_reorder_level, 'number', { min: 0 }))}
                ${field('Valuation method', input('inventory.valuation_method', s.valuation_method, 'select', { options: [['fifo', 'FIFO'], ['weighted_average', 'Weighted average'], ['lifo', 'LIFO']] }))}
            </div>
            ${renderToggles('inventory', [
                ['allow_negative_stock', 'Allow negative stock'],
                ['require_adjustment_approval', 'Require approval for adjustments'],
                ['automatic_inventory_updates', 'Automatic inventory updates'],
                ['enable_batch_tracking', 'Enable batch tracking'],
                ['enable_serial_tracking', 'Enable serial number tracking'],
                ['enable_expiry_tracking', 'Enable expiry tracking'],
                ['automatic_low_stock_alerts', 'Automatic low stock alerts'],
            ])}`, 'inventory');
    }

    function renderTransfers() {
        const s = state.draft?.settings?.transfers || {};
        return sectionCard(t('wh_sett_nav_transfers'), `
            <div class="wh-sett-grid">
                ${field('Transfer prefix', input('transfers.transfer_prefix', s.transfer_prefix))}
                ${field('Default status', input('transfers.default_status', s.default_status, 'select', { options: [['pending', 'Pending'], ['approved', 'Approved'], ['draft', 'Draft']] }))}
            </div>
            ${renderToggles('transfers', [
                ['require_approval', 'Require approval before transfer'],
                ['auto_approve_internal', 'Auto approve internal transfers'],
                ['allow_partial', 'Allow partial transfers'],
                ['require_notes', 'Require transfer notes'],
                ['auto_generate_number', 'Generate transfer numbers automatically'],
            ])}`, 'transfers');
    }

    function renderReceiving() {
        return sectionCard(t('wh_sett_nav_receiving'), renderToggles('receiving', [
            ['require_purchase_order', 'Require purchase order'],
            ['require_quality_inspection', 'Require quality inspection'],
            ['require_barcode_scan', 'Require barcode scan'],
            ['auto_generate_grn', 'Auto generate GRN'],
            ['auto_update_inventory', 'Auto update inventory'],
            ['require_manager_approval', 'Require manager approval'],
        ]), 'receiving');
    }

    function renderDispatch() {
        return sectionCard(t('wh_sett_nav_dispatch'), renderToggles('dispatch', [
            ['require_picking', 'Require picking'],
            ['require_packing', 'Require packing'],
            ['require_final_verification', 'Require final verification'],
            ['generate_dispatch_note', 'Generate dispatch note'],
            ['generate_delivery_note', 'Generate delivery note'],
            ['require_delivery_signature', 'Require signature on delivery'],
        ]), 'dispatch');
    }

    function renderBarcode() {
        const s = state.draft?.settings?.barcode || {};
        return sectionCard(t('wh_sett_nav_barcode'), `
            <div class="wh-sett-grid">
                ${field('Default barcode type', input('barcode.default_type', s.default_type, 'select', { options: [['code128', 'Code 128'], ['ean13', 'EAN-13'], ['ean8', 'EAN-8'], ['upc', 'UPC'], ['qr', 'QR Code']] }))}
                ${field('Barcode prefix', input('barcode.barcode_prefix', s.barcode_prefix))}
            </div>
            ${renderToggles('barcode', [
                ['auto_generate', 'Automatic barcode generation'],
                ['print_labels', 'Print labels'],
                ['print_qr_codes', 'Print QR codes'],
            ])}`, 'barcode');
    }

    function renderNotifications() {
        return sectionCard(t('wh_sett_nav_notifications'), `
            ${renderToggles('notifications', [
                ['low_stock', 'Low stock'],
                ['out_of_stock', 'Out of stock'],
                ['expired_products', 'Expired products'],
                ['damaged_products', 'Damaged products'],
                ['transfer_requests', 'Transfer requests'],
                ['transfer_approved', 'Transfer approved'],
                ['receiving_completed', 'Receiving completed'],
                ['dispatch_completed', 'Dispatch completed'],
                ['inventory_count_due', 'Inventory count due'],
                ['warehouse_full', 'Warehouse full'],
                ['channel_dashboard', 'Dashboard notifications'],
                ['channel_email', 'Email'],
                ['channel_sms', 'SMS'],
                ['channel_push', 'Push notifications'],
                ['channel_whatsapp', 'WhatsApp'],
            ])}`, 'notifications');
    }

    function renderSecurity() {
        const s = state.draft?.settings?.security || {};
        return sectionCard(t('wh_sett_nav_security'), `
            <div class="wh-sett-grid">
                ${field('Max failed attempts', input('security.max_failed_attempts', s.max_failed_attempts, 'number', { min: 3, max: 20 }))}
                ${field('Session timeout (minutes)', input('security.session_timeout_minutes', s.session_timeout_minutes, 'number', { min: 5, max: 480 }))}
                ${field('IP restrictions (optional)', input('security.ip_restrictions', s.ip_restrictions, 'textarea', { rows: 3 }))}
            </div>
            ${renderToggles('security', [
                ['require_password_critical', 'Require password for critical actions'],
                ['enable_audit_logs', 'Enable audit logs'],
                ['enable_activity_logs', 'Enable activity logs'],
            ])}`, 'security');
    }

    function renderOffline() {
        const s = state.draft?.settings?.offline || {};
        return sectionCard(t('wh_sett_nav_offline'), `
            <div class="wh-sett-grid">
                ${field('Conflict strategy', input('offline.conflict_strategy', s.conflict_strategy, 'select', { options: [['server_wins', 'Server wins'], ['client_wins', 'Client wins'], ['manual', 'Manual review']] }))}
                ${field('Sync frequency (minutes)', input('offline.sync_frequency_minutes', s.sync_frequency_minutes, 'number', { min: 1, max: 60 }))}
                ${field('Local storage limit (MB)', input('offline.local_storage_limit_mb', s.local_storage_limit_mb, 'number', { min: 10, max: 500 }))}
            </div>
            ${renderToggles('offline', [
                ['enable_offline_mode', 'Enable offline mode'],
                ['automatic_sync', 'Automatic synchronization'],
            ])}
            <p class="wh-muted wh-sett-offline-status" id="whSettOfflineStatus">${esc(t('wh_settings_hint'))}</p>`, 'offline');
    }

    function renderReports() {
        const s = state.draft?.settings?.reports || {};
        return sectionCard(t('wh_sett_nav_reports'), `
            <div class="wh-sett-grid">
                ${field('Default format', input('reports.default_format', s.default_format, 'select', { options: [['pdf', 'PDF'], ['excel', 'Excel'], ['csv', 'CSV'], ['print', 'Print']] }))}
                ${field('Default date range', input('reports.default_date_range', s.default_date_range, 'select', { options: [['7d', '7 days'], ['30d', '30 days'], ['90d', '90 days'], ['year', 'Year']] }))}
            </div>
            ${renderToggles('reports', [['automatic_scheduled_reports', 'Automatic scheduled reports']])}`, 'reports');
    }

    function renderTheme() {
        return sectionCard(t('wh_sett_nav_theme'), `
            <p class="wh-muted">${esc(t('wh_settings_hint'))}</p>
            <div id="appThemeSettings" class="app-theme-settings"></div>`, 'theme');
    }

    function renderLogs() {
        return sectionCard(t('wh_sett_nav_logs'), `
            <label class="wh-sett-search"><span class="material-icons-round">search</span>
                <input type="search" id="whSettAuditSearch" placeholder="${esc(t('wh_sett_audit_search'))}">
            </label>
            <div id="whSettAuditWrap" class="wh-sett-table-wrap"><p class="wh-muted">${esc(t('loading'))}</p></div>`, 'logs');
    }

    function renderAll() {
        const d = state.draft;
        if (!d) return;
        const readOnlyBanner = !canEdit ? `<div class="wh-sett-readonly">${esc(t('wh_sett_readonly'))}</div>` : '';
        root.innerHTML = `
            ${readOnlyBanner}
            <aside class="wh-sett-nav">
                <label class="wh-sett-wh-select">${esc(t('wh_sett_wh_select'))}
                    <select id="whSettWarehouse" class="wh-select"></select>
                </label>
                <nav>${NAV.map(([id, label, icon]) =>
                    `<button type="button" class="wh-sett-nav__item${state.section === id ? ' is-active' : ''}" data-section="${id}">
                        <span class="material-icons-round">${icon}</span><span>${esc(t(label))}</span>
                    </button>`
                ).join('')}</nav>
            </aside>
            <div class="wh-sett-main">
                ${renderGeneral(d)}
                ${renderWarehouse(d)}
                ${renderInventory()}
                ${renderTransfers()}
                ${renderReceiving()}
                ${renderDispatch()}
                ${renderBarcode()}
                ${renderNotifications()}
                ${renderSecurity()}
                ${renderOffline()}
                ${renderReports()}
                ${renderTheme()}
                ${renderLogs()}
            </div>`;

        showSection(state.section);
        bindFormEvents();
        loadWarehouseOptions(document.getElementById('whSettWarehouse'), state.warehouseId);
        if (state.section === 'logs') loadAudit();
        if (state.section === 'offline') updateOfflineStatus();
        if (state.section === 'theme' && window.AppTheme?.mountSettings) {
            AppTheme.mountSettings(document.getElementById('appThemeSettings'));
        }
        if (els.headActions) els.headActions.hidden = !canEdit;
        state.rendered = true;
    }

    function showSection(id) {
        state.section = id;
        root.querySelectorAll('[data-section-panel]').forEach((p) => {
            p.hidden = p.dataset.sectionPanel !== id;
        });
        root.querySelectorAll('.wh-sett-nav__item').forEach((b) => {
            b.classList.toggle('is-active', b.dataset.section === id);
        });
        if (id === 'logs') loadAudit();
        if (id === 'theme' && window.AppTheme?.mountSettings) {
            AppTheme.mountSettings(document.getElementById('appThemeSettings'));
        }
    }

    function collectPayload() {
        const payload = { csrf_token: csrf, settings: {}, warehouse: {} };
        root.querySelectorAll('input[name], select[name], textarea[name]').forEach((el) => {
            const name = el.name;
            if (!name) return;
            const parts = name.split('.');
            let val;
            if (el.type === 'checkbox') val = el.checked;
            else if (el.type === 'number') val = Number(el.value);
            else val = el.value;
            if (parts[0] === 'warehouse') {
                payload.warehouse[parts[1]] = val;
            } else if (parts.length === 2) {
                payload.settings[parts[0]] = payload.settings[parts[0]] || {};
                payload.settings[parts[0]][parts[1]] = val;
            }
        });
        return payload;
    }

    function bindFormEvents() {
        root.querySelectorAll('.wh-sett-nav__item').forEach((btn) => {
            btn.addEventListener('click', () => showSection(btn.dataset.section || 'general'));
        });
        document.getElementById('whSettWarehouse')?.addEventListener('change', (e) => {
            state.warehouseId = Number(e.target.value) || 0;
            load();
        });
        root.querySelectorAll('input, select, textarea').forEach((el) => {
            el.addEventListener('change', () => { state.dirty = true; });
        });
        document.getElementById('whSettAuditSearch')?.addEventListener('input', () => {
            clearTimeout(state.auditTimer);
            state.auditTimer = setTimeout(loadAudit, 350);
        });
        els.saveBtn?.addEventListener('click', save);
        els.cancelBtn?.addEventListener('click', () => { state.draft = JSON.parse(JSON.stringify(state.data)); state.dirty = false; renderAll(); });
        els.resetBtn?.addEventListener('click', resetSection);
    }

    async function save() {
        if (!canEdit || !state.dirty) return;
        if (!confirm(t('wh_sett_confirm_save'))) return;
        const payload = collectPayload();
        if (!navigator.onLine) {
            try { localStorage.setItem('wh_settings_pending', JSON.stringify({ warehouseId: state.warehouseId, payload })); } catch (_) {}
            toast(t('wh_sett_offline_cached'));
            return;
        }
        try {
            const res = await AdminAPI.saveWarehouseSettings(state.warehouseId, payload);
            if (res.status === 'success') {
                toast(t('wh_sett_saved'));
                state.dirty = false;
                await load(true);
            } else toast(res.message || t('wh_sett_error'), false);
        } catch { toast(t('connection_error'), false); }
    }

    async function resetSection() {
        if (!canEdit) return;
        if (!confirm(t('wh_sett_confirm_reset'))) return;
        const section = state.section;
        if (section === 'logs' || section === 'theme') return;
        try {
            const res = await AdminAPI.resetWarehouseSettings(state.warehouseId, section, csrf);
            if (res.status === 'success') { toast(t('wh_sett_saved')); await load(true); }
            else toast(res.message || t('wh_sett_error'), false);
        } catch { toast(t('connection_error'), false); }
    }

    async function loadAudit() {
        const wrap = document.getElementById('whSettAuditWrap');
        if (!wrap) return;
        const q = document.getElementById('whSettAuditSearch')?.value?.trim() || '';
        try {
            const res = await AdminAPI.getWarehouseSettingsAudit(state.warehouseId, { q, limit: 40 });
            const rows = res.data || [];
            if (!rows.length) { wrap.innerHTML = `<p class="wh-muted">${esc(t('wh_sett_audit_empty'))}</p>`; return; }
            wrap.innerHTML = `<table class="wh-table"><thead><tr>
                <th>${esc(t('wh_sett_audit_date'))}</th><th>${esc(t('wh_sett_audit_user'))}</th><th>${esc(t('wh_sett_audit_action'))}</th>
                <th>${esc(t('wh_sett_audit_old'))}</th><th>${esc(t('wh_sett_audit_new'))}</th><th>${esc(t('wh_sett_audit_ip'))}</th>
            </tr></thead><tbody>${rows.map((r) => `<tr>
                <td>${esc(fmtDate(r.created_at))}</td>
                <td>${esc(r.user_name || r.actor_name || '—')}</td>
                <td><code>${esc(r.setting_key)}</code></td>
                <td>${esc(r.old_value ?? '—')}</td>
                <td>${esc(r.new_value ?? '—')}</td>
                <td>${esc(r.ip_address || '—')}</td>
            </tr>`).join('')}</tbody></table>`;
        } catch {
            wrap.innerHTML = `<p class="wh-muted">${esc(t('load_error'))}</p>`;
        }
    }

    function updateOfflineStatus() {
        const el = document.getElementById('whSettOfflineStatus');
        if (!el || !window.WarehouseOffline) return;
        WarehouseOffline.status().then((s) => {
            el.textContent = s.pending ? `${s.pending} pending sync item(s)` : 'All synced';
        });
    }

    function saveCache(data) {
        try { localStorage.setItem(CACHE_KEY, JSON.stringify({ warehouseId: state.warehouseId, data, saved_at: Date.now() })); } catch (_) {}
    }

    function loadCache() {
        try {
            const raw = localStorage.getItem(CACHE_KEY);
            const parsed = raw ? JSON.parse(raw) : null;
            if (parsed?.warehouseId === state.warehouseId) return parsed;
        } catch (_) {}
        return null;
    }

    async function resolveWarehouseId() {
        if (state.warehouseId > 0) return state.warehouseId;
        state.warehouseId = Number(window.WH_PAGE?.warehouseId) || 0;
        if (state.warehouseId > 0) return state.warehouseId;
        try {
            const res = await AdminAPI.getWmsWarehouses();
            const first = (res.data || [])[0];
            if (first?.id) state.warehouseId = Number(first.id);
        } catch (_) { /* offline */ }
        return state.warehouseId;
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
            await resolveWarehouseId();
            if (!state.warehouseId) {
                throw new Error(t('wh_sett_no_warehouse'));
            }
            const res = await AdminAPI.getWarehouseSettings(state.warehouseId);
            if (res.status !== 'success') throw new Error(res.message || t('load_error'));
            state.data = res.data;
            state.draft = JSON.parse(JSON.stringify(res.data));
            state.dirty = false;
            renderAll();
            root.hidden = false;
            saveCache(state.data);
            const pending = localStorage.getItem('wh_settings_pending');
            if (pending && navigator.onLine) {
                try {
                    const p = JSON.parse(pending);
                    if (p.warehouseId === state.warehouseId) {
                        await AdminAPI.saveWarehouseSettings(state.warehouseId, p.payload);
                        localStorage.removeItem('wh_settings_pending');
                    }
                } catch (_) {}
            }
        } catch (err) {
            const cached = loadCache();
            if (cached?.data) {
                state.data = cached.data;
                state.draft = JSON.parse(JSON.stringify(cached.data));
                renderAll();
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

    window.addEventListener('online', () => load(true));
    document.addEventListener('wh:refresh', () => load(true));
    (async () => {
        await resolveWarehouseId();
        const cached = loadCache();
        if (cached?.data) {
            state.data = cached.data;
            state.draft = JSON.parse(JSON.stringify(cached.data));
            renderAll();
            root.hidden = false;
            setLoadingVisible(false);
            load(true);
        } else {
            load(false);
        }
    })();
});
