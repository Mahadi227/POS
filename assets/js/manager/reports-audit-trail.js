/**
 * Audit trail — manager reports
 */
(() => {
    const root = document.getElementById('auditTrailRoot');
    if (!root) return;

    const i18n = window.MANAGER_I18N || {};
    const locale = window.MANAGER_CONFIG?.locale || 'fr-FR';
    let lastFetchAt = null;
    let activeFilter = 'all';
    let filterState = { period: 'today', from: null, to: null };
    let searchQuery = '';
    let searchTimer = null;

    const els = {
        errorBanner: document.getElementById('mgrError'),
        lastUpdated: document.getElementById('lastUpdated'),
        periodFilter: document.getElementById('atPeriodFilter'),
        dateRange: document.getElementById('atDateRange'),
        dateFrom: document.getElementById('atDateFrom'),
        dateTo: document.getElementById('atDateTo'),
        dateApply: document.getElementById('atDateApply'),
        searchInput: document.getElementById('atSearchInput'),
        countTotal: document.getElementById('atCountTotal'),
        countApproved: document.getElementById('atCountApproved'),
        countRejected: document.getElementById('atCountRejected'),
        countUsers: document.getElementById('atCountUsers'),
        tableCount: document.getElementById('atTableCount'),
        migrationHint: document.getElementById('atMigrationHint'),
        summaryCards: document.querySelectorAll('#atSummary .ad-stat-card'),
        filterBar: document.getElementById('atFilterBar'),
    };

    const ACTION_ICONS = {
        approval_approved: 'check_circle',
        approval_rejected: 'cancel',
    };

    function t(key, ...args) {
        let str = i18n[key] || key;
        args.forEach((val) => {
            str = str.replace('%s', val);
        });
        return str;
    }

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s ?? '';
        return d.innerHTML;
    }

    function todayIso() {
        return new Date().toISOString().slice(0, 10);
    }

    function showError(msg) {
        if (!els.errorBanner) return;
        els.errorBanner.classList.add('is-visible');
        const text = els.errorBanner.querySelector('.ad-error-text');
        if (text) text.textContent = msg;
    }

    function hideError() {
        els.errorBanner?.classList.remove('is-visible');
    }

    function updateLastUpdated() {
        if (!els.lastUpdated || !lastFetchAt) return;
        const time = lastFetchAt.toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' });
        els.lastUpdated.textContent = `${t('last_updated')} · ${time}`;
    }

    function setSummaryLoading(loading) {
        els.summaryCards.forEach((card) => card.classList.toggle('is-loading', loading));
    }

    function setPeriodActive(period) {
        els.periodFilter?.querySelectorAll('.mgr-period-btn').forEach((btn) => {
            const active = btn.dataset.period === period;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        if (els.dateRange) {
            els.dateRange.hidden = period !== 'custom';
        }
    }

    function syncFilterBar() {
        els.filterBar?.querySelectorAll('[data-filter]').forEach((btn) => {
            btn.classList.toggle('is-active', btn.dataset.filter === activeFilter);
        });
    }

    function actionLabel(action) {
        const map = {
            approval_approved: t('audit_action_approved'),
            approval_rejected: t('audit_action_rejected'),
        };
        return map[action] || action || '—';
    }

    function actionBadgeClass(action) {
        if (action === 'approval_approved') return 'mgr-badge--ok';
        if (action === 'approval_rejected') return 'mgr-badge--off';
        return 'mgr-badge--idle';
    }

    function entityLabel(entityType, entityId) {
        if (!entityType && !entityId) return '—';
        const typeMap = {
            manager_approval: t('audit_entity_approval'),
        };
        const label = typeMap[entityType] || entityType || t('audit_entity_generic');
        return entityId ? `${label} #${entityId}` : label;
    }

    function formatDetails(details) {
        if (!details) return '—';
        if (typeof details === 'string') return details;
        if (typeof details === 'object') {
            const parts = Object.entries(details)
                .filter(([, v]) => v !== null && v !== undefined && v !== '')
                .slice(0, 3)
                .map(([k, v]) => `${k}: ${v}`);
            return parts.length ? parts.join(' · ') : '—';
        }
        return String(details);
    }

    function updateSummary(summary) {
        const s = summary || {};
        if (els.countTotal) els.countTotal.textContent = String(s.total ?? 0);
        if (els.countApproved) els.countApproved.textContent = String(s.approved ?? 0);
        if (els.countRejected) els.countRejected.textContent = String(s.rejected ?? 0);
        if (els.countUsers) els.countUsers.textContent = String(s.unique_users ?? 0);
    }

    function renderTable(items) {
        if (els.tableCount) els.tableCount.textContent = String(items?.length ?? 0);

        if (!items?.length) {
            root.innerHTML = `<p class="mgr-empty">${esc(t('no_audit_events'))}</p>`;
            return;
        }

        root.innerHTML = `
            <div class="mgr-table-wrap">
                <table class="modern-table mgr-at-table">
                    <thead>
                        <tr>
                            <th>${esc(t('col_date'))}</th>
                            <th>${esc(t('audit_col_manager'))}</th>
                            <th>${esc(t('audit_col_action'))}</th>
                            <th>${esc(t('audit_col_entity'))}</th>
                            <th>${esc(t('audit_col_details'))}</th>
                            <th>${esc(t('audit_col_ip'))}</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${items.map((row) => {
                            const icon = ACTION_ICONS[row.action] || 'history';
                            return `
                            <tr>
                                <td>
                                    <span class="mgr-at-date">${esc(ManagerAPI.formatDate(row.created_at))}</span>
                                </td>
                                <td><strong>${esc(row.user_name)}</strong></td>
                                <td>
                                    <span class="mgr-badge mgr-at-badge ${actionBadgeClass(row.action)}">
                                        <span class="material-icons-round">${icon}</span>
                                        ${esc(actionLabel(row.action))}
                                    </span>
                                </td>
                                <td>${esc(entityLabel(row.entity_type, row.entity_id))}</td>
                                <td class="mgr-at-details">${esc(formatDetails(row.details))}</td>
                                <td class="mgr-muted">${esc(row.ip_address || '—')}</td>
                            </tr>`;
                        }).join('')}
                    </tbody>
                </table>
            </div>`;
    }

    function buildQuery() {
        const query = {
            period: filterState.period,
            filter: activeFilter,
        };
        if (filterState.period === 'custom') {
            query.from = filterState.from || els.dateFrom?.value || todayIso();
            query.to = filterState.to || els.dateTo?.value || todayIso();
        }
        if (searchQuery) {
            query.q = searchQuery;
        }
        return query;
    }

    async function loadAudit(silent = false) {
        if (!silent) {
            setSummaryLoading(true);
            hideError();
            root.innerHTML = `<div class="mgr-list mgr-list--loading">${esc(t('loading'))}</div>`;
        }

        try {
            const res = await ManagerAPI.getAuditTrail(buildQuery());
            if (res.status !== 'success' || !res.data) {
                throw new Error(res.message || t('load_error'));
            }

            const d = res.data;

            if (els.migrationHint) {
                els.migrationHint.hidden = !!d.module_ready;
            }

            updateSummary(d.summary);
            renderTable(d.items || []);

            lastFetchAt = new Date();
            updateLastUpdated();
        } catch (e) {
            console.error(e);
            const msg = e.message || t('load_error');
            if (!silent) showError(msg);
            root.innerHTML = `<p class="mgr-empty">${esc(msg)}</p>`;
        }

        if (!silent) setSummaryLoading(false);
    }

    function initDateControls() {
        const today = todayIso();
        if (els.dateFrom && !els.dateFrom.value) els.dateFrom.value = today;
        if (els.dateTo && !els.dateTo.value) els.dateTo.value = today;
        if (els.dateFrom) els.dateFrom.max = today;
        if (els.dateTo) els.dateTo.max = today;

        els.dateApply?.addEventListener('click', () => {
            filterState.period = 'custom';
            filterState.from = els.dateFrom?.value || today;
            filterState.to = els.dateTo?.value || today;
            setPeriodActive('custom');
            loadAudit();
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        initDateControls();
        setPeriodActive(filterState.period);
        syncFilterBar();

        els.periodFilter?.querySelectorAll('[data-period]').forEach((btn) => {
            btn.addEventListener('click', () => {
                filterState.period = btn.dataset.period || 'today';
                setPeriodActive(filterState.period);
                if (filterState.period !== 'custom') {
                    loadAudit();
                }
            });
        });

        els.filterBar?.querySelectorAll('[data-filter]').forEach((btn) => {
            btn.addEventListener('click', () => {
                activeFilter = btn.dataset.filter || 'all';
                syncFilterBar();
                loadAudit();
            });
        });

        els.searchInput?.addEventListener('input', (e) => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => {
                searchQuery = String(e.target.value || '').trim();
                loadAudit(true);
            }, 350);
        });

        loadAudit();
    });

    document.addEventListener('mgr:refresh', () => loadAudit(true));
})();
