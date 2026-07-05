(function () {
    'use strict';

    const cfg = window.PLATFORM_CONFIG || {};
    const { apiGet, t, setLastUpdated } = window.PlatformAPI || {};

    let debounce = null;

    const CHANNEL_I18N = {
        application: 'plat_logs_channel_application',
        email: 'plat_logs_channel_email',
        sms: 'plat_logs_channel_sms',
        webhook: 'plat_logs_channel_webhook',
    };

    const LEVEL_I18N = {
        debug: 'plat_logs_level_debug',
        info: 'plat_logs_level_info',
        warning: 'plat_logs_level_warning',
        error: 'plat_logs_level_error',
        critical: 'plat_logs_level_critical',
    };

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s ?? '';
        return d.innerHTML;
    }

    function label(map, key) {
        return map[key] ? t(map[key]) : key;
    }

    function fmt(v) {
        if (!v) return '—';
        try { return new Date(v).toLocaleString(cfg.locale); } catch (e) { return '—'; }
    }

    function showError(msg) {
        const el = document.getElementById('platLogsError');
        document.getElementById('platLogsErrorText').textContent = msg || t('plat_logs_load_error');
        el.hidden = false;
    }

    function setKpiLoading(loading) {
        document.querySelectorAll('#platLogsKpiGrid .plat-kpi-card').forEach((c) => {
            c.classList.toggle('is-loading', loading);
        });
    }

    function updateCount(n) {
        const el = document.getElementById('platLogsCount');
        if (!el) return;
        const template = t('plat_logs_count');
        el.textContent = template.includes('%d') ? template.replace('%d', String(n)) : `${n}`;
    }

    function updateClearBtn() {
        const search = document.getElementById('platLogsSearch')?.value || '';
        const channel = document.getElementById('platLogsChannel')?.value || '';
        const level = document.getElementById('platLogsLevel')?.value || '';
        const btn = document.getElementById('platLogsClearFilters');
        if (btn) btn.hidden = !search && !channel && !level;
    }

    function levelPill(level) {
        const safe = esc(level || 'info');
        return `<span class="plat-gov-pill plat-logs-pill--${safe}">${esc(label(LEVEL_I18N, level))}</span>`;
    }

    function renderKpis(stats) {
        document.getElementById('platLogsKpiTotal').textContent = String(stats?.total ?? 0);
        document.getElementById('platLogsKpiToday').textContent = String(stats?.today ?? 0);
        document.getElementById('platLogsKpiErrors').textContent = String(stats?.errors ?? 0);
        document.getElementById('platLogsKpiEmail').textContent = String(stats?.email ?? 0);
        document.getElementById('platLogsKpiWebhook').textContent = String(stats?.webhook_failed ?? 0);
        updateCount(stats?.total ?? 0);
    }

    function renderEntries(rows) {
        const body = document.getElementById('platLogsEntries');
        if (!rows?.length) {
            body.innerHTML = `<tr><td colspan="6" class="plat-gov-muted">${esc(t('plat_no_data'))}</td></tr>`;
            return;
        }
        body.innerHTML = rows.map((r) => {
            const msg = (r.message || '').length > 80 ? `${r.message.slice(0, 80)}…` : r.message;
            const detailBtn = r.channel === 'application'
                ? `<button type="button" class="plat-gov-btn" data-log-ref="${esc(r.ref)}">${esc(t('plat_view_detail'))}</button>`
                : '';
            return `<tr>
                <td><span class="plat-logs-channel">${esc(label(CHANNEL_I18N, r.channel))}</span></td>
                <td>${levelPill(r.level)}</td>
                <td>${esc(msg || '—')}</td>
                <td>${esc(r.tenant_name || '—')}</td>
                <td>${esc(fmt(r.created_at))}</td>
                <td>${detailBtn}</td>
            </tr>`;
        }).join('');
    }

    function openDetail(ref) {
        apiGet(`logs/${encodeURIComponent(ref)}`).then((res) => {
            if (res.status !== 'success') return;
            const d = res.data;
            let ctx = '—';
            if (d.context_json) {
                try {
                    const parsed = typeof d.context_json === 'string' ? JSON.parse(d.context_json) : d.context_json;
                    ctx = JSON.stringify(parsed, null, 2);
                } catch (e) {
                    ctx = String(d.context_json);
                }
            }
            document.getElementById('platLogsDetail').innerHTML = `
                <div class="plat-gov-detail-row"><span>${esc(t('plat_logs_col_channel'))}</span>${esc(label(CHANNEL_I18N, d.channel))}</div>
                <div class="plat-gov-detail-row"><span>${esc(t('plat_logs_col_level'))}</span>${levelPill(d.level)}</div>
                <div class="plat-gov-detail-row"><span>${esc(t('plat_logs_col_message'))}</span>${esc(d.message || '—')}</div>
                <div class="plat-gov-detail-row"><span>${esc(t('plat_logs_col_org'))}</span>${esc(d.tenant_name || '—')}</div>
                <div class="plat-gov-detail-row"><span>${esc(t('plat_logs_col_date'))}</span>${esc(fmt(d.created_at))}</div>
                <div class="plat-gov-detail-row"><span>${esc(t('plat_logs_detail_context'))}</span><pre class="plat-gov-json">${esc(ctx)}</pre></div>
            `;
            document.getElementById('platLogsModal').hidden = false;
        }).catch(() => {});
    }

    function loadDashboard() {
        setKpiLoading(true);
        apiGet('logs/dashboard').then((res) => {
            if (res.status !== 'success') throw new Error();
            renderKpis(res.data?.stats);
            renderEntries(res.data?.recent);
            setLastUpdated?.();
        }).catch(() => showError()).finally(() => setKpiLoading(false));
    }

    function loadEntries() {
        const p = new URLSearchParams();
        const q = document.getElementById('platLogsSearch')?.value?.trim();
        const channel = document.getElementById('platLogsChannel')?.value;
        const level = document.getElementById('platLogsLevel')?.value;
        if (q) p.set('q', q);
        if (channel) p.set('channel', channel);
        if (level) p.set('level', level);
        const qs = p.toString();
        apiGet(`logs/entries${qs ? `?${qs}` : ''}`).then((res) => {
            if (res.status !== 'success') throw new Error();
            renderEntries(res.data?.entries);
            renderKpis(res.data?.stats);
            updateClearBtn();
        }).catch(() => showError());
    }

    document.getElementById('platLogsSearch')?.addEventListener('input', () => {
        clearTimeout(debounce);
        debounce = setTimeout(loadEntries, 300);
    });
    document.getElementById('platLogsChannel')?.addEventListener('change', loadEntries);
    document.getElementById('platLogsLevel')?.addEventListener('change', loadEntries);
    document.getElementById('platLogsClearFilters')?.addEventListener('click', () => {
        document.getElementById('platLogsSearch').value = '';
        document.getElementById('platLogsChannel').value = '';
        document.getElementById('platLogsLevel').value = '';
        loadEntries();
    });
    document.getElementById('platLogsEntries')?.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-log-ref]');
        if (btn) openDetail(btn.dataset.logRef);
    });
    document.querySelectorAll('[data-close-logs-modal]').forEach((el) => {
        el.addEventListener('click', () => { document.getElementById('platLogsModal').hidden = true; });
    });

    loadDashboard();
    loadEntries();
})();
