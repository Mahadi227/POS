/**
 * Gestion utilisateurs — Super Admin
 */
(() => {
    let allUsers = [];
    let assignableRoles = [];
    let stores = [];
    let currentRole = (window.ADMIN_PAGE && window.ADMIN_PAGE.role) || '';
    let currentStoreId = (window.ADMIN_PAGE && window.ADMIN_PAGE.storeId) || null;

    const $ = (id) => document.getElementById(id);

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = s ?? '';
        return d.innerHTML;
    }

    function showModal(id) {
        $(id)?.classList.add('active');
    }

    function hideModal(id) {
        $(id)?.classList.remove('active');
    }

    function showFormError(elId, msg) {
        const el = $(elId);
        if (!el) return;
        el.textContent = msg;
        el.classList.add('visible');
    }

    function hideFormError(elId) {
        $(elId)?.classList.remove('visible');
    }

    function roleBadge(slug) {
        return `<span class="um-role-badge um-role-badge--${escapeHtml(slug)}">${escapeHtml(AdminAPI.roleLabel(slug))}</span>`;
    }

    function filteredUsers() {
        const q = ($('userSearch')?.value || '').trim().toLowerCase();
        return allUsers.filter((u) => {
            if (q && !u.name.toLowerCase().includes(q) && !u.email.toLowerCase().includes(q)) {
                return false;
            }
            return true;
        });
    }

    async function loadStores() {
        const res = await AdminAPI.listStores();
        if (res.status === 'success') {
            stores = res.data || [];
            const sel = $('ufStore');
            if (sel) {
                // If not super_admin, only allow selecting the current store (read-only)
                if (currentRole && currentRole !== 'super_admin') {
                    const store = stores.find((s) => String(s.id) === String(currentStoreId) || s.id === currentStoreId);
                    sel.innerHTML = '';
                    if (store) {
                        const o = document.createElement('option');
                        o.value = store.id;
                        o.textContent = store.name;
                        sel.appendChild(o);
                        sel.value = store.id;
                        sel.disabled = true;
                    } else {
                        sel.innerHTML = '<option value="">— Aucune —</option>';
                        sel.disabled = true;
                    }
                } else {
                    sel.innerHTML = '<option value="">— Aucune —</option>';
                    stores.forEach((s) => {
                        const o = document.createElement('option');
                        o.value = s.id;
                        o.textContent = s.name;
                        sel.appendChild(o);
                    });
                    sel.disabled = false;
                }
            }

            const filter = $('storeFilter');
            if (filter) {
                filter.innerHTML = '<option value="">Toutes les succursales</option>';
                stores.forEach((s) => {
                    const o = document.createElement('option');
                    o.value = s.id;
                    o.textContent = s.name;
                    filter.appendChild(o);
                });
                filter.disabled = !(currentRole && currentRole === 'super_admin');
            }
        }
    }

    async function loadRoles() {
        const res = await AdminAPI.getRoles();
        if (res.status !== 'success') return;
        assignableRoles = (res.data || []).filter((r) => r.can_assign);
        const sel = $('ufRole');
        const permSel = $('permRoleSelect');
        if (sel) {
            sel.innerHTML = '';
            assignableRoles.forEach((r) => {
                const o = document.createElement('option');
                o.value = r.id;
                o.textContent = r.name;
                sel.appendChild(o);
            });
        }
        if (permSel) {
            permSel.innerHTML = '';
            (res.data || []).filter((r) => r.slug !== 'super_admin').forEach((r) => {
                const o = document.createElement('option');
                o.value = r.id;
                o.textContent = r.name;
                permSel.appendChild(o);
            });
        }
    }

    async function loadUsers() {
        const tbody = $('usersTableBody');
        tbody.innerHTML = '<tr><td colspan="6" class="ad-empty-row">Chargement…</td></tr>';

        const query = {};
        const role = $('roleFilter')?.value;
        const status = $('statusFilter')?.value;
        const store = $('storeFilter')?.value;
        if (role) query.role = role;
        if (status) query.status = status;
        if (store && currentRole === 'super_admin') query.store_id = store;

        // If the current user is not super_admin, scope users to their store
        if (currentRole && currentRole !== 'super_admin') {
            query.store_id = currentStoreId || undefined;
        }

        const res = await AdminAPI.getUsers(query);
        if (res.status !== 'success') {
            tbody.innerHTML = `<tr><td colspan="6" class="ad-empty-row">${escapeHtml(res.message || 'Erreur')}</td></tr>`;
            return;
        }

        allUsers = (res.data || []).filter((u) => u.role_slug !== 'super_admin');
        const actSel = $('activityUserFilter');
        if (actSel) delete actSel.dataset.ready;
        renderUsers();
    }

    function renderUsers() {
        const tbody = $('usersTableBody');
        const list = filteredUsers();

        if (!list.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="ad-empty-row">Aucun utilisateur</td></tr>';
            return;
        }

        tbody.innerHTML = list.map((u) => {
            const statusCls = u.is_active ? 'um-status--active' : 'um-status--suspended';
            const statusTxt = u.is_active ? 'Actif' : 'Suspendu';
            const lastLogin = u.last_login ? AdminAPI.formatDate(u.last_login) : '—';
            const suspendBtn = u.is_active
                ? `<button type="button" class="icon-btn" data-suspend="${u.id}" title="Suspendre"><span class="material-icons-round" style="font-size:18px;color:#d97706;">pause_circle</span></button>`
                : `<button type="button" class="icon-btn" data-activate="${u.id}" title="Réactiver"><span class="material-icons-round" style="font-size:18px;color:#16a34a;">play_circle</span></button>`;

            return `<tr>
                <td><strong>${escapeHtml(u.name)}</strong><br><small style="color:var(--text-muted);">${escapeHtml(u.email)}</small></td>
                <td>${roleBadge(u.role_slug)}</td>
                <td>${escapeHtml(u.store_name || '—')}</td>
                <td class="${statusCls}">${statusTxt}</td>
                <td style="color:var(--text-secondary);font-size:0.88rem;">${lastLogin}</td>
                <td><div class="um-row-actions">
                    <button type="button" class="icon-btn" data-edit="${u.id}" title="Modifier"><span class="material-icons-round">edit</span></button>
                    <button type="button" class="icon-btn" data-reset="${u.id}" data-name="${escapeAttr(u.name)}" title="Reset mot de passe"><span class="material-icons-round" style="color:var(--primary);">lock_reset</span></button>
                    ${suspendBtn}
                </div></td>
            </tr>`;
        }).join('');

        bindUserActions();
    }

    function escapeAttr(s) {
        return String(s ?? '').replace(/"/g, '&quot;');
    }

    function bindUserActions() {
        document.querySelectorAll('[data-edit]').forEach((btn) => {
            btn.addEventListener('click', () => openUserForm(parseInt(btn.dataset.edit, 10)));
        });
        document.querySelectorAll('[data-reset]').forEach((btn) => {
            btn.addEventListener('click', () => openResetModal(parseInt(btn.dataset.reset, 10), btn.dataset.name));
        });
        document.querySelectorAll('[data-suspend]').forEach((btn) => {
            btn.addEventListener('click', async () => {
                if (!confirm('Suspendre cet utilisateur ?')) return;
                const res = await AdminAPI.suspendUser(btn.dataset.suspend);
                if (res.status === 'success') loadUsers();
                else alert(res.message || 'Erreur');
            });
        });
        document.querySelectorAll('[data-activate]').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const res = await AdminAPI.activateUser(btn.dataset.activate);
                if (res.status === 'success') loadUsers();
                else alert(res.message || 'Erreur');
            });
        });
    }

    function openUserForm(id = null) {
        hideFormError('userFormError');
        $('userForm').reset();
        $('userId').value = id || '';
        const isEdit = !!id;

        $('userModalTitle').textContent = isEdit ? 'Modifier l\'utilisateur' : 'Nouvel utilisateur';
        $('passwordLabel').textContent = isEdit ? 'Nouveau mot de passe (laisser vide = inchangé)' : 'Mot de passe * (8+ caractères)';
        $('ufPassword').required = !isEdit;

        if (isEdit) {
            const u = allUsers.find((x) => x.id === id);
            if (u) {
                $('ufName').value = u.name;
                $('ufEmail').value = u.email;
                $('ufRole').value = u.role_id;
                $('ufStore').value = u.store_id || '';
            }
        }
        showModal('userModal');
    }

    function openResetModal(id, name) {
        hideFormError('resetFormError');
        $('resetForm').reset();
        $('resetUserId').value = id;
        $('resetUserLabel').textContent = `Utilisateur : ${name}`;
        showModal('resetModal');
    }

    async function loadPermissionsPanel() {
        const roleId = parseInt($('permRoleSelect')?.value, 10);
        const grid = $('permissionsGrid');
        if (!roleId) {
            grid.innerHTML = '<p>Sélectionnez un rôle</p>';
            return;
        }
        grid.innerHTML = 'Chargement…';
        const res = await AdminAPI.getRolePermissions(roleId);
        if (res.status !== 'success') {
            grid.innerHTML = escapeHtml(res.message || 'Erreur');
            return;
        }
        const perms = res.data?.permissions || [];
        grid.innerHTML = perms.map((p) => `
            <label class="um-perm-item">
                <input type="checkbox" value="${p.id}" ${p.assigned ? 'checked' : ''}>
                <span><strong>${escapeHtml(p.name)}</strong><br><small>${escapeHtml(p.description || '')}</small></span>
            </label>
        `).join('');
    }

    function populateActivityUserFilter() {
        const sel = $('activityUserFilter');
        if (!sel || sel.dataset.ready === '1') return;
        const opts = (allUsers || []).map((u) =>
            `<option value="${u.id}">${escapeHtml(u.name || u.email)}</option>`
        ).join('');
        sel.innerHTML = '<option value="">Tous les utilisateurs</option>' + opts;
        sel.dataset.ready = '1';
    }

    function shortUserAgent(ua) {
        if (!ua) return '';
        if (ua.length <= 48) return ua;
        return ua.slice(0, 45) + '…';
    }

    async function loadActivity() {
        const tbody = $('activityTableBody');
        tbody.innerHTML = '<tr><td colspan="6" class="ad-empty-row">Chargement…</td></tr>';

        populateActivityUserFilter();

        const type = $('activityTypeFilter')?.value || 'all';
        const userId = $('activityUserFilter')?.value || '';
        const query = { limit: 150, type };
        if (userId) query.user_id = userId;

        const res = await AdminAPI.getUserActivity(query);
        if (res.status !== 'success') {
            tbody.innerHTML = `<tr><td colspan="6">${escapeHtml(res.message || 'Erreur')}</td></tr>`;
            return;
        }

        const stats = res.stats || {};
        $('actStatLogins') && ($('actStatLogins').textContent = stats.logins_today ?? 0);
        $('actStatFailed') && ($('actStatFailed').textContent = stats.logins_failed ?? 0);
        $('actStatAdmin') && ($('actStatAdmin').textContent = stats.admin_actions ?? 0);
        $('actStatUsers') && ($('actStatUsers').textContent = stats.unique_users_today ?? 0);

        const rows = res.data || [];
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="ad-empty-row">Aucune activité enregistrée</td></tr>';
            return;
        }

        const statusLabel = { success: 'Succès', failed: 'Échec', error: 'Erreur' };

        tbody.innerHTML = rows.map((a) => {
            const cat = a.category === 'login' ? 'login' : 'admin';
            const catLabel = cat === 'login' ? 'Connexion' : 'Admin';
            const statusCls = a.status === 'success' ? 'success' : (a.status === 'failed' ? 'pending' : 'pending');
            const agent = shortUserAgent(a.user_agent);
            return `
            <tr>
                <td style="white-space:nowrap;">${AdminAPI.formatDate(a.created_at)}</td>
                <td>
                    <strong>${escapeHtml(a.user_name || '—')}</strong>
                    ${a.email ? `<br><small>${escapeHtml(a.email)}</small>` : ''}
                </td>
                <td><span class="um-cat-badge um-cat-badge--${cat}">${catLabel}</span></td>
                <td>
                    ${escapeHtml(a.action_label || a.action)}
                    ${agent ? `<small class="um-agent-hint" title="${escapeHtml(a.user_agent)}">${escapeHtml(agent)}</small>` : ''}
                </td>
                <td>${escapeHtml(a.ip_address || '—')}</td>
                <td><span class="status-badge ${statusCls}">${escapeHtml(statusLabel[a.status] || a.status)}</span></td>
            </tr>`;
        }).join('');
    }

    function initTabs() {
        document.querySelectorAll('.um-tab').forEach((tab) => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.um-tab').forEach((t) => t.classList.remove('active'));
                tab.classList.add('active');
                document.querySelectorAll('.um-panel').forEach((p) => p.classList.add('hidden'));
                $(`panel-${tab.dataset.panel}`)?.classList.remove('hidden');
                if (tab.dataset.panel === 'permissions') loadPermissionsPanel();
                if (tab.dataset.panel === 'activity') loadActivity();
            });
        });
    }

    function initEvents() {
        $('addUserBtn')?.addEventListener('click', () => openUserForm());
        $('closeUserModal')?.addEventListener('click', () => hideModal('userModal'));
        $('closeResetModal')?.addEventListener('click', () => hideModal('resetModal'));
        $('refreshUsers')?.addEventListener('click', loadUsers);
        $('userSearch')?.addEventListener('input', renderUsers);
        $('roleFilter')?.addEventListener('change', loadUsers);
        $('storeFilter')?.addEventListener('change', loadUsers);
        $('statusFilter')?.addEventListener('change', loadUsers);
        $('permRoleSelect')?.addEventListener('change', loadPermissionsPanel);
        $('activityTypeFilter')?.addEventListener('change', loadActivity);
        $('activityUserFilter')?.addEventListener('change', loadActivity);
        $('refreshActivity')?.addEventListener('click', loadActivity);

        $('savePermissionsBtn')?.addEventListener('click', async () => {
            const roleId = parseInt($('permRoleSelect').value, 10);
            const ids = [...document.querySelectorAll('#permissionsGrid input:checked')].map((c) => parseInt(c.value, 10));
            const res = await AdminAPI.updateRolePermissions(roleId, ids);
            if (res.status === 'success') alert('Permissions enregistrées');
            else alert(res.message || 'Erreur');
        });

        $('userForm')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            hideFormError('userFormError');
            const id = $('userId').value;
            const payload = {
                name: $('ufName').value.trim(),
                email: $('ufEmail').value.trim(),
                role_id: parseInt($('ufRole').value, 10),
                store_id: $('ufStore').value || null,
            };
            const pwd = $('ufPassword').value;
            if (!id) {
                payload.password = pwd;
                if (!pwd || pwd.length < 8) {
                    showFormError('userFormError', 'Mot de passe requis (8 caractères minimum)');
                    return;
                }
            } else if (pwd) {
                payload.password = pwd;
            }

            const res = id
                ? await AdminAPI.updateUser(id, payload)
                : await AdminAPI.createUser(payload);

            if (res.status === 'success') {
                hideModal('userModal');
                loadUsers();
            } else {
                showFormError('userFormError', res.message || 'Erreur');
            }
        });

        $('resetForm')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            hideFormError('resetFormError');
            const res = await AdminAPI.resetUserPassword($('resetUserId').value, $('resetPassword').value);
            if (res.status === 'success') {
                hideModal('resetModal');
                alert('Mot de passe réinitialisé');
            } else {
                showFormError('resetFormError', res.message || 'Erreur');
            }
        });
    }

    document.addEventListener('DOMContentLoaded', async () => {
        initTabs();
        initEvents();
        await Promise.all([loadRoles(), loadStores(), loadUsers()]);
        if ($('permRoleSelect')?.value) loadPermissionsPanel();
    });
})();
