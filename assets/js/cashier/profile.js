/**
 * Profil caissier
 */
document.addEventListener('DOMContentLoaded', () => {
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
    };

    let profileData = null;

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
        return new Date(dateStr).toLocaleDateString('fr-FR', {
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
                            ${active ? 'Compte actif' : 'Inactif'}
                        </span>
                        ${p.store_name ? `<span class="cp-badge"><span class="material-icons-round">storefront</span>${escapeHtml(p.store_name)}</span>` : ''}
                    </div>
                </div>
            </section>

            <div class="cp-stats">
                <div class="cp-stat">
                    <span class="cp-stat__icon cp-stat__icon--blue material-icons-round">receipt_long</span>
                    <div>
                        <div class="cp-stat__label">Ventes aujourd'hui</div>
                        <div class="cp-stat__value">${p.today_sales ?? 0}</div>
                    </div>
                </div>
                <div class="cp-stat">
                    <span class="cp-stat__icon cp-stat__icon--green material-icons-round">payments</span>
                    <div>
                        <div class="cp-stat__label">CA aujourd'hui</div>
                        <div class="cp-stat__value">${escapeHtml(CashierAPI.formatCurrency(p.today_revenue))}</div>
                    </div>
                </div>
                <div class="cp-stat">
                    <span class="cp-stat__icon cp-stat__icon--slate material-icons-round">schedule</span>
                    <div>
                        <div class="cp-stat__label">Dernière connexion</div>
                        <div class="cp-stat__value" style="font-size:0.85rem;">${escapeHtml(p.last_login ? CashierAPI.formatDate(p.last_login) : '—')}</div>
                    </div>
                </div>
            </div>

            <form id="profileForm" class="cp-grid" novalidate>
                <section class="cp-panel">
                    <div class="cp-panel__head">
                        <span class="material-icons-round">person</span>
                        Informations personnelles
                    </div>
                    <div class="cp-panel__body">
                        <div class="cp-field">
                            <label for="profileName">Nom complet</label>
                            <input type="text" id="profileName" name="name" required minlength="2" maxlength="120" value="${escapeHtml(p.name)}">
                        </div>
                        <div class="cp-field">
                            <label for="profileEmail">Adresse e-mail</label>
                            <input type="email" id="profileEmail" value="${escapeHtml(p.email)}" readonly disabled>
                            <p class="cp-field__hint">L'e-mail ne peut pas être modifié ici. Contactez l'administrateur.</p>
                        </div>
                        <div class="cp-field">
                            <label>Membre depuis</label>
                            <input type="text" value="${escapeHtml(formatMemberDate(p.member_since))}" readonly disabled>
                        </div>
                        ${p.store_location ? `<div class="cp-field"><label>Magasin</label><input type="text" value="${escapeHtml(p.store_name + ' — ' + p.store_location)}" readonly disabled></div>` : ''}
                    </div>
                </section>

                <section class="cp-panel">
                    <div class="cp-panel__head">
                        <span class="material-icons-round">lock</span>
                        Sécurité
                    </div>
                    <div class="cp-panel__body">
                        <p class="cp-field__hint" style="margin-bottom:14px;">Laissez les champs mot de passe vides pour ne changer que le nom.</p>
                        <div class="cp-field cp-password-toggle">
                            <label for="currentPassword">Mot de passe actuel</label>
                            <input type="password" id="currentPassword" name="current_password" autocomplete="current-password" placeholder="Requis si changement">
                            <button type="button" class="cp-toggle-pwd" data-target="currentPassword" aria-label="Afficher">
                                <span class="material-icons-round">visibility</span>
                            </button>
                        </div>
                        <hr class="cp-divider">
                        <div class="cp-field cp-password-toggle">
                            <label for="newPassword">Nouveau mot de passe</label>
                            <input type="password" id="newPassword" name="new_password" autocomplete="new-password" minlength="6" placeholder="Min. 6 caractères">
                            <button type="button" class="cp-toggle-pwd" data-target="newPassword" aria-label="Afficher">
                                <span class="material-icons-round">visibility</span>
                            </button>
                        </div>
                        <div class="cp-field cp-password-toggle">
                            <label for="confirmPassword">Confirmer le mot de passe</label>
                            <input type="password" id="confirmPassword" name="confirm_password" autocomplete="new-password" placeholder="Répéter le nouveau">
                            <button type="button" class="cp-toggle-pwd" data-target="confirmPassword" aria-label="Afficher">
                                <span class="material-icons-round">visibility</span>
                            </button>
                        </div>
                    </div>
                </section>
            </form>

            <div class="cp-actions">
                <button type="submit" form="profileForm" class="cp-btn cp-btn--primary" id="saveProfileBtn">
                    <span class="material-icons-round">save</span>
                    Enregistrer les modifications
                </button>
                <a href="dashboard.php" class="cp-btn cp-btn--ghost">
                    <span class="material-icons-round">dashboard</span>
                    Tableau de bord
                </a>
                <a href="../logout.php" class="cp-btn cp-btn--ghost" style="color:var(--danger);border-color:var(--danger-light);">
                    <span class="material-icons-round">logout</span>
                    Déconnexion
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
        try {
            const result = await CashierAPI.getProfile();
            if (result.status === 'success' && result.data) {
                renderProfile(result.data);
            } else {
                els.root.innerHTML = `<div class="cp-loading"><span class="material-icons-round">error_outline</span><p>${escapeHtml(result.message || 'Erreur')}</p></div>`;
            }
        } catch (err) {
            console.error(err);
            els.root.innerHTML = '<div class="cp-loading"><p>Erreur de chargement du profil.</p></div>';
        }
    }

    async function handleSubmit(e) {
        e.preventDefault();

        const name = els.nameInput?.value.trim() || '';
        const currentPassword = document.getElementById('currentPassword')?.value || '';
        const newPassword = document.getElementById('newPassword')?.value || '';
        const confirmPassword = document.getElementById('confirmPassword')?.value || '';

        if (name.length < 2) {
            toast('Le nom doit contenir au moins 2 caractères', 'err');
            return;
        }

        if (newPassword || confirmPassword || currentPassword) {
            if (newPassword.length < 6) {
                toast('Le nouveau mot de passe doit faire au moins 6 caractères', 'err');
                return;
            }
            if (newPassword !== confirmPassword) {
                toast('Les mots de passe ne correspondent pas', 'err');
                return;
            }
            if (!currentPassword) {
                toast('Saisissez votre mot de passe actuel', 'err');
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
                toast(result.message || 'Profil mis à jour', 'ok');
                document.getElementById('profileDisplayName').textContent = name;
                document.getElementById('profileAvatar').textContent = name.charAt(0).toUpperCase();
                const headerName = document.querySelector('.user-name');
                if (headerName) headerName.textContent = name;
                document.getElementById('currentPassword').value = '';
                document.getElementById('newPassword').value = '';
                document.getElementById('confirmPassword').value = '';
            } else {
                toast(result.message || 'Erreur', 'err');
            }
        } catch (err) {
            console.error(err);
            toast('Erreur de connexion', 'err');
        }

        els.saveBtn.disabled = false;
    }

    const menuBtn = document.getElementById('mobileMenuBtn');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    menuBtn?.addEventListener('click', () => {
        sidebar?.classList.toggle('open');
        overlay?.classList.toggle('active');
    });
    overlay?.addEventListener('click', () => {
        sidebar?.classList.remove('open');
        overlay?.classList.remove('active');
    });

    loadProfile();
});
