/**
 * Warehouse Portal — Help & Support Center
 */
document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('whHlpRoot');
    if (!root) return;

    const { t, esc, showError, hideError } = WarehouseUI;
    const csrf = window.WH_PAGE?.csrfToken || '';
    const CACHE_DB = 'warehouse_help_cache';
    const CACHE_STORE = 'hub';
    const CACHE_VER = 1;

    const state = { data: null, tipIndex: 0, searchTimer: null, searchResults: [] };

    const els = {
        loading: document.getElementById('whHlpLoading'),
        root,
        offlineBadge: document.getElementById('whHlpOfflineBadge'),
        toast: document.getElementById('whHlpToast'),
        modal: document.getElementById('whHlpArticleModal'),
        articleBody: document.getElementById('whHlpArticleBody'),
        breadcrumb: document.getElementById('whHlpBreadcrumb'),
    };

    function toast(msg, ok = true) {
        if (!els.toast) return;
        els.toast.textContent = msg;
        els.toast.className = `wh-hlp-toast show${ok ? '' : ' wh-hlp-toast--error'}`;
        clearTimeout(els.toast._t);
        els.toast._t = setTimeout(() => els.toast.classList.remove('show'), 3200);
    }

    function fmtDuration(sec) {
        if (!sec) return '';
        const m = Math.floor(sec / 60);
        const s = sec % 60;
        return `${m}:${String(s).padStart(2, '0')}`;
    }

    function fmtDate(v) {
        if (!v) return '—';
        try { return AdminAPI.formatDate(v, { dateStyle: 'medium' }); } catch { return v; }
    }

    function statusBadge(status) {
        const ok = ['online', 'open'].includes(status);
        return `<span class="wh-hlp-status wh-hlp-status--${ok ? 'ok' : 'warn'}">${esc(status)}</span>`;
    }

    async function openHelpDb() {
        return new Promise((resolve, reject) => {
            const req = indexedDB.open(CACHE_DB, CACHE_VER);
            req.onupgradeneeded = () => {
                const db = req.result;
                if (!db.objectStoreNames.contains(CACHE_STORE)) {
                    db.createObjectStore(CACHE_STORE);
                }
            };
            req.onsuccess = () => resolve(req.result);
            req.onerror = () => reject(req.error);
        });
    }

    async function cacheHub(data) {
        try {
            const db = await openHelpDb();
            await new Promise((resolve, reject) => {
                const tx = db.transaction(CACHE_STORE, 'readwrite');
                tx.objectStore(CACHE_STORE).put({ saved_at: Date.now(), data }, 'hub');
                tx.oncomplete = () => resolve();
                tx.onerror = () => reject(tx.error);
            });
        } catch (_) { /* quota */ }
    }

    async function loadCachedHub() {
        try {
            const db = await openHelpDb();
            return new Promise((resolve) => {
                const tx = db.transaction(CACHE_STORE, 'readonly');
                const req = tx.objectStore(CACHE_STORE).get('hub');
                req.onsuccess = () => resolve(req.result?.data || null);
                req.onerror = () => resolve(null);
            });
        } catch {
            return null;
        }
    }

    function renderHub(d) {
        const tips = d.tips || [];
        const tip = tips[state.tipIndex % Math.max(1, tips.length)] || '';
        const status = d.system_status || {};
        const quickLinks = [
            ['receiving/receive_stock.php', 'move_to_inbox', 'Receiving'],
            ['dispatch/dispatch_orders.php', 'local_shipping', 'Dispatch'],
            ['inventory/barcode_scanner.php', 'qr_code_scanner', 'Scanner'],
            ['reports/inventory_report.php', 'assessment', 'Reports'],
            ['profile.php', 'account_circle', 'Profile'],
            ['settings.php', 'settings', 'Settings'],
        ];

        root.innerHTML = `
        <header class="wh-hlp-hero">
            <div class="wh-hlp-hero__text">
                <h2>${esc(t('wh_hlp_title'))}</h2>
                <p>${esc(t('wh_hlp_subtitle'))}</p>
            </div>
            <div class="wh-hlp-search-wrap">
                <span class="material-icons-round">search</span>
                <input type="search" id="whHlpSearch" class="wh-hlp-search" placeholder="${esc(t('wh_hlp_search_ph'))}" autocomplete="off">
                <div class="wh-hlp-search-results" id="whHlpSearchResults" hidden></div>
            </div>
            ${tip ? `<p class="wh-hlp-tip"><span class="material-icons-round">lightbulb</span><span id="whHlpTip">${esc(tip)}</span></p>` : ''}
        </header>

        <section class="wh-hlp-quick">
            <h3>${esc(t('wh_hlp_quick_actions'))}</h3>
            <div class="wh-hlp-quick__grid">
                ${(d.guides || []).slice(0, 6).map((g) =>
                    `<button type="button" class="wh-hlp-quick-card" data-article="${esc(g.slug)}">
                        <span class="material-icons-round">menu_book</span>
                        <strong>${esc(g.title)}</strong>
                        <span>${esc(g.summary || '')}</span>
                    </button>`
                ).join('')}
            </div>
        </section>

        <div class="wh-hlp-grid">
            <section class="wh-hlp-panel">
                <h3>${esc(t('wh_hlp_categories'))}</h3>
                <div class="wh-hlp-cats">${(d.categories || []).map((c) =>
                    `<button type="button" class="wh-hlp-cat" data-cat="${esc(c.slug)}">
                        <span class="material-icons-round">${esc(c.icon)}</span><span>${esc(c.name)}</span>
                    </button>`
                ).join('')}</div>
            </section>

            <section class="wh-hlp-panel">
                <h3>${esc(t('wh_hlp_faq'))}</h3>
                <div class="wh-hlp-faq" id="whHlpFaq">${(d.faq || []).map((f, i) =>
                    `<details class="wh-hlp-faq__item"${i === 0 ? ' open' : ''}>
                        <summary>${esc(f.question)}</summary>
                        <p>${esc(f.answer)}</p>
                    </details>`
                ).join('')}</div>
            </section>

            <section class="wh-hlp-panel wh-hlp-panel--wide">
                <h3>${esc(t('wh_hlp_videos'))}</h3>
                <div class="wh-hlp-videos">${(d.videos || []).map((v) => {
                    const embed = v.video_type === 'youtube' ? v.video_url : v.video_url;
                    return `<article class="wh-hlp-video">
                        <div class="wh-hlp-video__frame"><iframe src="${esc(embed)}" title="${esc(v.title)}" loading="lazy" allowfullscreen></iframe></div>
                        <h4>${esc(v.title)}</h4>
                        <p>${esc(v.description || '')}${v.duration_seconds ? ` · ${fmtDuration(v.duration_seconds)}` : ''}</p>
                    </article>`;
                }).join('') || `<p class="wh-muted">${esc(t('wh_hlp_search_empty'))}</p>`}</div>
            </section>

            <section class="wh-hlp-panel">
                <h3>${esc(t('wh_hlp_manuals'))}</h3>
                <ul class="wh-hlp-manuals">${(d.manuals || []).map((m) =>
                    `<li><button type="button" class="wh-hlp-manual-link" data-manual="${esc(m.slug)}">
                        <span class="material-icons-round">picture_as_pdf</span>
                        <span>${esc(m.title)}</span>
                    </button></li>`
                ).join('')}</ul>
            </section>

            <section class="wh-hlp-panel">
                <h3>${esc(t('wh_hlp_status'))}</h3>
                <dl class="wh-hlp-status-list">
                    <div><dt>Database</dt><dd>${statusBadge(status.database || 'unknown')}</dd></div>
                    <div><dt>API</dt><dd>${statusBadge(status.api || 'online')}</dd></div>
                    <div><dt>Notifications</dt><dd>${statusBadge(status.notifications || 'unknown')}</dd></div>
                    <div><dt>Offline</dt><dd>${statusBadge(status.offline_mode ? 'online' : 'offline')}</dd></div>
                    <div><dt>Sync pending</dt><dd>${Number(status.sync_pending || 0)}</dd></div>
                    <div><dt>Last sync</dt><dd>${esc(fmtDate(status.last_sync))}</dd></div>
                </dl>
            </section>

            <section class="wh-hlp-panel">
                <h3>${esc(t('wh_hlp_updates'))}</h3>
                <ul class="wh-hlp-updates">${(d.updates || []).map((u) =>
                    `<li><span class="wh-hlp-update-type">${esc(u.update_type)}</span>
                        <strong>v${esc(u.version)} — ${esc(u.title)}</strong>
                        <p>${esc(u.body)}</p>
                        <small>${esc(fmtDate(u.published_at))}</small></li>`
                ).join('')}</ul>
            </section>
        </div>

        <div class="wh-hlp-forms">
            <section class="wh-hlp-panel">
                <h3>${esc(t('wh_hlp_contact'))}</h3>
                <form id="whHlpSupportForm" class="wh-hlp-form">
                    <input type="hidden" name="ticket_type" value="support">
                    <input type="hidden" name="csrf_token" value="${esc(csrf)}">
                    <label>${esc(t('wh_hlp_name'))}<input name="name" value="${esc(d.user?.name || '')}" required></label>
                    <label>${esc(t('wh_hlp_email'))}<input type="email" name="email" value="${esc(d.user?.email || '')}" required></label>
                    <label>${esc(t('wh_hlp_subject'))}<input name="subject" required minlength="3"></label>
                    <label>${esc(t('wh_hlp_category'))}
                        <select name="category">${(d.categories || []).map((c) => `<option value="${esc(c.slug)}">${esc(c.name)}</option>`).join('')}</select>
                    </label>
                    <label>${esc(t('wh_hlp_priority'))}
                        <select name="priority"><option value="low">Low</option><option value="normal" selected>Normal</option><option value="high">High</option><option value="critical">Critical</option></select>
                    </label>
                    <label>${esc(t('wh_hlp_description'))}<textarea name="description" rows="4" required minlength="10"></textarea></label>
                    <label>${esc(t('wh_hlp_attachment'))}<input type="file" name="attachment" accept=".jpg,.jpeg,.png,.webp,.pdf,.txt"></label>
                    <button type="submit" class="wh-btn">${esc(t('wh_hlp_submit'))}</button>
                </form>
            </section>

            <section class="wh-hlp-panel">
                <h3>${esc(t('wh_hlp_report'))}</h3>
                <form id="whHlpProblemForm" class="wh-hlp-form">
                    <input type="hidden" name="ticket_type" value="problem">
                    <input type="hidden" name="csrf_token" value="${esc(csrf)}">
                    <label>${esc(t('wh_hlp_problem_type'))}
                        <select name="problem_type">
                            <option value="system_error">System error</option>
                            <option value="inventory">Inventory error</option>
                            <option value="transfer">Transfer issue</option>
                            <option value="receiving">Receiving issue</option>
                            <option value="dispatch">Dispatch issue</option>
                            <option value="barcode">Barcode problem</option>
                            <option value="sync">Synchronization</option>
                            <option value="other">Other</option>
                        </select>
                    </label>
                    <label>${esc(t('wh_hlp_subject'))}<input name="subject" required></label>
                    <label>${esc(t('wh_hlp_description'))}<textarea name="description" rows="4" required minlength="10"></textarea></label>
                    <button type="submit" class="wh-btn wh-btn--warn">${esc(t('wh_hlp_submit'))}</button>
                </form>
            </section>

            <section class="wh-hlp-panel">
                <h3>${esc(t('wh_hlp_my_tickets'))}</h3>
                <ul class="wh-hlp-tickets">${(d.tickets || []).map((tk) =>
                    `<li><strong>${esc(tk.ticket_number)}</strong> — ${esc(tk.subject)}
                        <span class="wh-hlp-ticket-meta">${esc(tk.status)} · ${esc(fmtDate(tk.created_at))}</span></li>`
                ).join('') || `<li class="wh-muted">—</li>`}</ul>
            </section>
        </div>

        <nav class="wh-hlp-shortcuts" aria-label="Quick links">
            ${quickLinks.map(([href, icon, label]) =>
                `<a class="wh-hlp-shortcut" href="${esc(href)}"><span class="material-icons-round">${icon}</span>${esc(label)}</a>`
            ).join('')}
        </nav>`;

        bindEvents(d);
        if (tips.length > 1) {
            setInterval(() => {
                state.tipIndex += 1;
                const el = document.getElementById('whHlpTip');
                if (el) el.textContent = tips[state.tipIndex % tips.length];
            }, 8000);
        }
    }

    function bindEvents(d) {
        document.getElementById('whHlpSearch')?.addEventListener('input', (e) => {
            clearTimeout(state.searchTimer);
            state.searchTimer = setTimeout(() => runSearch(e.target.value.trim()), 280);
        });

        root.querySelectorAll('[data-article]').forEach((btn) => {
            btn.addEventListener('click', () => openArticle(btn.dataset.article));
        });
        root.querySelectorAll('[data-manual]').forEach((btn) => {
            btn.addEventListener('click', () => downloadManual(btn.dataset.manual));
        });
        root.querySelectorAll('[data-cat]').forEach((btn) => {
            btn.addEventListener('click', () => filterFaq(btn.dataset.cat));
        });

        document.getElementById('whHlpSupportForm')?.addEventListener('submit', (e) => submitTicket(e, 'support'));
        document.getElementById('whHlpProblemForm')?.addEventListener('submit', (e) => submitTicket(e, 'problem'));

        els.modal?.querySelectorAll('[data-close-modal]').forEach((el) => {
            el.addEventListener('click', closeModal);
        });
    }

    function filterFaq(catSlug) {
        const items = (state.data?.faq || []).filter((f) => !catSlug || f.category_slug === catSlug);
        const wrap = document.getElementById('whHlpFaq');
        if (!wrap) return;
        wrap.innerHTML = items.map((f, i) =>
            `<details class="wh-hlp-faq__item"${i === 0 ? ' open' : ''}><summary>${esc(f.question)}</summary><p>${esc(f.answer)}</p></details>`
        ).join('') || `<p class="wh-muted">${esc(t('wh_hlp_search_empty'))}</p>`;
    }

    async function runSearch(q) {
        const box = document.getElementById('whHlpSearchResults');
        if (!box) return;
        if (!q) { box.hidden = true; return; }
        try {
            const res = await AdminAPI.searchWarehouseHelp(q);
            const rows = res.data?.results || [];
            if (!rows.length) {
                box.innerHTML = `<p class="wh-muted">${esc(t('wh_hlp_search_empty'))}</p>`;
            } else {
                box.innerHTML = rows.map((r) =>
                    `<button type="button" class="wh-hlp-search-hit" data-article="${esc(r.slug || '')}" data-faq="${r.result_type === 'faq' ? r.id : ''}">
                        <span class="material-icons-round">${r.result_type === 'faq' ? 'help' : 'article'}</span>
                        <span><strong>${esc(r.title)}</strong>${r.summary ? `<small>${esc(r.summary)}</small>` : ''}</span>
                    </button>`
                ).join('');
                box.querySelectorAll('.wh-hlp-search-hit').forEach((hit) => {
                    hit.addEventListener('click', () => {
                        if (hit.dataset.article) openArticle(hit.dataset.article);
                        box.hidden = true;
                    });
                });
            }
            box.hidden = false;
        } catch {
            box.hidden = true;
        }
    }

    async function openArticle(slug) {
        if (!slug) return;
        try {
            const res = await AdminAPI.getWarehouseHelpArticle(slug);
            if (res.status !== 'success') throw new Error();
            const a = res.data;
            if (els.breadcrumb) els.breadcrumb.innerHTML = `<span>${esc(t('wh_hlp_breadcrumb_home'))}</span> / <span>${esc(a.title)}</span>`;
            if (els.articleBody) els.articleBody.innerHTML = `<h2>${esc(a.title)}</h2>${a.body || ''}`;
            if (els.modal) els.modal.hidden = false;
        } catch {
            toast(t('load_error'), false);
        }
    }

    function closeModal() {
        if (els.modal) els.modal.hidden = true;
    }

    async function downloadManual(slug) {
        try {
            const res = await AdminAPI.getWarehouseHelpManual(slug);
            if (res.status !== 'success') throw new Error();
            const w = window.open('', '_blank');
            if (w) {
                w.document.write(`<!DOCTYPE html><html><head><meta charset="utf-8"><title>${res.data.title}</title></head><body>${res.data.html}</body></html>`);
                w.document.close();
                w.print();
            }
        } catch {
            toast(t('load_error'), false);
        }
    }

    async function submitTicket(e, type) {
        e.preventDefault();
        const form = e.target;
        const fd = new FormData(form);
        fd.set('ticket_type', type);
        fd.set('warehouse_id', String(window.WH_PAGE?.warehouseId || ''));
        try {
            const res = await AdminAPI.createWarehouseHelpTicket(fd, csrf);
            if (res.status === 'success') {
                toast(t('wh_hlp_ticket_sent'));
                form.reset();
                await load(true);
            } else toast(res.message || t('wh_hlp_ticket_error'), false);
        } catch {
            toast(t('connection_error'), false);
        }
    }

    async function load(silent = false) {
        if (!silent) { els.loading.hidden = false; root.hidden = true; }
        hideError();
        if (els.offlineBadge) els.offlineBadge.hidden = true;
        try {
            const res = await AdminAPI.getWarehouseHelp();
            if (res.status !== 'success') throw new Error(res.message || t('load_error'));
            state.data = res.data;
            renderHub(state.data);
            root.hidden = false;
            await cacheHub(state.data);
        } catch (err) {
            const cached = await loadCachedHub();
            if (cached) {
                state.data = cached;
                renderHub(cached);
                root.hidden = false;
                if (els.offlineBadge) els.offlineBadge.hidden = false;
            } else showError(err.message || t('load_error'));
        } finally {
            els.loading.hidden = true;
        }
    }

    window.addEventListener('online', () => load(true));
    document.addEventListener('wh:refresh', () => load(true));
    load();
});
