document.addEventListener('DOMContentLoaded', () => {
    const tripSelector = document.getElementById('shipment-trip-selector');
    const natureSelector = document.getElementById('package-nature');
    const weightInput = document.getElementById('package-weight');

    const summaryWeight = document.getElementById('summary-weight');
    const summaryNature = document.getElementById('summary-nature');
    const summaryTotalPrice = document.getElementById('summary-total-price');

    function calculateDynamicShipment() {
        const weight = parseFloat(weightInput.value) || 0;
        
        // 1. Récupération du prix au kilo configuré sur la route du voyage sélectionné
        const selectedTrip = tripSelector.options[tripSelector.selectedIndex];
        const pricePerKg = selectedTrip ? parseFloat(selectedTrip.getAttribute('data-route-price-kg')) : 0;

        // 2. Récupération du surplus lié à la dangerosité ou fragilité de la marchandise
        const selectedNature = natureSelector.options[natureSelector.selectedIndex];
        const natureSurplus = selectedNature ? parseFloat(selectedNature.getAttribute('data-nature-surplus')) : 0;

        // Validation des étiquettes textuelles du bord droit
        summaryWeight.textContent = `${weight} Kg`;
        summaryNature.textContent = selectedNature ? selectedNature.text.split(' (')[0] : 'Standard';

        if (weight === 0 || pricePerKg === 0) {
            summaryTotalPrice.textContent = '0 CDF';
            return;
        }

        // 3. Calcul de la formule mathématique du fret
        const finalFretPrice = (weight * pricePerKg) + natureSurplus;

        summaryTotalPrice.textContent = `${finalFretPrice.toLocaleString()} CDF`;
    }

    // Écouteurs d'événements pour mettre à jour la facture de fret en direct
    if (tripSelector) tripSelector.addEventListener('change', calculateDynamicShipment);
    if (natureSelector) natureSelector.addEventListener('change', calculateDynamicShipment);
    if (weightInput) weightInput.addEventListener('input', calculateDynamicShipment);
});
