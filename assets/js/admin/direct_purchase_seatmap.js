// public/js/admin/direct_purchase_seatmap.js

document.addEventListener('DOMContentLoaded', () => {
    const seatRadios = document.querySelectorAll('.seat-radio');
    const summarySeatBadge = document.querySelector('#summary-seat-number');
    const summaryPriceDisplay = document.querySelector('#summary-total-price');

    seatRadios.forEach(radio => {
        radio.addEventListener('change', () => {
            if (radio.checked) {
                // Extrait le label associé à l'input radio de ta matrice
                const label = document.querySelector(`label[for="${radio.id}"]`);
                const seatLabel = label ? label.querySelector('.-number').textContent.trim() : "00";
                
                // Si la cellule est taguée VIP, on applique ton prix de classe
                const isVip = label && label.getAttribute('data-type') === 'vip';
                const finalPrice = isVip ? "45.00" : "35.00";

                if (summarySeatBadge && summaryPriceDisplay) {
                    summarySeatBadge.textContent = `N° ${seatLabel}`;
                    summaryPriceDisplay.textContent = `${parseFloat(finalPrice).toFixed(2)} USD`;
                }
            }
        });
    });
});
