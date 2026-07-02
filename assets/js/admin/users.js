/**
 * Admin users — teams, permissions, activity (Super Admin)
 */
(() => {
    const CFG = window.USERS_PAGE || {};
    const i18n = window.USERS_I18N || {};
    const locale = CFG.locale || (CFG.lang === 'fr' ? 'fr-FR' : 'en-US');
    const PAGE_SIZE = 15;

    let allUsers = [];
    let filteredUsers = [];
    let assignableRoles = [];
    let stores = [];
    let currentPage = 1;
    let statusChipFilter = '';
    let debounceTimer = null;
    let lastFetchAt = null;

    const $ = (id) => document.getElementById(id);

    const ROLE_KEYS = {
        super_admin: 'role_super_admin',
        admin: 'role_admin',
        manager: 'role_manager',
        cashier: 'role_cashier',
        staff: 'role_staff',
    };

    function t(key, ...args) {
        let str = i18n[key] || key;
        args.forEach((val) => {
            str = str.replace('%s', val);
        });
        return str;
    }

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value ?? '';
        return div.innerHTML;
    }

    function escapeAttr(value) {
        return String(value ?? '').replace(/"/g, '&quot;');
    }

    function roleLabel(slug) {
        const key = ROLE_KEYS[slug];
        return key ? t(key) : (slug || '—');
    }

    function toast(msg, type = 'success') {
        const el = $('usersToast');
        if (!el) return;
        el.textContent = msg;
        el.className = `inv-toast show ${type === 'error' ? 'error' : ''}`;
        clearTimeout(el._t);
        el._t = setTimeout(() => el.classList.remove('show'), 3200);
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

    function setStatsLoading(loading) {
        document.querySelectorAll('#umSummaryCards .ad-kpi').forEach((el) => {
            el.classList.toggle('is-loading', loading);
        });
    }

    function clearKpiLoading(el) {
        if (!el) return;
        el.closest('.ad-kpi')?.classList.remove('is-loading');
    }

    function updateDateHeader() {
        const label = new Date().toLocaleDateString(locale, {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        });
        const header = $('usersDate');
        if (header) header.textContent = label;

        const periodEl = $('umHeroPeriod');
        if (periodEl) periodEl.textContent = label;

        const scopeEl = $('umHeroScope');
        if (scopeEl) scopeEl.textContent = t('users_scope');
    }

    function columnLabels() {
        return {
            user: t('col_user'),
            role: t('col_role'),
            store: t('col_store'),
            status: t('col_status'),
            lastLogin: t('col_last_login'),
            actions: t('col_actions'),
            date: t('col_date'),
            type: t('col_type'),
            action: t('col_action'),
            ip: t('col_ip'),
        };
    }

    function updateLastUpdated() {
        const el = $('lastUpdated');
        if (!el || !lastFetchAt) return;
        const time = lastFetchAt.toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' });
        el.textContent = t('last_updated', time);
    }

    function roleBadge(slug) {
        return `<span class="um-role-badge um-role-badge--${escapeHtml(slug)}">${escapeHtml(roleLabel(slug))}</span>`;
    }

    function updateUserStats(users) {
        setStatsLoading(false);
        const active = users.filter((u) => u.is_active).length;
        const suspended = users.length - active;

        const totalEl = $('stat-total-users-val');
        if (totalEl) {
            totalEl.textContent = String(users.length);
            clearKpiLoading(totalEl);
        }
        const activeEl = $('stat-active-users-val');
        if (activeEl) {
            activeEl.textContent = String(active);
            clearKpiLoading(activeEl);
        }
        const suspendedEl = $('stat-suspended-users-val');
        if (suspendedEl) {
            suspendedEl.textContent = String(suspended);
            clearKpiLoading(suspendedEl);
        }
    }

    function updateActivityStats(stats) {
        const logins = stats.logins_today ?? 0;
        if ($('actStatLogins')) $('actStatLogins').textContent = String(logins);
        if ($('actStatFailed')) $('actStatFailed').textContent = String(stats.logins_failed ?? 0);
        if ($('actStatAdmin')) $('actStatAdmin').textContent = String(stats.admin_actions ?? 0);
        if ($('actStatUsers')) $('actStatUsers').textContent = String(stats.unique_users_today ?? 0);

        const heroLogins = $('stat-logins-today-val');
        if (heroLogins) {
            heroLogins.textContent = String(logins);
            clearKpiLoading(heroLogins);
        }
    }

    async function loadActivityHeroStats() {
        try {
            const res = await AdminAPI.getUserActivity({ limit: 1, type: 'all' });
            if (res.status === 'success') {
                updateActivityStats(res.stats || {});
            }
        } catch (e) {
            console.warn('activity hero stats', e);
            clearKpiLoading($('stat-logins-today-val'));
        }
    }

    function applyClientFilters() {
        const q = ($('userSearch')?.value || '').trim().toLowerCase();
        filteredUsers = allUsers.filter((u) => {
            if (q && !u.name.toLowerCase().includes(q) && !u.email.toLowerCase().includes(q)) {
                return false;
            }
            return true;
        });
    }

    async function loadStores() {
        const res = await AdminAPI.listStores();
        if (res.status !== 'success') return;

        stores = res.data || [];
        const sel = $('ufStore');
        if (sel) {
            sel.innerHTML = `<option value="">${escapeHtml(t('user_store_none'))}</option>`;
            stores.forEach((s) => {
                const o = document.createElement('option');
                o.value = s.id;
                o.textContent = s.name;
                sel.appendChild(o);
            });
        }

        const filter = $('storeFilter');
        if (filter) {
            filter.innerHTML = `<option value="">${escapeHtml(t('filter_all_stores'))}</option>`;
            stores.forEach((s) => {
                const o = document.createElement('option');
                o.value = s.id;
                o.textContent = s.name;
                filter.appendChild(o);
            });
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
        setStatsLoading(true);
        if (tbody) {
            tbody.innerHTML = `<tr><td colspan="6" class="ad-empty-row">${escapeHtml(t('loading'))}</td></tr>`;
        }

        const query = {};
        const role = $('roleFilter')?.value;
        const status = statusChipFilter || $('statusFilter')?.value;
        const store = $('storeFilter')?.value;
        if (role) query.role = role;
        if (status) query.status = status;
        if (store) query.store_id = store;

        try {
            const res = await AdminAPI.getUsers(query);
            if (res.status !== 'success') {
                if (tbody) {
                    tbody.innerHTML = `<tr><td colspan="6" class="ad-empty-row">${escapeHtml(res.message || t('load_error'))}</td></tr>`;
                }
                setStatsLoading(false);
                return;
            }

            allUsers = (res.data || []).filter((u) => u.role_slug !== 'super_admin');
            lastFetchAt = new Date();
            updateLastUpdated();
            updateUserStats(allUsers);

            const actSel = $('activityUserFilter');
            if (actSel) delete actSel.dataset.ready;

            currentPage = 1;
            renderUsers();
        } catch (e) {
            console.error(e);
            if (tbody) {
                tbody.innerHTML = `<tr><td colspan="6" class="ad-empty-row">${escapeHtml(t('connection_error'))}</td></tr>`;
            }
            setStatsLoading(false);
        }
    }

    function renderUsers() {
        const tbody = $('usersTableBody');
        if (!tbody) return;

        applyClientFilters();
        const list = filteredUsers;

        if (!list.length) {
            tbody.innerHTML = `<tr><td colspan="6" class="ad-empty-row">${escapeHtml(t('no_users'))}</td></tr>`;
            if ($('usersSummary')) $('usersSummary').textContent = t('no_users');
            if ($('pageInfo')) $('pageInfo').textContent = '1 / 1';
            if ($('pagePrev')) $('pagePrev').disabled = true;
            if ($('pageNext')) $('pageNext').disabled = true;
            return;
        }

        const totalPages = Math.max(1, Math.ceil(list.length / PAGE_SIZE));
        if (currentPage > totalPages) currentPage = totalPages;
        const start = (currentPage - 1) * PAGE_SIZE;
        const pageItems = list.slice(start, start + PAGE_SIZE);

        if ($('usersSummary')) {
            $('usersSummary').textContent = t('users_table_summary', list.length, currentPage, totalPages);
        }
        if ($('pageInfo')) $('pageInfo').textContent = `${currentPage} / ${totalPages}`;
        if ($('pagePrev')) $('pagePrev').disabled = currentPage <= 1;
        if ($('pageNext')) $('pageNext').disabled = currentPage >= totalPages;

        const lbl = columnLabels();

        tbody.innerHTML = pageItems.map((u) => {
            const statusCls = u.is_active ? 'um-status--active' : 'um-status--suspended';
            const statusTxt = u.is_active ? t('user_active') : t('user_suspended');
            const lastLogin = u.last_login ? AdminAPI.formatDate(u.last_login) : '—';
            const suspendBtn = u.is_active
                ? `<button type="button" class="icon-btn" data-suspend="${u.id}" title="${escapeHtml(t('suspend_user'))}"><span class="material-icons-round" style="font-size:18px;color:#d97706;">pause_circle</span></button>`
                : `<button type="button" class="icon-btn" data-activate="${u.id}" title="${escapeHtml(t('activate_user'))}"><span class="material-icons-round" style="font-size:18px;color:#16a34a;">play_circle</span></button>`;

            return `<tr class="um-row-clickable">
                <td data-label="${escapeAttr(lbl.user)}">
                    <div class="um-user-cell">
                        <span class="um-avatar">${escapeHtml((u.name || '?').charAt(0).toUpperCase())}</span>
                        <div>
                            <strong>${escapeHtml(u.name)}</strong>
                            <small>${escapeHtml(u.email)}</small>
                        </div>
                    </div>
                </td>
                <td data-label="${escapeAttr(lbl.role)}">${roleBadge(u.role_slug)}</td>
                <td data-label="${escapeAttr(lbl.store)}">${escapeHtml(u.store_name || '—')}</td>
                <td class="${statusCls}" data-label="${escapeAttr(lbl.status)}">${escapeHtml(statusTxt)}</td>
                <td class="um-last-login" data-label="${escapeAttr(lbl.lastLogin)}">${escapeHtml(lastLogin)}</td>
                <td class="um-col-actions" data-label="${escapeAttr(lbl.actions)}"><div class="um-row-actions">
                    <button type="button" class="icon-btn" data-edit="${u.id}" title="${escapeHtml(t('edit_user'))}"><span class="material-icons-round">edit</span></button>
                    <button type="button" class="icon-btn" data-reset="${u.id}" data-name="${escapeAttr(u.name)}" title="${escapeHtml(t('reset_password'))}"><span class="material-icons-round" style="color:var(--primary);">lock_reset</span></button>
                    ${suspendBtn}
                </div></td>
            </tr>`;
        }).join('');

        bindUserActions();
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
                if (!confirm(t('confirm_suspend'))) return;
                const res = await AdminAPI.suspendUser(btn.dataset.suspend);
                if (res.status === 'success') {
                    toast(res.message || t('user_saved'));
                    loadUsers();
                } else {
                    toast(res.message || t('error'), 'error');
                }
            });
        });
        document.querySelectorAll('[data-activate]').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const res = await AdminAPI.activateUser(btn.dataset.activate);
                if (res.status === 'success') {
                    toast(res.message || t('user_saved'));
                    loadUsers();
                } else {
                    toast(res.message || t('error'), 'error');
                }
            });
        });
    }

    function openUserForm(id = null) {
        hideFormError('userFormError');
        $('userForm')?.reset();
        $('userId').value = id || '';
        const isEdit = !!id;

        $('userModalTitle').textContent = isEdit ? t('user_modal_edit') : t('user_modal_new');
        $('passwordLabel').textContent = isEdit ? t('user_password_edit') : t('user_password');
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
        $('resetForm')?.reset();
        $('resetUserId').value = id;
        $('resetUserLabel').textContent = t('reset_password_for', name);
        showModal('resetModal');
    }

    async function loadPermissionsPanel() {
        const roleId = parseInt($('permRoleSelect')?.value, 10);
        const grid = $('permissionsGrid');
        if (!roleId) {
            grid.innerHTML = `<p class="ad-empty-row">${escapeHtml(t('select_role_permissions'))}</p>`;
            return;
        }
        grid.innerHTML = escapeHtml(t('loading'));
        const res = await AdminAPI.getRolePermissions(roleId);
        if (res.status !== 'success') {
            grid.innerHTML = escapeHtml(res.message || t('load_error'));
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
        sel.innerHTML = `<option value="">${escapeHtml(t('activity_all_users'))}</option>${opts}`;
        sel.dataset.ready = '1';
    }

    function shortUserAgent(ua) {
        if (!ua) return '';
        if (ua.length <= 48) return ua;
        return `${ua.slice(0, 45)}…`;
    }

    function activityCategoryMeta(a) {
        if (a.action === 'logout') {
            return { cat: 'logout', label: t('activity_type_logout') };
        }
        if (a.category === 'login') {
            return { cat: 'login', label: t('activity_type_login') };
        }
        return { cat: 'admin', label: t('activity_type_admin') };
    }

    function activityActionLabel(a) {
        const keys = {
            logout: 'activity_action_logout',
            login_success: 'activity_action_login_success',
            login_failed: 'activity_action_login_failed',
        };
        if (a.action === 'login_attempt') {
            return t(a.status === 'success' ? 'activity_action_login_success' : 'activity_action_login_failed');
        }
        const key = keys[a.action];
        return key ? t(key) : (a.action_label || a.action);
    }

    async function loadActivity() {
        const tbody = $('activityTableBody');
        tbody.innerHTML = `<tr><td colspan="6" class="ad-empty-row">${escapeHtml(t('loading'))}</td></tr>`;

        populateActivityUserFilter();

        const type = $('activityTypeFilter')?.value || 'all';
        const userId = $('activityUserFilter')?.value || '';
        const query = { limit: 150, type };
        if (userId) query.user_id = userId;

        const res = await AdminAPI.getUserActivity(query);
        if (res.status !== 'success') {
            tbody.innerHTML = `<tr><td colspan="6" class="ad-empty-row">${escapeHtml(res.message || t('load_error'))}</td></tr>`;
            return;
        }

        const stats = res.stats || {};
        updateActivityStats(stats);

        const rows = res.data || [];
        if (!rows.length) {
            tbody.innerHTML = `<tr><td colspan="6" class="ad-empty-row">${escapeHtml(t('no_activity'))}</td></tr>`;
            return;
        }

        const statusKeys = {
            success: 'activity_status_success',
            failed: 'activity_status_failed',
            error: 'activity_status_error',
        };

        const lbl = columnLabels();

        tbody.innerHTML = rows.map((a) => {
            const { cat, label: catLabel } = activityCategoryMeta(a);
            const statusCls = a.status === 'success' ? 'success' : 'pending';
            const statusKey = statusKeys[a.status] || a.status;
            const agent = shortUserAgent(a.user_agent);

            return `
            <tr>
                <td class="um-last-login" data-label="${escapeAttr(lbl.date)}">${AdminAPI.formatDate(a.created_at)}</td>
                <td data-label="${escapeAttr(lbl.user)}">
                    <strong>${escapeHtml(a.user_name || '—')}</strong>
                    ${a.email ? `<br><small>${escapeHtml(a.email)}</small>` : ''}
                </td>
                <td data-label="${escapeAttr(lbl.type)}"><span class="um-cat-badge um-cat-badge--${cat}">${escapeHtml(catLabel)}</span></td>
                <td data-label="${escapeAttr(lbl.action)}">
                    ${escapeHtml(activityActionLabel(a))}
                    ${agent ? `<small class="um-agent-hint" title="${escapeHtml(a.user_agent)}">${escapeHtml(agent)}</small>` : ''}
                </td>
                <td data-label="${escapeAttr(lbl.ip)}">${escapeHtml(a.ip_address || '—')}</td>
                <td data-label="${escapeAttr(lbl.status)}"><span class="status-badge ${statusCls}">${escapeHtml(t(statusKey) || a.status)}</span></td>
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

    function syncStatusChips() {
        document.querySelectorAll('.um-chips .inv-chip').forEach((c) => {
            const active = (c.dataset.status || '') === statusChipFilter;
            c.classList.toggle('active', active);
            c.setAttribute('aria-selected', active ? 'true' : 'false');
        });
    }

    function initEvents() {
        $('addUserBtn')?.addEventListener('click', () => openUserForm());
        $('addUserBtnHero')?.addEventListener('click', () => openUserForm());
        $('closeUserModal')?.addEventListener('click', () => hideModal('userModal'));
        $('cancelUserModal')?.addEventListener('click', () => hideModal('userModal'));
        $('closeResetModal')?.addEventListener('click', () => hideModal('resetModal'));
        $('cancelResetModal')?.addEventListener('click', () => hideModal('resetModal'));

        $('refreshUsersBtn')?.addEventListener('click', async () => {
            $('refreshUsersBtn')?.classList.add('spinning');
            await loadUsers();
            $('refreshUsersBtn')?.classList.remove('spinning');
        });

        $('userSearch')?.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                currentPage = 1;
                renderUsers();
            }, 300);
        });

        $('roleFilter')?.addEventListener('change', loadUsers);
        $('storeFilter')?.addEventListener('change', loadUsers);

        document.querySelectorAll('.um-chips .inv-chip').forEach((chip) => {
            chip.addEventListener('click', () => {
                statusChipFilter = chip.dataset.status || '';
                syncStatusChips();
                currentPage = 1;
                loadUsers();
            });
        });

        $('pagePrev')?.addEventListener('click', () => {
            if (currentPage > 1) {
                currentPage--;
                renderUsers();
            }
        });
        $('pageNext')?.addEventListener('click', () => {
            const totalPages = Math.max(1, Math.ceil(filteredUsers.length / PAGE_SIZE));
            if (currentPage < totalPages) {
                currentPage++;
                renderUsers();
            }
        });

        $('permRoleSelect')?.addEventListener('change', loadPermissionsPanel);
        $('activityTypeFilter')?.addEventListener('change', loadActivity);
        $('activityUserFilter')?.addEventListener('change', loadActivity);
        $('refreshActivity')?.addEventListener('click', loadActivity);

        $('userModal')?.addEventListener('click', (e) => {
            if (e.target === e.currentTarget) hideModal('userModal');
        });
        $('resetModal')?.addEventListener('click', (e) => {
            if (e.target === e.currentTarget) hideModal('resetModal');
        });

        $('savePermissionsBtn')?.addEventListener('click', async () => {
            const roleId = parseInt($('permRoleSelect').value, 10);
            const ids = [...document.querySelectorAll('#permissionsGrid input:checked')].map((c) => parseInt(c.value, 10));
            const res = await AdminAPI.updateRolePermissions(roleId, ids);
            if (res.status === 'success') toast(t('permissions_saved'));
            else toast(res.message || t('error'), 'error');
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
                    showFormError('userFormError', t('password_required'));
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
                toast(res.message || t('user_saved'));
                loadUsers();
            } else {
                showFormError('userFormError', res.message || t('error'));
            }
        });

        $('resetForm')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            hideFormError('resetFormError');
            const res = await AdminAPI.resetUserPassword($('resetUserId').value, $('resetPassword').value);
            if (res.status === 'success') {
                hideModal('resetModal');
                toast(t('reset_password_success'));
            } else {
                showFormError('resetFormError', res.message || t('error'));
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                hideModal('userModal');
                hideModal('resetModal');
            }
        });
    }

    document.addEventListener('DOMContentLoaded', async () => {
        updateDateHeader();
        initTabs();
        initEvents();
        await Promise.all([loadRoles(), loadStores(), loadUsers(), loadActivityHeroStats()]);
        if ($('permRoleSelect')?.value) loadPermissionsPanel();
    });
})();
