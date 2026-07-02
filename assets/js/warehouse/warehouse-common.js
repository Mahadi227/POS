/**
 * Warehouse portal — shared UI helpers
 */
window.WarehouseUI = (() => {
    const i18n = () => window.WH_I18N || {};
    const config = () => window.WH_CONFIG || {};
    const locale = () => config().locale || 'fr-FR';

    function currencyMeta(code) {
        const norm = (code || config().currency || 'FCFA').toUpperCase();
        return config().currencies?.[norm] || { code: norm, symbol: norm, locale: locale(), decimals: 0 };
    }

    function defaultCurrency() {
        return config().currency || window.WH_PAGE?.currency || 'FCFA';
    }

    function t(key, ...args) {
        let str = i18n()[key] || key;
        args.forEach((v) => { str = str.replace('%s', v); });
        return str;
    }

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s ?? '';
        return d.innerHTML;
    }

    function money(n, currencyCode) {
        if (typeof window.AdminAPI?.formatCurrency === 'function' && !currencyCode) {
            return window.AdminAPI.formatCurrency(n);
        }
        const meta = currencyMeta(currencyCode);
        const loc = meta.locale || locale();
        const decimals = meta.decimals ?? 0;
        const formatted = Number(n || 0).toLocaleString(loc, {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals,
        });
        return `${formatted} ${meta.symbol || meta.code}`;
    }

    function pct(n) {
        return `${Number(n || 0).toLocaleString(locale(), { maximumFractionDigits: 1 })}%`;
    }

    function showError(msg) {
        const b = document.getElementById('whErrorBanner');
        if (!b) return;
        b.hidden = false;
        b.textContent = msg;
    }

    function hideError() {
        const b = document.getElementById('whErrorBanner');
        if (b) {
            b.hidden = true;
            b.textContent = '';
        }
    }

    function setMigrationHint(ready) {
        const el = document.getElementById('whMigrationHint');
        if (el) {
            el.hidden = !!ready;
            if (!ready) el.textContent = t('wh_migration_hint');
        }
    }

    function updateLastUpdated() {
        const el = document.getElementById('whLastUpdated');
        if (!el) return;
        el.textContent = `${t('last_updated')} · ${new Date().toLocaleTimeString(locale(), { hour: '2-digit', minute: '2-digit' })}`;
    }

    function exportCsv(filename, rows) {
        if (!rows?.length) return;
        const csv = rows.map((r) => r.map((c) => `"${String(c ?? '').replace(/"/g, '""')}"`).join(',')).join('\n');
        const a = document.createElement('a');
        a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
        a.download = filename;
        a.click();
    }

    const NOTIF_MODULE_ICONS = {
        warehouse: 'warehouse',
        inventory: 'inventory_2',
        wms: 'warehouse',
        receiving: 'move_to_inbox',
        dispatch: 'local_shipping',
        transfers: 'sync_alt',
        pos: 'point_of_sale',
        cash_register: 'payments',
        accounting: 'receipt_long',
        users: 'people',
        system: 'settings',
    };

    const NOTIF_SEVERITY_ICONS = {
        critical: 'report',
        error: 'error_outline',
        warning: 'warning_amber',
        success: 'check_circle',
        info: 'info',
    };

    function notifSeverityTone(severity, priority) {
        const sev = (severity || 'info').toLowerCase();
        if (sev === 'critical' || sev === 'error' || priority === 'critical') return 'danger';
        if (sev === 'warning' || priority === 'high') return 'warn';
        if (sev === 'success') return 'success';
        return 'info';
    }

    function notifIcon(item, categoryIcons = {}) {
        const category = (item?.category || '').toLowerCase();
        const type = (item?.type || '').toLowerCase();
        const module = (item?.module || '').toLowerCase();
        if (category && categoryIcons[category]) return categoryIcons[category];
        if (type && categoryIcons[type]) return categoryIcons[type];
        if (module && NOTIF_MODULE_ICONS[module]) return NOTIF_MODULE_ICONS[module];
        return NOTIF_SEVERITY_ICONS[(item?.severity || 'info').toLowerCase()] || 'notifications';
    }

    async function refreshNotifBadge() {
        const badge = document.getElementById('whNotifBadge');
        if (!badge) return;
        const fn = typeof AdminAPI?.getWarehouseNotificationUnreadCount === 'function'
            ? AdminAPI.getWarehouseNotificationUnreadCount.bind(AdminAPI)
            : (typeof AdminAPI?.getNotificationUnreadCount === 'function'
                ? AdminAPI.getNotificationUnreadCount.bind(AdminAPI)
                : null);
        if (!fn) return;
        try {
            const res = await fn();
            if (res.status !== 'success') return;
            const count = Number(res.count ?? 0);
            badge.textContent = count > 99 ? '99+' : String(count);
            badge.hidden = count <= 0;
        } catch {
            /* optional */
        }
    }

    async function loadWarehouseOptions(selectEl, selectedId) {
        if (!selectEl) return;
        const res = await AdminAPI.getWmsWarehouses();
        const items = res.data || [];
        const cur = String(selectedId ?? selectEl.value ?? window.WH_PAGE?.warehouseId ?? '');
        selectEl.innerHTML = `<option value="">${esc(t('wh_all_warehouses'))}</option>`
            + items.map((w) => `<option value="${w.id}">${esc(w.name)}</option>`).join('');
        if (cur) selectEl.value = cur;
    }

    function isFormModalSheet() {
        return window.matchMedia('(max-width: 767px)').matches;
    }

    function resetFormModalSwipe(overlay, panel) {
        if (!panel) return;
        panel.classList.remove('is-dragging');
        panel.style.transition = '';
        panel.style.transform = '';
        if (overlay) {
            overlay.style.transition = '';
            overlay.style.opacity = '';
        }
    }

    function closeWhFormModal(overlay) {
        if (!overlay) return;
        const panel = overlay.querySelector('.wh-form-modal');
        resetFormModalSwipe(overlay, panel);
        overlay.classList.remove('is-open');
        overlay.setAttribute('aria-hidden', 'true');
        clearFormModalError(overlay);
    }

    function openWhFormModal(overlay) {
        if (!overlay) return;
        overlay.classList.add('is-open');
        overlay.setAttribute('aria-hidden', 'false');
    }

    function showFormModalError(overlay, message) {
        const err = overlay?.querySelector('.wh-fm-form-error');
        if (!err) {
            showError(message);
            return;
        }
        err.textContent = message || '';
        err.hidden = !message;
    }

    function clearFormModalError(overlay) {
        showFormModalError(overlay, '');
    }

    function bindFormModalSwipe(overlay, panel, scrollEl) {
        if (!overlay || !panel || panel.dataset.fmSwipeBound) return;
        panel.dataset.fmSwipeBound = '1';

        const SWIPE_CLOSE_PX = 72;
        const SWIPE_VELOCITY = 0.4;
        const swipeZone = panel.querySelector('.wh-fm-swipe-zone');
        const handle = panel.querySelector('.wh-fm-swipe-handle');

        let startY = 0;
        let startX = 0;
        let currentY = 0;
        let startTime = 0;
        let dragging = false;
        let canSwipe = false;

        function snapBack() {
            panel.style.transition = 'transform 0.24s cubic-bezier(0.4, 0, 0.2, 1)';
            panel.style.transform = '';
            overlay.style.transition = 'opacity 0.24s ease';
            overlay.style.opacity = '';
            setTimeout(() => resetFormModalSwipe(overlay, panel), 240);
        }

        function animateClose() {
            panel.style.transition = 'transform 0.22s cubic-bezier(0.4, 0, 0.2, 1)';
            panel.style.transform = 'translateY(100%)';
            overlay.style.transition = 'opacity 0.22s ease';
            overlay.style.opacity = '0';
            setTimeout(() => closeWhFormModal(overlay), 220);
        }

        function onTouchStart(e) {
            if (!isFormModalSheet() || !overlay.classList.contains('is-open')) return;

            const touch = e.touches[0];
            const target = e.target;
            const onHandle = handle?.contains(target);
            const onHeader = swipeZone?.contains(target);
            const scrollAtTop = !scrollEl || scrollEl.scrollTop <= 0;
            const isInteractive = target.closest('input, select, textarea, button, a, option');

            if (isInteractive && !onHandle) return;
            if (!onHandle && !onHeader && !scrollAtTop) return;

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

            if (deltaY < 0) {
                currentY = 0;
                panel.style.transform = `translateY(${deltaY * 0.2}px)`;
                return;
            }

            currentY = deltaY;
            e.preventDefault();
            panel.style.transform = `translateY(${deltaY}px)`;
            overlay.style.opacity = String(Math.max(0.15, 1 - deltaY / 280));
        }

        function onTouchEnd() {
            if (!dragging || !canSwipe) return;

            const elapsed = Math.max(1, Date.now() - startTime);
            const velocity = currentY / elapsed;

            if (currentY >= SWIPE_CLOSE_PX || (currentY > 28 && velocity > SWIPE_VELOCITY)) {
                animateClose();
            } else {
                snapBack();
            }

            dragging = false;
            canSwipe = false;
            panel.classList.remove('is-dragging');
        }

        panel.addEventListener('touchstart', onTouchStart, { passive: true });
        panel.addEventListener('touchmove', onTouchMove, { passive: false });
        panel.addEventListener('touchend', onTouchEnd);
        panel.addEventListener('touchcancel', onTouchEnd);
    }

    function enhanceFormModal(overlay) {
        const panel = overlay?.querySelector('.wh-form-modal');
        if (!panel || panel.dataset.fmEnhanced) return;
        panel.dataset.fmEnhanced = '1';

        const head = panel.querySelector('.wms-grn-modal__head');
        if (head && !panel.querySelector('.wh-fm-swipe-zone')) {
            const zone = document.createElement('div');
            zone.className = 'wh-fm-swipe-zone';
            zone.innerHTML = '<div class="wh-fm-swipe-handle" aria-hidden="true"></div>';
            head.parentNode.insertBefore(zone, head);
            zone.appendChild(head);
        }

        const compactForm = panel.querySelector('.wms-grn-form--compact');
        const info = panel.querySelector('.wms-grn-section--info');

        if (compactForm && !compactForm.querySelector('.wh-fm-meta-wrap')) {
            const body = compactForm.querySelector('.wms-grn-form__body');
            const sections = body?.querySelectorAll(':scope > .wms-grn-section, :scope > .wh-form-section') || [];
            if (sections.length > 1) {
                const details = document.createElement('details');
                details.className = 'wh-fm-meta-wrap wh-fm-meta-wrap--compact';
                details.open = true;
                const summary = document.createElement('summary');
                summary.className = 'wh-fm-meta-wrap__toggle';
                summary.innerHTML = `<span>${esc(t('wms_grn_section_info'))}</span><span class="material-icons-round" aria-hidden="true">expand_more</span>`;
                body.insertBefore(details, sections[0]);
                sections.forEach((section) => details.appendChild(section));
            }
        } else if (info && !info.closest('.wh-fm-meta-wrap')) {
            const title = info.querySelector('.wms-grn-section__title');
            const label = title?.textContent?.trim() || t('wms_grn_section_info');
            const details = document.createElement('details');
            details.className = 'wh-fm-meta-wrap';
            details.open = true;
            const summary = document.createElement('summary');
            summary.className = 'wh-fm-meta-wrap__toggle';
            summary.innerHTML = `<span>${esc(label)}</span><span class="material-icons-round" aria-hidden="true">expand_more</span>`;
            info.parentNode.insertBefore(details, info);
            details.appendChild(summary);
            details.appendChild(info);
            if (title) title.classList.add('wh-fm-sr-only');
        }

        const footer = panel.querySelector('.wms-grn-modal__footer');
        if (footer && !footer.querySelector('.wh-fm-form-error')) {
            const err = document.createElement('p');
            err.className = 'wh-fm-form-error';
            err.hidden = true;
            err.setAttribute('role', 'alert');
            footer.insertBefore(err, footer.firstChild);
        }

        const scrollEl = panel.querySelector('.wms-grn-lines__body')
            || panel.querySelector('.wms-grn-form__body');
        bindFormModalSwipe(overlay, panel, scrollEl);
    }

    function initWhFormModals() {
        document.querySelectorAll('.wms-modal-overlay').forEach((overlay) => {
            if (overlay.querySelector('.wh-form-modal')) {
                enhanceFormModal(overlay);
            }
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        initWhFormModals();
        refreshNotifBadge();
    });

    return {
        t, esc, money, pct, defaultCurrency, currencyMeta,
        showError, hideError, setMigrationHint, updateLastUpdated, exportCsv, loadWarehouseOptions,
        openWhFormModal, closeWhFormModal, showFormModalError, clearFormModalError, initWhFormModals,
        notifIcon, notifSeverityTone, refreshNotifBadge,
    };
})();
