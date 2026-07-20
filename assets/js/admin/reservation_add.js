document.addEventListener('DOMContentLoaded', () => {
    const seatInput = document.getElementById('selected-seat-input-backoffice');
    const summarySeatNumber = document.getElementById('summary-seat-number');
    const summaryTotalPrice = document.getElementById('summary-total-price');
    const tripSelector = document.getElementById('trip-selector-backoffice');

    // On cible les sièges générés par votre inclusion Twig
    // (Ajustez la classe si vos cases de sièges portent un autre nom dans system/seatmap.html.twig)
    const seatmapContainer = document.querySelector('.seatmap-matrix') || document.querySelector('.bus-seats-grid-layout');

    if (seatmapContainer) {
        seatmapContainer.addEventListener('click', (e) => {
            // On cherche si l'élément cliqué est un siège disponible
            const clickedSeat = e.target.closest('.seat-grid-cell.available') || e.target.closest('.seat-cell-item.available');
            
            if (!clickedSeat) return;

            // Retirer l'ancienne sélection graphique
            const previousSelected = seatmapContainer.querySelector('.selected');
            if (previousSelected) previousSelected.classList.remove('selected');

            // Activer le nouveau siège cliqué
            clickedSeat.classList.add('selected');

            // Récupérer le numéro écrit dans la cellule
            const seatNumber = clickedSeat.textContent.trim();
            
            // Mettre à jour le formulaire caché et le récapitulatif de caisse
            seatInput.value = seatNumber;
            summarySeatNumber.textContent = `N° ${seatNumber}`;

            // Calcul de tarif fictif
            let finalPrice = 45000; 
            summaryTotalPrice.textContent = `${finalPrice.toLocaleString()} CDF`;
        });
    }
});
