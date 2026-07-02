/**
 * Theme preference panel — Light / Dark / System (uses AppTheme)
 */
document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('appThemeSettings');
    if (!root || !window.AppTheme) return;

    const i18n = () => window.WH_I18N || window.ADMIN_I18N || {};
    const t = (key) => i18n()[key] || key;

    function label(mode) {
        const map = { light: t('theme_light'), dark: t('theme_dark'), system: t('theme_system') };
        return map[mode] || mode;
    }

    function icon(mode) {
        return { light: 'light_mode', dark: 'dark_mode', system: 'brightness_auto' }[mode] || 'palette';
    }

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s ?? '';
        return d.innerHTML;
    }

    function render() {
        const current = AppTheme.getMode();
        const effective = AppTheme.getEffectiveTheme();
        root.innerHTML = `
            <div class="app-theme-options" role="radiogroup" aria-label="${esc(t('theme'))}">
                ${['light', 'dark', 'system'].map((mode) => `
                    <button type="button" class="app-theme-option${current === mode ? ' is-active' : ''}"
                        data-theme-mode="${mode}" role="radio" aria-checked="${current === mode}">
                        <span class="material-icons-round">${icon(mode)}</span>
                        <span>${esc(label(mode))}</span>
                    </button>`).join('')}
            </div>
            <p class="app-theme-hint wh-muted">${esc(t('theme_effective').replace('%s', label(effective)))}</p>`;
    }

    root.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-theme-mode]');
        if (!btn) return;
        AppTheme.applyMode(btn.dataset.themeMode);
        render();
    });

    document.addEventListener('themechange', render);
    window.addEventListener('app-theme-changed', render);
    render();
});
