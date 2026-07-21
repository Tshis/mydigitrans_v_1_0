document.addEventListener('DOMContentLoaded', () => {
    const shipmentForm = document.getElementById('dynamic-shipment-form');
    if (!shipmentForm) return;

    // --- EXTRACTION DES MATRICES DE PRIX TWIG ---
    const weightMatrix = JSON.parse(shipmentForm.getAttribute('data-weight-pricing'));
    const unitMatrix = JSON.parse(shipmentForm.getAttribute('data-unit-pricing'));
    const natureMatrix = JSON.parse(shipmentForm.getAttribute('data-nature-pricing'));
    const currencyCode = shipmentForm.getAttribute('data-currency-code');

    const departureAgency = document.getElementById('departure-agency');
    const arrivalAgency = document.getElementById('arrival-agency');
    const packageNature = document.getElementById('package-nature');
    const billingType = document.getElementById('billing-type');
    
    const weightInputBlock = document.getElementById('weight-input-block');
    const weightInput = document.getElementById('package-weight');
    
    const unitInputBlock = document.getElementById('unit-input-block');
    const unitSelect = document.getElementById('package-unit-type');

    const summaryBillingMode = document.getElementById('summary-billing-mode');
    const summaryNature = document.getElementById('summary-nature');
    const summaryTotalPrice = document.getElementById('summary-total-price');

    // --- INTERACTION 1 : BASCULE DYNAMIQUE (POIDS VS FORFAIT) ---
    billingType.addEventListener('change', () => {
        if (billingType.value === 'unit') {
            weightInputBlock.classList.add('is-hidden');
            unitInputBlock.classList.remove('is-hidden');
            summaryBillingMode.textContent = "Forfaitaire";
            weightInput.value = ""; // Réinitialise le poids
        } else {
            weightInputBlock.classList.remove('is-hidden');
            unitInputBlock.classList.add('is-hidden');
            summaryBillingMode.textContent = "Au Kg";
        }
        calculateFretPrice();
    });

    // --- INTERACTION 2 : CALCULATEUR MÉTIER ---
    function calculateFretPrice() {
        const start = departureAgency.value;
        const end = arrivalAgency.value;
        const nature = packageNature.value;
        
        let basePrice = 0;

        // Récupération de la surcharge de nature depuis Twig
        const surplus = natureMatrix[nature] || 0;
        summaryNature.textContent = packageNature.options[packageNature.selectedIndex].text;

        if (billingType.value === 'weight') {
            // Mode A : Facturation au poids (Segment * Poids Kg)
            const weight = parseFloat(weightInput.value) || 0;
            const segmentKey = `${start}-${end}`;
            const pricePerKg = weightMatrix[segmentKey] || 0;
            basePrice = weight * pricePerKg;
        } else {
            // Mode B : Facturation forfaitaire par type de colis
            const unitType = unitSelect.value;
            basePrice = unitMatrix[unitType] || 0;
        }

        // Somme finale brute
        const finalFret = basePrice > 0 ? basePrice + surplus : 0;

        // Rendu final dynamique
        summaryTotalPrice.textContent = `${finalFret.toLocaleString()} ${currencyCode}`;
    }

    // Association des écouteurs
    departureAgency.addEventListener('change', calculateFretPrice);
    arrivalAgency.addEventListener('change', calculateFretPrice);
    packageNature.addEventListener('change', calculateFretPrice);
    weightInput.addEventListener('input', calculateFretPrice);
    unitSelect.addEventListener('change', calculateFretPrice);
});
