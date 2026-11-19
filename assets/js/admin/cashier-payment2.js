/**
 * Mydigitrans SaaS - Module Espace Caissier
 * Gestion des encaissements croisés et co-paiements (Multi-devises RDC)
 */
document.addEventListener('DOMContentLoaded', () => {
    const searchForm = document.getElementById('voucher-search-form');
    const voucherInput = document.getElementById('voucher-input');
    const paymentContainer = document.getElementById('cashier-payment-container');

    const docNatureText = document.getElementById('doc-nature-text');
    const docRefText = document.getElementById('doc-ref-text');
    const baseAmountDisplay = document.getElementById('base-amount-display');
    const discountInput = document.getElementById('discount-input');
    const discountType = document.getElementById('discount-type');
    const finalAmountDisplay = document.getElementById('final-amount-display');

    const docCurrencyBadges = document.querySelectorAll('.id-doc-currency');
    const currentDocCurrencySpan = document.querySelector('.js-current-doc-currency');

    // Les deux nouveaux champs d'encaissement réel libre
    const capturedAmountInput = document.getElementById('captured-amount');
    const capturedCurrencySelect = document.getElementById('captured-currency-select');

    let baseValue = 45000;
    let currentCurrencyCode = 'CDF'; 

    if (paymentContainer) paymentContainer.style.display = 'none';

    if (searchForm) {
        searchForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const query = voucherInput.value.trim().toLowerCase();

            if (paymentContainer) paymentContainer.style.display = 'grid';

            if (query.includes('colis') || query.includes('col')) { 
                docNatureText.innerText = "BON D'EXPÉDITION COLIS (FRET)";
                docRefText.innerText = "SHP-2026-0402";
                baseValue = 25.00;
                currentCurrencyCode = 'USD';
            } else { 
                docNatureText.innerText = "BON DE RÉSERVATION PASSAGER";
                docRefText.innerText = "RSV-2026-8943";
                baseValue = 45000;
                currentCurrencyCode = 'CDF';
            }

            baseAmountDisplay.value = new Intl.NumberFormat('fr-FR').format(baseValue);

            if (currentDocCurrencySpan) currentDocCurrencySpan.innerText = currentCurrencyCode;
            docCurrencyBadges.forEach(badge => badge.innerText = currentCurrencyCode);

            // Pré-remplissage indicatif par confort, mais l'agent peut modifier librement !
            if (capturedAmountInput) capturedAmountInput.value = baseValue;
            if (capturedCurrencySelect) capturedCurrencySelect.value = currentCurrencyCode;

            if (discountInput) discountInput.value = '';
            recalculateTotal();
        });
    }

    function recalculateTotal() {
        if (!discountInput || !finalAmountDisplay) return;

        let discount = parseFloat(discountInput.value) || 0;
        let finalValue = baseValue;

        if (discount < 0) {
            discount = 0;
            discountInput.value = 0;
        }

        if (discountType.value === 'percent') { 
            if (discount > 100) {
                discount = 100;
                discountInput.value = 100;
            }
            finalValue = baseValue - (baseValue * (discount / 100));
        } else { 
            if (discount > baseValue) {
                discount = baseValue;
                discountInput.value = baseValue;
            }
            finalValue = baseValue - discount;
        }

        finalAmountDisplay.value = new Intl.NumberFormat('fr-FR').format(finalValue);
        
        // Si aucune remise n'est touchée, on synchronise la suggestion de perception
        if (discount === 0 && capturedAmountInput && capturedCurrencySelect.value === currentCurrencyCode) {
            capturedAmountInput.value = finalValue;
        }
    }

    if (discountInput) discountInput.addEventListener('input', recalculateTotal);
    if (discountType) discountType.addEventListener('change', recalculateTotal);
});
