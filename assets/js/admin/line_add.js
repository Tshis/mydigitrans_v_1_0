document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('stops-container');
    const addButton = document.getElementById('add-stop-btn');
    const template = document.getElementById('stop-template');
    const terminusCard = document.getElementById('terminus-card');

    // Fonction globale pour recalculer les numéros d'ordre (1, 2, 3...)
    function updateOrderNumbers() {
        const allCards = container.querySelectorAll('.stop-row-card');
        
        allCards.forEach((card, index) => {
            const indicator = card.querySelector('.stop-order-indicator');
            if (indicator) {
                indicator.textContent = index + 1;
            }

            // Met à jour les index PHP (name="stops[X][city]") pour que Symfony reçoive un tableau propre
            if (card.classList.contains('intermediate-stop')) {
                const inputs = card.querySelectorAll('input');
                inputs.forEach(input => {
                    input.name = input.name.replace(/stops\[\d+\]/, `stops[${index}]`);
                });
            }
        });
    }

    // Gestion de l'ajout d'une escale
    addButton.addEventListener('click', () => {
        // Cloner le gabarit template
        const clone = template.content.cloneNode(true);
        const card = clone.querySelector('.stop-row-card');

        // Ajouter l'événement de suppression sur le nouveau bouton poubelle
        card.querySelector('.remove-stop-btn').addEventListener('click', () => {
            card.remove();
            updateOrderNumbers(); // Recalculer après suppression
        });

        // Insérer la nouvelle escale juste AVANT le terminus final
        container.insertBefore(card, terminusCard);

        // Mettre à jour les numéros
        updateOrderNumbers();
    });

    // Écouter la suppression sur les éléments initiaux si présents
    container.querySelectorAll('.remove-stop-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.target.closest('.stop-row-card').remove();
            updateOrderNumbers();
        });
    });

    // Lancement initial pour caler les numéros de départ
    updateOrderNumbers();
});
