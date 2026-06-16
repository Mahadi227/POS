/**
 * Cash register module settings (local preferences + offline sync)
 */
document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('crSettingsRoot');
    if (!root) return;

    const { t, esc } = CashRegistersUI;
    const prefs = JSON.parse(localStorage.getItem('cr_settings') || '{}');

    root.innerHTML = `
        <form class="cr-form" id="crSettingsForm">
            <label>${esc(t('cr_variance_tolerance'))}
                <input type="number" name="variance_tolerance" min="0" step="100" value="${esc(prefs.variance_tolerance ?? 500)}">
            </label>
            <label class="cr-check">
                <input type="checkbox" name="offline_sync" ${prefs.offline_sync !== false ? 'checked' : ''}>
                ${esc(t('cr_offline_sync'))}
            </label>
            <label class="cr-check">
                <input type="checkbox" name="auto_reconcile" ${prefs.auto_reconcile ? 'checked' : ''}>
                ${esc(t('cr_auto_reconcile'))}
            </label>
            <div class="cr-form-actions">
                <button type="submit" class="cr-btn">${esc(t('save'))}</button>
            </div>
        </form>
        <p class="cr-hint">${esc(t('cr_settings_hint'))}</p>`;

    root.querySelector('#crSettingsForm')?.addEventListener('submit', (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        localStorage.setItem('cr_settings', JSON.stringify({
            variance_tolerance: parseFloat(fd.get('variance_tolerance')) || 500,
            offline_sync: fd.get('offline_sync') === 'on',
            auto_reconcile: fd.get('auto_reconcile') === 'on',
        }));
        alert(t('cr_saved'));
    });
});
