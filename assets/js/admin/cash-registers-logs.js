/**
 * Cash register audit logs — filters, stats, table + mobile cards
 */
document.addEventListener('DOMContentLoaded', () => {
    const { t, esc, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv } = CashRegistersUI;

    const PAGE_SIZE = 25;
    const ALERT_ACTIONS = new Set([
        'register_opened', 'register_closed', 'cash_difference', 'large_refund',
        'large_withdrawal', 'register_inactive', 'transfer_requested', 'session_opened', 'session_closed',
    ]);

    const els = {
        root: document.getElementById('crLogsRoot'),
        meta: document.getElementById('crLogsMeta'),
        search: document.getElementById('crLogsSearch'),
        searchClear: document.getElementById('crLogsSearchClear'),
        action: document.getElementById('crLogsAction'),
        dateFrom: document.getElementById('crLogsDateFrom'),
        dateTo: document.getElementById('crLogsDateTo'),
        statTotal: document.getElementById('crLogsStatTotal'),
        statToday: document.getElementById('crLogsStatToday'),
        statUsers: document.getElementById('crLogsStatUsers'),
        statAlerts: document.getElementById('crLogsStatAlerts'),
        stats: document.querySelectorAll('.cr-logs-stat'),
        pagePrev: document.getElementById('crLogsPrev'),
        pageNext: document.getElementById('crLogsNext'),
        pageInfo: document.getElementById('crLogsPageInfo'),
        modal: document.getElementById('crLogDetailModal'),
        modalBody: document.getElementById('crLogDetailBody'),
        modalClose: document.getElementById('crLogDetailClose'),
    };

    let allLogs = [];
    let currentPage = 1;
    let searchDebounce = null;

    function actionIcon(action) {
        const a = String(action || '').toLowerCase();
        if (a.includes('open')) return 'lock_open';
        if (a.includes('close')) return 'lock';
        if (a.includes('transfer')) return 'sync_alt';
        if (a.includes('recon') || a.includes('difference') || a.includes('variance')) return 'difference';
        if (a.includes('refund')) return 'undo';
        if (a.includes('withdraw')) return 'money_off';
        if (a.includes('sale')) return 'point_of_sale';
        if (a.includes('inactive') || a.includes('deactiv')) return 'block';
        return 'history';
    }

    function actionTone(action) {
        const a = String(action || '').toLowerCase();
        if (a.includes('difference') || a.includes('refund') || a.includes('withdraw') || a.includes('inactive')) return 'warn';
        if (a.includes('close')) return 'off';
        if (a.includes('open')) return 'ok';
        if (a.includes('transfer')) return 'info';
        return 'neutral';
    }

    function humanizeAction(action) {
        if (!action) return '—';
        const key = `cr_log_${action}`;
        const label = t(key);
        if (label !== key) return label;
        return String(action).replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
    }

    function parseDetails(raw) {
        if (!raw) return null;
        if (typeof raw === 'object') return raw;
        try {
            return JSON.parse(raw);
        } catch {
            return { raw: String(raw) };
        }
    }

    function formatDetailsHtml(details) {
        if (!details || (typeof details === 'object' && !Object.keys(details).length)) {
            return `<p class="cr-empty">${esc(t('cr_logs_no_details'))}</p>`;
        }
        if (typeof details !== 'object') {
            return `<pre class="cr-logs-json">${esc(String(details))}</pre>`;
        }
        return `<pre class="cr-logs-json">${esc(JSON.stringify(details, null, 2))}</pre>`;
    }

    function isToday(dateStr) {
        if (!dateStr) return false;
        const d = new Date(dateStr);
        const now = new Date();
        return d.getFullYear() === now.getFullYear()
            && d.getMonth() === now.getMonth()
            && d.getDate() === now.getDate();
    }

    function setStatsLoading(on) {
        els.stats.forEach((el) => el.classList.toggle('is-loading', on));
    }

    function updateStats(items) {
        setStatsLoading(false);
        const users = new Set(items.map((l) => l.user_id).filter(Boolean));
        const todayCount = items.filter((l) => isToday(l.created_at)).length;
        const alertCount = items.filter((l) => ALERT_ACTIONS.has(l.action)).length;
        if (els.statTotal) els.statTotal.textContent = String(items.length);
        if (els.statToday) els.statToday.textContent = String(todayCount);
        if (els.statUsers) els.statUsers.textContent = String(users.size);
        if (els.statAlerts) els.statAlerts.textContent = String(alertCount);
    }

    function populateActionFilter(actions) {
        if (!els.action) return;
        const current = els.action.value;
        const opts = [`<option value="all">${esc(t('cr_logs_all_actions'))}</option>`];
        (actions || []).forEach((action) => {
            opts.push(`<option value="${esc(action)}">${esc(humanizeAction(action))}</option>`);
        });
        els.action.innerHTML = opts.join('');
        if ([...els.action.options].some((o) => o.value === current)) {
            els.action.value = current;
        }
    }

    function getQueryParams() {
        return {
            from: els.dateFrom?.value || undefined,
            to: els.dateTo?.value || undefined,
            action: els.action?.value || 'all',
            q: els.search?.value.trim() || undefined,
            limit: 300,
        };
    }

    function renderLogRow(log) {
        const tone = actionTone(log.action);
        const registerLabel = log.register_name
            ? `${log.register_name}${log.register_code ? ` (${log.register_code})` : ''}`
            : '—';
        return `
            <article class="cr-log-row" data-log-id="${esc(String(log.id))}">
                <div class="cr-log-row__icon cr-log-row__icon--${tone}">
                    <span class="material-icons-round">${esc(actionIcon(log.action))}</span>
                </div>
                <div class="cr-log-row__main">
                    <div class="cr-log-row__top">
                        <span class="cr-log-action cr-log-action--${tone}">${esc(humanizeAction(log.action))}</span>
                        <time class="cr-log-row__time">${esc(AdminAPI.formatDate(log.created_at))}</time>
                    </div>
                    <div class="cr-log-row__meta">
                        <span><span class="material-icons-round">storefront</span>${esc(registerLabel)}</span>
                        <span><span class="material-icons-round">person</span>${esc(log.user_name || '—')}</span>
                        ${log.ip_address ? `<span><span class="material-icons-round">language</span>${esc(log.ip_address)}</span>` : ''}
                    </div>
                </div>
                <button type="button" class="cr-log-row__btn" data-log-detail="${esc(String(log.id))}" aria-label="${esc(t('cr_logs_view_details'))}">
                    <span class="material-icons-round">chevron_right</span>
                </button>
            </article>`;
    }

    function renderTableDesktop(pageItems) {
        return `
            <div class="cr-logs-table-wrap">
                <table class="modern-table cr-logs-table">
                    <thead>
                        <tr>
                            <th>${esc(t('col_date'))}</th>
                            <th>${esc(t('cr_col_action'))}</th>
                            <th>${esc(t('cr_col_register'))}</th>
                            <th>${esc(t('cr_col_user'))}</th>
                            <th>${esc(t('cr_logs_col_ip'))}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        ${pageItems.map((log) => {
                            const tone = actionTone(log.action);
                            const registerLabel = log.register_name
                                ? `${log.register_name}${log.register_code ? ` · ${log.register_code}` : ''}`
                                : '—';
                            return `
                            <tr>
                                <td class="cr-logs-table__date">${esc(AdminAPI.formatDate(log.created_at))}</td>
                                <td><span class="cr-log-action cr-log-action--${tone}">${esc(humanizeAction(log.action))}</span></td>
                                <td>${esc(registerLabel)}</td>
                                <td>${esc(log.user_name || '—')}</td>
                                <td class="cr-logs-table__ip">${esc(log.ip_address || '—')}</td>
                                <td class="cr-logs-table__act">
                                    <button type="button" class="cr-btn cr-btn--ghost cr-logs-detail-btn" data-log-detail="${esc(String(log.id))}">
                                        ${esc(t('cr_logs_view_details'))}
                                    </button>
                                </td>
                            </tr>`;
                        }).join('')}
                    </tbody>
                </table>
            </div>`;
    }

    function renderList() {
        const totalPages = Math.max(1, Math.ceil(allLogs.length / PAGE_SIZE));
        if (currentPage > totalPages) currentPage = totalPages;
        const start = (currentPage - 1) * PAGE_SIZE;
        const pageItems = allLogs.slice(start, start + PAGE_SIZE);

        if (els.meta) {
            els.meta.textContent = allLogs.length
                ? t('cr_logs_table_summary', String(allLogs.length), String(currentPage), String(totalPages))
                : t('cr_no_data');
        }
        if (els.pageInfo) els.pageInfo.textContent = `${currentPage} / ${totalPages}`;
        if (els.pagePrev) els.pagePrev.disabled = currentPage <= 1;
        if (els.pageNext) els.pageNext.disabled = currentPage >= totalPages;

        if (!pageItems.length) {
            els.root.innerHTML = `<p class="cr-empty">${esc(t('cr_no_data'))}</p>`;
            return;
        }

        const mobileList = `<div class="cr-logs-list">${pageItems.map(renderLogRow).join('')}</div>`;
        const desktopTable = renderTableDesktop(pageItems);
        els.root.innerHTML = `${mobileList}${desktopTable}`;
        bindDetailButtons();
    }

    function openDetail(id) {
        const log = allLogs.find((l) => String(l.id) === String(id));
        if (!log || !els.modal || !els.modalBody) return;
        const details = parseDetails(log.details);
        els.modalBody.innerHTML = `
            <dl class="cr-logs-detail-grid">
                <div><dt>${esc(t('col_date'))}</dt><dd>${esc(AdminAPI.formatDate(log.created_at))}</dd></div>
                <div><dt>${esc(t('cr_col_action'))}</dt><dd>${esc(humanizeAction(log.action))}</dd></div>
                <div><dt>${esc(t('cr_col_register'))}</dt><dd>${esc(log.register_name || '—')}${log.register_code ? ` (${esc(log.register_code)})` : ''}</dd></div>
                <div><dt>${esc(t('cr_col_user'))}</dt><dd>${esc(log.user_name || '—')}</dd></div>
                <div><dt>${esc(t('cr_logs_col_ip'))}</dt><dd>${esc(log.ip_address || '—')}</dd></div>
                <div><dt>${esc(t('cr_logs_col_entity'))}</dt><dd>${esc(log.entity_type || '—')}${log.entity_id ? ` #${esc(String(log.entity_id))}` : ''}</dd></div>
            </dl>
            <h3 class="cr-logs-detail-sub">${esc(t('cr_logs_col_details'))}</h3>
            ${formatDetailsHtml(details)}`;
        els.modal.hidden = false;
        els.modal.classList.add('is-open');
    }

    function closeDetail() {
        if (!els.modal) return;
        els.modal.hidden = true;
        els.modal.classList.remove('is-open');
    }

    function bindDetailButtons() {
        els.root.querySelectorAll('[data-log-detail]').forEach((btn) => {
            btn.addEventListener('click', () => openDetail(btn.getAttribute('data-log-detail')));
        });
    }

    function buildExportRows() {
        return [
            ['Date', 'Action', 'Register', 'Code', 'User', 'IP', 'Entity', 'Entity ID', 'Details'],
            ...allLogs.map((l) => [
                l.created_at,
                l.action,
                l.register_name || '',
                l.register_code || '',
                l.user_name || '',
                l.ip_address || '',
                l.entity_type || '',
                l.entity_id || '',
                typeof l.details === 'string' ? l.details : JSON.stringify(l.details || ''),
            ]),
        ];
    }

    async function load() {
        hideError();
        setStatsLoading(true);
        els.root.innerHTML = `<div class="cr-loading">${esc(t('loading'))}</div>`;
        try {
            const res = await AdminAPI.getCashRegisterLogs(getQueryParams());
            if (res.status !== 'success') throw new Error(res.message);
            setMigrationHint(res.module_ready);
            allLogs = res.data || [];
            populateActionFilter(res.actions || []);
            currentPage = 1;
            updateStats(allLogs);
            renderList();
            updateLastUpdated();
        } catch (e) {
            console.error(e);
            showError(e.message || t('load_error'));
            els.root.innerHTML = `<p class="cr-empty">${esc(e.message || t('connection_error'))}</p>`;
            setStatsLoading(false);
        }
    }

    els.search?.addEventListener('input', () => {
        els.searchClear?.classList.toggle('visible', !!els.search.value.trim());
        clearTimeout(searchDebounce);
        searchDebounce = setTimeout(load, 320);
    });

    els.searchClear?.addEventListener('click', () => {
        if (els.search) els.search.value = '';
        els.searchClear?.classList.remove('visible');
        load();
    });

    els.action?.addEventListener('change', load);
    document.getElementById('crLogsFilterBtn')?.addEventListener('click', load);
    document.getElementById('crLogsExportBtn')?.addEventListener('click', () => {
        if (!allLogs.length) return;
        exportCsv('cash-register-audit-logs.csv', buildExportRows());
    });

    els.pagePrev?.addEventListener('click', () => {
        if (currentPage > 1) {
            currentPage -= 1;
            renderList();
        }
    });
    els.pageNext?.addEventListener('click', () => {
        currentPage += 1;
        renderList();
    });

    els.modalClose?.addEventListener('click', closeDetail);
    els.modal?.addEventListener('click', (e) => {
        if (e.target === els.modal) closeDetail();
    });

    document.addEventListener('cr:refresh', load);
    load();
});
