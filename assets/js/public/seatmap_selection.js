document.addEventListener('DOMContentLoaded', () => {
    const seatRadios = document.querySelectorAll('.js-seat-btn'); 
    const summaryBadge = document.getElementById('js-summary-seat-badge');
    
    // On sélectionne TOUS les inputs masqués générés par Symfony 8 pour la collection de sièges
    const allSeatInputs = document.querySelectorAll('input[name^="booking[seats]"]');
    const maxPassengersCount = allSeatInputs.length; 

    seatRadios.forEach(radio => {
        radio.addEventListener('change', () => {
            // Récupérer la liste de tous les sièges cochés
            const checkedBoxes = document.querySelectorAll('.js-seat-btn:checked');
            let selectedSeats = Array.from(checkedBoxes).map(cb => cb.getAttribute('data-seat-label'));

            // Sécurité : Si l'utilisateur dépasse son quota, on décoche le dernier cliqué
            if (selectedSeats.length > maxPassengersCount) {
                alert(`Vous ne pouvez sélectionner que ${maxPassengersCount} siège(s) pour cette réservation.`);
                radio.checked = false;
                return;
            }

            // Mise à jour de l'affichage dans la barre latérale (ex: "5, 6")
            if (summaryBadge) {
                summaryBadge.innerText = selectedSeats.length > 0 ? selectedSeats.join(', ') : 'Aucun choisi';
            }

            // ◄ ALIGNEMENT SYMFONY 8 : On distribue les valeurs dans chaque case de la collection
            allSeatInputs.forEach((input, index) => {
                if (selectedSeats[index]) {
                    input.value = selectedSeats[index]; // ex: "5" puis "6"
                } else {
                    input.value = ''; // On nettoie si l'utilisateur a décoché
                }
            });
        });
    });
});
