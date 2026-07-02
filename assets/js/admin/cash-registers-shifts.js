/**
 * Shift management — register sessions by shift type, filters, detail modal
 */
document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('crShiftRoot');
    if (!root) return;

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv } = CashRegistersUI;
    const PAGE_SIZE = 15;

    const state = { items: [], status: 'all', shiftType: 'all', search: '', page: 1 };

    const els = {
        search: document.getElementById('crShiftSearch'),
        searchClear: document.getElementById('crShiftSearchClear'),
        dateFrom: document.getElementById('crShiftDateFrom'),
        dateTo: document.getElementById('crShiftDateTo'),
        statusFilters: document.getElementById('crShiftStatusFilters'),
        shiftFilters: document.getElementById('crShiftTypeFilters'),
        stats: document.getElementById('crShiftStats'),
        count: document.getElementById('crShiftCount'),
        openBadge: document.getElementById('crShiftOpenBadge'),
        statOpen: document.getElementById('crShiftStatOpen'),
        statClosed: document.getElementById('crShiftStatClosed'),
        statSales: document.getElementById('crShiftStatSales'),
        statToday: document.getElementById('crShiftStatToday'),
        meta: document.getElementById('crShiftMeta'),
        pagePrev: document.getElementById('crShiftPrev'),
        pageNext: document.getElementById('crShiftNext'),
        pageInfo: document.getElementById('crShiftPageInfo'),
        exportBtn: document.getElementById('crShiftExportBtn'),
        refreshBtn: document.getElementById('crShiftRefreshBtn'),
        detailModal: document.getElementById('crShiftDetailModal'),
        detailBody: document.getElementById('crShiftDetailBody'),
    };

    const SHIFT_KEYS = {
        morning: 'cr_shift_morning',
        afternoon: 'cr_shift_afternoon',
        evening: 'cr_shift_evening',
        night: 'cr_shift_night',
    };

    function shiftLabel(type) {
        const key = SHIFT_KEYS[type];
        return key ? t(key) : String(type || '—');
    }

    function shiftIcon(type) {
        const map = { morning: 'wb_sunny', afternoon: 'light_mode', evening: 'nights_stay', night: 'bedtime' };
        return map[type] || 'schedule';
    }

    function statusLabel(status) {
        if (status === 'open') return t('cr_filter_session_open');
        if (status === 'closed') return t('cr_filter_session_closed');
        return status;
    }

    function statusClass(status) {
        return status === 'open' ? 'ok' : (status === 'closed' ? 'off' : 'neutral');
    }

    function isToday(dateStr) {
        if (!dateStr) return false;
        const d = new Date(dateStr);
        const now = new Date();
        return d.getFullYear() === now.getFullYear() && d.getMonth() === now.getMonth() && d.getDate() === now.getDate();
    }

    function formatDuration(openedAt, closedAt) {
        if (!openedAt) return '—';
        const start = new Date(openedAt).getTime();
        const end = closedAt ? new Date(closedAt).getTime() : Date.now();
        const mins = Math.max(0, Math.floor((end - start) / 60000));
        const h = Math.floor(mins / 60);
        const m = mins % 60;
        if (h > 0) return `${h}h ${m}m`;
        return `${m}m`;
    }

    function filteredItems() {
        let list = [...state.items];
        const q = state.search.trim().toLowerCase();
        if (state.status !== 'all') list = list.filter((s) => s.status === state.status);
        if (state.shiftType !== 'all') list = list.filter((s) => s.shift_type === state.shiftType);
        if (q) {
            list = list.filter((s) => {
                const hay = [s.register_name, s.register_code, s.cashier_name, s.store_name, s.shift_type, s.status]
                    .map((v) => String(v ?? '').toLowerCase()).join(' ');
                return hay.includes(q);
            });
        }
        return list.sort((a, b) => {
            if (a.status === 'open' && b.status !== 'open') return -1;
            if (b.status === 'open' && a.status !== 'open') return 1;
            return new Date(b.opened_at || 0) - new Date(a.opened_at || 0);
        });
    }

    function paginated() {
        const all = filteredItems();
        const totalPages = Math.max(1, Math.ceil(all.length / PAGE_SIZE));
        if (state.page > totalPages) state.page = totalPages;
        const start = (state.page - 1) * PAGE_SIZE;
        return { all, pageItems: all.slice(start, start + PAGE_SIZE), totalPages };
    }

    function updateStats() {
        const all = state.items;
        const open = all.filter((s) => s.status === 'open');
        const closed = all.filter((s) => s.status === 'closed');
        const sales = all.reduce((sum, s) => sum + Number(s.total_sales || 0), 0);
        const today = all.filter((s) => isToday(s.opened_at)).length;

        const set = (el, val) => { if (el) { el.textContent = val; el.classList.remove('is-loading'); } };
        set(els.statOpen, String(open.length));
        set(els.statClosed, String(closed.length));
        set(els.statSales, money(sales));
        set(els.statToday, String(today));

        if (els.openBadge) {
            if (open.length) {
                els.openBadge.hidden = false;
                els.openBadge.textContent = String(open.length);
            } else {
                els.openBadge.hidden = true;
            }
        }
        if (els.count) {
            const n = filteredItems().length;
            els.count.textContent = n ? `${n}` : t('cr_no_data');
        }
    }

    function detailHtml(s) {
        const rows = [
            [t('cr_col_register'), s.register_name],
            [t('cr_branch'), s.store_name],
            [t('cr_col_cashier'), s.cashier_name],
            [t('cr_shift_col_shift'), shiftLabel(s.shift_type)],
            [t('col_status'), statusLabel(s.status)],
            [t('cr_col_opened'), AdminAPI.formatDate(s.opened_at)],
            [t('cr_col_closed'), s.closed_at ? AdminAPI.formatDate(s.closed_at) : '—'],
            [t('cr_shift_duration'), formatDuration(s.opened_at, s.closed_at)],
            [t('cr_opening_balance'), money(s.opening_balance)],
            [t('cr_stat_sales_today'), money(s.total_sales)],
            [t('cr_col_expected'), s.expected_cash != null ? money(s.expected_cash) : '—'],
            [t('cr_counted_cash'), s.counted_cash != null ? money(s.counted_cash) : '—'],
            [t('cr_col_difference'), s.variance != null ? money(s.variance) : '—'],
        ];
        return `<dl class="cr-shift-detail">${rows.map(([label, val]) => `
            <div class="cr-shift-detail__row">
                <dt>${esc(label)}</dt>
                <dd>${esc(String(val ?? '—'))}</dd>
            </div>`).join('')}
        </dl>`;
    }

    function openDetail(s) {
        if (!els.detailModal || !els.detailBody) return;
        els.detailBody.innerHTML = detailHtml(s);
        els.detailModal.hidden = false;
    }

    function closeDetail() {
        if (els.detailModal) els.detailModal.hidden = true;
    }

    function renderTable() {
        const { all, pageItems, totalPages } = paginated();
        updateStats();

        if (els.meta) els.meta.textContent = t('cr_shift_table_summary', all.length, state.page, totalPages);
        if (els.pageInfo) els.pageInfo.textContent = `${state.page} / ${totalPages}`;
        if (els.pagePrev) els.pagePrev.disabled = state.page <= 1;
        if (els.pageNext) els.pageNext.disabled = state.page >= totalPages;

        if (!pageItems.length) {
            root.innerHTML = `<div class="cr-data-empty"><span class="material-icons-round">schedule</span><p>${esc(t('cr_no_data'))}</p></div>`;
            return;
        }

        root.innerHTML = `
            <div class="cr-data-table-wrap">
                <table class="modern-table cr-data-table">
                    <thead><tr>
                        <th>${esc(t('cr_col_register'))}</th>
                        <th>${esc(t('cr_col_cashier'))}</th>
                        <th>${esc(t('cr_shift_col_shift'))}</th>
                        <th>${esc(t('cr_col_opened'))}</th>
                        <th>${esc(t('cr_col_closed'))}</th>
                        <th>${esc(t('cr_stat_sales_today'))}</th>
                        <th>${esc(t('col_status'))}</th>
                        <th></th>
                    </tr></thead>
                    <tbody>${pageItems.map((s) => `
                        <tr>
                            <td><strong>${esc(s.register_name)}</strong></td>
                            <td>${esc(s.cashier_name || '—')}</td>
                            <td><span class="cr-data-type cr-data-type--info"><span class="material-icons-round">${shiftIcon(s.shift_type)}</span>${esc(shiftLabel(s.shift_type))}</span></td>
                            <td>${esc(AdminAPI.formatDate(s.opened_at))}</td>
                            <td>${esc(s.closed_at ? AdminAPI.formatDate(s.closed_at) : '—')}</td>
                            <td><strong>${esc(money(s.total_sales))}</strong></td>
                            <td><span class="cr-data-status cr-data-status--${statusClass(s.status)}">${esc(statusLabel(s.status))}</span></td>
                            <td><button type="button" class="cr-btn cr-btn--sm cr-btn--ghost" data-detail="${s.id}">${esc(t('cr_view_details'))}</button></td>
                        </tr>`).join('')}
                    </tbody>
                </table>
            </div>
            <div class="cr-data-list">${pageItems.map((s) => `
                <article class="cr-data-list__item cr-data-list__item--stack">
                    <div class="cr-data-list__main">
                        <strong>${esc(s.register_name)}</strong>
                        <span>${esc(s.cashier_name || '—')} · ${esc(shiftLabel(s.shift_type))}</span>
                        <span class="cr-data-status cr-data-status--${statusClass(s.status)}">${esc(statusLabel(s.status))}</span>
                    </div>
                    <div class="cr-data-list__side">
                        <strong>${esc(money(s.total_sales))}</strong>
                        <button type="button" class="cr-btn cr-btn--sm cr-btn--ghost" data-detail="${s.id}">${esc(t('cr_view_details'))}</button>
                    </div>
                </article>`).join('')}
            </div>`;

        root.querySelectorAll('[data-detail]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const id = Number(btn.dataset.detail);
                const row = state.items.find((s) => Number(s.id) === id);
                if (row) openDetail(row);
            });
        });
    }

    function exportData() {
        const list = filteredItems();
        if (!list.length) return;
        exportCsv(`cash-shifts-${new Date().toISOString().slice(0, 10)}.csv`, [
            [t('cr_col_register'), t('cr_col_cashier'), t('cr_shift_col_shift'), t('cr_col_opened'), t('cr_col_closed'), t('cr_stat_sales_today'), t('col_status')],
            ...list.map((s) => [
                s.register_name, s.cashier_name, shiftLabel(s.shift_type),
                AdminAPI.formatDate(s.opened_at),
                s.closed_at ? AdminAPI.formatDate(s.closed_at) : '',
                s.total_sales, statusLabel(s.status),
            ]),
        ]);
    }

    function setStatus(status) {
        state.status = status;
        state.page = 1;
        els.statusFilters?.querySelectorAll('.cr-reg-chip').forEach((chip) => {
            const active = chip.dataset.status === status;
            chip.classList.toggle('is-active', active);
            chip.setAttribute('aria-selected', active ? 'true' : 'false');
        });
    }

    function setShiftType(shift) {
        state.shiftType = shift;
        state.page = 1;
        els.shiftFilters?.querySelectorAll('.cr-reg-chip').forEach((chip) => {
            const active = chip.dataset.shift === shift;
            chip.classList.toggle('is-active', active);
            chip.setAttribute('aria-selected', active ? 'true' : 'false');
        });
    }

    function initToolbar() {
        els.statusFilters?.querySelectorAll('[data-status]').forEach((chip) => {
            chip.addEventListener('click', () => { setStatus(chip.dataset.status || 'all'); renderTable(); });
        });
        els.shiftFilters?.querySelectorAll('[data-shift]').forEach((chip) => {
            chip.addEventListener('click', () => { setShiftType(chip.dataset.shift || 'all'); renderTable(); });
        });
        els.stats?.querySelectorAll('[data-stat-filter]').forEach((btn) => {
            btn.addEventListener('click', () => { setStatus(btn.dataset.statFilter || 'all'); renderTable(); });
        });
        els.search?.addEventListener('input', () => {
            state.search = els.search.value;
            els.searchClear?.classList.toggle('visible', !!els.search.value);
            state.page = 1;
            renderTable();
        });
        els.searchClear?.addEventListener('click', () => {
            if (els.search) els.search.value = '';
            els.searchClear?.classList.remove('visible');
            state.search = '';
            state.page = 1;
            renderTable();
        });
        els.dateFrom?.addEventListener('change', () => load());
        els.dateTo?.addEventListener('change', () => load());
        els.exportBtn?.addEventListener('click', exportData);
        els.refreshBtn?.addEventListener('click', () => load());
        els.pagePrev?.addEventListener('click', () => { if (state.page > 1) { state.page -= 1; renderTable(); } });
        els.pageNext?.addEventListener('click', () => { state.page += 1; renderTable(); });
        els.detailModal?.querySelectorAll('[data-close-shift-modal]').forEach((el) => el.addEventListener('click', closeDetail));
    }

    async function load() {
        hideError();
        root.innerHTML = `<div class="cr-loading">${esc(t('loading'))}</div>`;
        document.querySelectorAll('#crShiftStats .cr-data-stat__value').forEach((el) => el.classList.add('is-loading'));
        try {
            const res = await AdminAPI.getCashRegisterSessions({
                from: els.dateFrom?.value || undefined,
                to: els.dateTo?.value || undefined,
            });
            if (res.status !== 'success') throw new Error(res.message);
            setMigrationHint(res.module_ready ?? true);
            state.items = res.data || [];
            state.page = 1;
            renderTable();
            updateLastUpdated();
        } catch (e) {
            showError(e.message || t('load_error'));
            root.innerHTML = `<div class="cr-data-empty"><span class="material-icons-round">error_outline</span><p>${esc(e.message)}</p></div>`;
        }
    }

    initToolbar();
    load();
    document.addEventListener('cr:refresh', load);
});
