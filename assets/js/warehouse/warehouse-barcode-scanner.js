/**
 * Warehouse barcode scanner — USB wedge, manual entry, camera lookup with WMS stock details
 */
document.addEventListener('DOMContentLoaded', () => {
    const wedgeInput = document.getElementById('whScanWedgeInput');
    if (!wedgeInput) return;

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv, loadWarehouseOptions } = WarehouseUI;

    const MIN_LENGTH = 4;
    const DEBOUNCE_MS = 450;
    const WEDGE_TIMEOUT_MS = 80;
    const HISTORY_MAX = 50;
    const HISTORY_KEY = 'wh_scan_history_v1';
    const CAMERA_FACING_ENV = '__environment__';
    const CAMERA_FACING_USER = '__user__';

    const STOCK_KEYS = {
        ok: 'wms_stock_ok',
        low: 'wms_stock_low',
        out: 'wms_stock_out',
        alert: 'wms_stock_alert',
    };

    const state = {
        history: [],
        stats: { session: 0, found: 0, notFound: 0 },
        activeTab: 'camera',
        isProcessing: false,
        html5Qr: null,
        cameraRunning: false,
        activeCameraId: null,
        wedgeBuffer: '',
        wedgeTimer: null,
        lastScan: { code: '', at: 0 },
    };

    const els = {
        wedge: wedgeInput,
        manualForm: document.getElementById('whScanManualForm'),
        manualInput: document.getElementById('whScanManualInput'),
        warehouse: document.getElementById('whScanWarehouse'),
        tabCamera: document.getElementById('whScanTabCamera'),
        tabManual: document.getElementById('whScanTabManual'),
        panelCamera: document.getElementById('whScanPanelCamera'),
        panelManual: document.getElementById('whScanPanelManual'),
        reader: document.getElementById('whScanCameraReader'),
        cameraSelect: document.getElementById('whScanCameraSelect'),
        cameraStart: document.getElementById('whScanCameraStart'),
        cameraStop: document.getElementById('whScanCameraStop'),
        statusBadge: document.getElementById('whScanStatusBadge'),
        statusText: document.getElementById('whScanStatusText'),
        flash: document.getElementById('whScanFlash'),
        lastWrap: document.getElementById('whScanLastWrap'),
        lastCode: document.getElementById('whScanLastCode'),
        lastResult: document.getElementById('whScanLastResult'),
        result: document.getElementById('whScanResult'),
        resultEmpty: document.getElementById('whScanResultEmpty'),
        historyBody: document.getElementById('whScanHistoryBody'),
        statSession: document.getElementById('whScanStatSession'),
        statFound: document.getElementById('whScanStatFound'),
        statNotFound: document.getElementById('whScanStatNotFound'),
        heroMeta: document.getElementById('whScanHeroMeta'),
        exportBtn: document.getElementById('whScanExportBtn'),
        clearBtn: document.getElementById('whScanClearBtn'),
    };

    function stockLabel(status) {
        return t(STOCK_KEYS[status] || status) || status || '—';
    }

    function stockBadge(status) {
        const cls = status === 'ok' ? 'ok' : (status === 'out' ? 'off' : 'warn');
        return `<span class="cr-badge cr-badge--${cls}">${esc(stockLabel(status))}</span>`;
    }

    function qtyCell(qty, reorder) {
        const n = Number(qty || 0);
        const low = reorder != null && n > 0 && n <= Number(reorder);
        const out = n === 0;
        const cls = out ? 'wms-qty--out' : (low ? 'wms-qty--low' : '');
        return `<span class="wms-qty ${cls}">${n.toLocaleString()}</span>`;
    }

    function normalizeCode(raw) {
        return String(raw ?? '').trim().replace(/[\u0000-\u001F\u007F]/g, '');
    }

    function isMobileDevice() {
        return /Android|iPhone|iPad|iPod|Mobile|webOS|BlackBerry/i.test(navigator.userAgent)
            || (navigator.maxTouchPoints > 1 && window.matchMedia('(max-width: 900px)').matches);
    }

    function cameraErrorMessage(err) {
        const name = err?.name || '';
        const msg = String(err?.message || err || '').toLowerCase();
        if (!window.isSecureContext) return t('scanner_insecure_context');
        if (name === 'NotAllowedError' || name === 'PermissionDeniedError' || msg.includes('permission')) {
            return t('scanner_permission_denied');
        }
        if (name === 'NotFoundError' || name === 'DevicesNotFoundError' || msg.includes('not found')) {
            return t('scanner_no_camera');
        }
        return t('scanner_no_camera');
    }

    function getScanConfig() {
        const config = {
            fps: isMobileDevice() ? 10 : 12,
            qrbox: (viewfinderWidth, viewfinderHeight) => {
                const w = Math.min(viewfinderWidth * 0.88, 300);
                const h = Math.min(88, Math.floor(viewfinderHeight * 0.4));
                return { width: Math.floor(w), height: Math.max(56, h) };
            },
            disableFlip: false,
        };
        if (!isMobileDevice()) config.aspectRatio = 1.333;
        return config;
    }

    function setStatus(kind, text) {
        if (!els.statusBadge || !els.statusText) return;
        els.statusBadge.className = `wh-scan-status wh-scan-status--${kind}`;
        els.statusText.textContent = text;
        const pulse = ['scanning', 'processing'].includes(kind);
        els.statusBadge.classList.toggle('is-pulse', pulse);
    }

    function showResultLoading(code) {
        if (!els.result) return;
        els.result.innerHTML = `
            <div class="wh-scan-result__loading" aria-live="polite">
                <span class="wh-scan-result__spinner" aria-hidden="true"></span>
                <p>${esc(t('scanner_status_processing'))}</p>
                ${code ? `<code>${esc(code)}</code>` : ''}
            </div>`;
    }

    function loadHistoryStore() {
        try {
            const raw = sessionStorage.getItem(HISTORY_KEY);
            const parsed = raw ? JSON.parse(raw) : null;
            if (!parsed || !Array.isArray(parsed.history)) return;
            state.history = parsed.history.slice(0, HISTORY_MAX);
            state.stats = {
                session: Number(parsed.stats?.session || state.history.length),
                found: Number(parsed.stats?.found || state.history.filter((r) => r.found).length),
                notFound: Number(parsed.stats?.notFound || state.history.filter((r) => !r.found).length),
            };
        } catch (_) { /* ignore */ }
    }

    function saveHistoryStore() {
        try {
            sessionStorage.setItem(HISTORY_KEY, JSON.stringify({ history: state.history, stats: state.stats }));
        } catch (_) { /* ignore */ }
    }

    function warehouseQuery() {
        const wh = els.warehouse?.value?.trim();
        return wh ? `warehouse_id=${encodeURIComponent(wh)}` : '';
    }

    function showFlash(kind) {
        if (!els.flash) return;
        els.flash.hidden = false;
        els.flash.className = `wh-scan-flash wh-scan-flash--${kind}`;
        requestAnimationFrame(() => els.flash.classList.add('is-visible'));
        setTimeout(() => {
            els.flash?.classList.remove('is-visible');
            setTimeout(() => { if (els.flash) els.flash.hidden = true; }, 200);
        }, 350);
    }

    function showLastScan(code, resultText, found) {
        if (!els.lastWrap) return;
        els.lastWrap.hidden = false;
        if (els.lastCode) els.lastCode.textContent = code;
        if (els.lastResult) {
            els.lastResult.textContent = resultText;
            els.lastResult.className = `wh-scan-last__result wh-scan-last__result--${found ? 'ok' : 'err'}`;
        }
    }

    function updateStats() {
        if (els.statSession) els.statSession.textContent = String(state.stats.session);
        if (els.statFound) els.statFound.textContent = String(state.stats.found);
        if (els.statNotFound) els.statNotFound.textContent = String(state.stats.notFound);
        if (els.heroMeta) {
            const wh = els.warehouse?.selectedOptions?.[0]?.text || t('wh_all_warehouses');
            els.heroMeta.textContent = `${wh} · ${state.stats.session} ${t('wh_scan_stat_session').toLowerCase()}`;
        }
    }

    function renderHistory() {
        if (!els.historyBody) return;
        if (!state.history.length) {
            els.historyBody.innerHTML = `<tr><td colspan="4" class="wh-scan-empty-cell">${esc(t('wh_scan_history_empty'))}</td></tr>`;
            return;
        }
        els.historyBody.innerHTML = state.history.map((row) => `<tr class="wh-scan-list-row">
            <td class="wh-scan-col--time"><time datetime="${esc(row.iso)}">${esc(row.time)}</time></td>
            <td class="wh-scan-col--code"><code>${esc(row.code)}</code></td>
            <td class="wh-scan-col--product">${esc(row.productName || '—')}</td>
            <td class="wh-scan-col--status"><span class="cr-badge cr-badge--${row.found ? 'ok' : 'off'}">${esc(row.found ? t('wh_scan_status_found') : t('wh_scan_status_not_found'))}</span></td>
        </tr>`).join('');
    }

    function pushHistory(entry) {
        state.history.unshift(entry);
        if (state.history.length > HISTORY_MAX) state.history.length = HISTORY_MAX;
        renderHistory();
        saveHistoryStore();
    }

    function renderProduct(product, wms) {
        if (!els.result) return;
        const p = product || {};
        const wmsData = wms || {};
        const totals = wmsData.totals || {};
        const whRows = wmsData.warehouses || [];
        const retailQty = Number(p.stock_quantity ?? 0);

        const whTable = whRows.length
            ? `<div class="wh-scan-table-wrap"><table class="modern-table wh-table wh-scan-wh-list-table"><thead><tr>
                <th class="wh-scan-col--wh">${esc(t('wms_nav_warehouses'))}</th>
                <th class="wh-scan-col--qty">${esc(t('wms_col_qty'))}</th>
                <th class="wh-scan-col--qty">${esc(t('wh_prod_col_available'))}</th>
                <th class="wh-scan-col--qty">${esc(t('wh_prod_col_reserved'))}</th>
                <th class="wh-scan-col--value">${esc(t('wms_col_value'))}</th>
                <th class="wh-scan-col--loc">${esc(t('wms_col_location'))}</th>
                <th class="wh-scan-col--status">${esc(t('col_status'))}</th>
            </tr></thead><tbody>${whRows.map((w) => `<tr class="wh-scan-list-row">
                <td class="wh-scan-col--wh"><strong>${esc(w.warehouse_name)}</strong></td>
                <td class="wh-scan-col--qty">${qtyCell(w.quantity, w.reorder_level)}</td>
                <td class="wh-scan-col--qty">${Number(w.available_qty ?? (w.quantity - w.reserved_qty)).toLocaleString()}</td>
                <td class="wh-scan-col--qty">${Number(w.reserved_qty || 0).toLocaleString()}</td>
                <td class="wh-scan-col--value">${esc(money(w.stock_value))}</td>
                <td class="wh-scan-col--loc">${esc(w.location_code || '—')}</td>
                <td class="wh-scan-col--status">${stockBadge(w.stock_status)}</td>
            </tr>`).join('')}</tbody></table></div>`
            : `<p class="wh-scan-result__empty-inline">${esc(t('wh_prod_no_wh_stock'))}</p>`;

        const whQ = warehouseQuery();
        const skuQ = p.sku ? encodeURIComponent(p.sku) : '';
        const links = [];
        if (p.sku) links.push(`<a class="wh-scan-link" href="stock_ledger.php?q=${skuQ}">${esc(t('wh_prod_link_ledger'))}</a>`);
        if (p.sku) links.push(`<a class="wh-scan-link" href="products.php?q=${skuQ}">${esc(t('wh_scan_link_products'))}</a>`);
        links.push(`<a class="wh-scan-link" href="warehouse_inventory.php${whQ ? `?${whQ}${skuQ ? `&q=${skuQ}` : ''}` : (skuQ ? `?q=${skuQ}` : '')}">${esc(t('wh_scan_link_inv'))}</a>`);
        if (window.WH_PAGE?.canReceive) {
            links.push(`<a class="wh-scan-link" href="../receiving/receive_stock.php">${esc(t('wh_scan_link_receive'))}</a>`);
        }

        els.result.innerHTML = `
            <article class="wh-scan-product">
                <header class="wh-scan-product__head">
                    <div>
                        <h4 class="wh-scan-product__name">${esc(p.name || '—')}</h4>
                        <p class="wh-scan-product__meta">${esc([p.sku, p.barcode, p.category_name].filter(Boolean).join(' · '))}</p>
                    </div>
                    ${p.image_url ? `<img class="wh-scan-product__img" src="${esc(p.image_url)}" alt="" loading="lazy">` : ''}
                </header>
                <dl class="wh-scan-product__stats">
                    <div><dt>${esc(t('wh_scan_retail_stock'))}</dt><dd>${retailQty.toLocaleString()}</dd></div>
                    <div><dt>${esc(t('wh_scan_wh_stock'))}</dt><dd>${Number(totals.total_qty ?? 0).toLocaleString()}</dd></div>
                    <div><dt>${esc(t('wms_col_value'))}</dt><dd>${esc(money(totals.total_value ?? p.price))}</dd></div>
                </dl>
                <nav class="wh-scan-product__links">${links.join('')}</nav>
                <h5 class="wh-scan-product__section">${esc(t('wh_prod_wh_breakdown'))}</h5>
                ${whTable}
            </article>`;
    }

    function renderNotFound(code) {
        if (!els.result) return;
        els.result.innerHTML = `
            <div class="wh-scan-result__miss">
                <span class="material-icons-round">search_off</span>
                <p>${esc(t('scanner_status_not_found'))}</p>
                <code>${esc(code)}</code>
            </div>`;
    }

    function clearResult() {
        if (!els.result) return;
        els.result.innerHTML = '';
        if (els.resultEmpty) {
            els.result.appendChild(els.resultEmpty);
            els.resultEmpty.hidden = false;
        }
    }

    async function enrichProduct(product) {
        if (!product?.id) return null;
        const params = {};
        const wh = els.warehouse?.value?.trim();
        if (wh) params.warehouse_id = wh;
        try {
            const res = await AdminAPI.getWmsProduct(product.id, params);
            if (res.status === 'success') {
                setMigrationHint(res.module_ready !== false);
                return res.data;
            }
        } catch (err) {
            console.warn('wh-scan: WMS enrich failed', err);
        }
        return null;
    }

    function isDuplicateScan(code) {
        const now = Date.now();
        if (code === state.lastScan.code && now - state.lastScan.at < DEBOUNCE_MS) return true;
        state.lastScan = { code, at: now };
        return false;
    }

    async function processScan(rawCode, source = 'unknown') {
        const code = normalizeCode(rawCode);
        if (!code || state.isProcessing || isDuplicateScan(code)) return;

        if (code.length < MIN_LENGTH) {
            setStatus('err', t('scanner_code_too_short'));
            showFlash('err');
            return;
        }

        state.isProcessing = true;
        hideError();
        setStatus('processing', t('scanner_status_processing'));
        showFlash('warn');
        showResultLoading(code);

        const time = new Date();
        const timeStr = time.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit', second: '2-digit' });

        try {
            const res = await AdminAPI.scanBarcode(code);
            if (res.status !== 'success' || !res.data) throw new Error('not_found');

            const wms = await enrichProduct(res.data);
            const product = res.data;

            state.stats.session += 1;
            state.stats.found += 1;
            updateStats();
            renderProduct(product, wms);
            setStatus('found', t('scanner_status_found'));
            showFlash('ok');
            showLastScan(code, product.name || t('wh_scan_status_found'), true);
            pushHistory({
                iso: time.toISOString(),
                time: timeStr,
                code,
                productName: product.name || '',
                found: true,
            });

            if (source === 'wedge' || source === 'manual') {
                if (els.wedge) els.wedge.value = '';
                if (els.manualInput) els.manualInput.value = '';
            }
            updateLastUpdated();
        } catch (err) {
            state.stats.session += 1;
            state.stats.notFound += 1;
            updateStats();
            renderNotFound(code);
            setStatus('not_found', t('scanner_status_not_found'));
            showFlash('err');
            showLastScan(code, t('wh_scan_status_not_found'), false);
            pushHistory({
                iso: time.toISOString(),
                time: timeStr,
                code,
                productName: '',
                found: false,
            });
            if (source === 'wedge' || source === 'manual') {
                if (els.wedge) els.wedge.value = '';
                if (els.manualInput) els.manualInput.value = '';
            }
        } finally {
            state.isProcessing = false;
            if (source !== 'camera') els.wedge?.focus();
        }
    }

    function setTab(tab) {
        state.activeTab = tab;
        const isCamera = tab === 'camera';
        els.tabCamera?.classList.toggle('active', isCamera);
        els.tabManual?.classList.toggle('active', !isCamera);
        els.tabCamera?.setAttribute('aria-selected', isCamera ? 'true' : 'false');
        els.tabManual?.setAttribute('aria-selected', !isCamera ? 'true' : 'false');
        if (els.panelCamera) els.panelCamera.hidden = !isCamera;
        if (els.panelManual) els.panelManual.hidden = isCamera;

        if (!isCamera) {
            if (state.cameraRunning) stopCamera();
            els.manualInput?.focus();
            setStatus('ready', t('scanner_status_ready'));
        } else if (typeof Html5Qrcode === 'undefined') {
            setStatus('ready', t('scanner_not_loaded'));
        } else {
            setStatus('ready', isMobileDevice() ? t('scanner_allow_camera') : t('scanner_status_ready'));
            loadCameraDevices();
        }
    }

    function shouldIgnoreWedgeTarget(target) {
        if (!target || !(target instanceof Element)) return true;
        const tag = target.tagName;
        if (tag === 'TEXTAREA' || tag === 'SELECT') return true;
        if (tag === 'INPUT') {
            if (target.id === 'whScanWedgeInput' || target.id === 'whScanManualInput') return true;
            const type = (target.getAttribute('type') || 'text').toLowerCase();
            if (['text', 'search', 'tel', 'email', 'password', 'url', 'number'].includes(type)) return true;
        }
        return false;
    }

    function onGlobalKeydown(e) {
        if (shouldIgnoreWedgeTarget(e.target)) return;
        if (state.activeTab === 'manual' && document.activeElement === els.manualInput) return;

        if (e.key === 'Enter') {
            if (state.wedgeBuffer.length >= MIN_LENGTH) {
                e.preventDefault();
                const code = state.wedgeBuffer;
                state.wedgeBuffer = '';
                clearTimeout(state.wedgeTimer);
                processScan(code, 'wedge');
            } else {
                state.wedgeBuffer = '';
                clearTimeout(state.wedgeTimer);
            }
            return;
        }

        if (e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey) {
            state.wedgeBuffer += e.key;
            clearTimeout(state.wedgeTimer);
            state.wedgeTimer = setTimeout(() => { state.wedgeBuffer = ''; }, WEDGE_TIMEOUT_MS);
        }
    }

    function populateCameraSelect(list, selectedId) {
        if (!els.cameraSelect) return;

        const prev = selectedId || els.cameraSelect.value || state.activeCameraId;
        els.cameraSelect.innerHTML = '';

        if (!list.length) {
            const rear = document.createElement('option');
            rear.value = CAMERA_FACING_ENV;
            rear.textContent = t('scanner_camera_rear');
            els.cameraSelect.appendChild(rear);

            const front = document.createElement('option');
            front.value = CAMERA_FACING_USER;
            front.textContent = t('scanner_camera_front');
            els.cameraSelect.appendChild(front);

            els.cameraSelect.disabled = false;
            state.activeCameraId = prev === CAMERA_FACING_USER ? CAMERA_FACING_USER : CAMERA_FACING_ENV;
            els.cameraSelect.value = state.activeCameraId;
            return;
        }

        list.forEach((cam, i) => {
            const opt = document.createElement('option');
            opt.value = cam.id;
            opt.textContent = cam.label || `${t('scanner_select_camera')} ${i + 1}`;
            els.cameraSelect.appendChild(opt);
        });

        els.cameraSelect.disabled = false;
        const back = list.find((c) => /back|rear|environment|arrière|trasera/i.test(c.label || ''));
        const match = list.find((c) => c.id === prev);
        state.activeCameraId = match?.id || back?.id || list[list.length - 1]?.id || list[0]?.id;
        els.cameraSelect.value = state.activeCameraId || '';
    }

    async function loadCameraDevices() {
        if (typeof Html5Qrcode === 'undefined' || !els.cameraSelect) return [];
        let cameras = [];
        try {
            cameras = await Html5Qrcode.getCameras();
        } catch (_) {
            cameras = [];
        }
        populateCameraSelect(cameras, state.activeCameraId);
        return cameras;
    }

    async function refreshCamerasAfterStart() {
        try {
            const list = await Html5Qrcode.getCameras();
            if (list.length) populateCameraSelect(list, state.activeCameraId);
        } catch (_) { /* labels may stay as facing mode on some devices */ }
    }

    function cameraAttempts() {
        const attempts = [];
        const id = els.cameraSelect?.value || state.activeCameraId;
        if (id && id !== CAMERA_FACING_ENV && id !== CAMERA_FACING_USER) attempts.push(id);
        if (id === CAMERA_FACING_USER) attempts.push({ facingMode: 'user' });
        attempts.push(
            { facingMode: { ideal: 'environment' } },
            { facingMode: 'environment' },
            { facingMode: { ideal: 'user' } },
            { facingMode: 'user' }
        );
        const seen = new Set();
        return attempts.filter((it) => {
            const key = typeof it === 'string' ? it : JSON.stringify(it);
            if (seen.has(key)) return false;
            seen.add(key);
            return true;
        });
    }

    async function startCamera() {
        if (typeof Html5Qrcode === 'undefined' || !els.reader) {
            setStatus('err', t('scanner_not_loaded'));
            return;
        }
        if (!window.isSecureContext) {
            setStatus('err', t('scanner_insecure_context'));
            return;
        }
        if (state.html5Qr) return;

        state.activeCameraId = els.cameraSelect?.value || state.activeCameraId;
        if (els.reader) els.reader.innerHTML = '';
        state.html5Qr = new Html5Qrcode('whScanCameraReader');
        let started = false;
        let lastError = null;
        for (const attempt of cameraAttempts()) {
            try {
                await state.html5Qr.start(
                    attempt,
                    getScanConfig(),
                    (decoded) => processScan(decoded, 'camera'),
                    () => {}
                );
                started = true;
                if (typeof attempt === 'string') state.activeCameraId = attempt;
                break;
            } catch (e) {
                lastError = e;
                try { await state.html5Qr.stop(); } catch (ignore) { /* */ }
                if (els.reader) els.reader.innerHTML = '';
            }
        }

        if (!started) {
            state.html5Qr = null;
            setStatus('err', cameraErrorMessage(lastError));
            if (els.cameraStart) els.cameraStart.hidden = false;
            if (els.cameraStop) els.cameraStop.hidden = true;
            return;
        }

        state.cameraRunning = true;
        if (els.cameraStart) els.cameraStart.hidden = true;
        if (els.cameraStop) els.cameraStop.hidden = false;
        setStatus('scanning', t('scanner_status_scanning'));
        refreshCamerasAfterStart();
    }

    async function stopCamera() {
        if (state.html5Qr) {
            try { await state.html5Qr.stop(); } catch (e) { /* */ }
            try { state.html5Qr.clear(); } catch (e) { /* */ }
            state.html5Qr = null;
        }
        if (els.reader) els.reader.innerHTML = '';
        state.cameraRunning = false;
        if (els.cameraStart) els.cameraStart.hidden = false;
        if (els.cameraStop) els.cameraStop.hidden = true;
        setStatus('ready', t('scanner_status_ready'));
    }

    function exportHistory() {
        if (!state.history.length) return;
        exportCsv('warehouse-scan-history.csv', [
            [t('wh_scan_col_time'), t('wh_scan_col_code'), t('wh_scan_col_product'), t('wh_scan_col_status')],
            ...state.history.map((r) => [r.time, r.code, r.productName, r.found ? t('wh_scan_status_found') : t('wh_scan_status_not_found')]),
        ]);
    }

    function clearHistory() {
        state.history = [];
        state.stats = { session: 0, found: 0, notFound: 0 };
        updateStats();
        renderHistory();
        saveHistoryStore();
        clearResult();
        if (els.lastWrap) els.lastWrap.hidden = true;
        setStatus('ready', t('scanner_status_ready'));
    }

    function initFromQuery() {
        const q = new URLSearchParams(window.location.search).get('q');
        if (q) processScan(q, 'query');
    }

    els.tabCamera?.addEventListener('click', () => setTab('camera'));
    els.tabManual?.addEventListener('click', () => setTab('manual'));
    els.cameraStart?.addEventListener('click', startCamera);
    els.cameraStop?.addEventListener('click', stopCamera);
    els.cameraSelect?.addEventListener('change', async () => {
        state.activeCameraId = els.cameraSelect.value;
        if (state.cameraRunning) {
            await stopCamera();
            await startCamera();
        }
    });
    els.manualForm?.addEventListener('submit', (ev) => {
        ev.preventDefault();
        const code = els.manualInput?.value;
        if (code) processScan(code, 'manual');
    });
    els.wedge?.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        processScan(els.wedge.value, 'wedge');
    });
    els.warehouse?.addEventListener('change', () => {
        updateStats();
        const last = state.history.find((h) => h.found);
        if (last?.code) processScan(last.code, 'refresh');
    });
    els.exportBtn?.addEventListener('click', exportHistory);
    els.clearBtn?.addEventListener('click', clearHistory);
    document.addEventListener('keydown', onGlobalKeydown);
    document.addEventListener('wh:refresh', () => {
        if (state.history[0]?.code) processScan(state.history[0].code, 'refresh');
        else updateLastUpdated();
    });

    loadHistoryStore();
    updateStats();
    renderHistory();

    loadWarehouseOptions(els.warehouse).then(() => {
        const whId = String(window.WH_PAGE?.warehouseId || '');
        if (whId && els.warehouse) els.warehouse.value = whId;
        updateStats();
        initFromQuery();
    }).catch((err) => showError(err.message || t('load_error')));

    updateLastUpdated();
    setTab('camera');
});
