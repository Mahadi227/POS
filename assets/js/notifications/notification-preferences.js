/**
 * Notification Preferences UI (dynamic save/load)
 */
(() => {
    const form = document.getElementById('notifPrefsForm');
    if (!form) return;

    const alertBox = document.getElementById('prefsAlert');
    const saveBtn = document.getElementById('prefsSaveBtn');
    const resetBtn = document.getElementById('prefsResetBtn');
    const stateEl = document.getElementById('prefsState');
    const themeBtn = document.getElementById('notifThemeBtn');
    const whatsappEnabled = document.getElementById('whatsappEnabled');
    const whatsappPhoneWrap = document.getElementById('whatsappPhoneWrap');
    const whatsappPhone = document.getElementById('whatsappPhone');
    const i18n = window.NOTIF_PREFS_I18N || {};
    const initialFromServer = window.NOTIF_PREFS_INITIAL || {};

    const t = (key) => i18n[key] || key;

    function normalizePhone(value) {
        const clean = String(value || '').replace(/[^\d+]/g, '');
        if (!clean || !/^\+?[0-9]{8,15}$/.test(clean)) {
            return '';
        }
        return clean;
    }

    function isValidWhatsAppPhone(value) {
        return normalizePhone(value) !== '';
    }

    function toggleWhatsAppPhone() {
        if (!whatsappPhoneWrap || !whatsappEnabled) return;
        const on = whatsappEnabled.checked;
        whatsappPhoneWrap.classList.toggle('hidden', !on);
        if (whatsappPhone) {
            whatsappPhone.required = on;
        }
    }

    let baseState = {};
    let hydrated = false;

    function normalize(values) {
        return {
            email_enabled: values.email_enabled ? 1 : 0,
            sms_enabled: values.sms_enabled ? 1 : 0,
            push_enabled: values.push_enabled ? 1 : 0,
            whatsapp_enabled: values.whatsapp_enabled ? 1 : 0,
            whatsapp_phone: normalizePhone(values.whatsapp_phone),
            browser_enabled: values.browser_enabled ? 1 : 0,
            sound_enabled: values.sound_enabled ? 1 : 0,
            quiet_hours_start: values.quiet_hours_start || '',
            quiet_hours_end: values.quiet_hours_end || '',
            min_priority: values.min_priority || 'low',
        };
    }

    function readForm() {
        const fd = new FormData(form);
        return normalize({
            email_enabled: fd.get('email_enabled') === '1',
            sms_enabled: fd.get('sms_enabled') === '1',
            push_enabled: fd.get('push_enabled') === '1',
            whatsapp_enabled: fd.get('whatsapp_enabled') === '1',
            whatsapp_phone: String(fd.get('whatsapp_phone') || ''),
            browser_enabled: fd.get('browser_enabled') === '1',
            sound_enabled: fd.get('sound_enabled') === '1',
            quiet_hours_start: String(fd.get('quiet_hours_start') || ''),
            quiet_hours_end: String(fd.get('quiet_hours_end') || ''),
            min_priority: String(fd.get('min_priority') || 'low'),
        });
    }

    function applyToForm(values) {
        const v = normalize(values);
        form.email_enabled.checked = !!v.email_enabled;
        form.sms_enabled.checked = !!v.sms_enabled;
        form.push_enabled.checked = !!v.push_enabled;
        form.whatsapp_enabled.checked = !!v.whatsapp_enabled;
        if (form.whatsapp_phone) {
            form.whatsapp_phone.value = v.whatsapp_phone || '';
        }
        form.browser_enabled.checked = !!v.browser_enabled;
        form.sound_enabled.checked = !!v.sound_enabled;
        form.quiet_hours_start.value = v.quiet_hours_start;
        form.quiet_hours_end.value = v.quiet_hours_end;
        form.min_priority.value = v.min_priority;
    }

    function isDirty() {
        const cur = readForm();
        return JSON.stringify(cur) !== JSON.stringify(baseState);
    }

    function renderState() {
        if (!hydrated) {
            stateEl.textContent = t('loading');
            return;
        }
        stateEl.textContent = isDirty() ? t('prefs_unsaved') : t('prefs_up_to_date');
        saveBtn.disabled = !isDirty();
    }

    function showAlert(message, type = 'success') {
        if (!alertBox) return;
        alertBox.classList.remove('hidden', 'notif-alert--success', 'notif-alert--error');
        alertBox.classList.add(type === 'error' ? 'notif-alert--error' : 'notif-alert--success');
        alertBox.textContent = message;
        clearTimeout(showAlert._timer);
        showAlert._timer = setTimeout(() => {
            alertBox.classList.add('hidden');
        }, 2800);
    }

    async function refreshFromApi() {
        try {
            const res = await NotificationAPI.preferences();
            if (res.status === 'success' && res.data) {
                baseState = normalize(res.data);
                applyToForm(baseState);
            } else {
                baseState = normalize(initialFromServer);
                applyToForm(baseState);
            }
        } catch (e) {
            baseState = normalize(initialFromServer);
            applyToForm(baseState);
        } finally {
            hydrated = true;
            toggleWhatsAppPhone();
            renderState();
        }
    }

    function validateBeforeSave(payload) {
        if (payload.whatsapp_enabled && !payload.whatsapp_phone) {
            showAlert(t('whatsapp_phone_required'), 'error');
            if (whatsappPhone) {
                whatsappPhone.focus();
            }
            return false;
        }
        return true;
    }

    async function save() {
        const payload = readForm();
        if (!validateBeforeSave(payload)) {
            renderState();
            return;
        }
        saveBtn.disabled = true;
        saveBtn.textContent = `${t('prefs_saving')}...`;
        try {
            const res = await NotificationAPI.preferences(payload);
            if (res.status === 'success') {
                baseState = { ...payload };
                showAlert(t('prefs_saved'), 'success');
            } else {
                throw new Error(res.message || 'Save failed');
            }
        } catch (e) {
            showAlert(e.message || 'Save failed', 'error');
        } finally {
            saveBtn.textContent = t('save');
            renderState();
        }
    }

    form.addEventListener('input', renderState);
    form.addEventListener('change', (e) => {
        if (e.target === whatsappEnabled) {
            toggleWhatsAppPhone();
        }
        renderState();
    });
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        if (isDirty()) save();
    });

    resetBtn?.addEventListener('click', () => {
        applyToForm(baseState);
        renderState();
    });

    themeBtn?.addEventListener('click', () => {
        const html = document.documentElement;
        const isDark = html.getAttribute('data-theme') === 'dark';
        html.setAttribute('data-theme', isDark ? 'light' : 'dark');
        localStorage.setItem('admin-theme', isDark ? 'light' : 'dark');
        const icon = themeBtn.querySelector('.material-icons-round');
        if (icon) icon.textContent = isDark ? 'dark_mode' : 'light_mode';
    });

    const savedTheme = localStorage.getItem('admin-theme');
    if (savedTheme) {
        document.documentElement.setAttribute('data-theme', savedTheme);
        const icon = themeBtn?.querySelector('.material-icons-round');
        if (icon) icon.textContent = savedTheme === 'dark' ? 'light_mode' : 'dark_mode';
    }

    refreshFromApi();
})();

