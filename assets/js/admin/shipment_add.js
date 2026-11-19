/**
 * Mydigitrans SaaS - Module Fret (Shipment)
 * Gestion dynamique de la facturation au poids (par segment réel) ou par pièce avec saisie manuelle
 */
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('dynamic-shipment-form');
    if (!form) return;

    const tripsMeta = JSON.parse(form.getAttribute('data-trips-meta') || '{}');
    const systemCurrency = form.getAttribute('data-base-currency') || 'CDF';

    // IDs générés automatiquement par Symfony Form (ShipmentCreateType)
    const tripSelect = document.getElementById('shipment_create_trip');
    const depStopSelect = document.getElementById('shipment_create_departureStop');
    const arrStopSelect = document.getElementById('shipment_create_arrivalStop');

    const billingTypeSelect = document.getElementById('shipment_create_billingType');
    const weightBlock = document.getElementById('weight-input-block');
    const weightInput = document.getElementById('shipment_create_weight');

    const unitManualBlock = document.getElementById('unit-manual-block');
    const priceManualInput = document.getElementById('shipment_create_priceManual');
    const currencyManualSelect = document.getElementById('shipment_create_currencyManual');

    const summaryBillingMode = document.getElementById('summary-billing-mode');
    const summaryUnitFare = document.getElementById('summary-unit-fare');
    const summaryTotalPrice = document.getElementById('summary-total-price');

    function filterStopsForTrip() {
        const tripId = tripSelect ? tripSelect.value : '';
        const meta = tripsMeta[tripId] || null;
        const allowedIds = meta ? meta.stopIds.map(String) : [];

        [depStopSelect, arrStopSelect].forEach((sel, sIdx) => {
            if (!sel) return;
            let firstVisible = null;
            let lastVisible = null;

            Array.from(sel.options).forEach((opt) => {
                if (opt.value === '') return;
                const visible = allowedIds.length === 0 || allowedIds.includes(opt.value);
                opt.hidden = !visible;
                opt.disabled = !visible;
                if (visible) {
                    if (!firstVisible) firstVisible = opt;
                    lastVisible = opt;
                }
            });

            const currentOpt = sel.selectedOptions ? sel.selectedOptions : null;
            if (!currentOpt || currentOpt.value === '' || currentOpt.disabled) {
                const target = sIdx === 0 ? firstVisible : lastVisible;
                if (target) {
                    sel.value = target.value;
                    if (sel.tomselect) {
                        sel.tomselect.setValue(target.value, true);
                    }
                }
            }

            if (sel.tomselect) {
                sel.tomselect.sync();
            }
        });

        calculateShipmentFare();
    }

    function calculateShipmentFare() {
        const mode = billingTypeSelect ? billingTypeSelect.value : 'weight';
        let totalPrice = 0;
        let currentCurrency = systemCurrency;

        if (mode === 'weight') {
            weightBlock?.classList.remove('is-hidden');
            unitManualBlock?.classList.add('is-hidden');

            if (weightInput) weightInput.required = true;
            if (priceManualInput) priceManualInput.required = false;
            if (currencyManualSelect) currencyManualSelect.required = false;

            const tripId = tripSelect ? tripSelect.value : '';
            const meta = tripsMeta[tripId] || null;
            let rate = meta ? (parseFloat(meta.pricePerKg) || 0) : 0;

            if (meta && meta.segmentRates && depStopSelect && arrStopSelect) {
                const key = `${depStopSelect.value}-${arrStopSelect.value}`;
                if (meta.segmentRates[key]) {
                    rate = parseFloat(meta.segmentRates[key].rate) || 0;
                    currentCurrency = meta.segmentRates[key].currency || systemCurrency;
                }
            }

            if (summaryBillingMode) summaryBillingMode.innerText = 'Au Kg';
            if (summaryUnitFare) summaryUnitFare.innerText = `${rate.toFixed(2)} ${currentCurrency} / Kg`;

            const weight = parseFloat(weightInput?.value || '0') || 0;
            totalPrice = weight * rate;
        } else {
            weightBlock?.classList.add('is-hidden');
            unitManualBlock?.classList.remove('is-hidden');

            if (weightInput) weightInput.required = false;
            if (priceManualInput) priceManualInput.required = true;
            if (currencyManualSelect) currencyManualSelect.required = true;

            if (summaryBillingMode) summaryBillingMode.innerText = 'Par Pièce (Forfait)';

            const selectedOpt = currencyManualSelect?.selectedOptions?.[0];

            currentCurrency = selectedOpt && selectedOpt.text ? selectedOpt.text.trim() : systemCurrency;
            const manualPrice = parseFloat(priceManualInput?.value || '0') || 0;

            if (summaryUnitFare) summaryUnitFare.innerText = 'Fixé manuellement';
            totalPrice = manualPrice;
        }

        const formattedTotal = new Intl.NumberFormat('fr-FR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(totalPrice);

        if (summaryTotalPrice) summaryTotalPrice.innerText = `${formattedTotal} ${currentCurrency}`;
    }

    tripSelect?.addEventListener('change', filterStopsForTrip);
    depStopSelect?.addEventListener('change', calculateShipmentFare);
    arrStopSelect?.addEventListener('change', calculateShipmentFare);
    billingTypeSelect?.addEventListener('change', calculateShipmentFare);
    weightInput?.addEventListener('input', calculateShipmentFare);
    priceManualInput?.addEventListener('input', calculateShipmentFare);
    currencyManualSelect?.addEventListener('change', calculateShipmentFare);

    // Attendre que TomSelect termine son initialisation asynchrone
    setTimeout(filterStopsForTrip, 80);
});
