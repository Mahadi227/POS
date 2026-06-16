document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('wmsSettingsRoot');
    if (!root) return;
    const { t, esc, showError, hideError, updateLastUpdated } = WmsUI;
    const STORAGE_KEY = 'wms_settings';

    function loadPrefs() {
        try {
            return JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
        } catch {
            return {};
        }
    }

    function render() {
        const prefs = loadPrefs();
        const offline = prefs.offline !== false;
        root.innerHTML = `<form class="cr-form wms-settings-form" id="wmsSettingsForm">
            <div class="wms-settings-row">
                <label class="cr-check">
                    <input type="checkbox" name="offline" ${offline ? 'checked' : ''}>
                    <span><strong>${esc(t('wms_offline_sync'))}</strong></span>
                </label>
                <p class="cr-muted wms-settings-hint">${esc(t('wms_settings_offline_hint'))}</p>
            </div>
            <div class="cr-form-actions">
                <button type="submit" class="cr-btn"><span class="material-icons-round">save</span>${esc(t('save'))}</button>
            </div>
        </form>`;

        root.querySelector('#wmsSettingsForm')?.addEventListener('submit', (e) => {
            e.preventDefault();
            hideError();
            const offlineChecked = e.target.offline.checked;
            localStorage.setItem(STORAGE_KEY, JSON.stringify({ offline: offlineChecked }));
            if (window.WmsOffline?.setEnabled) {
                window.WmsOffline.setEnabled(offlineChecked);
            }
            updateLastUpdated();
            const toast = document.createElement('div');
            toast.className = 'wms-toast';
            toast.textContent = t('wms_saved');
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 2500);
        });
    }

    render();
    document.addEventListener('wms:refresh', render);
});
