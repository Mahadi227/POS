/**
 * Receive stock — USB wedge + mobile camera barcode scanning
 */
const WarehouseReceiveScan = (() => {
    const MIN_LENGTH = 3;
    const DEBOUNCE_MS = 450;
    const WEDGE_TIMEOUT_MS = 80;
    const CAMERA_FACING_ENV = '__environment__';
    const CAMERA_FACING_USER = '__user__';

    let t = (k) => k;
    let onScan = null;
    let enabled = false;
    let activeTab = 'wedge';
    let isProcessing = false;
    let html5Qr = null;
    let cameraRunning = false;
    let activeCameraId = null;
    let wedgeBuffer = '';
    let wedgeTimer = null;
    let lastScan = { code: '', at: 0 };

    const els = {};

    function isMobileDevice() {
        return /Android|iPhone|iPad|iPod|Mobile|webOS|BlackBerry/i.test(navigator.userAgent)
            || (navigator.maxTouchPoints > 1 && window.matchMedia('(max-width: 900px)').matches);
    }

    function normalizeCode(raw) {
        return String(raw ?? '').trim().replace(/[\u0000-\u001F\u007F]/g, '');
    }

    function cameraErrorMessage(err) {
        const name = err?.name || '';
        const msg = String(err?.message || err || '').toLowerCase();
        if (!window.isSecureContext) return t('scanner_insecure_context');
        if (name === 'NotAllowedError' || name === 'PermissionDeniedError' || msg.includes('permission')) {
            return t('scanner_permission_denied');
        }
        return t('scanner_no_camera');
    }

    function setStatus(kind, text) {
        const badge = els.statusBadge;
        const label = els.statusText;
        if (!badge || !label) return;
        badge.className = `wh-rcv-scan-status wh-rcv-scan-status--${kind}`;
        label.textContent = text;
        badge.classList.toggle('is-pulse', ['scanning', 'processing'].includes(kind));
    }

    function flash(kind) {
        if (!els.flash) return;
        els.flash.hidden = false;
        els.flash.className = `wh-rcv-scan-flash wh-rcv-scan-flash--${kind}`;
        requestAnimationFrame(() => els.flash?.classList.add('is-visible'));
        setTimeout(() => {
            els.flash?.classList.remove('is-visible');
            setTimeout(() => { if (els.flash) els.flash.hidden = true; }, 200);
        }, 300);
    }

    function isDuplicate(code) {
        const now = Date.now();
        if (code === lastScan.code && now - lastScan.at < DEBOUNCE_MS) return true;
        lastScan = { code, at: now };
        return false;
    }

    async function dispatchScan(raw, source) {
        const code = normalizeCode(raw);
        if (!enabled || !code || isProcessing || isDuplicate(code)) return;
        if (code.length < MIN_LENGTH) {
            setStatus('err', t('scanner_code_too_short'));
            flash('err');
            return;
        }
        isProcessing = true;
        setStatus('processing', t('scanner_status_processing'));
        flash('warn');
        try {
            const ok = await onScan?.(code, source);
            if (ok === false) throw new Error('scan_failed');
            setStatus('ok', t('scanner_status_found'));
            flash('ok');
            if (source === 'wedge' || source === 'wedge-global') {
                if (els.wedge) els.wedge.value = '';
            }
        } catch (_) {
            setStatus('err', t('wh_rcv_product_not_found'));
            flash('err');
        } finally {
            isProcessing = false;
            if (activeTab === 'wedge') focusWedge();
            else if (activeTab === 'camera' && cameraRunning) {
                setStatus('scanning', t('scanner_status_scanning'));
            } else {
                setStatus('ready', t('scanner_status_ready'));
            }
        }
    }

    function getScanConfig() {
        return {
            fps: isMobileDevice() ? 10 : 12,
            qrbox: (w, h) => {
                const width = Math.min(w * 0.9, 260);
                const height = Math.min(72, Math.floor(h * 0.38));
                return { width: Math.floor(width), height: Math.max(48, height) };
            },
            disableFlip: false,
            aspectRatio: isMobileDevice() ? undefined : 1.333,
        };
    }

    function populateCameraSelect(list, selectedId) {
        if (!els.cameraSelect) return;
        const prev = selectedId || els.cameraSelect.value || activeCameraId;
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
            activeCameraId = prev === CAMERA_FACING_USER ? CAMERA_FACING_USER : CAMERA_FACING_ENV;
        } else {
            list.forEach((cam, i) => {
                const opt = document.createElement('option');
                opt.value = cam.id;
                opt.textContent = cam.label || `${t('scanner_select_camera')} ${i + 1}`;
                els.cameraSelect.appendChild(opt);
            });
            const back = list.find((c) => /back|rear|environment/i.test(c.label || ''));
            const match = list.find((c) => c.id === prev);
            activeCameraId = match?.id || back?.id || list[0]?.id;
        }
        els.cameraSelect.value = activeCameraId || '';
        els.cameraSelect.disabled = false;
    }

    async function loadCameraDevices() {
        if (typeof Html5Qrcode === 'undefined' || !els.cameraSelect) return;
        let cameras = [];
        try {
            cameras = await Html5Qrcode.getCameras();
        } catch (_) { /* */ }
        populateCameraSelect(cameras, activeCameraId);
    }

    function cameraAttempts() {
        const id = els.cameraSelect?.value || activeCameraId;
        const attempts = [];
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
        if (html5Qr) return;

        activeCameraId = els.cameraSelect?.value || activeCameraId;
        if (els.reader) els.reader.innerHTML = '';
        html5Qr = new Html5Qrcode('whRcvCameraReader');
        let started = false;
        let lastError = null;
        for (const attempt of cameraAttempts()) {
            try {
                await html5Qr.start(
                    attempt,
                    getScanConfig(),
                    (decoded) => dispatchScan(decoded, 'camera'),
                    () => {}
                );
                started = true;
                if (typeof attempt === 'string') activeCameraId = attempt;
                break;
            } catch (e) {
                lastError = e;
                try { await html5Qr.stop(); } catch (_) { /* */ }
                if (els.reader) els.reader.innerHTML = '';
            }
        }
        if (!started) {
            html5Qr = null;
            setStatus('err', cameraErrorMessage(lastError));
            if (els.cameraStart) els.cameraStart.hidden = false;
            if (els.cameraStop) els.cameraStop.hidden = true;
            return;
        }
        cameraRunning = true;
        if (els.cameraStart) els.cameraStart.hidden = true;
        if (els.cameraStop) els.cameraStop.hidden = false;
        setStatus('scanning', t('scanner_status_scanning'));
        try {
            const list = await Html5Qrcode.getCameras();
            if (list.length) populateCameraSelect(list, activeCameraId);
        } catch (_) { /* */ }
    }

    async function stopCamera() {
        if (html5Qr) {
            try { await html5Qr.stop(); } catch (_) { /* */ }
            try { html5Qr.clear(); } catch (_) { /* */ }
            html5Qr = null;
        }
        if (els.reader) els.reader.innerHTML = '';
        cameraRunning = false;
        if (els.cameraStart) els.cameraStart.hidden = false;
        if (els.cameraStop) els.cameraStop.hidden = true;
        if (activeTab === 'camera') setStatus('ready', t('scanner_status_ready'));
    }

    function setTab(tab) {
        activeTab = tab;
        const isCamera = tab === 'camera';
        els.tabWedge?.classList.toggle('active', !isCamera);
        els.tabCamera?.classList.toggle('active', isCamera);
        els.tabWedge?.setAttribute('aria-selected', !isCamera ? 'true' : 'false');
        els.tabCamera?.setAttribute('aria-selected', isCamera ? 'true' : 'false');
        if (els.panelWedge) els.panelWedge.hidden = isCamera;
        if (els.panelCamera) els.panelCamera.hidden = !isCamera;
        if (!isCamera) {
            stopCamera();
            focusWedge();
            setStatus('ready', t('scanner_status_ready'));
        } else if (typeof Html5Qrcode === 'undefined') {
            setStatus('err', t('scanner_not_loaded'));
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
            if (target.id === 'whRcvScan') return true;
            const type = (target.getAttribute('type') || 'text').toLowerCase();
            if (['text', 'search', 'tel', 'email', 'password', 'url', 'number'].includes(type)) return true;
        }
        return false;
    }

    function onGlobalKeydown(e) {
        if (!enabled || activeTab !== 'wedge') return;
        if (shouldIgnoreWedgeTarget(e.target)) return;
        if (e.key === 'Enter') {
            if (wedgeBuffer.length >= MIN_LENGTH) {
                e.preventDefault();
                const code = wedgeBuffer;
                wedgeBuffer = '';
                clearTimeout(wedgeTimer);
                dispatchScan(code, 'wedge-global');
            } else {
                wedgeBuffer = '';
                clearTimeout(wedgeTimer);
            }
            return;
        }
        if (e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey) {
            wedgeBuffer += e.key;
            clearTimeout(wedgeTimer);
            wedgeTimer = setTimeout(() => { wedgeBuffer = ''; }, WEDGE_TIMEOUT_MS);
        }
    }

    function focusWedge() {
        if (enabled && activeTab === 'wedge') els.wedge?.focus();
    }

    function bind() {
        els.tabWedge?.addEventListener('click', () => setTab('wedge'));
        els.tabCamera?.addEventListener('click', () => setTab('camera'));
        els.cameraStart?.addEventListener('click', startCamera);
        els.cameraStop?.addEventListener('click', stopCamera);
        els.cameraSelect?.addEventListener('change', async () => {
            activeCameraId = els.cameraSelect.value;
            if (cameraRunning) {
                await stopCamera();
                await startCamera();
            }
        });
        els.wedge?.addEventListener('keydown', (e) => {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            dispatchScan(els.wedge.value, 'wedge');
        });
        document.addEventListener('keydown', onGlobalKeydown);
    }

    function cacheElements() {
        els.dock = document.getElementById('whRcvScanDock');
        els.wedge = document.getElementById('whRcvScan');
        els.tabWedge = document.getElementById('whRcvScanTabWedge');
        els.tabCamera = document.getElementById('whRcvScanTabCamera');
        els.panelWedge = document.getElementById('whRcvScanPanelWedge');
        els.panelCamera = document.getElementById('whRcvScanPanelCamera');
        els.statusBadge = document.getElementById('whRcvScanStatus');
        els.statusText = document.getElementById('whRcvScanStatusText');
        els.reader = document.getElementById('whRcvCameraReader');
        els.flash = document.getElementById('whRcvScanFlash');
        els.cameraSelect = document.getElementById('whRcvCameraSelect');
        els.cameraStart = document.getElementById('whRcvCameraStart');
        els.cameraStop = document.getElementById('whRcvCameraStop');
    }

    function init(options = {}) {
        cacheElements();
        if (!els.dock) return;
        t = options.t || ((k, ...args) => {
            let str = (window.WH_I18N && window.WH_I18N[k]) || k;
            args.forEach((v) => { str = str.replace('%s', v); });
            return str;
        });
        onScan = options.onScan || null;
        enabled = options.enabled !== false;
        if (!enabled) {
            els.dock.hidden = true;
            return;
        }
        bind();
        setTab(isMobileDevice() ? 'camera' : 'wedge');
    }

    return { init, focusWedge, stopCamera, setTab };
})();
