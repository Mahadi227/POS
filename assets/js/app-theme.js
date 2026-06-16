/**
 * App-wide light/dark theme — shared by admin & cashier modules.
 * Storage key: app-theme (syncs legacy admin-theme / theme keys).
 */
(() => {
    const STORAGE_KEY = 'app-theme';
    const LEGACY_KEYS = ['admin-theme', 'theme', 'cashier-theme'];
    const META_LIGHT = '#2563eb';
    const META_DARK = '#111827';

    function getSavedTheme() {
        const primary = localStorage.getItem(STORAGE_KEY);
        if (primary === 'light' || primary === 'dark') return primary;

        for (const key of LEGACY_KEYS) {
            const value = localStorage.getItem(key);
            if (value === 'light' || value === 'dark') {
                persistTheme(value, false);
                return value;
            }
        }
        return null;
    }

    function persistTheme(theme, dispatch = true) {
        localStorage.setItem(STORAGE_KEY, theme);
        localStorage.setItem('admin-theme', theme);
        localStorage.setItem('theme', theme);

        const html = document.documentElement;
        html.setAttribute('data-theme', theme);

        const meta = document.querySelector('meta[name="theme-color"]');
        if (meta) meta.setAttribute('content', theme === 'dark' ? META_DARK : META_LIGHT);

        updateToggleIcons(theme);

        if (dispatch) {
            window.dispatchEvent(new CustomEvent('app-theme-changed', { detail: { theme } }));
        }
    }

    function updateToggleIcons(theme) {
        const iconName = theme === 'dark' ? 'light_mode' : 'dark_mode';
        document.querySelectorAll('#theme-toggle .material-icons-round').forEach((icon) => {
            icon.textContent = iconName;
        });
    }

    function bindToggleButtons() {
        document.querySelectorAll('#theme-toggle').forEach((btn) => {
            if (btn.dataset.themeBound === '1') return;
            btn.dataset.themeBound = '1';
            btn.addEventListener('click', () => {
                const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
                persistTheme(isDark ? 'light' : 'dark');
            });
        });
    }

    function initTheme() {
        const saved = getSavedTheme();
        if (saved) persistTheme(saved, false);
        bindToggleButtons();
    }

    window.AppTheme = {
        getTheme: () => document.documentElement.getAttribute('data-theme') || 'light',
        getSavedTheme,
        applyTheme: (theme) => persistTheme(theme === 'dark' ? 'dark' : 'light'),
        toggle: () => {
            const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            persistTheme(isDark ? 'light' : 'dark');
        },
        STORAGE_KEY,
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTheme);
    } else {
        initTheme();
    }
})();
