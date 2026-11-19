document.addEventListener('DOMContentLoaded', () => {
    const searchForm = document.getElementById('voucher-search-form');
    const voucherInput = document.getElementById('voucher-input');
    const lookupError = document.getElementById('voucher-lookup-error');

    const hiddenRef = document.getElementById('cashier_payment_voucherReference');
    const baseAmountDisplay = document.getElementById('base-amount-display');
    const finalAmountDisplay = document.getElementById('final-amount-display');
    const currencyBadges = document.querySelectorAll('.id-doc-currency');

    const discountInput = document.getElementById('discount-input');
    const discountType = document.getElementById('discount-type');
    const capturedAmountInput = document.getElementById('captured-amount');
    const capturedCurrencySelect = document.getElementById('captured-currency-select');

    const docNatureText = document.getElementById('doc-nature-text');
    const docRefText = document.getElementById('doc-ref-text');
    const docActor1Label = document.getElementById('doc-actor1-label');
    const docActor1Name = document.getElementById('doc-actor1-name');
    const docActor1Phone = document.getElementById('doc-actor1-phone');
    const docActor2Label = document.getElementById('doc-actor2-label');
    const docActor2Name = document.getElementById('doc-actor2-name');
    const docActor2Phone = document.getElementById('doc-actor2-phone');

    function formatMoney(val) {
        return new Intl.NumberFormat('fr-FR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(val);
    }

    function recalculateNet() {
        const rawBase = parseFloat(baseAmountDisplay?.dataset.raw || '0') || 0;
        const disc = Math.max(0, parseFloat(discountInput?.value || '0') || 0);
        const type = discountType?.value || 'percent';

        let net = rawBase;
        if (type === 'percent') {
            const pct = Math.min(100, disc);
            net = rawBase - (rawBase * pct / 100);
        } else {
            net = Math.max(0, rawBase - disc);
        }

        net = Math.round(net * 100) / 100;
        if (finalAmountDisplay) {
            finalAmountDisplay.value = formatMoney(net);
        }
        if (capturedAmountInput && rawBase > 0) {
            capturedAmountInput.value = net.toFixed(2);
        }
    }

    async function loadVoucher(ref) {
        const cleanRef = ref.trim().toUpperCase();
        if (!cleanRef || !searchForm) return;

        lookupError?.classList.add('is-hidden');
        const url = `${searchForm.dataset.lookupUrl}?ref=${encodeURIComponent(cleanRef)}`;

        try {
            const resp = await fetch(url, { headers: { 'Accept': 'application/json' } });
            const json = await resp.json();

            if (!resp.ok || !json.found) {
                if (lookupError) {
                    lookupError.textContent = json.message || 'Document introuvable.';
                    lookupError.classList.remove('is-hidden');
                }
                return;
            }

            const v = json.voucher;
            if (hiddenRef) hiddenRef.value = v.reference;
            if (baseAmountDisplay) {
                baseAmountDisplay.dataset.raw = String(v.amountRaw);
                baseAmountDisplay.value = formatMoney(v.amountRaw);
            }
            currencyBadges.forEach(el => { el.textContent = v.currencyCode; });

            if (capturedCurrencySelect && v.currencyId) {
                capturedCurrencySelect.value = String(v.currencyId);
            }

            if (docNatureText) docNatureText.textContent = v.natureLabel;
            if (docRefText) docRefText.textContent = v.reference;
            if (docActor1Label) docActor1Label.textContent = v.actor1Label;
            if (docActor1Name) docActor1Name.textContent = v.actor1Name;
            if (docActor1Phone) docActor1Phone.textContent = v.actor1Phone;
            if (docActor2Label) docActor2Label.textContent = v.actor2Label;
            if (docActor2Name) docActor2Name.textContent = v.actor2Name;
            if (docActor2Phone) docActor2Phone.textContent = v.actor2Phone;

            recalculateNet();
        } catch (err) {
            if (lookupError) {
                lookupError.textContent = 'Erreur réseau lors de la recherche du dossier.';
                lookupError.classList.remove('is-hidden');
            }
        }
    }

    searchForm?.addEventListener('submit', (e) => {
        e.preventDefault();
        loadVoucher(voucherInput?.value || '');
    });

    discountInput?.addEventListener('input', recalculateNet);
    discountType?.addEventListener('change', recalculateNet);

    if (voucherInput && voucherInput.value.trim() !== '') {
        loadVoucher(voucherInput.value);
    }
});
