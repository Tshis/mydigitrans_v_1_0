document.addEventListener('DOMContentLoaded', () => {
    const bookingForm = document.getElementById('dynamic-booking-form');
    if (!bookingForm) return;

    // --- EXTRACTION SÉCURISÉE DES DONNÉES INJECTÉES PAR TWIG ---
    const pricingMatrix = JSON.parse(bookingForm.getAttribute('data-pricing'));
    const fareCategories = JSON.parse(bookingForm.getAttribute('data-fares'));
    const currencyCode = bookingForm.getAttribute('data-currency-code'); // Ex: "USD"


    const seatInput = document.getElementById('selected-seat-input-backoffice');
    const departureSelector = document.getElementById('departure-stop');
    const arrivalSelector = document.getElementById('arrival-stop');
    
    const summarySeatNumber = document.getElementById('summary-seat-number');
    const summaryFareType = document.getElementById('summary-fare-type');
    const summaryTotalPrice = document.getElementById('summary-total-price');
    const seatmapMatrix = document.querySelector('.seatmap-matrix');

    let currentSelectedSeatType = 'seat'; // 'seat' ou 'vip'

    if (seatmapMatrix) {
        seatmapMatrix.addEventListener('change', (e) => {
            if (e.target.classList.contains('seat-radio')) {
                const label = seatmapMatrix.querySelector(`label[for="${e.target.id}"]`);
                if (!label) return;

                const seatLabelNumber = label.querySelector('.-number').textContent.trim();
                currentSelectedSeatType = label.getAttribute('data-type'); // Extrait 'vip' ou 'seat'

                seatInput.value = e.target.value;
                summarySeatNumber.textContent = `N° ${seatLabelNumber}`;
                summaryFareType.textContent = currentSelectedSeatType === 'vip' ? 'VIP' : 'Standard';

                calculateDynamicFare();
            }
        });
    }

    if (departureSelector) departureSelector.addEventListener('change', calculateDynamicFare);
    if (arrivalSelector) arrivalSelector.addEventListener('change', calculateDynamicFare);

    function calculateDynamicFare() {
        if (!seatInput.value) return;

        const startStop = departureSelector.value;
        const endStop = arrivalSelector.value;
        
        // Clé combinée pour faire la recherche dans l'objet JSON (ex: "1-3")
        const segmentKey = `${startStop}-${endStop}`;

        // 1. Récupération du prix de base du segment depuis Twig (ou repli par défaut)
        const baseSegmentPrice = pricingMatrix[segmentKey] || 0;

        // 2. Récupération de la majoration de catégorie depuis Twig (vip ou seat)
        const categorySurplus = fareCategories[currentSelectedSeatType] || 0;

        // 3. Somme finale calculée dynamiquement
        const finalPrice = baseSegmentPrice + categorySurplus;

        summaryTotalPrice.textContent = `${finalPrice.toLocaleString()} ${currencyCode}`;
    }
});
