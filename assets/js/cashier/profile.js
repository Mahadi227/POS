/**
 * Cashier profile
 */
document.addEventListener('DOMContentLoaded', () => {
    const cfg = window.PROFILE_CONFIG || {};
    const i18n = window.PROFILE_I18N || {};
    const locale = cfg.locale || (cfg.lang === 'fr' ? 'fr-FR' : 'en-US');

    if (window.POS_CONFIG && !window.POS_CONFIG.locale) {
        window.POS_CONFIG.locale = locale;
        window.POS_CONFIG.lang = cfg.lang || 'en';
    }

    const els = {
        root: document.getElementById('profileRoot'),
        form: document.getElementById('profileForm'),
        nameInput: document.getElementById('profileName'),
        emailDisplay: document.getElementById('profileEmail'),
        currentPwd: document.getElementById('currentPassword'),
        newPwd: document.getElementById('newPassword'),
        confirmPwd: document.getElementById('confirmPassword'),
        saveBtn: document.getElementById('saveProfileBtn'),
        toast: document.getElementById('profileToast'),
        errorBanner: document.getElementById('profileError'),
        headerDate: document.getElementById('cpHeaderDate'),
        lastUpdated: document.getElementById('lastUpdated'),
    };

    let profileData = null;
    let lastLoadAt = null;

    function updateHeaderDate() {
        const now = new Date();
        const dateStr = now.toLocaleDateString(locale, {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });
        if (els.headerDate) els.headerDate.textContent = dateStr;
        if (els.lastUpdated && lastLoadAt) {
            const time = lastLoadAt.toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' });
            els.lastUpdated.textContent = `${t('last_updated')} · ${time}`;
        }
    }

    function showError(msg) {
        if (!els.errorBanner) return;
        els.errorBanner.classList.add('is-visible');
        const text = els.errorBanner.querySelector('.cp-error-text');
        if (text) text.textContent = msg;
    }

    function hideError() {
        els.errorBanner?.classList.remove('is-visible');
    }

    function t(key, ...args) {
        let str = i18n[key] || key;
        args.forEach((val) => {
            str = str.replace('%s', val);
        });
        return str;
    }

    function toast(message, type = 'ok') {
        if (!els.toast) return;
        els.toast.textContent = message;
        els.toast.className = `cp-toast cp-toast--${type} show`;
        setTimeout(() => els.toast.classList.remove('show'), 3200);
    }

    function escapeHtml(str) {
        const d = document.createElement('div');
        d.textContent = str ?? '';
        return d.innerHTML;
    }

    function formatMemberDate(dateStr) {
        if (!dateStr) return '—';
        return new Date(dateStr).toLocaleDateString(locale, {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });
    }

    function renderProfile(p) {
        profileData = p;
        const initial = (p.name || 'C').charAt(0).toUpperCase();
        const active = p.is_active !== false;

        els.root.innerHTML = `
            <section class="cp-hero">
                <div class="cp-avatar" id="profileAvatar">${escapeHtml(initial)}</div>
                <div class="cp-hero__info">
                    <h2 id="profileDisplayName">${escapeHtml(p.name)}</h2>
                    <p class="cp-hero__email" id="profileDisplayEmail">${escapeHtml(p.email)}</p>
                    <div class="cp-hero__badges">
                        <span class="cp-badge"><span class="material-icons-round">badge</span>${escapeHtml(p.role)}</span>
                        <span class="cp-badge ${active ? 'cp-badge--active' : 'cp-badge--inactive'}">
                            <span class="material-icons-round">${active ? 'check_circle' : 'block'}</span>
                            ${active ? t('account_active') : t('account_inactive')}
                        </span>
                        ${p.store_name ? `<span class="cp-badge"><span class="material-icons-round">storefront</span>${escapeHtml(p.store_name)}</span>` : ''}
                    </div>
                </div>
            </section>

            <div class="cp-stats">
                <div class="cp-stat">
                    <span class="cp-stat__icon cp-stat__icon--blue material-icons-round">receipt_long</span>
                    <div>
                        <div class="cp-stat__label">${t('sales_today')}</div>
                        <div class="cp-stat__value">${p.today_sales ?? 0}</div>
                    </div>
                </div>
                <div class="cp-stat">
                    <span class="cp-stat__icon cp-stat__icon--green material-icons-round">payments</span>
                    <div>
                        <div class="cp-stat__label">${t('revenue_today')}</div>
                        <div class="cp-stat__value">${escapeHtml(CashierAPI.formatCurrency(p.today_revenue))}</div>
                    </div>
                </div>
                <div class="cp-stat">
                    <span class="cp-stat__icon cp-stat__icon--slate material-icons-round">schedule</span>
                    <div>
                        <div class="cp-stat__label">${t('last_login')}</div>
                        <div class="cp-stat__value" style="font-size:0.85rem;">${escapeHtml(p.last_login ? CashierAPI.formatDate(p.last_login) : '—')}</div>
                    </div>
                </div>
            </div>

            <form id="profileForm" class="cp-grid" novalidate>
                <section class="cp-panel">
                    <div class="cp-panel__head">
                        <span class="material-icons-round">person</span>
                        ${t('personal_info')}
                    </div>
                    <div class="cp-panel__body">
                        <div class="cp-field">
                            <label for="profileName">${t('full_name')}</label>
                            <input type="text" id="profileName" name="name" required minlength="2" maxlength="120" value="${escapeHtml(p.name)}">
                        </div>
                        <div class="cp-field">
                            <label for="profileEmail">${t('email_address')}</label>
                            <input type="email" id="profileEmail" value="${escapeHtml(p.email)}" readonly disabled>
                            <p class="cp-field__hint">${t('email_hint')}</p>
                        </div>
                        <div class="cp-field">
                            <label>${t('member_since')}</label>
                            <input type="text" value="${escapeHtml(formatMemberDate(p.member_since))}" readonly disabled>
                        </div>
                        ${p.store_location ? `<div class="cp-field"><label>${t('store_label')}</label><input type="text" value="${escapeHtml(p.store_name + ' — ' + p.store_location)}" readonly disabled></div>` : ''}
                    </div>
                </section>

                <section class="cp-panel">
                    <div class="cp-panel__head">
                        <span class="material-icons-round">lock</span>
                        ${t('security')}
                    </div>
                    <div class="cp-panel__body">
                        <p class="cp-field__hint" style="margin-bottom:14px;">${t('password_section_hint')}</p>
                        <div class="cp-field cp-password-toggle">
                            <label for="currentPassword">${t('current_password')}</label>
                            <input type="password" id="currentPassword" name="current_password" autocomplete="current-password" placeholder="${t('current_password_ph')}">
                            <button type="button" class="cp-toggle-pwd" data-target="currentPassword" aria-label="${t('show_password')}">
                                <span class="material-icons-round">visibility</span>
                            </button>
                        </div>
                        <hr class="cp-divider">
                        <div class="cp-field cp-password-toggle">
                            <label for="newPassword">${t('new_password')}</label>
                            <input type="password" id="newPassword" name="new_password" autocomplete="new-password" minlength="6" placeholder="${t('new_password_ph')}">
                            <button type="button" class="cp-toggle-pwd" data-target="newPassword" aria-label="${t('show_password')}">
                                <span class="material-icons-round">visibility</span>
                            </button>
                        </div>
                        <div class="cp-field cp-password-toggle">
                            <label for="confirmPassword">${t('confirm_password')}</label>
                            <input type="password" id="confirmPassword" name="confirm_password" autocomplete="new-password" placeholder="${t('confirm_password_ph')}">
                            <button type="button" class="cp-toggle-pwd" data-target="confirmPassword" aria-label="${t('show_password')}">
                                <span class="material-icons-round">visibility</span>
                            </button>
                        </div>
                    </div>
                </section>
            </form>

            <div class="cp-actions">
                <button type="submit" form="profileForm" class="cp-btn cp-btn--primary" id="saveProfileBtn">
                    <span class="material-icons-round">save</span>
                    ${t('save_changes')}
                </button>
                <a href="dashboard.php" class="cp-btn cp-btn--ghost">
                    <span class="material-icons-round">dashboard</span>
                    ${t('dashboard_link')}
                </a>
                <a href="../logout.php" class="cp-btn cp-btn--ghost" style="color:var(--danger);border-color:var(--danger-light);">
                    <span class="material-icons-round">logout</span>
                    ${t('logout')}
                </a>
            </div>`;

        els.form = document.getElementById('profileForm');
        els.nameInput = document.getElementById('profileName');
        els.saveBtn = document.getElementById('saveProfileBtn');

        els.form?.addEventListener('submit', handleSubmit);

        document.querySelectorAll('.cp-toggle-pwd').forEach((btn) => {
            btn.addEventListener('click', () => {
                const input = document.getElementById(btn.dataset.target);
                if (!input) return;
                const show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                btn.querySelector('.material-icons-round').textContent = show ? 'visibility_off' : 'visibility';
            });
        });
    }

    async function loadProfile() {
        hideError();
        try {
            const result = await CashierAPI.getProfile();
            if (result.status === 'success' && result.data) {
                lastLoadAt = new Date();
                updateHeaderDate();
                renderProfile(result.data);
            } else {
                const msg = result.message || t('error');
                showError(msg);
                els.root.innerHTML = `<div class="cp-loading"><span class="material-icons-round">error_outline</span><p>${escapeHtml(msg)}</p></div>`;
            }
        } catch (err) {
            console.error(err);
            showError(t('load_error'));
            els.root.innerHTML = `<div class="cp-loading"><span class="material-icons-round">error_outline</span><p>${escapeHtml(t('load_error'))}</p></div>`;
        }
    }

    async function handleSubmit(e) {
        e.preventDefault();

        const name = els.nameInput?.value.trim() || '';
        const currentPassword = document.getElementById('currentPassword')?.value || '';
        const newPassword = document.getElementById('newPassword')?.value || '';
        const confirmPassword = document.getElementById('confirmPassword')?.value || '';

        if (name.length < 2) {
            toast(t('name_min_length'), 'err');
            return;
        }

        if (newPassword || confirmPassword || currentPassword) {
            if (newPassword.length < 6) {
                toast(t('password_min_length'), 'err');
                return;
            }
            if (newPassword !== confirmPassword) {
                toast(t('password_mismatch'), 'err');
                return;
            }
            if (!currentPassword) {
                toast(t('current_password_required'), 'err');
                return;
            }
        }

        els.saveBtn.disabled = true;

        try {
            const result = await CashierAPI.updateProfile({
                name,
                current_password: currentPassword,
                new_password: newPassword,
                confirm_password: confirmPassword,
            });

            if (result.status === 'success') {
                toast(result.message || t('updated_success'), 'ok');
                document.getElementById('profileDisplayName').textContent = name;
                document.getElementById('profileAvatar').textContent = name.charAt(0).toUpperCase();
                const headerName = document.querySelector('.cp-header-user .name');
                if (headerName) headerName.textContent = name;
                document.getElementById('currentPassword').value = '';
                document.getElementById('newPassword').value = '';
                document.getElementById('confirmPassword').value = '';
            } else {
                toast(result.message || t('error'), 'err');
            }
        } catch (err) {
            console.error(err);
            toast(t('connection_error'), 'err');
        }

        els.saveBtn.disabled = false;
    }

    updateHeaderDate();
    loadProfile();
});
