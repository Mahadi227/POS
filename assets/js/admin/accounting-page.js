/**
 * Generic accounting pages — load API endpoint and render table
 */
document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('accPageRoot');
    if (!root) return;

    const endpoint = root.dataset.endpoint;
    if (!endpoint) return;

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv } = AccountingUI;

    function flattenData(data) {
        if (Array.isArray(data)) return { rows: data, type: 'array' };
        if (data?.rows) return { rows: data.rows, type: 'array' };
        if (data?.accounts) return { rows: data.accounts, type: 'accounts' };
        if (data?.summary) return { rows: [data.summary], type: 'summary' };
        if (data?.assets || data?.liabilities) return { rows: data, type: 'report' };
        if (typeof data === 'object' && data !== null) return { rows: [data], type: 'object' };
        return { rows: [], type: 'empty' };
    }

    function renderReport(data) {
        if (data.revenue !== undefined) {
            return `<div class="acc-report-cards">
                <article class="acc-report-card"><span>${esc(t('kpi_revenue'))}</span><strong>${esc(money(data.revenue))}</strong></article>
                <article class="acc-report-card"><span>COGS</span><strong>${esc(money(data.cogs))}</strong></article>
                <article class="acc-report-card"><span>${esc(t('kpi_gross_profit'))}</span><strong>${esc(money(data.gross_profit))}</strong></article>
                <article class="acc-report-card"><span>${esc(t('kpi_expenses'))}</span><strong>${esc(money(data.expenses))}</strong></article>
                <article class="acc-report-card acc-report-card--highlight"><span>${esc(t('kpi_net_profit'))}</span><strong>${esc(money(data.net_profit))}</strong></article>
            </div>`;
        }
        if (data.assets) {
            const section = (title, items) => items?.length ? `<h4>${esc(title)}</h4><table class="modern-table acc-table"><thead><tr><th>Code</th><th>Account</th><th>Balance</th></tr></thead><tbody>
                ${items.map((r) => `<tr><td>${esc(r.code)}</td><td>${esc(r.name)}</td><td>${esc(money(r.balance))}</td></tr>`).join('')}
            </tbody></table>` : '';
            return section(t('report_assets'), data.assets) + section(t('report_liabilities'), data.liabilities) + section(t('report_equity'), data.equity);
        }
        if (data.cash_in) {
            return `<div class="acc-report-cards">
                <article class="acc-report-card"><span>${esc(t('cash_in'))}</span><strong>${esc(money(data.cash_in?.total))}</strong></article>
                <article class="acc-report-card"><span>${esc(t('cash_out'))}</span><strong>${esc(money(data.cash_out?.total))}</strong></article>
                <article class="acc-report-card acc-report-card--highlight"><span>${esc(t('net_cash_flow'))}</span><strong>${esc(money(data.net_cash_flow))}</strong></article>
            </div>`;
        }
        return `<pre class="acc-json">${esc(JSON.stringify(data, null, 2))}</pre>`;
    }

    function renderTable(rows) {
        if (!rows.length) return `<div class="acc-empty"><span class="material-icons-round">inbox</span><p>${esc(t('cr_no_data'))}</p></div>`;
        const keys = Object.keys(rows[0]).filter((k) => !k.startsWith('lines') && k !== 'details');
        return `<div class="acc-table-wrap"><table class="modern-table acc-table"><thead><tr>
            ${keys.map((k) => `<th>${esc(k.replace(/_/g, ' '))}</th>`).join('')}
        </tr></thead><tbody>${rows.map((row) => `<tr>${keys.map((k) => {
            let val = row[k];
            if (k.includes('amount') || k.includes('balance') || k.includes('revenue') || k.includes('total')) val = money(val);
            else if (val && typeof val === 'object') val = JSON.stringify(val);
            return `<td>${esc(String(val ?? '—'))}</td>`;
        }).join('')}</tr>`).join('')}</tbody></table></div>`;
    }

    async function load() {
        hideError();
        root.innerHTML = `<div class="acc-loading">${esc(t('loading'))}</div>`;
        try {
            const from = new Date();
            from.setDate(1);
            const res = await AdminAPI.getAccounting(endpoint, {
                from: from.toISOString().slice(0, 10),
                to: new Date().toISOString().slice(0, 10),
            });
            if (res.status !== 'success') throw new Error(res.message);
            setMigrationHint(res.module_ready ?? true);
            const { rows, type } = flattenData(res.data);
            if (type === 'report' || type === 'summary' || type === 'object') {
                root.innerHTML = `<div class="acc-panel"><div class="acc-panel__body">${renderReport(res.data)}</div></div>`;
            } else {
                root.innerHTML = `<div class="acc-panel"><header class="acc-panel__head">
                    <span>${rows.length} ${esc(t('records'))}</span>
                    <button type="button" class="acc-btn acc-btn--ghost" id="accExportBtn">${esc(t('cr_export_csv'))}</button>
                </header><div class="acc-panel__body">${renderTable(rows)}</div></div>`;
                document.getElementById('accExportBtn')?.addEventListener('click', () => {
                    if (!rows.length) return;
                    const keys = Object.keys(rows[0]);
                    exportCsv(`accounting-${endpoint.replace(/\//g, '-')}.csv`, [keys, ...rows.map((r) => keys.map((k) => r[k]))]);
                });
            }
            updateLastUpdated();
        } catch (e) {
            showError(e.message || t('load_error'));
            root.innerHTML = `<div class="acc-empty"><p>${esc(e.message)}</p></div>`;
        }
    }

    load();
    document.addEventListener('acc:refresh', load);
});
