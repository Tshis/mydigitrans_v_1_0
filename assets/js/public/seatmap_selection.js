document.addEventListener('DOMContentLoaded', () => {
    const seatRadios = document.querySelectorAll('.js-seat-btn'); 
    const summaryBadge = document.getElementById('js-summary-seat-badge');
    
    const allSeatInputs = document.querySelectorAll('input[id^="js-seat-input-"], #js-selected-seat-input');
    
    // ◄ SÉCURITÉ JAVASCRIPT : Si on n'est pas dans le tunnel de réservation, on coupe le script
    if (allSeatInputs.length === 0) {
        return; 
    }

    const maxPassengersCount = allSeatInputs.length; 

    seatRadios.forEach(radio => {

         radio.addEventListener('change', (e) => {
        // ◄ SÉCURITÉ ABSOLUE : Si l'input est désactivé par Twig, on annule immédiatement
        if (radio.disabled || radio.classList.contains('disabled')) {
            radio.checked = false;
            return;
        }
            const checkedBoxes = document.querySelectorAll('.js-seat-btn:checked');
            let selectedSeats = Array.from(checkedBoxes).map(cb => cb.getAttribute('data-seat-label'));

            if (selectedSeats.length > maxPassengersCount) {
                alert(`Vous ne pouvez sélectionner que ${maxPassengersCount} siège(s) pour cette réservation.`);
                radio.checked = false;
                return;
            }

            if (summaryBadge) {
                summaryBadge.innerText = selectedSeats.length > 0 ? selectedSeats.join(', ') : 'Aucun choisi';
            }

            const mainInput = document.getElementById('js-selected-seat-input');
            if (mainInput) {
                mainInput.value = selectedSeats[0] ? selectedSeats[0] : '';
            }

            for (let i = 1; i < maxPassengersCount; i++) {
                const extraInput = document.getElementById(`js-seat-input-${i}`);
                if (extraInput) {
                    extraInput.value = selectedSeats[i] ? selectedSeats[i] : '';
                }
            }
        });
    });
});
