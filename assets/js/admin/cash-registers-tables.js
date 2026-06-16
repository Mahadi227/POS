/**
 * Cash registers — table pages (recon, movements, transfers, shifts, reports, logs)
 */
document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('crTableRoot');
    const page = document.body.dataset.crPage;
    if (!root || !page) return;

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv } = CashRegistersUI;

    async function loadReconciliation() {
        const status = document.getElementById('crFilterStatus')?.value || 'all';
        const res = await AdminAPI.getCashReconciliations(status === 'all' ? null : status);
        const items = res.data || [];
        if (!items.length) return `<p class="cr-empty">${esc(t('cr_no_data'))}</p>`;
        return `
            <table class="modern-table"><thead><tr>
                <th>${esc(t('cr_col_register'))}</th><th>${esc(t('cr_col_cashier'))}</th>
                <th>${esc(t('cr_col_expected'))}</th><th>${esc(t('cr_col_physical'))}</th>
                <th>${esc(t('cr_col_difference'))}</th><th>${esc(t('col_status'))}</th><th></th>
            </tr></thead><tbody>
            ${items.map((r) => `
                <tr>
                    <td>${esc(r.register_name)}</td>
                    <td>${esc(r.cashier_name)}</td>
                    <td>${esc(money(r.expected_cash))}</td>
                    <td>${esc(money(r.physical_cash))}</td>
                    <td>${esc(money(r.difference))}</td>
                    <td>${esc(r.status)}</td>
                    <td>${r.status === 'pending' ? `
                        <button type="button" class="cr-btn" data-approve="${r.id}">${esc(t('cr_recon_approve'))}</button>
                        <button type="button" class="cr-btn cr-btn--warn" data-reject="${r.id}">${esc(t('cr_recon_reject'))}</button>` : ''}
                    </td>
                </tr>`).join('')}
            </tbody></table>`;
    }

    async function loadMovements() {
        const res = await AdminAPI.getCashMovements({
            type: document.getElementById('crFilterType')?.value || 'all',
            from: document.getElementById('crDateFrom')?.value || undefined,
            to: document.getElementById('crDateTo')?.value || undefined,
        });
        const items = res.data || [];
        if (!items.length) return `<p class="cr-empty">${esc(t('cr_no_data'))}</p>`;
        window.__crExportRows = [['Date', 'Register', 'Type', 'Amount', 'By'], ...items.map((m) => [m.created_at, m.register_name, m.movement_type, m.amount, m.created_by_name])];
        return `<table class="modern-table"><thead><tr>
            <th>${esc(t('col_date'))}</th><th>${esc(t('cr_col_register'))}</th><th>${esc(t('cr_col_action'))}</th>
            <th>${esc(t('cr_amount'))}</th><th>${esc(t('cr_col_cashier'))}</th>
        </tr></thead><tbody>${items.map((m) => `
            <tr><td>${esc(AdminAPI.formatDate(m.created_at))}</td><td>${esc(m.register_name || '—')}</td>
            <td>${esc(m.movement_type)}</td><td>${esc(money(m.amount))}</td><td>${esc(m.created_by_name || '—')}</td></tr>`).join('')}
        </tbody></table>`;
    }

    async function loadTransfers() {
        const res = await AdminAPI.getCashTransfers(document.getElementById('crFilterStatus')?.value || null);
        const items = res.data || [];
        if (!items.length) return `<p class="cr-empty">${esc(t('cr_no_data'))}</p>`;
        return `<table class="modern-table"><thead><tr>
            <th>${esc(t('cr_transfer_type'))}</th><th>${esc(t('cr_amount'))}</th><th>${esc(t('col_status'))}</th><th>${esc(t('cr_reason'))}</th><th></th>
        </tr></thead><tbody>${items.map((tr) => `
            <tr><td>${esc(tr.transfer_type)}</td><td>${esc(money(tr.amount))}</td><td>${esc(tr.status)}</td><td>${esc(tr.reason || '—')}</td>
            <td>${tr.status === 'pending' ? `<button class="cr-btn" data-tapprove="${tr.id}">${esc(t('cr_recon_approve'))}</button>` : ''}
            ${tr.status === 'approved' ? `<button class="cr-btn" data-tcomplete="${tr.id}">Complete</button>` : ''}</td></tr>`).join('')}
        </tbody></table>`;
    }

    async function loadShifts() {
        const res = await AdminAPI.getCashRegisterSessions(document.getElementById('crFilterStatus')?.value || null);
        const items = res.data || [];
        if (!items.length) return `<p class="cr-empty">${esc(t('cr_no_data'))}</p>`;
        return `<table class="modern-table"><thead><tr>
            <th>${esc(t('cr_col_register'))}</th><th>${esc(t('cr_col_cashier'))}</th><th>Shift</th>
            <th>${esc(t('cr_col_opened'))}</th><th>${esc(t('cr_col_closed'))}</th><th>${esc(t('cr_stat_sales_today'))}</th><th>${esc(t('col_status'))}</th>
        </tr></thead><tbody>${items.map((s) => `
            <tr><td>${esc(s.register_name)}</td><td>${esc(s.cashier_name)}</td><td>${esc(s.shift_type)}</td>
            <td>${esc(AdminAPI.formatDate(s.opened_at))}</td><td>${esc(s.closed_at ? AdminAPI.formatDate(s.closed_at) : '—')}</td>
            <td>${esc(money(s.total_sales))}</td><td>${esc(s.status)}</td></tr>`).join('')}
        </tbody></table>`;
    }

    async function loadHistory() {
        const res = await AdminAPI.getCashRegisterHistory({
            from: document.getElementById('crDateFrom')?.value || undefined,
            to: document.getElementById('crDateTo')?.value || undefined,
        });
        const items = res.data || [];
        window.__crHistoryItems = items;
        if (!items.length) return `<p class="cr-empty">${esc(t('cr_no_data'))}</p>`;
        window.__crExportRows = [
            ['Date', 'Register', 'Branch', 'Cashier', 'Opening', 'Expected', 'Counted', 'Variance'],
            ...items.map((s) => [
                s.opened_at, s.register_name, s.store_name, s.cashier_name,
                s.opening_balance, s.expected_cash, s.counted_cash, s.variance,
            ]),
        ];
        return `<table class="modern-table" id="crReportTable"><thead><tr>
            <th>${esc(t('col_date'))}</th><th>${esc(t('cr_col_register'))}</th><th>${esc(t('cr_branch'))}</th><th>${esc(t('cr_col_cashier'))}</th>
            <th>${esc(t('cr_opening_balance'))}</th><th>${esc(t('cr_col_expected'))}</th><th>${esc(t('cr_counted_cash'))}</th><th>${esc(t('cr_col_difference'))}</th>
        </tr></thead><tbody>${items.map((s) => `
            <tr><td>${esc(AdminAPI.formatDate(s.opened_at))}</td><td>${esc(s.register_name)}</td><td>${esc(s.store_name)}</td><td>${esc(s.cashier_name)}</td>
            <td>${esc(money(s.opening_balance))}</td><td>${esc(money(s.expected_cash))}</td><td>${esc(money(s.counted_cash))}</td><td>${esc(money(s.variance))}</td></tr>`).join('')}
        </tbody></table>`;
    }

    async function loadLogs() {
        const res = await AdminAPI.getCashRegisterLogs();
        const items = res.data || [];
        if (!items.length) return `<p class="cr-empty">${esc(t('cr_no_data'))}</p>`;
        return `<table class="modern-table"><thead><tr>
            <th>${esc(t('col_date'))}</th><th>${esc(t('cr_col_action'))}</th><th>${esc(t('cr_col_register'))}</th><th>User</th>
        </tr></thead><tbody>${items.map((l) => `
            <tr><td>${esc(AdminAPI.formatDate(l.created_at))}</td><td>${esc(l.action)}</td><td>${esc(l.register_name || '—')}</td><td>${esc(l.user_name || '—')}</td></tr>`).join('')}
        </tbody></table>`;
    }

    async function load() {
        hideError();
        root.innerHTML = `<div class="cr-loading">${esc(t('loading'))}</div>`;
        try {
            let html = '';
            if (page === 'reconciliation') html = await loadReconciliation();
            else if (page === 'movements') html = await loadMovements();
            else if (page === 'transfers') html = await loadTransfers();
            else if (page === 'shifts') html = await loadShifts();
            else if (page === 'reports') html = await loadHistory();
            else if (page === 'logs') html = await loadLogs();
            root.innerHTML = `<div class="cr-table-wrap">${html}</div>`;
            bindActions();
            updateLastUpdated();
        } catch (e) {
            showError(e.message || t('load_error'));
            root.innerHTML = `<p class="cr-empty">${esc(e.message)}</p>`;
        }
    }

    function bindActions() {
        root.querySelectorAll('[data-approve]').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const note = prompt('Note (optional)') || '';
                const res = await AdminAPI.approveCashReconciliation(Number(btn.dataset.approve), note);
                if (res.status === 'success') load(); else alert(res.message);
            });
        });
        root.querySelectorAll('[data-reject]').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const note = prompt('Reason') || '';
                const res = await AdminAPI.rejectCashReconciliation(Number(btn.dataset.reject), note);
                if (res.status === 'success') load(); else alert(res.message);
            });
        });
        root.querySelectorAll('[data-tapprove]').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const res = await AdminAPI.approveCashTransfer(Number(btn.dataset.tapprove));
                if (res.status === 'success') load(); else alert(res.message);
            });
        });
        root.querySelectorAll('[data-tcomplete]').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const res = await AdminAPI.completeCashTransfer(Number(btn.dataset.tcomplete));
                if (res.status === 'success') load(); else alert(res.message);
            });
        });
    }

    document.getElementById('crFilterBtn')?.addEventListener('click', load);
    document.getElementById('crExportCsvBtn')?.addEventListener('click', () => {
        if (window.__crExportRows) exportCsv(`cash-register-${page || 'export'}.csv`, window.__crExportRows);
    });
    document.getElementById('crExportPdfBtn')?.addEventListener('click', async () => {
        if (page !== 'reports' || !window.__crHistoryItems?.length) {
            alert(t('cr_no_data'));
            return;
        }
        if (!window.CashRegisterReportExport) return;
        const btn = document.getElementById('crExportPdfBtn');
        btn?.setAttribute('disabled', 'disabled');
        try {
            const ctx = CashRegisterReportExport.buildHistoryContext(
                window.__crHistoryItems,
                document.getElementById('crDateFrom')?.value || '',
                document.getElementById('crDateTo')?.value || '',
                t,
                window.ADMIN_CONFIG?.locale || 'fr-FR'
            );
            const result = await CashRegisterReportExport.exportPdf(ctx);
            if (result?.fallback) alert(t('pdf_fallback_print'));
        } catch (e) {
            alert(e.message || t('error'));
        } finally {
            btn?.removeAttribute('disabled');
        }
    });
    document.getElementById('crPrintBtn')?.addEventListener('click', () => {
        const table = document.getElementById('crReportTable') || root.querySelector('table');
        if (!table) return;
        const w = window.open('', '_blank');
        w.document.write(`<html><head><title>${document.title}</title></head><body>${table.outerHTML}</body></html>`);
        w.document.close();
        w.print();
    });
    document.getElementById('crNewTransferBtn')?.addEventListener('click', async () => {
        const amount = parseFloat(prompt(t('cr_amount'), '0') || '0');
        const reason = prompt(t('cr_reason')) || '';
        const res = await AdminAPI.createCashTransfer({
            store_id: window.ADMIN_PAGE?.storeId,
            transfer_type: 'register_to_safe',
            amount,
            reason,
        });
        if (res.status === 'success') load(); else alert(res.message);
    });

    load();
    document.addEventListener('cr:refresh', load);
});
