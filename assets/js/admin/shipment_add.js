/**
 * Mydigitrans SaaS - Module Fret (Shipment)
 * Gestion dynamique de la facturation au poids ou par pièce avec saisie manuelle
 */
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('dynamic-shipment-form');
    if (!form) return;

    // Métadonnées système injectées par Twig
    const pricePerKg = parseFloat(form.getAttribute('data-price-per-kg')) || 0;
    const systemCurrency = form.getAttribute('data-system-currency') || 'USD';

    // Sélecteurs de formulaires
    const billingTypeSelect = document.getElementById('billing-type');
    const weightBlock = document.getElementById('weight-input-block');
    const weightInput = document.getElementById('package-weight');
    
    // Blocs manuels par pièce
    const unitManualBlock = document.getElementById('unit-manual-block');
    const priceManualInput = document.getElementById('price-manual-input');
    const currencyManualSelect = document.getElementById('currency-manual-select');

    // Éléments du volet de récapitulatif latéral
    const summaryBillingMode = document.getElementById('summary-billing-mode');
    const summaryUnitFare = document.getElementById('summary-unit-fare');
    const summaryTotalPrice = document.getElementById('summary-total-price');

    function calculateShipmentFare() {
        const mode = billingTypeSelect.value;
        let totalPrice = 0;
        let currentCurrency = systemCurrency;

        if (mode === 'weight') {
            // 1. Logique d'affichage et de validation au Poids
            weightBlock.classList.remove('is-hidden');
            unitManualBlock.classList.add('is-hidden');
            
            weightInput.required = true;
            priceManualInput.required = false;
            currencyManualSelect.required = false;

            summaryBillingMode.innerText = "Au Kg";
            summaryUnitFare.innerText = `${pricePerKg.toFixed(2)} ${systemCurrency} / Kg`;

            const weight = parseFloat(weightInput.value) || 0;
            totalPrice = weight * pricePerKg;
        } else {
            // 2. Logique d'affichage et de validation manuelle par Pièce
            weightBlock.classList.add('is-hidden');
            unitManualBlock.classList.remove('is-hidden');
            
            weightInput.required = false;
            priceManualInput.required = true;
            currencyManualSelect.required = true;

            summaryBillingMode.innerText = "Par Pièce (Forfait)";
            
            currentCurrency = currencyManualSelect.value;
            const manualPrice = parseFloat(priceManualInput.value) || 0;
            
            summaryUnitFare.innerText = "Fixé manuellement";
            totalPrice = manualPrice;
        }

        // Rendu formaté final
        const formattedTotal = new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(totalPrice);
        summaryTotalPrice.innerText = `${formattedTotal} ${currentCurrency}`;
    }

    // Attachement des écouteurs d'événements
    billingTypeSelect.addEventListener('change', calculateShipmentFare);
    weightInput.addEventListener('input', calculateShipmentFare);
    priceManualInput.addEventListener('input', calculateShipmentFare);
    currencyManualSelect.addEventListener('change', calculateShipmentFare);

    // Lancement au chargement de la vue
    calculateShipmentFare();
});
