/**
 * Cash movements — filters, stats, table + mobile cards
 */
document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('crMvRoot');
    if (!root) return;

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv } = CashRegistersUI;
    const PAGE_SIZE = 20;

    const state = { items: [], type: 'all', search: '', page: 1, dateFrom: '', dateTo: '' };

    const els = {
        search: document.getElementById('crMvSearch'),
        searchClear: document.getElementById('crMvSearchClear'),
        dateFrom: document.getElementById('crMvDateFrom'),
        dateTo: document.getElementById('crMvDateTo'),
        typeFilters: document.getElementById('crMvTypeFilters'),
        count: document.getElementById('crMvCount'),
        meta: document.getElementById('crMvMeta'),
        statTotal: document.getElementById('crMvStatTotal'),
        statVolume: document.getElementById('crMvStatVolume'),
        statSales: document.getElementById('crMvStatSales'),
        statToday: document.getElementById('crMvStatToday'),
        pagePrev: document.getElementById('crMvPrev'),
        pageNext: document.getElementById('crMvNext'),
        pageInfo: document.getElementById('crMvPageInfo'),
        exportBtn: document.getElementById('crMvExportBtn'),
        refreshBtn: document.getElementById('crMvRefreshBtn'),
    };

    const TYPE_KEYS = {
        opening_cash: 'cr_mv_type_opening_cash',
        sale: 'cr_mv_type_sale',
        refund: 'cr_mv_type_refund',
        closing_cash: 'cr_mv_type_closing_cash',
        transfer_out: 'cr_mv_type_transfer_out',
        adjustment: 'cr_mv_type_adjustment',
    };

    function typeLabel(type) {
        const key = TYPE_KEYS[type];
        return key ? t(key) : String(type || '—').replace(/_/g, ' ');
    }

    function typeIcon(type) {
        const map = {
            opening_cash: 'lock_open',
            closing_cash: 'lock',
            sale: 'point_of_sale',
            refund: 'undo',
            transfer_out: 'sync_alt',
            adjustment: 'tune',
        };
        return map[type] || 'swap_horiz';
    }

    function typeTone(type) {
        if (type === 'refund' || type === 'transfer_out') return 'warn';
        if (type === 'sale' || type === 'opening_cash') return 'ok';
        if (type === 'closing_cash') return 'off';
        return 'neutral';
    }

    function isToday(dateStr) {
        if (!dateStr) return false;
        const d = new Date(dateStr);
        const now = new Date();
        return d.getFullYear() === now.getFullYear() && d.getMonth() === now.getMonth() && d.getDate() === now.getDate();
    }

    function filteredItems() {
        let list = [...state.items];
        const q = state.search.trim().toLowerCase();
        if (q) {
            list = list.filter((m) => {
                const hay = [m.register_name, m.created_by_name, m.movement_type, m.reason, m.payment_method]
                    .map((v) => String(v ?? '').toLowerCase()).join(' ');
                return hay.includes(q);
            });
        }
        return list;
    }

    function paginated() {
        const all = filteredItems();
        const totalPages = Math.max(1, Math.ceil(all.length / PAGE_SIZE));
        if (state.page > totalPages) state.page = totalPages;
        const start = (state.page - 1) * PAGE_SIZE;
        return { all, pageItems: all.slice(start, start + PAGE_SIZE), totalPages };
    }

    function updateStats() {
        const list = filteredItems();
        const volume = list.reduce((s, m) => s + Math.abs(Number(m.amount || 0)), 0);
        const sales = list.filter((m) => m.movement_type === 'sale').reduce((s, m) => s + Number(m.amount || 0), 0);
        const today = list.filter((m) => isToday(m.created_at)).length;

        const set = (el, val) => { if (el) { el.textContent = val; el.classList.remove('is-loading'); } };
        set(els.statTotal, String(list.length));
        set(els.statVolume, money(volume));
        set(els.statSales, money(sales));
        set(els.statToday, String(today));
        if (els.count) els.count.textContent = list.length ? `${list.length}` : t('cr_no_data');
    }

    function renderTable() {
        const { all, pageItems, totalPages } = paginated();
        updateStats();

        if (els.meta) {
            els.meta.textContent = t('cr_mv_table_summary', all.length, state.page, totalPages);
        }
        if (els.pageInfo) els.pageInfo.textContent = `${state.page} / ${totalPages}`;
        if (els.pagePrev) els.pagePrev.disabled = state.page <= 1;
        if (els.pageNext) els.pageNext.disabled = state.page >= totalPages;

        if (!pageItems.length) {
            root.innerHTML = `<div class="cr-data-empty"><span class="material-icons-round">receipt_long</span><p>${esc(t('cr_no_data'))}</p></div>`;
            return;
        }

        root.innerHTML = `
            <div class="cr-data-table-wrap">
                <table class="modern-table cr-data-table" id="crMvTable">
                    <thead><tr>
                        <th>${esc(t('col_date'))}</th>
                        <th>${esc(t('cr_col_register'))}</th>
                        <th>${esc(t('cr_col_action'))}</th>
                        <th>${esc(t('cr_amount'))}</th>
                        <th>${esc(t('cr_col_cashier'))}</th>
                    </tr></thead>
                    <tbody>${pageItems.map((m) => {
                        const tone = typeTone(m.movement_type);
                        const amt = Number(m.amount || 0);
                        const amtClass = amt < 0 ? 'is-danger' : (amt > 0 ? 'is-ok' : '');
                        return `<tr>
                            <td>${esc(AdminAPI.formatDate(m.created_at))}</td>
                            <td>${esc(m.register_name || '—')}</td>
                            <td><span class="cr-data-type cr-data-type--${tone}"><span class="material-icons-round">${typeIcon(m.movement_type)}</span>${esc(typeLabel(m.movement_type))}</span></td>
                            <td class="${amtClass}"><strong>${esc(money(m.amount))}</strong></td>
                            <td>${esc(m.created_by_name || '—')}</td>
                        </tr>`;
                    }).join('')}</tbody>
                </table>
            </div>
            <div class="cr-data-list">${pageItems.map((m) => {
                const tone = typeTone(m.movement_type);
                return `<article class="cr-data-list__item">
                    <span class="cr-data-type cr-data-type--${tone}"><span class="material-icons-round">${typeIcon(m.movement_type)}</span></span>
                    <div class="cr-data-list__main">
                        <strong>${esc(typeLabel(m.movement_type))}</strong>
                        <span>${esc(m.register_name || '—')} · ${esc(AdminAPI.formatDate(m.created_at))}</span>
                    </div>
                    <strong>${esc(money(m.amount))}</strong>
                </article>`;
            }).join('')}</div>`;
    }

    function exportData() {
        const list = filteredItems();
        if (!list.length) return;
        exportCsv(`cash-movements-${new Date().toISOString().slice(0, 10)}.csv`, [
            [t('col_date'), t('cr_col_register'), t('cr_col_action'), t('cr_amount'), t('cr_col_cashier')],
            ...list.map((m) => [AdminAPI.formatDate(m.created_at), m.register_name, typeLabel(m.movement_type), m.amount, m.created_by_name]),
        ]);
    }

    function setType(type) {
        state.type = type;
        state.page = 1;
        els.typeFilters?.querySelectorAll('.cr-reg-chip').forEach((chip) => {
            const active = chip.dataset.type === type;
            chip.classList.toggle('is-active', active);
            chip.setAttribute('aria-selected', active ? 'true' : 'false');
        });
    }

    function initToolbar() {
        els.typeFilters?.querySelectorAll('[data-type]').forEach((chip) => {
            chip.addEventListener('click', () => { setType(chip.dataset.type || 'all'); load(); });
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
            els.search?.focus();
        });
        els.dateFrom?.addEventListener('change', () => load());
        els.dateTo?.addEventListener('change', () => load());
        els.exportBtn?.addEventListener('click', exportData);
        els.refreshBtn?.addEventListener('click', () => load());
        els.pagePrev?.addEventListener('click', () => { if (state.page > 1) { state.page -= 1; renderTable(); } });
        els.pageNext?.addEventListener('click', () => { state.page += 1; renderTable(); });
    }

    async function load() {
        hideError();
        root.innerHTML = `<div class="cr-loading">${esc(t('loading'))}</div>`;
        document.querySelectorAll('#crMvStats .cr-data-stat__value').forEach((el) => el.classList.add('is-loading'));
        try {
            const res = await AdminAPI.getCashMovements({
                type: state.type,
                from: els.dateFrom?.value || undefined,
                to: els.dateTo?.value || undefined,
                q: state.search.trim() || undefined,
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
