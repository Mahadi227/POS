/**
 * App-wide theme system v2 — light / dark / system, portal accents, unified events.
 * Storage: app-theme (values: light | dark | system)
 * Legacy keys synced: admin-theme, theme, cashier-theme
 */
(() => {
    const STORAGE_KEY = 'app-theme';
    const LEGACY_KEYS = ['admin-theme', 'theme', 'cashier-theme'];
    const META_DARK = '#111827';

    const PORTAL_ACCENTS = {
        admin: '#2563eb',
        warehouse: '#0d9488',
        accounting: '#059669',
        cashier: '#2563eb',
        manager: '#7c3aed',
        registers: '#2563eb',
        notifications: '#2563eb',
    };

    const TOGGLE_SELECTORS = '#theme-toggle, [data-theme-toggle], #whThemeToggle';

    function readStoredMode() {
        const primary = localStorage.getItem(STORAGE_KEY);
        if (primary === 'light' || primary === 'dark' || primary === 'system') {
            return primary;
        }
        for (const key of LEGACY_KEYS) {
            const value = localStorage.getItem(key);
            if (value === 'light' || value === 'dark') {
                persistMode(value, false);
                return value;
            }
        }
        return 'light';
    }

    function systemPrefersDark() {
        return window.matchMedia?.('(prefers-color-scheme: dark)').matches ?? false;
    }

    function resolveEffective(mode) {
        if (mode === 'dark') return 'dark';
        if (mode === 'system') return systemPrefersDark() ? 'dark' : 'light';
        return 'light';
    }

    function getAccent() {
        const html = document.documentElement;
        return html.dataset.themeAccent
            || document.querySelector('meta[name="theme-accent"]')?.getAttribute('content')
            || PORTAL_ACCENTS[html.dataset.portal || 'admin']
            || PORTAL_ACCENTS.admin;
    }

    function syncLegacyKeys(mode) {
        localStorage.setItem(STORAGE_KEY, mode);
        const effective = resolveEffective(mode);
        localStorage.setItem('admin-theme', effective);
        localStorage.setItem('theme', effective);
        localStorage.setItem('cashier-theme', effective);
    }

    function updateMetaColor(effective) {
        const meta = document.querySelector('meta[name="theme-color"]');
        if (!meta) return;
        meta.setAttribute('content', effective === 'dark' ? META_DARK : getAccent());
    }

    function updateToggleIcons(mode, effective) {
        const showLightModeIcon = effective === 'dark';
        const iconName = showLightModeIcon ? 'light_mode' : 'dark_mode';
        document.querySelectorAll(TOGGLE_SELECTORS).forEach((btn) => {
            const icon = btn.querySelector('.material-icons-round');
            if (icon) icon.textContent = iconName;
            btn.setAttribute('aria-pressed', effective === 'dark' ? 'true' : 'false');
            btn.dataset.themeMode = mode;
        });
    }

    function dispatchThemeChange(mode, effective) {
        const detail = { theme: effective, mode };
        window.dispatchEvent(new CustomEvent('app-theme-changed', { detail }));
        document.dispatchEvent(new CustomEvent('themechange', { detail }));
    }

    function applyMode(mode, dispatch = true) {
        const normalized = mode === 'dark' || mode === 'system' ? mode : 'light';
        syncLegacyKeys(normalized);
        const effective = resolveEffective(normalized);
        const html = document.documentElement;
        html.setAttribute('data-theme', effective);
        html.setAttribute('data-theme-mode', normalized);
        updateMetaColor(effective);
        updateToggleIcons(normalized, effective);
        if (dispatch) dispatchThemeChange(normalized, effective);
        return effective;
    }

    function bindToggleButtons() {
        document.querySelectorAll(TOGGLE_SELECTORS).forEach((btn) => {
            if (btn.dataset.themeBound === '1') return;
            btn.dataset.themeBound = '1';
            btn.addEventListener('click', () => {
                const effective = getEffectiveTheme();
                applyMode(effective === 'dark' ? 'light' : 'dark');
            });
        });
    }

    function bindSystemListener() {
        if (window.__appThemeMediaBound) return;
        window.__appThemeMediaBound = true;
        window.matchMedia?.('(prefers-color-scheme: dark)').addEventListener('change', () => {
            if (readStoredMode() === 'system') {
                applyMode('system', true);
            }
        });
    }

    function initTheme() {
        applyMode(readStoredMode(), false);
        bindToggleButtons();
        bindSystemListener();
    }

    function getMode() {
        return readStoredMode();
    }

    function getEffectiveTheme() {
        return resolveEffective(readStoredMode());
    }

    window.AppTheme = {
        STORAGE_KEY,
        PORTAL_ACCENTS,
        getMode,
        getTheme: getEffectiveTheme,
        getEffectiveTheme,
        getAccent,
        applyTheme: (mode) => applyMode(mode),
        applyMode,
        toggle: () => {
            const effective = getEffectiveTheme();
            return applyMode(effective === 'dark' ? 'light' : 'dark');
        },
        bindToggles: bindToggleButtons,
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTheme);
    } else {
        initTheme();
    }
})();
