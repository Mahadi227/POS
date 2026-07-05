(function () {
    'use strict';

    const cfg = window.PLATFORM_CONFIG || {};
    const { apiGet, apiPost, apiPut, t, setLastUpdated } = window.PlatformAPI || {};
    const lang = (cfg.locale || 'en').toLowerCase().startsWith('fr') ? 'fr' : 'en';

    let debounceTimer = null;
    let categories = [];
    let activeArticleId = 0;
    let activeCategorySlug = '';

    const TYPE_I18N = {
        article: 'plat_kb_type_article',
        guide: 'plat_kb_type_guide',
        faq: 'plat_kb_type_faq',
    };

    const AUDIENCE_I18N = {
        tenant: 'plat_kb_audience_tenant',
        support: 'plat_kb_audience_support',
        public: 'plat_kb_audience_public',
    };

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s ?? '';
        return d.innerHTML;
    }

    function label(map, key) {
        const i18n = map[key];
        return i18n ? t(i18n) : (key || '—');
    }

    function localized(article, field) {
        const key = lang === 'fr' ? `${field}_fr` : `${field}_en`;
        const fallback = `${field}_en`;
        return article[key] || article[fallback] || '';
    }

    function catName(cat) {
        return lang === 'fr' ? (cat.name_fr || cat.name_en) : (cat.name_en || cat.name_fr);
    }

    function formatDateTime(value) {
        if (!value) return '—';
        try {
            return new Date(value).toLocaleString(cfg.locale || undefined);
        } catch (e) {
            return '—';
        }
    }

    function showError(msg) {
        const el = document.getElementById('platKbError');
        const text = document.getElementById('platKbErrorText');
        if (!el || !text) return;
        text.textContent = msg || t('plat_kb_load_error');
        el.hidden = false;
    }

    function hideError() {
        const el = document.getElementById('platKbError');
        if (el) el.hidden = true;
    }

    function showAlert(msg) {
        const el = document.getElementById('platKbAlert');
        if (!el) return;
        el.textContent = msg;
        el.hidden = false;
        clearTimeout(showAlert._timer);
        showAlert._timer = setTimeout(() => { el.hidden = true; }, 3500);
    }

    function setKpiLoading(loading) {
        document.querySelectorAll('#platKbKpiGrid .plat-kpi-card').forEach((card) => {
            card.classList.toggle('is-loading', loading);
        });
    }

    function updateCount(n) {
        const el = document.getElementById('platKbCount');
        if (!el) return;
        const template = t('plat_kb_count');
        el.textContent = template.includes('%d') ? template.replace('%d', String(n)) : `${n}`;
    }

    function updateClearBtn() {
        const search = document.getElementById('platKbSearch')?.value || '';
        const cat = document.getElementById('platKbCategoryFilter')?.value || '';
        const aud = document.getElementById('platKbAudienceFilter')?.value || '';
        const pub = document.getElementById('platKbPublishedFilter')?.value || '';
        const btn = document.getElementById('platKbClearFilters');
        if (btn) btn.hidden = !search && !cat && !aud && !pub && !activeCategorySlug;
    }

    function renderKpis(stats) {
        document.getElementById('platKbKpiCats').textContent = String(stats.categories ?? 0);
        document.getElementById('platKbKpiArticles').textContent = String(stats.articles ?? 0);
        document.getElementById('platKbKpiPublished').textContent = String(stats.published ?? 0);
        document.getElementById('platKbKpiDrafts').textContent = String(stats.drafts ?? 0);
    }

    function populateCategoryFilters() {
        const filter = document.getElementById('platKbCategoryFilter');
        const formCat = document.getElementById('platKbFormCategory');
        const opts = [`<option value="">${esc(t('plat_kb_filter_all_categories'))}</option>`];
        const formOpts = [];

        categories.forEach((cat) => {
            const name = esc(catName(cat));
            opts.push(`<option value="${esc(cat.slug)}">${name}</option>`);
            formOpts.push(`<option value="${cat.id}">${name}</option>`);
        });

        if (filter) filter.innerHTML = opts.join('');
        if (formCat) formCat.innerHTML = formOpts.join('');
    }

    function renderCategoryChips() {
        const panel = document.getElementById('platKbCatsPanel');
        const el = document.getElementById('platKbCats');
        if (!panel || !el) return;

        if (!categories.length) {
            panel.hidden = true;
            return;
        }

        panel.hidden = false;
        el.innerHTML = categories.map((cat) => {
            const active = activeCategorySlug === cat.slug ? ' is-active' : '';
            return `<button type="button" class="plat-kb-cat-chip${active}" data-cat="${esc(cat.slug)}">
                <span class="material-icons-round" aria-hidden="true">${esc(cat.icon || 'folder')}</span>
                ${esc(catName(cat))}
                <span class="plat-kb-muted">(${esc(String(cat.article_count ?? 0))})</span>
            </button>`;
        }).join('');

        el.querySelectorAll('[data-cat]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const slug = btn.getAttribute('data-cat') || '';
                activeCategorySlug = activeCategorySlug === slug ? '' : slug;
                const filter = document.getElementById('platKbCategoryFilter');
                if (filter) filter.value = activeCategorySlug;
                renderCategoryChips();
                updateClearBtn();
                loadArticles();
            });
        });
    }

    function renderArticles(rows, total) {
        const grid = document.getElementById('platKbGrid');
        const empty = document.getElementById('platKbEmpty');
        if (!grid) return;

        updateCount(total ?? rows.length);

        if (!rows?.length) {
            grid.innerHTML = '';
            if (empty) empty.hidden = false;
            return;
        }

        if (empty) empty.hidden = true;
        grid.innerHTML = rows.map((article) => {
            const title = localized(article, 'title');
            const summary = localized(article, 'summary');
            const catLabel = lang === 'fr'
                ? (article.category_name_fr || article.category_name_en)
                : (article.category_name_en || article.category_name_fr);
            const pubCls = Number(article.is_published) === 1 ? 'plat-kb-pill--published' : 'plat-kb-pill--draft';
            const pubLabel = Number(article.is_published) === 1 ? t('plat_kb_status_published') : t('plat_kb_status_draft');
            const icon = article.category_icon || 'article';

            return `<article class="plat-kb-card">
                <div class="plat-kb-card__head">
                    <div class="plat-kb-card__icon" aria-hidden="true">
                        <span class="material-icons-round">${esc(icon)}</span>
                    </div>
                    <div>
                        <h3 class="plat-kb-card__title">${esc(title)}</h3>
                        <p class="plat-kb-card__summary">${esc(summary || '—')}</p>
                    </div>
                </div>
                <div class="plat-kb-card__meta">
                    <span class="plat-kb-pill">${esc(catLabel || '')}</span>
                    <span class="plat-kb-pill">${esc(label(TYPE_I18N, article.article_type))}</span>
                    <span class="plat-kb-pill">${esc(label(AUDIENCE_I18N, article.audience))}</span>
                    <span class="plat-kb-pill ${pubCls}">${esc(pubLabel)}</span>
                </div>
                <div class="plat-kb-card__actions">
                    <button type="button" class="plat-kb-btn" data-view="${esc(String(article.id))}">${esc(t('plat_kb_open'))}</button>
                    <button type="button" class="plat-kb-btn plat-kb-btn--primary" data-edit="${esc(String(article.id))}">${esc(t('plat_kb_edit_title'))}</button>
                </div>
            </article>`;
        }).join('');

        grid.querySelectorAll('[data-view]').forEach((btn) => {
            btn.addEventListener('click', () => openDrawer(Number(btn.getAttribute('data-view'))));
        });
        grid.querySelectorAll('[data-edit]').forEach((btn) => {
            btn.addEventListener('click', () => openEditModal(Number(btn.getAttribute('data-edit'))));
        });
    }

    function articlesQuery() {
        const params = new URLSearchParams();
        const q = document.getElementById('platKbSearch')?.value?.trim();
        const category = document.getElementById('platKbCategoryFilter')?.value || activeCategorySlug;
        const audience = document.getElementById('platKbAudienceFilter')?.value;
        const published = document.getElementById('platKbPublishedFilter')?.value;
        if (q) params.set('q', q);
        if (category) params.set('category', category);
        if (audience) params.set('audience', audience);
        if (published) params.set('published', published);
        params.set('per_page', '100');
        return `knowledge/articles?${params.toString()}`;
    }

    async function loadArticles() {
        hideError();
        try {
            const res = await apiGet(articlesQuery());
            if (res.status !== 'success') throw new Error(res.message || t('plat_kb_load_error'));

            const payload = res.data || {};
            categories = payload.categories || categories;
            populateCategoryFilters();
            renderCategoryChips();
            if (payload.stats) renderKpis(payload.stats);
            renderArticles(payload.articles || [], payload.total);
            setLastUpdated?.();
        } catch (e) {
            showError(e.message || t('load_error'));
        } finally {
            setKpiLoading(false);
        }
    }

    async function openDrawer(id) {
        activeArticleId = id;
        const drawer = document.getElementById('platKbDrawer');
        drawer.hidden = false;

        document.getElementById('platKbDrawerTitle').textContent = t('loading') + '…';
        document.getElementById('platKbDrawerMeta').textContent = '';
        document.getElementById('platKbDrawerBody').innerHTML = `<p class="plat-kb-muted">${esc(t('loading'))}…</p>`;

        try {
            const res = await apiGet(`knowledge/${id}`);
            if (res.status !== 'success') throw new Error(res.message || t('plat_kb_load_error'));

            const article = res.data || {};
            const title = localized(article, 'title');
            const summary = localized(article, 'summary');
            const body = localized(article, 'body');
            const catLabel = lang === 'fr'
                ? (article.category_name_fr || article.category_name_en)
                : (article.category_name_en || article.category_name_fr);

            document.getElementById('platKbDrawerTitle').textContent = title;
            document.getElementById('platKbDrawerMeta').textContent = [
                article.slug,
                catLabel,
                label(TYPE_I18N, article.article_type),
                formatDateTime(article.updated_at),
            ].filter(Boolean).join(' · ');

            document.getElementById('platKbDrawerBody').innerHTML = `
                ${summary ? `<summary>${esc(summary)}</summary>` : ''}
                <div class="plat-kb-article-body">${body}</div>`;

            const pubBtn = document.getElementById('platKbDrawerPublish');
            const isPub = Number(article.is_published) === 1;
            pubBtn.textContent = isPub ? t('plat_kb_unpublish') : t('plat_kb_publish');
            pubBtn.dataset.published = isPub ? '1' : '0';
        } catch (e) {
            showError(e.message || t('load_error'));
            drawer.hidden = true;
        }
    }

    function closeDrawer() {
        document.getElementById('platKbDrawer').hidden = true;
        activeArticleId = 0;
    }

    function openCreateModal() {
        document.getElementById('platKbModalTitle').textContent = t('plat_kb_add_title');
        document.getElementById('platKbForm').reset();
        document.getElementById('platKbFormId').value = '';
        document.getElementById('platKbModal').hidden = false;
    }

    async function openEditModal(id) {
        try {
            const res = await apiGet(`knowledge/${id}`);
            if (res.status !== 'success') throw new Error(res.message || t('plat_kb_load_error'));
            const a = res.data || {};

            document.getElementById('platKbModalTitle').textContent = t('plat_kb_edit_title');
            document.getElementById('platKbFormId').value = String(a.id);
            document.getElementById('platKbFormCategory').value = String(a.category_id || '');
            document.getElementById('platKbFormType').value = a.article_type || 'article';
            document.getElementById('platKbFormAudience').value = a.audience || 'tenant';
            document.getElementById('platKbFormSlug').value = a.slug || '';
            document.getElementById('platKbFormTitleEn').value = a.title_en || '';
            document.getElementById('platKbFormTitleFr').value = a.title_fr || '';
            document.getElementById('platKbFormSummaryEn').value = a.summary_en || '';
            document.getElementById('platKbFormSummaryFr').value = a.summary_fr || '';
            document.getElementById('platKbFormBodyEn').value = a.body_en || '';
            document.getElementById('platKbFormBodyFr').value = a.body_fr || '';
            document.getElementById('platKbFormPublished').checked = Number(a.is_published) === 1;
            document.getElementById('platKbModal').hidden = false;
        } catch (e) {
            showError(e.message || t('load_error'));
        }
    }

    function closeModal() {
        document.getElementById('platKbModal').hidden = true;
    }

    document.getElementById('platKbAddOpen')?.addEventListener('click', openCreateModal);
    document.querySelectorAll('[data-close-modal]').forEach((el) => el.addEventListener('click', closeModal));
    document.querySelectorAll('[data-close-drawer]').forEach((el) => el.addEventListener('click', closeDrawer));

    document.getElementById('platKbDrawerEdit')?.addEventListener('click', () => {
        if (activeArticleId) {
            closeDrawer();
            openEditModal(activeArticleId);
        }
    });

    document.getElementById('platKbDrawerPublish')?.addEventListener('click', async () => {
        if (!activeArticleId) return;
        const btn = document.getElementById('platKbDrawerPublish');
        const publish = btn.dataset.published !== '1';
        try {
            const res = await apiPost(`knowledge/${activeArticleId}/publish`, { is_published: publish });
            if (res.status !== 'success') throw new Error(t('action_error'));
            showAlert(t('action_success'));
            await loadArticles();
            await openDrawer(activeArticleId);
        } catch (e) {
            showError(e.message || t('action_error'));
        }
    });

    document.getElementById('platKbForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = e.target;
        const id = Number(document.getElementById('platKbFormId').value || 0);
        const body = {
            category_id: Number(form.category_id?.value),
            article_type: form.article_type?.value,
            audience: form.audience?.value,
            slug: form.slug?.value?.trim(),
            title_en: form.title_en?.value?.trim(),
            title_fr: form.title_fr?.value?.trim(),
            summary_en: form.summary_en?.value?.trim(),
            summary_fr: form.summary_fr?.value?.trim(),
            body_en: form.body_en?.value?.trim(),
            body_fr: form.body_fr?.value?.trim(),
            is_published: form.is_published?.checked,
        };

        try {
            const res = id > 0
                ? await apiPut(`knowledge/${id}`, body)
                : await apiPost('knowledge/articles', body);
            if (res.status !== 'success') throw new Error(res.message || t('action_error'));
            closeModal();
            showAlert(t('action_success'));
            await loadArticles();
        } catch (err) {
            showError(err.message || t('action_error'));
        }
    });

    document.getElementById('platKbSearch')?.addEventListener('input', () => {
        updateClearBtn();
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(loadArticles, 300);
    });

    ['platKbCategoryFilter', 'platKbAudienceFilter', 'platKbPublishedFilter'].forEach((id) => {
        document.getElementById(id)?.addEventListener('change', () => {
            if (id === 'platKbCategoryFilter') {
                activeCategorySlug = document.getElementById('platKbCategoryFilter').value || '';
                renderCategoryChips();
            }
            updateClearBtn();
            loadArticles();
        });
    });

    document.getElementById('platKbClearFilters')?.addEventListener('click', () => {
        document.getElementById('platKbSearch').value = '';
        document.getElementById('platKbCategoryFilter').value = '';
        document.getElementById('platKbAudienceFilter').value = '';
        document.getElementById('platKbPublishedFilter').value = '';
        activeCategorySlug = '';
        renderCategoryChips();
        updateClearBtn();
        loadArticles();
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeModal();
            closeDrawer();
        }
    });

    document.addEventListener('DOMContentLoaded', () => {
        setKpiLoading(true);
        loadArticles();
    });
    document.addEventListener('plat:refresh', loadArticles);
})();
