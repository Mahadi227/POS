(function () {
    'use strict';
    const cfg = window.PLATFORM_CONFIG || {};
    const { apiGet, t, setLastUpdated } = window.PlatformAPI || {};

    const TPL_I18N = {
        account_locked: 'plat_sms_tpl_account_locked',
        trial_ending: 'plat_sms_tpl_trial_ending',
        payment_failed: 'plat_sms_tpl_payment_failed',
        security_alert: 'plat_sms_tpl_security_alert',
        otp_verification: 'plat_sms_tpl_otp_verification',
    };
    const CAT_I18N = { security: 'plat_sms_cat_security', billing: 'plat_sms_cat_billing' };
    const STATUS_I18N = { sent: 'plat_sms_status_sent', failed: 'plat_sms_status_failed' };

    let debounce = null;

    function esc(s) { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; }
    function label(map, k) { return map[k] ? t(map[k]) : k; }
    function fmt(v) { try { return new Date(v).toLocaleString(cfg.locale); } catch (e) { return '—'; } }

    function showError(msg) {
        const el = document.getElementById('platSmsError');
        document.getElementById('platSmsErrorText').textContent = msg || t('plat_sms_load_error');
        el.hidden = false;
    }
    function setKpiLoading(l) {
        document.querySelectorAll('#platSmsKpiGrid .plat-kpi-card').forEach((c) => c.classList.toggle('is-loading', l));
    }

    function renderTemplates(rows) {
        const el = document.getElementById('platSmsTemplates');
        const filter = document.getElementById('platSmsTemplateFilter');
        if (!rows?.length) { el.innerHTML = `<p class="plat-comms-muted">${esc(t('plat_no_data'))}</p>`; return; }
        el.innerHTML = rows.map((r) => `<div class="plat-comms-card"><div class="plat-comms-card__head"><div class="plat-comms-card__icon"><span class="material-icons-round">${esc(r.icon || 'sms')}</span></div><div><h4 class="plat-comms-card__title">${esc(label(TPL_I18N, r.key) || r.key)}</h4><p class="plat-comms-card__meta">${esc(label(CAT_I18N, r.category))} · ${esc(String(r.sent_count ?? 0))} sent</p></div></div></div>`).join('');
        if (filter) {
            filter.innerHTML = `<option value="">${esc(t('plat_sms_filter_all_templates'))}</option>` +
                rows.map((r) => `<option value="${esc(r.key)}">${esc(label(TPL_I18N, r.key) || r.key)}</option>`).join('');
        }
    }

    function renderLogs(rows, total) {
        const body = document.getElementById('platSmsLogs');
        const count = document.getElementById('platSmsCount');
        const template = t('plat_sms_count');
        if (count) count.textContent = template.includes('%d') ? template.replace('%d', String(total ?? rows.length)) : `${total ?? rows.length}`;
        if (!rows?.length) { body.innerHTML = `<tr><td colspan="5" class="plat-comms-muted">${esc(t('plat_no_data'))}</td></tr>`; return; }
        body.innerHTML = rows.map((r) => {
            const pill = r.status === 'failed' ? 'plat-comms-pill--failed' : 'plat-comms-pill--sent';
            return `<tr>
                <td><code>${esc(r.template_key)}</code></td>
                <td>${esc(r.recipient)}</td>
                <td>${esc(r.tenant_name || '—')}</td>
                <td><span class="plat-comms-pill ${pill}">${esc(label(STATUS_I18N, r.status))}</span></td>
                <td>${esc(fmt(r.sent_at))}</td>
            </tr>`;
        }).join('');
    }

    async function refresh() {
        setKpiLoading(true);
        try {
            const search = document.getElementById('platSmsSearch')?.value?.trim();
            const template = document.getElementById('platSmsTemplateFilter')?.value;
            let path = 'sms/dashboard';
            if (search || template) {
                const p = new URLSearchParams();
                if (search) p.set('q', search);
                if (template) p.set('template', template);
                path = `sms/logs?${p.toString()}`;
                const res = await apiGet(path);
                if (res.status !== 'success') throw new Error(res.message);
                const d = res.data || {};
                if (d.stats) {
                    document.getElementById('platSmsKpiTotal').textContent = String(d.stats.total ?? 0);
                    document.getElementById('platSmsKpiToday').textContent = String(d.stats.today ?? 0);
                    document.getElementById('platSmsKpiFailed').textContent = String(d.stats.failed ?? 0);
                    document.getElementById('platSmsKpiTemplates').textContent = String(d.stats.templates ?? 0);
                }
                renderLogs(d.logs || [], (d.logs || []).length);
            } else {
                const res = await apiGet(path);
                if (res.status !== 'success') throw new Error(res.message);
                const d = res.data || {};
                const s = d.stats || {};
                document.getElementById('platSmsKpiTotal').textContent = String(s.total ?? 0);
                document.getElementById('platSmsKpiToday').textContent = String(s.today ?? 0);
                document.getElementById('platSmsKpiFailed').textContent = String(s.failed ?? 0);
                document.getElementById('platSmsKpiTemplates').textContent = String(s.templates ?? 0);
                renderTemplates(d.templates || []);
                renderLogs(d.logs || [], (d.logs || []).length);
            }
            setLastUpdated?.();
        } catch (e) { showError(e.message); }
        finally { setKpiLoading(false); }
    }

    document.getElementById('platSmsSearch')?.addEventListener('input', () => { clearTimeout(debounce); debounce = setTimeout(refresh, 300); });
    document.getElementById('platSmsTemplateFilter')?.addEventListener('change', refresh);
    document.addEventListener('DOMContentLoaded', refresh);
    document.addEventListener('plat:refresh', refresh);
})();
