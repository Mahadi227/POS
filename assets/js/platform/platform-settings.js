(function () {
    'use strict';

    const { apiGet, apiPut, t, setLastUpdated } = window.PlatformAPI || {};

    const CAT_I18N = {
        general: 'plat_settings_cat_general',
        security: 'plat_settings_cat_security',
        communications: 'plat_settings_cat_communications',
        billing: 'plat_settings_cat_billing',
    };

    const KEY_I18N = {
        product_name: 'plat_settings_key_product_name',
        support_email: 'plat_settings_key_support_email',
        default_locale: 'plat_settings_key_default_locale',
        lockout_threshold: 'plat_settings_key_lockout_threshold',
        lockout_window_minutes: 'plat_settings_key_lockout_window_minutes',
        email_from: 'plat_settings_key_email_from',
        trial_days: 'plat_settings_key_trial_days',
    };

    const CAT_ICONS = {
        general: 'public',
        security: 'security',
        communications: 'mail',
        billing: 'receipt_long',
    };

    let settingsData = {};

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s ?? '';
        return d.innerHTML;
    }

    function showError(msg) {
        const el = document.getElementById('platSettingsError');
        document.getElementById('platSettingsErrorText').textContent = msg || t('plat_settings_load_error');
        el.hidden = false;
    }

    function showAlert(msg) {
        const el = document.getElementById('platSettingsAlert');
        el.textContent = msg;
        el.hidden = false;
        clearTimeout(showAlert._t);
        showAlert._t = setTimeout(() => { el.hidden = true; }, 3500);
    }

    function setKpiLoading(loading) {
        document.querySelectorAll('#platSettingsKpiGrid .plat-kpi-card').forEach((c) => {
            c.classList.toggle('is-loading', loading);
        });
    }

    function fieldLabel(key, fallback) {
        return KEY_I18N[key] ? t(KEY_I18N[key]) : fallback || key;
    }

    function renderField(item) {
        const key = item.key;
        const type = item.type || 'string';
        const val = esc(String(item.value ?? ''));
        const label = esc(fieldLabel(key, item.description));
        const desc = esc(item.description || '');

        if (type === 'select' && key === 'default_locale') {
            return `<label class="plat-settings-field" data-key="${esc(key)}">
                <span>${label}</span>
                <small>${desc}</small>
                <select name="${esc(key)}">
                    <option value="en" ${val === 'en' ? 'selected' : ''}>English</option>
                    <option value="fr" ${val === 'fr' ? 'selected' : ''}>Français</option>
                </select>
            </label>`;
        }

        const inputType = type === 'number' ? 'number' : (type === 'email' ? 'email' : 'text');
        const min = type === 'number' ? ' min="0"' : '';
        return `<label class="plat-settings-field" data-key="${esc(key)}">
            <span>${label}</span>
            <small>${desc}</small>
            <input type="${inputType}" name="${esc(key)}" value="${val}"${min}>
        </label>`;
    }

    function renderSections(grouped) {
        const container = document.getElementById('platSettingsSections');
        const cats = Object.keys(grouped || {});
        if (!cats.length) {
            container.innerHTML = `<p class="plat-gov-muted">${esc(t('plat_no_data'))}</p>`;
            return;
        }

        container.innerHTML = cats.map((cat) => {
            const items = grouped[cat] || [];
            const icon = CAT_ICONS[cat] || 'settings';
            return `<section class="plat-panel plat-settings-section" data-category="${esc(cat)}">
                <header class="plat-settings-section__head">
                    <h3><span class="material-icons-round" aria-hidden="true">${esc(icon)}</span>${esc(CAT_I18N[cat] ? t(CAT_I18N[cat]) : cat)}</h3>
                    <button type="button" class="plat-settings-save-btn" data-save-cat="${esc(cat)}">
                        <span class="material-icons-round" aria-hidden="true">save</span>
                        ${esc(t('plat_settings_save'))}
                    </button>
                </header>
                <form class="plat-settings-form" data-cat-form="${esc(cat)}">
                    ${items.map(renderField).join('')}
                </form>
            </section>`;
        }).join('');
    }

    function renderFlags(flags) {
        const panel = document.getElementById('platSettingsFlagsPanel');
        const el = document.getElementById('platSettingsFlags');
        if (!flags?.length) {
            panel.hidden = true;
            return;
        }
        panel.hidden = false;
        el.innerHTML = flags.map((f) => {
            const key = esc(f.key_name);
            const checked = Number(f.default_enabled) === 1 ? 'checked' : '';
            return `<div class="plat-settings-flag-row" data-flag="${key}">
                <div>
                    <strong><code>${key}</code></strong>
                    <p>${esc(f.description || '')}</p>
                </div>
                <label class="plat-settings-toggle">
                    <input type="checkbox" name="${key}" ${checked}>
                    <span></span>
                </label>
            </div>`;
        }).join('');
    }

    function collectCategory(cat) {
        const form = document.querySelector(`[data-cat-form="${cat}"]`);
        if (!form) return {};
        const out = {};
        form.querySelectorAll('[name]').forEach((input) => {
            if (input.type === 'number') {
                out[input.name] = parseInt(input.value, 10) || 0;
            } else {
                out[input.name] = input.value;
            }
        });
        return out;
    }

    function saveCategory(cat) {
        const values = collectCategory(cat);
        apiPut('settings/values', { settings: values }).then((res) => {
            if (res.status !== 'success') throw new Error();
            showAlert(t('action_success'));
            load();
        }).catch(() => showAlert(t('action_error')));
    }

    function saveFlags() {
        const flags = [];
        document.querySelectorAll('#platSettingsFlags [data-flag]').forEach((row) => {
            const key = row.dataset.flag;
            const input = row.querySelector('input[type="checkbox"]');
            flags.push({ key_name: key, default_enabled: input?.checked ? 1 : 0 });
        });
        apiPut('settings/feature-flags', { flags }).then((res) => {
            if (res.status !== 'success') throw new Error();
            showAlert(t('action_success'));
            load();
        }).catch(() => showAlert(t('action_error')));
    }

    function renderKpis(stats) {
        document.getElementById('platSetKpiSettings').textContent = String(stats?.settings ?? 0);
        document.getElementById('platSetKpiCategories').textContent = String(stats?.categories ?? 0);
        document.getElementById('platSetKpiFlags').textContent = String(stats?.feature_flags ?? 0);
    }

    function load() {
        setKpiLoading(true);
        apiGet('settings/dashboard').then((res) => {
            if (res.status !== 'success') throw new Error();
            settingsData = res.data || {};
            renderKpis(settingsData.stats);
            renderSections(settingsData.settings);
            renderFlags(settingsData.feature_flags);
            setLastUpdated?.();
        }).catch(() => showError()).finally(() => setKpiLoading(false));
    }

    document.getElementById('platSettingsSections')?.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-save-cat]');
        if (btn) saveCategory(btn.dataset.saveCat);
    });
    document.getElementById('platSettingsSaveFlags')?.addEventListener('click', saveFlags);

    load();
})();
