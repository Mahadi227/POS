/**
 * RetailPOS — Barcode Scanning Module (cashier)
 *
 * ARCHITECTURE
 * ============
 *
 *  ┌──────────────────────────────────────────────────────────────────┐
 *  │                         INPUT LAYER                               │
 *  ├──────────────────┬────────────────────┬───────────────────────────┤
 *  │ Hardware wedge   │ Search + Enter     │ Camera (html5-qrcode)     │
 *  │ global keydown   │ #searchInput       │ #barcodeScannerModal      │
 *  │ buffer + timing  │                    │                           │
 *  └────────┬─────────┴─────────┬──────────┴─────────────┬─────────────┘
 *           │                   │                        │
 *           └───────────────────┼────────────────────────┘
 *                               ▼
 *                    ┌─────────────────────┐
 *                    │    processScan()    │  normalize, debounce
 *                    └──────────┬──────────┘
 *                               ▼
 *                    ┌─────────────────────┐
 *                    │   validateScan()    │  length, charset, empty
 *                    └──────────┬──────────┘
 *                               ▼
 *                    ┌─────────────────────┐
 *                    │  lookupProduct()    │  1. IndexedDB (offline)
 *                    │                     │  2. API scan (online)
 *                    └──────────┬──────────┘
 *                               ▼
 *              found ──► onAddToCart() ──► success / warning sound
 *              missing ► onNotFound()  ──► error sound + UI feedback
 */
const BarcodeScanner = (() => {
    const DEFAULTS = {
        minLength: 3,
        maxLength: 128,
        debounceMs: 450,
        wedgeCharTimeoutMs: 80,
        wedgeMinLength: 4,
        soundsEnabled: true,
    };

    let config = { ...DEFAULTS };
    let db = null;
    let wedgeBuffer = '';
    let wedgeTimer = null;
    let lastScan = { code: '', at: 0 };
    let html5QrcodeScanner = null;
    let audioCtx = null;
    let boundKeydown = null;
    let isProcessing = false;

    /** @type {{ searchInput?: HTMLInputElement, scanBtn?: HTMLElement, modal?: HTMLElement, reader?: HTMLElement, status?: HTMLElement, checkoutModal?: HTMLElement }} */
    let els = {};

    function init(options = {}) {
        config = { ...DEFAULTS, ...options };
        db = config.db || null;

        els.searchInput = document.getElementById('searchInput');
        els.scanBtn = document.getElementById('openCameraScannerBtn');
        els.modal = document.getElementById('barcodeScannerModal');
        els.reader = document.getElementById('barcode-scanner-reader');
        els.status = document.getElementById('scanStatusBadge');
        els.checkoutModal = document.getElementById('checkoutModal');

        els.scanBtn?.addEventListener('click', openCamera);
        document.getElementById('closeBarcodeScannerBtn')?.addEventListener('click', closeCamera);
        els.modal?.querySelector('[data-close-scanner]')?.addEventListener('click', closeCamera);

        boundKeydown = onGlobalKeydown;
        document.addEventListener('keydown', boundKeydown);

        els.searchInput?.addEventListener('keydown', onSearchKeydown);
    }

    function destroy() {
        if (boundKeydown) document.removeEventListener('keydown', boundKeydown);
        closeCamera();
    }

    function normalizeCode(raw) {
        return String(raw ?? '')
            .trim()
            .replace(/[\u0000-\u001F\u007F]/g, '');
    }

    function validateScan(code) {
        if (!code) return { ok: false, reason: 'empty', message: 'Code vide' };
        if (code.length < config.minLength) {
            return { ok: false, reason: 'too_short', message: `Code trop court (min. ${config.minLength})` };
        }
        if (code.length > config.maxLength) {
            return { ok: false, reason: 'too_long', message: 'Code trop long' };
        }
        if (!/^[\w\-.\/+ ]+$/i.test(code)) {
            return { ok: false, reason: 'invalid_chars', message: 'Caractères non valides' };
        }
        return { ok: true };
    }

    function isDuplicateScan(code) {
        const now = Date.now();
        if (code === lastScan.code && now - lastScan.at < config.debounceMs) {
            return true;
        }
        lastScan = { code, at: now };
        return false;
    }

    function shouldIgnoreWedgeTarget(target) {
        if (!target || !(target instanceof Element)) return true;
        const tag = target.tagName;
        if (tag === 'TEXTAREA') return true;
        if (tag === 'SELECT') return true;
        if (tag === 'INPUT') {
            if (target.id === 'searchInput') return true;
            const type = (target.getAttribute('type') || 'text').toLowerCase();
            if (['text', 'search', 'tel', 'email', 'password', 'url'].includes(type)) return true;
            if (type === 'number' && els.checkoutModal?.classList.contains('is-open')) return true;
        }
        return false;
    }

    function onGlobalKeydown(e) {
        if (shouldIgnoreWedgeTarget(e.target)) return;
        if (els.modal?.classList.contains('is-open')) return;

        if (e.key === 'Enter') {
            if (wedgeBuffer.length >= config.wedgeMinLength) {
                e.preventDefault();
                const code = wedgeBuffer;
                wedgeBuffer = '';
                clearTimeout(wedgeTimer);
                processScan(code, 'wedge');
            } else {
                wedgeBuffer = '';
                clearTimeout(wedgeTimer);
            }
            return;
        }

        if (e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey) {
            wedgeBuffer += e.key;
            clearTimeout(wedgeTimer);
            wedgeTimer = setTimeout(() => {
                wedgeBuffer = '';
            }, config.wedgeCharTimeoutMs);
        }
    }

    function isLikelyBarcode(code) {
        if (/^\d{4,}$/.test(code)) return true;
        if (/^[A-Z0-9\-./+]{4,}$/i.test(code) && !/\s/.test(code)) return true;
        return false;
    }

    function onSearchKeydown(e) {
        if (e.key !== 'Enter') return;
        const query = normalizeCode(els.searchInput?.value);
        if (!query) return;
        e.preventDefault();
        processScan(query, 'search', { silentNotFound: !isLikelyBarcode(query) });
    }

    async function lookupProduct(code) {
        if (db) {
            const byBarcode = await db.products.where('barcode').equals(code).first();
            if (byBarcode) return { product: byBarcode, source: 'local_barcode' };
            const bySku = await db.products.where('sku').equals(code).first();
            if (bySku) return { product: bySku, source: 'local_sku' };
        }

        if (navigator.onLine && typeof config.apiScan === 'function') {
            try {
                const res = await config.apiScan(code);
                if (res?.status === 'success' && res.data) {
                    if (db) {
                        try {
                            await db.products.put(res.data);
                        } catch (err) {
                            console.warn('BarcodeScanner: cache update', err);
                        }
                    }
                    return { product: res.data, source: 'api' };
                }
            } catch (err) {
                console.warn('BarcodeScanner: API lookup failed', err);
            }
        }

        return { product: null, source: null };
    }

    function setStatus(state, message) {
        if (!els.status) return;
        els.status.hidden = false;
        els.status.textContent = message;
        els.status.classList.remove(
            'pos-cashier__scan-status--ok',
            'pos-cashier__scan-status--err',
            'pos-cashier__scan-status--warn'
        );
        if (state === 'ok') els.status.classList.add('pos-cashier__scan-status--ok');
        else if (state === 'warn') els.status.classList.add('pos-cashier__scan-status--warn');
        else els.status.classList.add('pos-cashier__scan-status--err');

        clearTimeout(setStatus._timer);
        setStatus._timer = setTimeout(() => {
            if (els.status) els.status.hidden = true;
        }, 2200);
    }

    function flashSearch(state) {
        const wrap = els.searchInput?.closest('.pos-cashier__search');
        if (!wrap) return;
        wrap.classList.remove('pos-cashier__search--scan-ok', 'pos-cashier__search--scan-err');
        wrap.classList.add(state === 'ok' ? 'pos-cashier__search--scan-ok' : 'pos-cashier__search--scan-err');
        clearTimeout(flashSearch._timer);
        flashSearch._timer = setTimeout(() => {
            wrap.classList.remove('pos-cashier__search--scan-ok', 'pos-cashier__search--scan-err');
        }, 600);
    }

    function playSound(type) {
        if (!config.soundsEnabled) return;
        try {
            if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            osc.connect(gain);
            gain.connect(audioCtx.destination);

            if (type === 'success') {
                osc.frequency.setValueAtTime(880, audioCtx.currentTime);
                osc.frequency.setValueAtTime(1174, audioCtx.currentTime + 0.08);
                gain.gain.setValueAtTime(0.15, audioCtx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.15);
                osc.start(audioCtx.currentTime);
                osc.stop(audioCtx.currentTime + 0.15);
            } else if (type === 'warning') {
                osc.frequency.setValueAtTime(440, audioCtx.currentTime);
                gain.gain.setValueAtTime(0.12, audioCtx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.2);
                osc.start(audioCtx.currentTime);
                osc.stop(audioCtx.currentTime + 0.2);
            } else {
                osc.type = 'square';
                osc.frequency.setValueAtTime(220, audioCtx.currentTime);
                osc.frequency.setValueAtTime(180, audioCtx.currentTime + 0.1);
                gain.gain.setValueAtTime(0.1, audioCtx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.25);
                osc.start(audioCtx.currentTime);
                osc.stop(audioCtx.currentTime + 0.25);
            }
        } catch (err) {
            console.warn('BarcodeScanner: sound', err);
        }
    }

    async function processScan(rawCode, source = 'unknown', options = {}) {
        const code = normalizeCode(rawCode);
        if (isProcessing) return;
        if (isDuplicateScan(code)) return;

        const validation = validateScan(code);
        if (!validation.ok) {
            if (!options.silentNotFound) {
                playSound('error');
                setStatus('err', validation.message);
                flashSearch('err');
                config.onValidationError?.(code, validation.reason, validation.message);
            }
            return;
        }

        isProcessing = true;
        if (!options.silentNotFound) setStatus('warn', `Recherche… ${code}`);

        try {
            const { product, source: lookupSource } = await lookupProduct(code);

            if (!product) {
                if (!options.silentNotFound) {
                    playSound('error');
                    setStatus('err', `Introuvable : ${code}`);
                    flashSearch('err');
                    config.onNotFound?.(code, source);
                }
                return;
            }

            const stock = parseInt(product.stock_quantity, 10) || 0;
            if (stock < 1) {
                playSound('warning');
                setStatus('warn', `${product.name} — rupture`);
                flashSearch('err');
                config.onOutOfStock?.(product, code);
                return;
            }

            const result = config.onAddToCart?.(product, { code, source, lookupSource });

            if (result?.ok === false) {
                playSound(result.reason === 'max_stock' ? 'warning' : 'error');
                setStatus('warn', result.message || 'Ajout impossible');
                flashSearch('err');
                return;
            }

            playSound('success');
            setStatus('ok', `✓ ${product.name}`);
            flashSearch('ok');

            if (els.searchInput && (source === 'search' || source === 'wedge')) {
                els.searchInput.value = '';
                config.onSearchCleared?.();
            }

            if (source === 'camera') closeCamera();
            config.onSuccess?.(product, { code, source, lookupSource });
        } finally {
            isProcessing = false;
        }
    }

    async function openCamera() {
        if (!els.modal || !els.reader) return;
        els.modal.classList.add('is-open');
        els.modal.setAttribute('aria-hidden', 'false');

        if (typeof Html5QrcodeScanner === 'undefined') {
            els.reader.innerHTML =
                '<p class="pos-cashier__scanner-fallback">Bibliothèque scanner non chargée.</p>';
            return;
        }

        if (html5QrcodeScanner) {
            try {
                await html5QrcodeScanner.clear();
            } catch (e) {
                /* ignore */
            }
            html5QrcodeScanner = null;
        }

        html5QrcodeScanner = new Html5QrcodeScanner(
            'barcode-scanner-reader',
            {
                fps: 12,
                qrbox: { width: 280, height: 120 },
                rememberLastUsedCamera: true,
            },
            false
        );

        html5QrcodeScanner.render(
            (decodedText) => processScan(decodedText, 'camera'),
            () => {}
        );
    }

    async function closeCamera() {
        if (html5QrcodeScanner) {
            try {
                await html5QrcodeScanner.clear();
            } catch (e) {
                /* ignore */
            }
            html5QrcodeScanner = null;
        }
        if (els.reader) els.reader.innerHTML = '';
        if (els.modal) {
            els.modal.classList.remove('is-open');
            els.modal.setAttribute('aria-hidden', 'true');
        }
    }

    return {
        init,
        destroy,
        openCamera,
        closeCamera,
        processScan,
        validateScan,
        playSound,
        normalizeCode,
    };
})();
