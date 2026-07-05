(function () {
    'use strict';
    const cfg = window.PLATFORM_CONFIG || {};
    const { apiGet, apiPost, t, setLastUpdated } = window.PlatformAPI || {};
    const lang = (cfg.locale || 'en').toLowerCase().startsWith('fr') ? 'fr' : 'en';

    const AUDIENCE_I18N = { all: 'plat_notif_audience_all', active: 'plat_notif_audience_active', trial: 'plat_notif_audience_trial', suspended: 'plat_notif_audience_suspended' };
    const STATUS_I18N = { sent: 'plat_notif_status_sent', draft: 'plat_notif_status_draft' };

    function esc(s) { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; }
    function label(map, k) { return map[k] ? t(map[k]) : k; }
    function localized(row, f) { return row[lang === 'fr' ? f + '_fr' : f + '_en'] || row[f + '_en'] || ''; }
    function fmt(v) { try { return new Date(v).toLocaleString(cfg.locale); } catch (e) { return '—'; } }

    function showError(msg) {
        const el = document.getElementById('platNotifError');
        document.getElementById('platNotifErrorText').textContent = msg || t('plat_notif_load_error');
        el.hidden = false;
    }
    function showAlert(msg) {
        const el = document.getElementById('platNotifAlert');
        el.textContent = msg; el.hidden = false;
        setTimeout(() => { el.hidden = true; }, 3500);
    }
    function setKpiLoading(l) {
        document.querySelectorAll('#platNotifKpiGrid .plat-kpi-card').forEach((c) => c.classList.toggle('is-loading', l));
    }

    function renderChannels(rows) {
        const el = document.getElementById('platNotifChannels');
        if (!rows?.length) { el.innerHTML = `<p class="plat-comms-muted">${esc(t('plat_no_data'))}</p>`; return; }
        el.innerHTML = rows.map((r) => `<div class="plat-comms-card"><div class="plat-comms-card__head"><div class="plat-comms-card__icon"><span class="material-icons-round">hub</span></div><div><h4 class="plat-comms-card__title">${esc(lang === 'fr' ? r.name_fr : r.name_en)}</h4><p class="plat-comms-card__meta">${esc(r.slug)}</p></div></div></div>`).join('');
    }

    function renderBroadcasts(rows) {
        const body = document.getElementById('platNotifBroadcasts');
        if (!rows?.length) { body.innerHTML = `<tr><td colspan="6" class="plat-comms-muted">${esc(t('plat_no_data'))}</td></tr>`; return; }
        body.innerHTML = rows.map((r) => {
            const pill = r.status === 'sent' ? 'plat-comms-pill--sent' : 'plat-comms-pill--draft';
            const sendBtn = r.status === 'draft' ? `<button type="button" class="plat-comms-btn plat-comms-btn--primary" data-send="${r.id}">${esc(t('plat_notif_send'))}</button>` : '';
            return `<tr>
                <td><strong>${esc(localized(r, 'title'))}</strong></td>
                <td>${esc(label(AUDIENCE_I18N, r.audience))}</td>
                <td><span class="plat-comms-pill ${pill}">${esc(label(STATUS_I18N, r.status))}</span></td>
                <td>${esc(String(r.recipient_count ?? 0))}</td>
                <td>${esc(r.sent_at ? fmt(r.sent_at) : '—')}</td>
                <td>${sendBtn}</td>
            </tr>`;
        }).join('');
        body.querySelectorAll('[data-send]').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const res = await apiPost(`notifications/${btn.getAttribute('data-send')}/send`, {});
                if (res.status === 'success') { showAlert(t('action_success')); refresh(); }
                else showError(t('action_error'));
            });
        });
    }

    async function refresh() {
        setKpiLoading(true);
        try {
            const res = await apiGet('notifications/dashboard');
            if (res.status !== 'success') throw new Error(res.message);
            const d = res.data || {};
            const s = d.stats || {};
            document.getElementById('platNotifKpiTotal').textContent = String(s.broadcasts ?? 0);
            document.getElementById('platNotifKpiSent').textContent = String(s.sent ?? 0);
            document.getElementById('platNotifKpiDrafts').textContent = String(s.drafts ?? 0);
            document.getElementById('platNotifKpiTemplates').textContent = String(s.templates ?? 0);
            document.getElementById('platNotifKpiChannels').textContent = String(s.channels ?? 0);
            renderChannels(d.channels || []);
            renderBroadcasts(d.broadcasts || []);
            setLastUpdated?.();
        } catch (e) { showError(e.message); }
        finally { setKpiLoading(false); }
    }

    document.getElementById('platNotifAddOpen')?.addEventListener('click', () => { document.getElementById('platNotifModal').hidden = false; });
    document.querySelectorAll('[data-close-modal]').forEach((el) => el.addEventListener('click', () => { document.getElementById('platNotifModal').hidden = true; }));
    document.getElementById('platNotifForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const f = e.target;
        const body = { title_en: f.title_en.value.trim(), title_fr: f.title_fr.value.trim(), message_en: f.message_en.value.trim(), message_fr: f.message_fr.value.trim(), audience: f.audience.value };
        const res = await apiPost('notifications/broadcasts', body);
        if (res.status === 'success') { document.getElementById('platNotifModal').hidden = true; f.reset(); showAlert(t('action_success')); refresh(); }
        else showError(t('action_error'));
    });

    document.addEventListener('DOMContentLoaded', refresh);
    document.addEventListener('plat:refresh', refresh);
})();
