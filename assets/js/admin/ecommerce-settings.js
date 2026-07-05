document.addEventListener('DOMContentLoaded', () => {
    const { t, updateLastUpdated, toast } = EcommerceUI;
    const form = document.getElementById('ecomSettingsForm');

    async function load() {
        const data = await AdminAPI.getEcommerceSettings();
        if (data.status !== 'ok') return;
        const settings = data.settings || {};
        const stores = data.stores || [];
        const paystackCurrencies = data.paystack_currencies || ['NGN', 'USD', 'GHS', 'ZAR', 'KES'];

        const storeSelect = document.getElementById('ecomDefaultStore');
        if (storeSelect) {
            storeSelect.innerHTML = stores.map((s) =>
                `<option value="${s.id}">${s.name}</option>`
            ).join('');
            storeSelect.value = settings.default_store_id || stores[0]?.id || '';
        }

        document.getElementById('ecomCurrency').value = settings.currency || 'EUR';
        document.getElementById('ecomTaxRate').value = settings.tax_rate ?? 0;

        const currencySelect = document.getElementById('ecomPaystackCurrency');
        if (currencySelect) {
            const current = settings.paystack_currency || '';
            currencySelect.innerHTML = `<option value="">${t('ecom_paystack_currency_auto')}</option>`
                + paystackCurrencies.map((c) => `<option value="${c}">${c}</option>`).join('');
            currencySelect.value = current;
        }

        document.getElementById('ecomPaystackEnabled').checked = !!Number(settings.paystack_enabled);
        document.getElementById('ecomPaystackPublic').value = settings.paystack_public_key || '';

        const secretInput = document.getElementById('ecomPaystackSecret');
        const secretHint = document.getElementById('ecomPaystackSecretHint');
        if (secretInput) {
            secretInput.value = '';
            secretInput.placeholder = settings.paystack_secret_key_set
                ? t('ecom_paystack_secret_placeholder')
                : 'sk_test_...';
        }
        if (secretHint && settings.paystack_secret_key_set) {
            secretHint.textContent = t('ecom_paystack_secret_saved');
        }

        updateLastUpdated();
    }

    form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(form);
        const payload = {
            default_store_id: Number(fd.get('default_store_id')),
            currency: fd.get('currency'),
            tax_rate: Number(fd.get('tax_rate')),
            paystack_enabled: fd.get('paystack_enabled') ? 1 : 0,
            paystack_public_key: fd.get('paystack_public_key'),
            paystack_currency: fd.get('paystack_currency'),
        };
        const secret = String(fd.get('paystack_secret_key') || '').trim();
        if (secret) {
            payload.paystack_secret_key = secret;
        }
        await AdminAPI.saveEcommerceSettings(payload);
        toast(t('ecom_saved'));
        load();
    });

    load();
    document.addEventListener('ecom:refresh', load);
});
