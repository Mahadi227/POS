(function () {
    'use strict';

    const cfg = window.PLATFORM_CONFIG || {};
    const { apiGet, t, setLastUpdated } = window.PlatformAPI || {};
    const lang = (cfg.locale || 'en').toLowerCase().startsWith('fr') ? 'fr' : 'en';

    let articles = [];
    let debounce = null;

    const TYPE_I18N = {
        article: 'plat_kb_type_article',
        guide: 'plat_kb_type_guide',
        faq: 'plat_kb_type_faq',
    };

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s ?? '';
        return d.innerHTML;
    }

    function localized(item, field) {
        const key = lang === 'fr' ? `${field}_fr` : `${field}_en`;
        const fallback = `${field}_en`;
        return item[key] || item[fallback] || '';
    }

    function typeLabel(type) {
        const key = TYPE_I18N[type];
        return key ? t(key) : type;
    }

    function showError(msg) {
        const el = document.getElementById('platHelpError');
        const text = document.getElementById('platHelpErrorText');
        if (!el || !text) return;
        text.textContent = msg || t('plat_help_load_error');
        el.hidden = false;
    }

    function hideError() {
        const el = document.getElementById('platHelpError');
        if (el) el.hidden = true;
    }

    function applySearch(query) {
        const q = query.trim().toLowerCase();

        document.querySelectorAll('[data-help-search]').forEach((el) => {
            const hay = (el.getAttribute('data-help-search') || '').toLowerCase();
            const match = !q || hay.includes(q);
            el.classList.toggle('is-hidden', !match);
        });

        document.querySelectorAll('.plat-help-guide-card').forEach((el) => {
            const id = parseInt(el.dataset.id, 10);
            const article = articles.find((a) => Number(a.id) === id);
            if (!article) return;
            const hay = `${localized(article, 'title')} ${localized(article, 'summary')}`.toLowerCase();
            el.classList.toggle('is-hidden', q && !hay.includes(q));
        });
    }

    function renderGuides(list) {
        const wrap = document.getElementById('platHelpGuides');
        if (!wrap) return;

        if (!list.length) {
            wrap.innerHTML = `<p class="plat-help-muted">${esc(t('plat_help_guides_empty'))}</p>`;
            return;
        }

        wrap.innerHTML = list.map((a) => {
            const title = localized(a, 'title');
            const summary = localized(a, 'summary');
            return `<button type="button" class="plat-help-guide-card" data-id="${a.id}">
                <span class="plat-help-guide-card__meta">
                    <strong>${esc(title)}</strong>
                    <span>${esc(summary || t('plat_help_read_guide'))}</span>
                </span>
                <span class="plat-help-guide-type">${esc(typeLabel(a.type || 'article'))}</span>
            </button>`;
        }).join('');

        wrap.querySelectorAll('.plat-help-guide-card').forEach((btn) => {
            btn.addEventListener('click', () => openArticle(parseInt(btn.dataset.id, 10)));
        });
    }

    async function openArticle(id) {
        const dialog = document.getElementById('platHelpArticleDialog');
        const titleEl = document.getElementById('platHelpArticleTitle');
        const bodyEl = document.getElementById('platHelpArticleBody');
        if (!dialog || !titleEl || !bodyEl) return;

        titleEl.textContent = t('loading');
        bodyEl.textContent = '…';
        dialog.showModal();

        try {
            const res = await apiGet(`knowledge/${id}`);
            if (res.status !== 'success' || !res.data) {
                throw new Error(res.message || t('load_error'));
            }
            titleEl.textContent = localized(res.data, 'title');
            bodyEl.textContent = localized(res.data, 'body') || localized(res.data, 'summary') || t('plat_no_data');
        } catch (e) {
            titleEl.textContent = t('load_error');
            bodyEl.textContent = e.message || t('load_error');
        }
    }

    async function loadGuides() {
        hideError();
        try {
            const res = await apiGet('knowledge/articles?published=yes&per_page=30');
            if (res.status !== 'success' || !res.data) {
                throw new Error(res.message || t('plat_help_load_error'));
            }

            const all = res.data.articles || [];
            articles = all.filter((a) => {
                const audience = a.audience || 'tenant';
                return audience === 'support' || audience === 'public';
            });

            if (!articles.length) {
                articles = all.slice(0, 12);
            }

            renderGuides(articles);
            setLastUpdated();
        } catch (e) {
            console.error(e);
            showError(e.message || t('load_error'));
            renderGuides([]);
        }
    }

    function initSearch() {
        const input = document.getElementById('platHelpSearch');
        if (!input) return;
        input.addEventListener('input', () => {
            clearTimeout(debounce);
            debounce = setTimeout(() => applySearch(input.value), 180);
        });
    }

    function initDialog() {
        const dialog = document.getElementById('platHelpArticleDialog');
        document.getElementById('platHelpArticleClose')?.addEventListener('click', () => dialog?.close());
        dialog?.addEventListener('click', (e) => {
            if (e.target === dialog) dialog.close();
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        initSearch();
        initDialog();
        loadGuides();
    });
    document.addEventListener('plat:refresh', loadGuides);
})();
