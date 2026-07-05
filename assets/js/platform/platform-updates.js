(function () {
    'use strict';

    const cfg = window.PLATFORM_CONFIG || {};
    const updCfg = window.PLATFORM_UPDATES || {};
    const { apiGet, apiPost, apiPut, t, setLastUpdated } = window.PlatformAPI || {};

    let releases = [];
    let migrations = [];
    let currentVersion = null;
    let debounce = null;

    const TYPE_I18N = {
        major: 'plat_upd_type_major',
        minor: 'plat_upd_type_minor',
        patch: 'plat_upd_type_patch',
        hotfix: 'plat_upd_type_hotfix',
        migration: 'plat_upd_type_migration',
    };

    const STATUS_I18N = {
        draft: 'plat_upd_status_draft',
        scheduled: 'plat_upd_status_scheduled',
        released: 'plat_upd_status_released',
        rolled_back: 'plat_upd_status_rolled_back',
    };

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s ?? '';
        return d.innerHTML;
    }

    function label(map, key) {
        return map[key] ? t(map[key]) : key;
    }

    function fmt(v) {
        if (!v) return '—';
        try { return new Date(v).toLocaleString(cfg.locale); } catch (e) { return '—'; }
    }

    async function apiDelete(path) {
        const res = await fetch(`${cfg.apiBase}?request=platform/${path}`, {
            method: 'DELETE',
            credentials: 'same-origin',
        });
        return res.json();
    }

    function showError(msg) {
        const el = document.getElementById('platUpdError');
        document.getElementById('platUpdErrorText').textContent = msg || t('plat_upd_load_error');
        el.hidden = false;
        document.getElementById('platUpdAlert').hidden = true;
    }

    function hideError() {
        const el = document.getElementById('platUpdError');
        if (el) el.hidden = true;
    }

    function showSuccess(msg) {
        const el = document.getElementById('platUpdAlert');
        el.textContent = msg;
        el.hidden = false;
        hideError();
        clearTimeout(showSuccess._t);
        showSuccess._t = setTimeout(() => { el.hidden = true; }, 4000);
    }

    function setKpiLoading(loading) {
        document.querySelectorAll('#platUpdKpiGrid .plat-kpi-card').forEach((c) => {
            c.classList.toggle('is-loading', loading);
        });
    }

    function updateCount(n) {
        const el = document.getElementById('platUpdCount');
        if (!el) return;
        const template = t('plat_upd_count');
        el.textContent = template.includes('%d') ? template.replace('%d', String(n)) : `${n}`;
    }

    function updateCurrentVersion() {
        const el = document.getElementById('platUpdCurrent');
        if (!el) return;
        if (!currentVersion) {
            el.textContent = '';
            return;
        }
        const template = t('plat_upd_current');
        el.textContent = template.includes('%s') ? template.replace('%s', currentVersion) : `v${currentVersion}`;
    }

    function statusPill(status) {
        const cls = status === 'released' ? 'success'
            : (status === 'scheduled' ? 'neutral' : (status === 'rolled_back' ? 'failed' : 'neutral'));
        return `<span class="plat-gov-pill plat-gov-pill--${cls}">${esc(label(STATUS_I18N, status))}</span>`;
    }

    function renderKpis(stats) {
        document.getElementById('platUpdKpiTotal').textContent = String(stats?.total ?? 0);
        document.getElementById('platUpdKpiReleased').textContent = String(stats?.released ?? 0);
        document.getElementById('platUpdKpiScheduled').textContent = String(stats?.scheduled ?? 0);
        document.getElementById('platUpdKpiDraft').textContent = String(stats?.draft ?? 0);
        document.getElementById('platUpdKpiMigrations').textContent = String(stats?.migrations ?? 0);
        updateCount(stats?.total ?? 0);
    }

    function filterReleases() {
        const q = (document.getElementById('platUpdSearch')?.value || '').trim().toLowerCase();
        const type = document.getElementById('platUpdTypeFilter')?.value || '';
        const status = document.getElementById('platUpdStatusFilter')?.value || '';

        return releases.filter((r) => {
            if (type && r.release_type !== type) return false;
            if (status && r.status !== status) return false;
            if (q) {
                const hay = `${r.version} ${r.title} ${r.summary} ${r.migration_version || ''}`.toLowerCase();
                if (!hay.includes(q)) return false;
            }
            return true;
        });
    }

    function renderReleases() {
        const body = document.getElementById('platUpdBody');
        const list = filterReleases();

        if (!list.length) {
            body.innerHTML = `<tr><td colspan="7" class="plat-gov-muted">${esc(t('plat_no_data'))}</td></tr>`;
            return;
        }

        body.innerHTML = list.map((r) => {
            const maint = Number(r.requires_maintenance) === 1
                ? `<span class="material-icons-round plat-upd-version__maint" title="${esc(t('plat_upd_field_maintenance'))}">build</span>`
                : '';
            const actions = [`<button type="button" class="plat-upd-btn" data-view="${r.id}">${esc(t('plat_upd_view'))}</button>`];

            if (updCfg.canManage) {
                if (r.status === 'draft' || r.status === 'scheduled') {
                    actions.push(`<button type="button" class="plat-upd-btn" data-edit="${r.id}">${esc(t('plat_upd_edit'))}</button>`);
                    actions.push(`<button type="button" class="plat-upd-btn plat-upd-btn--primary" data-publish="${r.id}">${esc(t('plat_upd_publish'))}</button>`);
                }
                if (r.status === 'draft') {
                    actions.push(`<button type="button" class="plat-upd-btn plat-upd-btn--danger" data-delete="${r.id}">${esc(t('plat_upd_delete'))}</button>`);
                }
            }

            return `<tr>
                <td>
                    <span class="plat-upd-version">${maint}${esc(r.version)}</span>
                </td>
                <td>
                    <strong>${esc(r.title)}</strong>
                    <span class="plat-upd-summary">${esc(r.summary || '')}</span>
                </td>
                <td>${esc(label(TYPE_I18N, r.release_type))}</td>
                <td>${statusPill(r.status)}</td>
                <td><code>${esc(r.migration_version || '—')}</code></td>
                <td>${esc(fmt(r.published_at))}</td>
                <td><div class="plat-upd-actions">${actions.join('')}</div></td>
            </tr>`;
        }).join('');

        body.querySelectorAll('[data-view]').forEach((btn) => {
            btn.addEventListener('click', () => viewRelease(parseInt(btn.dataset.view, 10)));
        });
        body.querySelectorAll('[data-edit]').forEach((btn) => {
            btn.addEventListener('click', () => openEdit(parseInt(btn.dataset.edit, 10)));
        });
        body.querySelectorAll('[data-publish]').forEach((btn) => {
            btn.addEventListener('click', () => publishRelease(parseInt(btn.dataset.publish, 10)));
        });
        body.querySelectorAll('[data-delete]').forEach((btn) => {
            btn.addEventListener('click', () => deleteRelease(parseInt(btn.dataset.delete, 10)));
        });
    }

    function renderMigrations() {
        const body = document.getElementById('platUpdMigBody');
        if (!migrations.length) {
            body.innerHTML = `<tr><td colspan="4" class="plat-gov-muted">${esc(t('plat_no_data'))}</td></tr>`;
            return;
        }

        body.innerHTML = migrations.map((m) => {
            const relStatus = m.release_status ? statusPill(m.release_status) : '—';
            return `<tr>
                <td><code>${esc(m.version)}</code></td>
                <td>${esc(fmt(m.applied_at))}</td>
                <td>${esc(m.release_version || '—')}</td>
                <td>${m.release_status ? relStatus : '—'}</td>
            </tr>`;
        }).join('');
    }

    function changelogHtml(text) {
        if (!text) return `<p class="plat-gov-muted">${esc(t('plat_no_data'))}</p>`;
        const lines = String(text).split('\n').filter(Boolean);
        const items = lines.map((line) => {
            const cleaned = line.replace(/^[-*]\s*/, '');
            return `<li>${esc(cleaned)}</li>`;
        }).join('');
        return `<ul class="plat-upd-changelog">${items}</ul>`;
    }

    function viewRelease(id) {
        const r = releases.find((x) => x.id === id);
        if (!r) return;

        document.getElementById('platUpdViewTitle').textContent = `v${r.version} — ${r.title}`;
        document.getElementById('platUpdViewBody').innerHTML = `
            <div class="plat-upd-view-meta">
                ${statusPill(r.status)}
                <span class="plat-gov-pill plat-gov-pill--neutral">${esc(label(TYPE_I18N, r.release_type))}</span>
                ${Number(r.requires_maintenance) === 1 ? `<span class="plat-gov-pill plat-gov-pill--failed">${esc(t('plat_upd_field_maintenance'))}</span>` : ''}
            </div>
            <p class="plat-upd-view-summary">${esc(r.summary || '')}</p>
            ${r.migration_version ? `<p><strong>${esc(t('plat_upd_col_migration'))}:</strong> <code>${esc(r.migration_version)}</code></p>` : ''}
            <p><strong>${esc(t('plat_upd_field_changelog'))}</strong></p>
            ${changelogHtml(r.changelog)}
            <p class="plat-gov-muted" style="margin-top:12px;font-size:0.8rem">
                ${esc(t('plat_upd_col_published'))}: ${esc(fmt(r.published_at))}
                ${r.released_by_name ? ` · ${esc(r.released_by_name)}` : ''}
            </p>`;

        const foot = document.getElementById('platUpdViewFoot');
        foot.innerHTML = '';
        if (updCfg.canManage && (r.status === 'draft' || r.status === 'scheduled')) {
            const pub = document.createElement('button');
            pub.type = 'button';
            pub.className = 'plat-upd-dialog__submit';
            pub.textContent = t('plat_upd_publish');
            pub.addEventListener('click', () => {
                document.getElementById('platUpdViewDialog').close();
                publishRelease(id);
            });
            foot.appendChild(pub);
        }

        document.getElementById('platUpdViewDialog').showModal();
    }

    function openCreate() {
        document.getElementById('platUpdFormTitle').textContent = t('plat_upd_create_title');
        document.getElementById('platUpdEditId').value = '';
        document.getElementById('platUpdVersion').value = '';
        document.getElementById('platUpdTitle').value = '';
        document.getElementById('platUpdSummary').value = '';
        document.getElementById('platUpdChangelog').value = '';
        document.getElementById('platUpdType').value = 'minor';
        document.getElementById('platUpdStatus').value = 'draft';
        document.getElementById('platUpdMigration').value = '';
        document.getElementById('platUpdMaintenance').checked = false;
        document.getElementById('platUpdFormDialog')?.showModal();
    }

    function openEdit(id) {
        const r = releases.find((x) => x.id === id);
        if (!r) return;

        document.getElementById('platUpdFormTitle').textContent = t('plat_upd_edit_title');
        document.getElementById('platUpdEditId').value = String(id);
        document.getElementById('platUpdVersion').value = r.version || '';
        document.getElementById('platUpdTitle').value = r.title || '';
        document.getElementById('platUpdSummary').value = r.summary || '';
        document.getElementById('platUpdChangelog').value = r.changelog || '';
        document.getElementById('platUpdType').value = r.release_type || 'minor';
        document.getElementById('platUpdStatus').value = r.status === 'scheduled' ? 'scheduled' : 'draft';
        document.getElementById('platUpdMigration').value = r.migration_version || '';
        document.getElementById('platUpdMaintenance').checked = Number(r.requires_maintenance) === 1;
        document.getElementById('platUpdFormDialog')?.showModal();
    }

    async function saveRelease(e) {
        e.preventDefault();
        const editId = document.getElementById('platUpdEditId')?.value;
        const body = {
            version: document.getElementById('platUpdVersion')?.value?.trim(),
            title: document.getElementById('platUpdTitle')?.value?.trim(),
            summary: document.getElementById('platUpdSummary')?.value?.trim(),
            changelog: document.getElementById('platUpdChangelog')?.value?.trim(),
            release_type: document.getElementById('platUpdType')?.value,
            status: document.getElementById('platUpdStatus')?.value,
            migration_version: document.getElementById('platUpdMigration')?.value?.trim(),
            requires_maintenance: document.getElementById('platUpdMaintenance')?.checked,
        };

        try {
            const res = editId
                ? await apiPut(`updates/${editId}`, body)
                : await apiPost('updates', body);
            if (res.status !== 'success') throw new Error(res.message || t('action_error'));
            document.getElementById('platUpdFormDialog')?.close();
            showSuccess(t('plat_upd_create_success'));
            await loadDashboard();
        } catch (err) {
            showError(err.message || t('action_error'));
        }
    }

    async function publishRelease(id) {
        if (!confirm(t('plat_upd_confirm_publish'))) return;
        try {
            const res = await apiPost(`updates/${id}/publish`, {});
            if (res.status !== 'success') throw new Error(res.message || t('action_error'));
            showSuccess(t('plat_upd_publish_success'));
            await loadDashboard();
        } catch (e) {
            showError(e.message || t('action_error'));
        }
    }

    async function deleteRelease(id) {
        if (!confirm(t('plat_upd_confirm_delete'))) return;
        try {
            const res = await apiDelete(`updates/${id}`);
            if (res.status !== 'success') throw new Error(res.message || t('action_error'));
            showSuccess(t('action_success'));
            await loadDashboard();
        } catch (e) {
            showError(e.message || t('action_error'));
        }
    }

    async function loadDashboard() {
        setKpiLoading(true);
        hideError();
        try {
            const res = await apiGet('updates/dashboard');
            if (res.status !== 'success' || !res.data) {
                throw new Error(res.message || t('plat_upd_load_error'));
            }
            releases = res.data.releases || [];
            migrations = res.data.migrations || [];
            currentVersion = res.data.current_version || null;
            renderKpis(res.data.stats || {});
            updateCurrentVersion();
            renderReleases();
            renderMigrations();
            setLastUpdated();
        } catch (e) {
            console.error(e);
            showError(e.message || t('load_error'));
        } finally {
            setKpiLoading(false);
        }
    }

    function switchTab(tab) {
        document.querySelectorAll('.plat-upd-tab').forEach((el) => {
            const active = el.dataset.tab === tab;
            el.classList.toggle('is-active', active);
            el.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        document.getElementById('platUpdReleasesPanel').hidden = tab !== 'releases';
        document.getElementById('platUpdMigrationsPanel').hidden = tab !== 'migrations';
    }

    function initDialog() {
        document.getElementById('platUpdCreateOpen')?.addEventListener('click', openCreate);
        document.getElementById('platUpdFormClose')?.addEventListener('click', () => {
            document.getElementById('platUpdFormDialog')?.close();
        });
        document.getElementById('platUpdFormCancel')?.addEventListener('click', () => {
            document.getElementById('platUpdFormDialog')?.close();
        });
        document.getElementById('platUpdForm')?.addEventListener('submit', saveRelease);
        document.getElementById('platUpdViewClose')?.addEventListener('click', () => {
            document.getElementById('platUpdViewDialog')?.close();
        });
    }

    function initTabs() {
        document.querySelectorAll('.plat-upd-tab').forEach((btn) => {
            btn.addEventListener('click', () => switchTab(btn.dataset.tab));
        });
    }

    function initFilters() {
        const rerender = () => renderReleases();
        document.getElementById('platUpdSearch')?.addEventListener('input', () => {
            clearTimeout(debounce);
            debounce = setTimeout(rerender, 180);
        });
        document.getElementById('platUpdTypeFilter')?.addEventListener('change', rerender);
        document.getElementById('platUpdStatusFilter')?.addEventListener('change', rerender);
    }

    document.addEventListener('DOMContentLoaded', () => {
        initTabs();
        initFilters();
        initDialog();
        loadDashboard();
    });
    document.addEventListener('plat:refresh', loadDashboard);
})();
