(function () {
    'use strict';
    const cfg = window.PLATFORM_CONFIG || {};
    const { apiGet, t, setLastUpdated } = window.PlatformAPI || {};

    const TPL_I18N = {
        welcome: 'plat_email_tpl_welcome',
        trial_ending_7: 'plat_email_tpl_trial_ending_7',
        trial_ending_3: 'plat_email_tpl_trial_ending_3',
        trial_ending_1: 'plat_email_tpl_trial_ending_1',
        payment_failed: 'plat_email_tpl_payment_failed',
    };
    const CAT_I18N = { onboarding: 'plat_email_cat_onboarding', billing: 'plat_email_cat_billing' };

    let debounce = null;

    function esc(s) { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; }
    function label(map, k) { return map[k] ? t(map[k]) : k; }
    function fmt(v) { try { return new Date(v).toLocaleString(cfg.locale); } catch (e) { return '—'; } }

    function showError(msg) {
        const el = document.getElementById('platEmailError');
        document.getElementById('platEmailErrorText').textContent = msg || t('plat_email_load_error');
        el.hidden = false;
    }
    function setKpiLoading(l) {
        document.querySelectorAll('#platEmailKpiGrid .plat-kpi-card').forEach((c) => c.classList.toggle('is-loading', l));
    }

    function renderTemplates(rows) {
        const el = document.getElementById('platEmailTemplates');
        const filter = document.getElementById('platEmailTemplateFilter');
        if (!rows?.length) { el.innerHTML = `<p class="plat-comms-muted">${esc(t('plat_no_data'))}</p>`; return; }
        el.innerHTML = rows.map((r) => `<div class="plat-comms-card"><div class="plat-comms-card__head"><div class="plat-comms-card__icon"><span class="material-icons-round">${esc(r.icon || 'mail')}</span></div><div><h4 class="plat-comms-card__title">${esc(label(TPL_I18N, r.key) || r.key)}</h4><p class="plat-comms-card__meta">${esc(label(CAT_I18N, r.category))} · ${esc(String(r.sent_count ?? 0))} sent</p></div></div></div>`).join('');
        if (filter) {
            filter.innerHTML = `<option value="">${esc(t('plat_email_filter_all_templates'))}</option>` +
                rows.map((r) => `<option value="${esc(r.key)}">${esc(label(TPL_I18N, r.key) || r.key)}</option>`).join('');
        }
    }

    function renderLogs(rows, total) {
        const body = document.getElementById('platEmailLogs');
        const count = document.getElementById('platEmailCount');
        const template = t('plat_email_count');
        if (count) count.textContent = template.includes('%d') ? template.replace('%d', String(total ?? rows.length)) : `${total ?? rows.length}`;
        if (!rows?.length) { body.innerHTML = `<tr><td colspan="4" class="plat-comms-muted">${esc(t('plat_no_data'))}</td></tr>`; return; }
        body.innerHTML = rows.map((r) => `<tr>
            <td><code>${esc(r.template_key)}</code></td>
            <td>${esc(r.recipient)}</td>
            <td>${esc(r.tenant_name || '—')}</td>
            <td>${esc(fmt(r.sent_at))}</td>
        </tr>`).join('');
    }

    function query() {
        const p = new URLSearchParams();
        const q = document.getElementById('platEmailSearch')?.value?.trim();
        const tpl = document.getElementById('platEmailTemplateFilter')?.value;
        if (q) p.set('q', q);
        if (tpl) p.set('template', tpl);
        return `emails/dashboard${p.toString() ? '?' + p.toString().replace('emails/dashboard', '') : ''}`;
    }

    async function refresh() {
        setKpiLoading(true);
        try {
            const search = document.getElementById('platEmailSearch')?.value?.trim();
            const template = document.getElementById('platEmailTemplateFilter')?.value;
            let path = 'emails/dashboard';
            if (search || template) {
                const p = new URLSearchParams();
                if (search) p.set('q', search);
                if (template) p.set('template', template);
                path = `emails/logs?${p.toString()}`;
                const res = await apiGet(path);
                if (res.status !== 'success') throw new Error(res.message);
                const d = res.data || {};
                if (d.stats) {
                    document.getElementById('platEmailKpiTotal').textContent = String(d.stats.total ?? 0);
                    document.getElementById('platEmailKpiToday').textContent = String(d.stats.today ?? 0);
                    document.getElementById('platEmailKpiTemplates').textContent = String(d.stats.templates ?? 0);
                    document.getElementById('platEmailKpiTenants').textContent = String(d.stats.tenants ?? 0);
                }
                renderLogs(d.logs || [], (d.logs || []).length);
            } else {
                const res = await apiGet(path);
                if (res.status !== 'success') throw new Error(res.message);
                const d = res.data || {};
                const s = d.stats || {};
                document.getElementById('platEmailKpiTotal').textContent = String(s.total ?? 0);
                document.getElementById('platEmailKpiToday').textContent = String(s.today ?? 0);
                document.getElementById('platEmailKpiTemplates').textContent = String(s.templates ?? 0);
                document.getElementById('platEmailKpiTenants').textContent = String(s.tenants ?? 0);
                renderTemplates(d.templates || []);
                renderLogs(d.logs || [], (d.logs || []).length);
            }
            setLastUpdated?.();
        } catch (e) { showError(e.message); }
        finally { setKpiLoading(false); }
    }

    document.getElementById('platEmailSearch')?.addEventListener('input', () => { clearTimeout(debounce); debounce = setTimeout(refresh, 300); });
    document.getElementById('platEmailTemplateFilter')?.addEventListener('change', refresh);
    document.addEventListener('DOMContentLoaded', refresh);
    document.addEventListener('plat:refresh', refresh);
})();
