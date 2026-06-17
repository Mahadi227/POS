/**
 * Inventory CSV import — upload, preview, validate, import
 */
(() => {
    const CFG = window.INVENTORY_CONFIG || { storeId: 1 };
    const i18n = window.INVENTORY_I18N || {};
    const MAX_ROWS = 500;

    const $ = (id) => document.getElementById(id);

    const HEADER_MAP = {
        name: ['name', 'nom', 'product_name', 'produit', 'product'],
        sku: ['sku', 'reference', 'ref', 'code'],
        barcode: ['barcode', 'code_barre', 'ean', 'upc'],
        category: ['category', 'categorie', 'catégorie', 'category_name'],
        price: ['price', 'prix', 'sale_price', 'prix_vente'],
        cost: ['cost', 'cout', 'coût', 'cost_price', 'prix_achat'],
        stock: ['stock', 'stock_quantity', 'quantity', 'qty', 'quantite', 'quantité'],
        min_stock: ['min_stock', 'min_stock_level', 'alerte', 'stock_min'],
        unit: ['unit', 'unite', 'unité'],
        expiry: ['expiry', 'expiry_date', 'expiration', 'date_expiration'],
    };

    const TEMPLATE_HEADERS = ['name', 'sku', 'barcode', 'category', 'price', 'cost', 'stock', 'min_stock', 'unit', 'expiry'];
    const TEMPLATE_SAMPLE = [
        ['Mineral Water 1.5L', 'BEV-001', '3760123456789', 'Beverages', '500', '300', '120', '10', 'piece', ''],
        ['Rice 5kg', 'GRO-002', '', 'Groceries', '4500', '3800', '45', '5', 'piece', ''],
    ];

    let currentStep = 1;
    let parsedRows = [];
    let previewData = null;
    let fileName = '';

    function t(key, ...args) {
        let str = i18n[key] || key;
        args.forEach((val) => {
            str = str.replace('%s', val);
        });
        return str;
    }

    function escapeHtml(s) {
        return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function toast(msg, type = 'success') {
        const el = $('invToast');
        if (!el) return;
        el.textContent = msg;
        el.className = `inv-toast show ${type}`;
        clearTimeout(toast._t);
        toast._t = setTimeout(() => el.classList.remove('show'), 3200);
    }

    function openModal() {
        $('importModalOverlay')?.classList.add('active');
    }

    function closeModal() {
        $('importModalOverlay')?.classList.remove('active');
    }

    function normalizeHeader(h) {
        return String(h || '').trim().toLowerCase().replace(/^\ufeff/, '').replace(/\s+/g, '_');
    }

    function mapHeaders(headers) {
        const map = {};
        headers.forEach((raw, idx) => {
            const h = normalizeHeader(raw);
            Object.entries(HEADER_MAP).forEach(([field, aliases]) => {
                if (aliases.includes(h) && map[field] === undefined) {
                    map[field] = idx;
                }
            });
        });
        return map;
    }

    function parseCsv(text) {
        const rows = [];
        let row = [];
        let cell = '';
        let inQuotes = false;

        for (let i = 0; i < text.length; i++) {
            const ch = text[i];
            const next = text[i + 1];
            if (inQuotes) {
                if (ch === '"' && next === '"') {
                    cell += '"';
                    i++;
                } else if (ch === '"') {
                    inQuotes = false;
                } else {
                    cell += ch;
                }
            } else if (ch === '"') {
                inQuotes = true;
            } else if (ch === ',') {
                row.push(cell);
                cell = '';
            } else if (ch === '\n' || (ch === '\r' && next === '\n')) {
                row.push(cell);
                if (row.some((c) => String(c).trim() !== '')) rows.push(row);
                row = [];
                cell = '';
                if (ch === '\r') i++;
            } else if (ch !== '\r') {
                cell += ch;
            }
        }
        if (cell !== '' || row.length) {
            row.push(cell);
            if (row.some((c) => String(c).trim() !== '')) rows.push(row);
        }
        return rows;
    }

    function rowsToObjects(csvRows) {
        if (!csvRows.length) return [];
        const headers = csvRows[0];
        const colMap = mapHeaders(headers);
        if (colMap.name === undefined || colMap.sku === undefined || colMap.price === undefined) {
            return null;
        }
        const out = [];
        for (let i = 1; i < csvRows.length; i++) {
            const r = csvRows[i];
            const obj = {};
            Object.entries(colMap).forEach(([field, idx]) => {
                obj[field] = (r[idx] ?? '').trim();
            });
            if (!obj.name && !obj.sku) continue;
            out.push({
                name: obj.name || '',
                sku: obj.sku || '',
                barcode: obj.barcode || '',
                category: obj.category || '',
                price: obj.price ?? '',
                cost: obj.cost ?? '0',
                stock_quantity: obj.stock ?? '0',
                min_stock_level: obj.min_stock ?? '5',
                unit: obj.unit || 'piece',
                expiry_date: obj.expiry || '',
            });
        }
        return out;
    }

    function validateRowLocal(row) {
        if (!row.name) return t('error') + ': name';
        if (!row.sku) return t('error') + ': SKU';
        if (row.price === '' || isNaN(Number(row.price)) || Number(row.price) < 0) return t('error') + ': price';
        return null;
    }

    function setStep(step) {
        currentStep = step;
        document.querySelectorAll('.inv-import__step').forEach((el) => {
            el.classList.toggle('active', Number(el.dataset.step) <= step);
            el.classList.toggle('done', Number(el.dataset.step) < step);
        });
        $('importPanelUpload').hidden = step !== 1;
        $('importPanelPreview').hidden = step !== 2;
        $('importPanelResult').hidden = step !== 3;
        $('importBackBtn').hidden = step <= 1;
        $('importCancelBtn').textContent = step === 3 ? t('import_close') : (i18n.cancel || 'Cancel');

        const nextBtn = $('importNextBtn');
        if (step === 1) {
            nextBtn.textContent = t('import_preview_btn');
            nextBtn.disabled = parsedRows.length === 0;
        } else if (step === 2) {
            nextBtn.textContent = t('import_run_btn');
            nextBtn.disabled = false;
        } else {
            nextBtn.hidden = true;
        }
        if (step < 3) nextBtn.hidden = false;
    }

    function resetImport() {
        parsedRows = [];
        previewData = null;
        fileName = '';
        $('importFileInput').value = '';
        $('importFileName').textContent = '';
        $('importPreviewBody').innerHTML = '';
        $('importResultBody').innerHTML = '';
        $('importResultCards').innerHTML = '';
        $('importProgressWrap').hidden = true;
        $('importResultTableWrap').hidden = true;
        setStep(1);
    }

    function handleFile(file) {
        if (!file) return;
        if (!/\.csv$/i.test(file.name) && file.type !== 'text/csv') {
            toast(t('import_parse_error'), 'error');
            return;
        }
        const reader = new FileReader();
        reader.onload = (e) => {
            try {
                const csvRows = parseCsv(e.target.result || '');
                const objects = rowsToObjects(csvRows);
                if (!objects) {
                    toast(t('import_parse_error'), 'error');
                    return;
                }
                if (!objects.length) {
                    toast(t('import_empty_file'), 'error');
                    return;
                }
                if (objects.length > MAX_ROWS) {
                    toast(t('import_max_rows', MAX_ROWS), 'error');
                    return;
                }
                parsedRows = objects;
                fileName = file.name;
                $('importFileName').textContent = `${fileName} — ${t('import_rows_detected', objects.length)}`;
                $('importNextBtn').disabled = false;
                $('importDropzone')?.classList.add('has-file');
            } catch (err) {
                console.error(err);
                toast(t('import_parse_error'), 'error');
            }
        };
        reader.readAsText(file, 'UTF-8');
    }

    function renderPreviewTable(preview) {
        const tbody = $('importPreviewBody');
        if (!tbody) return;
        const items = preview?.preview || [];
        tbody.innerHTML = items.slice(0, 100).map((item, idx) => {
            const row = parsedRows[item.line - 1] || parsedRows[idx] || {};
            const isErr = item.status === 'error';
            return `<tr class="${isErr ? 'inv-import__row--error' : ''}">
                <td>${item.line}</td>
                <td>${escapeHtml(row.name || item.name)}</td>
                <td>${escapeHtml(row.sku || item.sku)}</td>
                <td>${escapeHtml(row.category || '—')}</td>
                <td>${escapeHtml(row.price ?? '')}</td>
                <td>${escapeHtml(row.stock_quantity ?? '')}</td>
                <td><span class="inv-import__badge ${isErr ? 'error' : 'ok'}">${escapeHtml(isErr ? t('import_status_error') : t('import_status_ok'))}</span></td>
            </tr>`;
        }).join('');
        if (items.length > 100) {
            tbody.innerHTML += `<tr><td colspan="7" class="ad-empty-row">+ ${items.length - 100} more…</td></tr>`;
        }
    }

    function renderPreviewSummary(data) {
        const el = $('importPreviewSummary');
        if (!el || !data) return;
        el.innerHTML = `
            <div class="inv-import__stat ok"><strong>${data.created}</strong><span>${escapeHtml(t('import_action_create'))}</span></div>
            <div class="inv-import__stat ok"><strong>${data.updated}</strong><span>${escapeHtml(t('import_action_update'))}</span></div>
            <div class="inv-import__stat ${data.skipped ? 'warn' : ''}"><strong>${data.skipped}</strong><span>${escapeHtml(t('import_skipped'))}</span></div>`;
    }

    function renderResult(data) {
        $('importProgressWrap').hidden = true;
        $('importResultTableWrap').hidden = false;
        const d = data?.data || data || {};
        $('importResultCards').innerHTML = `
            <div class="inv-import__result-card success">
                <span class="material-icons-round">add_circle</span>
                <div><strong>${d.created ?? 0}</strong><span>${escapeHtml(t('import_created'))}</span></div>
            </div>
            <div class="inv-import__result-card info">
                <span class="material-icons-round">sync</span>
                <div><strong>${d.updated ?? 0}</strong><span>${escapeHtml(t('import_updated'))}</span></div>
            </div>
            <div class="inv-import__result-card ${d.skipped ? 'warn' : ''}">
                <span class="material-icons-round">warning</span>
                <div><strong>${d.skipped ?? 0}</strong><span>${escapeHtml(t('import_skipped'))}</span></div>
            </div>`;

        const tbody = $('importResultBody');
        const items = d.preview || d.errors || [];
        tbody.innerHTML = items.map((item) => {
            const isErr = item.status === 'error';
            const action = item.action === 'update' ? t('import_action_update') : (item.action === 'create' ? t('import_action_create') : '—');
            return `<tr class="${isErr ? 'inv-import__row--error' : ''}">
                <td>${item.line}</td>
                <td>${escapeHtml(item.sku)}</td>
                <td>${escapeHtml(item.name)}</td>
                <td>${escapeHtml(action)}</td>
                <td>${escapeHtml(item.message || '')}</td>
            </tr>`;
        }).join('');
    }

    async function runDryRun() {
        const nextBtn = $('importNextBtn');
        nextBtn.disabled = true;
        nextBtn.textContent = t('import_validating');
        const payload = {
            store_id: CFG.storeId,
            dry_run: true,
            update_existing: $('importUpdateExisting')?.checked !== false,
            create_categories: $('importCreateCategories')?.checked !== false,
            rows: parsedRows,
        };
        const result = await AdminAPI.importProducts(payload);
        nextBtn.disabled = false;
        if (result.status !== 'success') {
            toast(result.error || result.message || t('error'), 'error');
            nextBtn.textContent = t('import_preview_btn');
            return false;
        }
        previewData = result.data;
        renderPreviewSummary(result.data);
        renderPreviewTable(result.data);
        return true;
    }

    async function runImport() {
        const nextBtn = $('importNextBtn');
        nextBtn.disabled = true;
        $('importProgressWrap').hidden = false;
        $('importProgressFill').style.width = '30%';
        $('importProgressText').textContent = t('import_running', '30');

        const payload = {
            store_id: CFG.storeId,
            dry_run: false,
            update_existing: $('importUpdateExisting')?.checked !== false,
            create_categories: $('importCreateCategories')?.checked !== false,
            rows: parsedRows,
        };

        $('importProgressFill').style.width = '70%';
        $('importProgressText').textContent = t('import_running', '70');

        const result = await AdminAPI.importProducts(payload);
        $('importProgressFill').style.width = '100%';
        $('importProgressText').textContent = t('import_done');

        if (result.status !== 'success') {
            toast(result.error || result.message || t('error'), 'error');
            nextBtn.disabled = false;
            return;
        }

        renderResult(result);
        setStep(3);
        toast(t('import_success_toast', result.data?.created ?? 0, result.data?.updated ?? 0));

        document.dispatchEvent(new CustomEvent('inventory-imported'));
    }

    function downloadTemplate() {
        const lines = [
            TEMPLATE_HEADERS.join(','),
            ...TEMPLATE_SAMPLE.map((r) => r.map((c) => (String(c).includes(',') ? `"${c}"` : c)).join(',')),
        ];
        const blob = new Blob(['\ufeff' + lines.join('\n')], { type: 'text/csv;charset=utf-8' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'inventory_import_template.csv';
        a.click();
        URL.revokeObjectURL(a.href);
    }

    function init() {
        $('importBtn')?.addEventListener('click', () => {
            resetImport();
            openModal();
        });

        $('closeImportModalBtn')?.addEventListener('click', closeModal);
        $('importCancelBtn')?.addEventListener('click', () => {
            closeModal();
            if (currentStep === 3) document.dispatchEvent(new CustomEvent('inventory-imported'));
        });

        $('importBackBtn')?.addEventListener('click', () => {
            if (currentStep === 2) setStep(1);
        });

        $('importNextBtn')?.addEventListener('click', async () => {
            if (currentStep === 1) {
                if (!parsedRows.length) {
                    toast(t('import_no_file'), 'error');
                    return;
                }
                const ok = await runDryRun();
                if (ok) setStep(2);
            } else if (currentStep === 2) {
                setStep(3);
                $('importPanelResult').hidden = false;
                $('importResultCards').innerHTML = '';
                $('importResultTableWrap').hidden = true;
                await runImport();
            }
        });

        $('importTemplateBtn')?.addEventListener('click', downloadTemplate);

        const dropzone = $('importDropzone');
        const fileInput = $('importFileInput');
        fileInput?.addEventListener('change', (e) => handleFile(e.target.files?.[0]));

        dropzone?.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropzone.classList.add('dragover');
        });
        dropzone?.addEventListener('dragleave', () => dropzone.classList.remove('dragover'));
        dropzone?.addEventListener('drop', (e) => {
            e.preventDefault();
            dropzone.classList.remove('dragover');
            handleFile(e.dataTransfer?.files?.[0]);
        });

        $('importModalOverlay')?.addEventListener('click', (e) => {
            if (e.target === $('importModalOverlay')) closeModal();
        });

        document.addEventListener('inventory-imported', () => {
            if (typeof window.inventoryRefreshAll === 'function') {
                window.inventoryRefreshAll();
            }
        });
    }

    document.addEventListener('DOMContentLoaded', init);
})();
