// Variable globale ou d'instance pour suivre le siège sélectionné
let selectedSeatElement = null;

function selectSeat(element) {
    // Si l'élément cliqué est déjà vendu, on refuse l'action
    if (element.classList.contains('is-booked')) return;

    // Étape 1 : Si un siège était déjà sélectionné avant, on le remet à son état normal
    if (selectedSeatElement) {
        selectedSeatElement.classList.remove('is-currently-selected');
    }

    // Étape 2 : Si l'utilisateur clique à nouveau sur le même siège, cela annule simplement la sélection
    if (selectedSeatElement === element) {
        selectedSeatElement = null;
        updateTicketForm(null, null); // Vide le numéro de siège dans le formulaire
        return;
    }

    // Étape 3 : Appliquer le style de sélection sur le nouveau siège
    selectedSeatElement = element;
    element.classList.add('is-currently-selected');

    // Étape 4 : Récupérer les métadonnées de la place pour ton formulaire Symfony
    const idSiege = element.getAttribute('data-seat-id');
    const numeroSiege = element.getAttribute('data-seat-number');

    // Appel de la fonction de mise à jour de ton formulaire de vente
    updateTicketForm(idSiege, numeroSiege);
}

// Exemple d'action de liaison avec ton formulaire de vente de billet
function updateTicketForm(id, label) {
    const inputId = document.getElementById('ticket-form-seat-id'); // Champ masqué dans ton form
    const displayLabel = document.getElementById('ticket-form-seat-display'); // Zone d'affichage du texte

    if (inputId && displayLabel) {
        if (id) {
            inputId.value = id;
            displayLabel.textContent = `Siège sélectionné : N° ${label}`;
            displayLabel.style.color = '#2ece71';
        } else {
            inputId.value = '';
            displayLabel.textContent = 'Aucun siège sélectionné';
            displayLabel.style.color = '';
        }
    }
}
