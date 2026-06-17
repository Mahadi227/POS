/**
 * Admin inventory — professional barcode scanner (camera, manual, USB wedge)
 */
const InventoryScanner = (() => {
    const MIN_LENGTH = 4;
    const DEBOUNCE_MS = 500;
    const WEDGE_TIMEOUT_MS = 80;
    const CAMERA_FACING_ENV = '__environment__';
    const CAMERA_FACING_USER = '__user__';

    let i18n = {};
    let onScan = null;
    let html5QrCode = null;
    let cameras = [];
    let activeCameraId = null;
    let isRunning = false;
    let isProcessing = false;
    let torchOn = false;
    let lastScan = { code: '', at: 0 };
    let wedgeBuffer = '';
    let wedgeTimer = null;
    let activeTab = 'camera';

    const $ = (id) => document.getElementById(id);

    const els = {};

    function t(key, ...args) {
        let str = i18n[key] || key;
        args.forEach((val) => { str = str.replace('%s', val); });
        return str;
    }

    function isMobileDevice() {
        return /Android|iPhone|iPad|iPod|Mobile|webOS|BlackBerry/i.test(navigator.userAgent)
            || (navigator.maxTouchPoints > 1 && window.matchMedia('(max-width: 900px)').matches);
    }

    function cameraErrorMessage(err) {
        const name = err?.name || '';
        const msg = String(err?.message || err || '').toLowerCase();
        if (name === 'NotAllowedError' || name === 'PermissionDeniedError' || msg.includes('permission')) {
            return t('scanner_permission_denied');
        }
        if (name === 'NotFoundError' || name === 'DevicesNotFoundError' || msg.includes('not found')) {
            return t('scanner_no_camera');
        }
        return t('scanner_no_camera');
    }

    function init(options = {}) {
        i18n = options.i18n || window.INVENTORY_I18N || {};
        onScan = options.onScan || null;

        els.overlay = $('scannerModalOverlay');
        els.reader = $('inv-scanner-reader');
        els.statusBadge = $('scannerStatusBadge');
        els.statusText = $('scannerStatusText');
        els.flash = $('scannerFlash');
        els.lastScan = $('scannerLastScan');
        els.lastCode = $('scannerLastCode');
        els.lastResult = $('scannerLastResult');
        els.cameraSelect = $('scannerCameraSelect');
        els.startBtn = $('scannerStartBtn');
        els.stopBtn = $('scannerStopBtn');
        els.torchBtn = $('scannerTorchBtn');
        els.tabCamera = $('scannerTabCamera');
        els.tabManual = $('scannerTabManual');
        els.panelCamera = $('scannerPanelCamera');
        els.panelManual = $('scannerPanelManual');
        els.manualForm = $('scannerManualForm');
        els.manualInput = $('scannerManualInput');
        els.viewport = document.querySelector('.inv-scanner__viewport');
        els.hint = document.querySelector('.inv-scanner__hint');

        $('scanBarcodeBtn')?.addEventListener('click', open);
        $('closeScannerBtn')?.addEventListener('click', close);
        $('closeScannerBtn2')?.addEventListener('click', close);
        els.overlay?.addEventListener('click', (ev) => {
            if (ev.target === els.overlay) close();
        });

        els.tabCamera?.addEventListener('click', () => setTab('camera'));
        els.tabManual?.addEventListener('click', () => setTab('manual'));

        els.startBtn?.addEventListener('click', startCamera);
        els.stopBtn?.addEventListener('click', stopCamera);
        els.torchBtn?.addEventListener('click', toggleTorch);
        els.cameraSelect?.addEventListener('change', async () => {
            activeCameraId = els.cameraSelect?.value || null;
            if (isRunning) {
                await stopCamera();
                await startCamera();
            }
        });

        els.manualForm?.addEventListener('submit', (ev) => {
            ev.preventDefault();
            const code = els.manualInput?.value?.trim();
            if (code) processScan(code, 'manual');
        });

        document.addEventListener('keydown', onWedgeKeydown);
    }

    function setTab(tab) {
        activeTab = tab;
        const isCamera = tab === 'camera';
        els.tabCamera?.classList.toggle('active', isCamera);
        els.tabManual?.classList.toggle('active', !isCamera);
        els.tabCamera?.setAttribute('aria-selected', isCamera ? 'true' : 'false');
        els.tabManual?.setAttribute('aria-selected', !isCamera ? 'true' : 'false');
        if (els.panelCamera) els.panelCamera.hidden = !isCamera;
        if (els.panelManual) els.panelManual.hidden = isCamera;

        if (!isCamera) {
            if (isRunning) stopCamera();
            els.manualInput?.focus();
            setStatus('ready', t('scanner_status_ready'));
        } else if (isRunning) {
            setStatus('scanning', t('scanner_status_scanning'));
        } else if (typeof Html5Qrcode !== 'undefined') {
            setStatus('ready', isMobileDevice() ? t('scanner_allow_camera') : t('scanner_status_ready'));
            startCamera();
        } else {
            setStatus('ready', t('scanner_status_ready'));
        }
    }

    function setStatus(kind, text) {
        if (!els.statusBadge || !els.statusText) return;
        els.statusBadge.className = `inv-scanner__status inv-scanner__status--${kind}`;
        els.statusText.textContent = text;
    }

    function showFlash(kind) {
        if (!els.flash) return;
        els.flash.hidden = false;
        els.flash.className = `inv-scanner__flash inv-scanner__flash--${kind}`;
        requestAnimationFrame(() => els.flash.classList.add('is-visible'));
        setTimeout(() => {
            els.flash?.classList.remove('is-visible');
            setTimeout(() => { if (els.flash) els.flash.hidden = true; }, 200);
        }, 350);
    }

    function showLastScan(code, resultText, found) {
        if (!els.lastScan) return;
        els.lastScan.hidden = false;
        if (els.lastCode) els.lastCode.textContent = code;
        if (els.lastResult) {
            els.lastResult.textContent = resultText;
            els.lastResult.className = `inv-scanner__last-result inv-scanner__last-result--${found ? 'ok' : 'new'}`;
        }
    }

    function normalizeCode(raw) {
        return String(raw ?? '').trim().replace(/[\u0000-\u001F\u007F]/g, '');
    }

    function isDuplicate(code) {
        const now = Date.now();
        if (code === lastScan.code && now - lastScan.at < DEBOUNCE_MS) return true;
        lastScan = { code, at: now };
        return false;
    }

    function shouldIgnoreWedgeTarget(target) {
        if (!target || !(target instanceof Element)) return true;
        const tag = target.tagName;
        if (tag === 'TEXTAREA' || tag === 'SELECT') return true;
        if (tag === 'INPUT') {
            const type = (target.getAttribute('type') || 'text').toLowerCase();
            if (['text', 'search', 'tel', 'email', 'password', 'url', 'number'].includes(type)) {
                if (target.id === 'scannerManualInput' && els.overlay?.classList.contains('active')) return false;
                return true;
            }
        }
        return false;
    }

    function onWedgeKeydown(e) {
        if (shouldIgnoreWedgeTarget(e.target)) return;
        if (e.ctrlKey || e.altKey || e.metaKey) return;

        if (e.key === 'Enter') {
            const code = normalizeCode(wedgeBuffer);
            wedgeBuffer = '';
            clearTimeout(wedgeTimer);
            if (code.length >= MIN_LENGTH) processScan(code, 'wedge');
            return;
        }

        if (e.key.length === 1) {
            wedgeBuffer += e.key;
            clearTimeout(wedgeTimer);
            wedgeTimer = setTimeout(() => { wedgeBuffer = ''; }, WEDGE_TIMEOUT_MS);
        }
    }

    async function processScan(raw, source) {
        const code = normalizeCode(raw);
        if (!code || code.length < MIN_LENGTH) {
            if (source === 'manual') setStatus('error', t('scanner_code_too_short'));
            return;
        }
        if (isProcessing || isDuplicate(code)) return;

        isProcessing = true;
        setStatus('processing', t('scanner_status_processing'));
        showFlash('scan');

        try {
            const result = await onScan?.(code, { source });
            const found = result?.status === 'success';
            const label = found
                ? (result.data?.name || t('scanner_status_found'))
                : t('scanner_status_not_found');

            const modalOpen = els.overlay?.classList.contains('active');

            if (modalOpen) {
                setStatus(found ? 'found' : 'notfound', label);
                showLastScan(code, label, found);
                showFlash(found ? 'ok' : 'new');
                if (source === 'camera') await stopCamera();
                if (source === 'manual' && els.manualInput) els.manualInput.value = '';
                await new Promise((r) => setTimeout(r, found ? 400 : 600));
                await close();
            }

            window.inventoryNavigateBarcodeScan?.(code, result);
        } catch (err) {
            setStatus('error', err?.message || t('error'));
            showFlash('err');
        } finally {
            isProcessing = false;
        }
    }

    function populateCameraSelect(list, selectedId) {
        if (!els.cameraSelect) return;

        const prev = selectedId || els.cameraSelect.value || activeCameraId;
        els.cameraSelect.innerHTML = '';

        if (!list.length && isMobileDevice()) {
            const rear = document.createElement('option');
            rear.value = CAMERA_FACING_ENV;
            rear.textContent = t('scanner_camera_rear');
            els.cameraSelect.appendChild(rear);

            const front = document.createElement('option');
            front.value = CAMERA_FACING_USER;
            front.textContent = t('scanner_camera_front');
            els.cameraSelect.appendChild(front);

            els.cameraSelect.disabled = false;
            els.startBtn.disabled = false;
            activeCameraId = prev === CAMERA_FACING_USER ? CAMERA_FACING_USER : CAMERA_FACING_ENV;
            els.cameraSelect.value = activeCameraId;
            return;
        }

        if (!list.length) {
            const opt = document.createElement('option');
            opt.value = '';
            opt.textContent = t('scanner_no_camera');
            els.cameraSelect.appendChild(opt);
            els.cameraSelect.disabled = true;
            els.startBtn.disabled = true;
            activeCameraId = null;
            return;
        }

        list.forEach((cam, i) => {
            const opt = document.createElement('option');
            opt.value = cam.id;
            opt.textContent = cam.label || `${t('scanner_select_camera')} ${i + 1}`;
            els.cameraSelect.appendChild(opt);
        });

        els.cameraSelect.disabled = false;
        els.startBtn.disabled = false;

        const backCam = list.find((c) => /back|rear|environment|arrière|trasera/i.test(c.label || ''));
        const match = list.find((c) => c.id === prev);
        activeCameraId = match?.id || backCam?.id || list[list.length - 1]?.id || list[0]?.id;
        els.cameraSelect.value = activeCameraId;
    }

    async function loadCameras() {
        if (typeof Html5Qrcode === 'undefined') return;

        try {
            cameras = await Html5Qrcode.getCameras();
        } catch (e) {
            cameras = [];
        }

        populateCameraSelect(cameras, activeCameraId);
    }

    function resolveCameraConstraints() {
        const selected = els.cameraSelect?.value || activeCameraId;
        const attempts = [];

        if (selected && selected !== CAMERA_FACING_ENV && selected !== CAMERA_FACING_USER) {
            attempts.push(selected);
        }
        if (selected === CAMERA_FACING_USER) {
            attempts.push({ facingMode: 'user' });
        }

        attempts.push(
            { facingMode: { ideal: 'environment' } },
            { facingMode: 'environment' },
            { facingMode: { ideal: 'user' } },
            { facingMode: 'user' },
        );

        const seen = new Set();
        return attempts.filter((item) => {
            const key = typeof item === 'string' ? item : JSON.stringify(item);
            if (seen.has(key)) return false;
            seen.add(key);
            return true;
        });
    }

    function getScanConfig() {
        const config = {
            fps: isMobileDevice() ? 10 : 12,
            qrbox: qrBoxFn,
            disableFlip: false,
        };
        if (!isMobileDevice()) {
            config.aspectRatio = 1.333;
        }
        return config;
    }

    function qrBoxFn(viewfinderWidth, viewfinderHeight) {
        const w = Math.min(viewfinderWidth * 0.88, 300);
        const h = Math.min(88, Math.floor(viewfinderHeight * 0.4));
        return { width: Math.floor(w), height: Math.max(56, h) };
    }

    async function refreshCamerasAfterStart() {
        try {
            const list = await Html5Qrcode.getCameras();
            if (list.length) {
                cameras = list;
                populateCameraSelect(cameras, activeCameraId);
            }
        } catch (e) { /* labels may stay as facing mode on some devices */ }
    }

    async function startCamera() {
        if (typeof Html5Qrcode === 'undefined') {
            setStatus('error', t('scanner_not_loaded'));
            return;
        }
        if (isRunning || !els.reader) return;

        activeCameraId = els.cameraSelect?.value || activeCameraId;

        if (html5QrCode) {
            try { await html5QrCode.stop(); } catch (e) { /* */ }
            try { html5QrCode.clear(); } catch (e) { /* */ }
            html5QrCode = null;
        }

        if (els.reader) els.reader.innerHTML = '';
        html5QrCode = new Html5Qrcode('inv-scanner-reader');

        const constraints = resolveCameraConstraints();
        let lastError = null;
        let started = false;

        for (const cameraIdOrConfig of constraints) {
            try {
                await html5QrCode.start(
                    cameraIdOrConfig,
                    getScanConfig(),
                    (text) => processScan(text, 'camera'),
                    () => {},
                );
                started = true;
                if (typeof cameraIdOrConfig === 'string') {
                    activeCameraId = cameraIdOrConfig;
                    if (els.cameraSelect) els.cameraSelect.value = cameraIdOrConfig;
                }
                break;
            } catch (e) {
                lastError = e;
                try { await html5QrCode.stop(); } catch (err) { /* */ }
                if (els.reader) els.reader.innerHTML = '';
            }
        }

        if (!started) {
            setStatus('error', cameraErrorMessage(lastError));
            html5QrCode = null;
            isRunning = false;
            if (els.startBtn) els.startBtn.hidden = false;
            if (els.stopBtn) els.stopBtn.hidden = true;
            return;
        }

        isRunning = true;
        els.viewport?.classList.add('is-live');
        els.startBtn.hidden = true;
        els.stopBtn.hidden = false;
        setStatus('scanning', t('scanner_status_scanning'));
        await checkTorchSupport();
        refreshCamerasAfterStart();
    }

    async function checkTorchSupport() {
        if (!html5QrCode || !els.torchBtn) return;
        try {
            const caps = html5QrCode.getRunningTrackCameraCapabilities();
            const supported = caps?.torch === true;
            els.torchBtn.hidden = !supported;
            torchOn = false;
            els.torchBtn.querySelector('.material-icons-round').textContent = 'flashlight_on';
            els.torchBtn.title = t('scanner_torch_on');
        } catch (e) {
            els.torchBtn.hidden = true;
        }
    }

    async function toggleTorch() {
        if (!html5QrCode || !isRunning) return;
        torchOn = !torchOn;
        try {
            await html5QrCode.applyVideoConstraints({ advanced: [{ torch: torchOn }] });
            const icon = els.torchBtn?.querySelector('.material-icons-round');
            if (icon) icon.textContent = torchOn ? 'flashlight_off' : 'flashlight_on';
            els.torchBtn.title = torchOn ? t('scanner_torch_off') : t('scanner_torch_on');
        } catch (e) {
            torchOn = !torchOn;
        }
    }

    async function stopCamera() {
        torchOn = false;
        if (els.torchBtn) {
            els.torchBtn.hidden = true;
            els.torchBtn.querySelector('.material-icons-round').textContent = 'flashlight_on';
        }
        if (html5QrCode && isRunning) {
            try {
                await html5QrCode.stop();
            } catch (e) { /* */ }
            try {
                html5QrCode.clear();
            } catch (e) { /* */ }
        }
        html5QrCode = null;
        isRunning = false;
        els.viewport?.classList.remove('is-live');
        if (els.reader) els.reader.innerHTML = '';
        if (els.startBtn) els.startBtn.hidden = false;
        if (els.stopBtn) els.stopBtn.hidden = true;
        if (activeTab === 'camera') {
            setStatus('ready', isMobileDevice() ? t('scanner_allow_camera') : t('scanner_status_ready'));
        }
    }

    async function open() {
        if (!els.overlay) return;
        els.overlay.classList.add('active');
        els.overlay.setAttribute('aria-hidden', 'false');
        if (els.lastScan) els.lastScan.hidden = true;
        if (els.manualInput) els.manualInput.value = '';

        if (typeof Html5Qrcode === 'undefined') {
            setStatus('error', t('scanner_not_loaded'));
            return;
        }

        if (isMobileDevice()) {
            populateCameraSelect([], CAMERA_FACING_ENV);
            if (els.hint) {
                els.hint.innerHTML = `<span class="material-icons-round">photo_camera</span> ${t('scanner_allow_camera')}`;
            }
            setTab('camera');
            setStatus('ready', t('scanner_allow_camera'));
            await startCamera();
            loadCameras().catch(() => {});
            return;
        }

        if (els.hint) {
            els.hint.innerHTML = `<span class="material-icons-round">usb</span> ${t('scanner_usb_hint')}`;
        }
        setTab('camera');
        await loadCameras();
        await startCamera();
    }

    async function close() {
        await stopCamera();
        els.overlay?.classList.remove('active');
        els.overlay?.setAttribute('aria-hidden', 'true');
        setStatus('ready', t('scanner_status_ready'));
        if (els.hint) {
            els.hint.innerHTML = `<span class="material-icons-round">usb</span> ${t('scanner_usb_hint')}`;
        }
    }

    return { init, open, close, stopCamera };
})();

document.addEventListener('DOMContentLoaded', () => {
    InventoryScanner.init({
        i18n: window.INVENTORY_I18N || {},
        onScan: window.inventoryOnBarcodeScan,
    });
});
