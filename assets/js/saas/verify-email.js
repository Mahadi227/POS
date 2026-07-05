(function () {
    'use strict';

    const cfg = window.VERIFY_EMAIL_CONFIG || {};
    const t = (k) => cfg.i18n?.[k] || k;

    function setMessage(text, type) {
        const el = document.getElementById('verifyMessage');
        if (!el) return;
        el.textContent = text;
        if (type) {
            el.dataset.status = type;
        }
    }

    function setResendLoading(isLoading) {
        const btn = document.getElementById('resendBtn');
        if (!btn) return;
        btn.disabled = isLoading;
        btn.setAttribute('aria-busy', isLoading ? 'true' : 'false');
        const label = isLoading ? t('loading') : t('verify_resend');
        if (btn.querySelector('#resendBtnText')) {
            btn.querySelector('#resendBtnText').textContent = label;
        } else {
            btn.textContent = label;
        }
    }

    function showDevLink(url) {
        if (!url) return;
        let panel = document.getElementById('verifyDevPanel');
        if (!panel) {
            const status = document.querySelector('.verify-status');
            panel = document.createElement('div');
            panel.id = 'verifyDevPanel';
            panel.className = 'verify-dev-panel';
            panel.setAttribute('role', 'region');
            status?.insertAdjacentElement('afterend', panel);
        }
        panel.innerHTML = `
            <div class="verify-dev-panel__head">
                <span class="material-icons-round" aria-hidden="true">developer_mode</span>
                <strong>${escapeHtml(t('verify_dev_title') || 'Local development')}</strong>
            </div>
            <p class="verify-dev-panel__hint">${escapeHtml(t('verify_dev_hint') || '')}</p>
            <a href="${escapeHtml(url)}" class="verify-dev-panel__link" id="verifyDevLink">${escapeHtml(url)}</a>
            <button type="button" class="verify-dev-panel__copy" id="verifyDevCopy" data-url="${escapeHtml(url)}">
                <span class="material-icons-round" aria-hidden="true">content_copy</span>
                ${escapeHtml(t('verify_dev_copy') || 'Copy link')}
            </button>
        `;
        bindDevCopy();
    }

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = s ?? '';
        return d.innerHTML;
    }

    function bindDevCopy() {
        document.getElementById('verifyDevCopy')?.addEventListener('click', async (e) => {
            const url = e.currentTarget?.dataset?.url || '';
            if (!url) return;
            try {
                await navigator.clipboard.writeText(url);
                e.currentTarget.textContent = t('verify_dev_copied');
            } catch (_) {
                window.prompt('Copy link:', url);
            }
        });
    }

    bindDevCopy();

    document.getElementById('verifyThemeToggle')?.addEventListener('click', () => {
        if (window.AppTheme?.toggle) {
            window.AppTheme.toggle();
        }
    });

    document.getElementById('resendBtn')?.addEventListener('click', async () => {
        setResendLoading(true);
        try {
            const res = await fetch(cfg.resendUrl || '../api/v1/index.php?request=onboarding/resend-verification', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({}),
            });
            let data;
            try {
                data = await res.json();
            } catch (_) {
                throw new Error('parse');
            }
            setMessage(
                data.status === 'success' ? t('verify_resent') : t('verify_resend_error'),
                data.status === 'success' ? 'pending' : 'error'
            );
            if (data.status === 'success' && data.dev_verify_url) {
                showDevLink(data.dev_verify_url);
            }
        } catch (_) {
            setMessage(t('verify_resend_error'), 'error');
        } finally {
            setResendLoading(false);
        }
    });
})();
