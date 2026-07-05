(function () {
    'use strict';

    const cfg = window.DEV_PORTAL_CONFIG || {};
    const t = (k) => cfg.i18n?.[k] || k;

    document.getElementById('devThemeToggle')?.addEventListener('click', () => {
        if (window.AppTheme?.toggle) {
            window.AppTheme.toggle();
        }
    });

    document.getElementById('devCopyCurl')?.addEventListener('click', async () => {
        const code = document.getElementById('devCurlSnippet')?.textContent || '';
        if (!code) return;
        const btn = document.getElementById('devCopyCurl');
        try {
            await navigator.clipboard.writeText(code);
            if (btn) {
                const prev = btn.innerHTML;
                btn.innerHTML = '<span class="material-icons-round" style="font-size:14px">check</span> ' + t('dev_copied');
                setTimeout(() => { btn.innerHTML = prev; }, 2000);
            }
        } catch (_) {
            /* ignore */
        }
    });
})();
