(function () {
    'use strict';

    const cfg = window.PLATFORM_CONFIG || {};
    const { apiGet, t, setLastUpdated } = window.PlatformAPI || {};

    let allReports = [];
    let search = '';
    let category = '';
    let previewKey = '';

    const REPORT_I18N = {
        tenants: 'plat_report_tenants',
        subscriptions: 'plat_report_subscriptions',
        billing: 'plat_report_billing',
        revenue_monthly: 'plat_report_revenue_monthly',
        usage: 'plat_report_usage',
        licenses: 'plat_report_licenses',
        audit: 'plat_report_audit',
    };

    const REPORT_DESC_I18N = {
        tenants: 'plat_report_desc_tenants',
        subscriptions: 'plat_report_desc_subscriptions',
        billing: 'plat_report_desc_billing',
        revenue_monthly: 'plat_report_desc_revenue_monthly',
        usage: 'plat_report_desc_usage',
        licenses: 'plat_report_desc_licenses',
        audit: 'plat_report_desc_audit',
    };

    const CATEGORY_I18N = {
        core: 'plat_reports_cat_core',
        billing: 'plat_reports_cat_billing',
        operations: 'plat_reports_cat_operations',
        product: 'plat_reports_cat_product',
        security: 'plat_reports_cat_security',
    };

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s ?? '';
        return d.innerHTML;
    }

    function apiUrl(path) {
        const [base, query] = String(path).split('?');
        const url = `${cfg.apiBase}?request=platform/${base}`;
        return query ? `${url}&${query}` : url;
    }

    function reportLabel(key) {
        const i18n = REPORT_I18N[key];
        return i18n ? t(i18n) : key;
    }

    function reportDesc(key) {
        const i18n = REPORT_DESC_I18N[key];
        return i18n ? t(i18n) : '';
    }

    function categoryLabel(cat) {
        const i18n = CATEGORY_I18N[cat];
        return i18n ? t(i18n) : cat;
    }

    function showError(msg) {
        const el = document.getElementById('platReportsError');
        const text = document.getElementById('platReportsErrorText');
        if (!el || !text) return;
        text.textContent = msg || t('plat_reports_load_error');
        el.hidden = false;
    }

    function hideError() {
        const el = document.getElementById('platReportsError');
        if (el) el.hidden = true;
    }

    function setKpiLoading(loading) {
        document.querySelectorAll('#platReportsKpiGrid .plat-kpi-card').forEach((card) => {
            card.classList.toggle('is-loading', loading);
        });
    }

    function updateCount(n) {
        const el = document.getElementById('platReportsCount');
        if (!el) return;
        const template = t('plat_reports_count');
        el.textContent = template.includes('%d') ? template.replace('%d', String(n)) : `${n}`;
    }

    function updateClearBtn() {
        const btn = document.getElementById('platReportsClearFilters');
        if (!btn) return;
        btn.hidden = !search && !category;
    }

    function filteredReports() {
        const q = search.trim().toLowerCase();
        return allReports.filter((rep) => {
            if (category && rep.category !== category) return false;
            if (!q) return true;
            const label = reportLabel(rep.key).toLowerCase();
            const desc = reportDesc(rep.key).toLowerCase();
            return rep.key.toLowerCase().includes(q) || label.includes(q) || desc.includes(q);
        });
    }

    function renderReportCard(rep) {
        const icon = rep.icon || 'assessment';
        const unavailable = !rep.available;
        const rows = Number(rep.rows ?? 0);
        const formatLabel = t('plat_reports_format_csv');

        return `
            <article class="plat-report-card${unavailable ? ' is-unavailable' : ''}" data-key="${esc(rep.key)}">
                <div class="plat-report-card__head">
                    <div class="plat-report-card__icon" aria-hidden="true">
                        <span class="material-icons-round">${esc(icon)}</span>
                    </div>
                    <div>
                        <h3 class="plat-report-card__title">${esc(reportLabel(rep.key))}</h3>
                        <p class="plat-report-card__desc">${esc(reportDesc(rep.key))}</p>
                    </div>
                </div>
                <div class="plat-report-card__meta">
                    <span class="plat-report-cat">${esc(categoryLabel(rep.category))}</span>
                    <span class="plat-report-stat">
                        <strong>${esc(String(rows))}</strong> ${esc(t('plat_reports_col_rows'))}
                    </span>
                    <span class="plat-report-stat">${esc(formatLabel)}</span>
                    ${unavailable ? `<span class="plat-report-stat plat-reports-muted">${esc(t('plat_reports_unavailable'))}</span>` : ''}
                </div>
                <div class="plat-report-card__actions">
                    <button type="button" class="plat-report-btn" data-preview="${esc(rep.key)}" ${unavailable ? 'disabled' : ''}>
                        <span class="material-icons-round" aria-hidden="true">visibility</span>
                        ${esc(t('plat_reports_preview'))}
                    </button>
                    <button type="button" class="plat-report-btn plat-report-btn--primary" data-export="${esc(rep.key)}" ${unavailable ? 'disabled' : ''}>
                        <span class="material-icons-round" aria-hidden="true">download</span>
                        ${esc(t('plat_reports_export'))}
                    </button>
                </div>
            </article>`;
    }

    function renderGrid() {
        const grid = document.getElementById('platReportsGrid');
        const empty = document.getElementById('platReportsEmpty');
        if (!grid) return;

        const rows = filteredReports();
        updateCount(rows.length);

        if (!rows.length) {
            grid.innerHTML = '';
            if (empty) empty.hidden = false;
            return;
        }

        if (empty) empty.hidden = true;
        grid.innerHTML = rows.map(renderReportCard).join('');

        grid.querySelectorAll('[data-preview]').forEach((btn) => {
            btn.addEventListener('click', () => openPreview(btn.getAttribute('data-preview')));
        });
        grid.querySelectorAll('[data-export]').forEach((btn) => {
            btn.addEventListener('click', () => exportReport(btn.getAttribute('data-export')));
        });
    }

    function exportReport(key) {
        if (!key) return;
        window.open(apiUrl(`reports/${encodeURIComponent(key)}/export`), '_blank');
    }

    function closeModal() {
        const modal = document.getElementById('platReportsModal');
        if (modal) modal.hidden = true;
        previewKey = '';
    }

    async function openPreview(key) {
        if (!key) return;
        previewKey = key;

        const modal = document.getElementById('platReportsModal');
        const title = document.getElementById('platReportsModalTitle');
        const meta = document.getElementById('platReportsModalMeta');
        const head = document.getElementById('platReportsPreviewHead');
        const body = document.getElementById('platReportsPreviewBody');

        if (!modal || !title || !meta || !head || !body) return;

        title.textContent = reportLabel(key);
        meta.textContent = t('loading') + '…';
        head.innerHTML = '';
        body.innerHTML = `<tr><td class="plat-reports-muted">${esc(t('loading'))}…</td></tr>`;
        modal.hidden = false;

        try {
            const res = await apiGet(`reports/${encodeURIComponent(key)}/preview`);
            if (res.status !== 'success') {
                throw new Error(res.message || t('plat_reports_load_error'));
            }

            const data = res.data || {};
            const columns = data.columns || [];
            const rows = data.rows || [];
            const total = data.total ?? rows.length;

            const metaTemplate = t('plat_reports_preview_rows');
            meta.textContent = metaTemplate.includes('%d')
                ? metaTemplate.replace('%d', String(total))
                : `${total}`;

            head.innerHTML = `<tr>${columns.map((c) => `<th>${esc(c)}</th>`).join('')}</tr>`;

            if (!rows.length) {
                body.innerHTML = `<tr><td colspan="${columns.length || 1}" class="plat-reports-muted">${esc(t('plat_no_data'))}</td></tr>`;
                return;
            }

            body.innerHTML = rows.map((row) => `<tr>${columns.map((col) => {
                const val = row[col];
                return `<td>${esc(val === null || val === undefined ? '—' : String(val))}</td>`;
            }).join('')}</tr>`).join('');
        } catch (e) {
            meta.textContent = e.message || t('load_error');
            body.innerHTML = `<tr><td class="plat-reports-muted">${esc(e.message || t('load_error'))}</td></tr>`;
        }
    }

    async function refresh() {
        hideError();
        setKpiLoading(true);

        try {
            const res = await apiGet('reports/catalog');
            if (res.status !== 'success') {
                throw new Error(res.message || t('plat_reports_load_error'));
            }

            const stats = res.data?.stats || {};
            allReports = res.data?.reports || [];

            document.getElementById('platRepKpiTotal').textContent = String(stats.reports ?? 0);
            document.getElementById('platRepKpiCats').textContent = String(stats.categories ?? 0);
            document.getElementById('platRepKpiRows').textContent = String(stats.rows ?? 0);
            document.getElementById('platRepKpiFormats').textContent = t('plat_reports_format_csv');

            renderGrid();
            setLastUpdated?.();
        } catch (e) {
            showError(e.message || t('load_error'));
        } finally {
            setKpiLoading(false);
        }
    }

    document.getElementById('platReportsSearch')?.addEventListener('input', (e) => {
        search = e.target.value || '';
        updateClearBtn();
        renderGrid();
    });

    document.getElementById('platReportsCategory')?.addEventListener('change', (e) => {
        category = e.target.value || '';
        updateClearBtn();
        renderGrid();
    });

    document.getElementById('platReportsClearFilters')?.addEventListener('click', () => {
        search = '';
        category = '';
        const searchEl = document.getElementById('platReportsSearch');
        const catEl = document.getElementById('platReportsCategory');
        if (searchEl) searchEl.value = '';
        if (catEl) catEl.value = '';
        updateClearBtn();
        renderGrid();
    });

    document.getElementById('platReportsModalClose')?.addEventListener('click', closeModal);
    document.getElementById('platReportsModalBackdrop')?.addEventListener('click', closeModal);
    document.getElementById('platReportsModalExport')?.addEventListener('click', () => {
        if (previewKey) exportReport(previewKey);
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeModal();
    });

    document.addEventListener('DOMContentLoaded', refresh);
    document.addEventListener('plat:refresh', refresh);
})();
