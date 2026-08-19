document.addEventListener('DOMContentLoaded', () => {
    const routePicker = document.getElementById('js-master-route-picker');
    const form = document.getElementById('bulk-fare-form');

    // 1. Changement de route
    if (routePicker) {
        routePicker.addEventListener('change', (e) => {
            const routeId = e.target.value;
            // On redirige vers la page de la route sélectionnée
            window.location.href = `/admin/agency/line/${routeId}/fare-matrix`;
        });
    }

    // 2. Sécurité de saisie
    if (form) {
        form.addEventListener('submit', function(e) {
            const directPriceInput = document.querySelector('input[name="direct_amount"]');
            const directPrice = parseFloat(directPriceInput.value) || 0;
            const segmentPrices = document.querySelectorAll('.matrix-input[name*="[amount]"]');
            
            let hasError = false;
            segmentPrices.forEach(input => {
                const val = parseFloat(input.value) || 0;
                if (val > directPrice && directPrice > 0) {
                    input.style.borderColor = '#d94516';
                    hasError = true;
                } else {
                    input.style.borderColor = '';
                }
            });

            if (hasError) {
                if (!confirm("Attention : Certains tronçons sont plus chers que le trajet direct. Confirmer l'enregistrement ?")) {
                    e.preventDefault();
                }
            }
        });
    }
});
