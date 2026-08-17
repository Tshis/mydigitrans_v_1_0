document.addEventListener('DOMContentLoaded', () => {
    const btnAdd = document.getElementById('js-add-stop-row');
    const wrapper = document.getElementById('js-stops-wrapper');
    const template = document.getElementById('stop-row-template');
    
    // runningIndex sert uniquement pour les noms des champs (stops[0], stops[1]...)
    // On ne le décrémente jamais pour éviter les doublons de clés côté serveur.
    let runningIndex = wrapper.querySelectorAll('.stop-node-row').length;

    /**
     * Fonction pour mettre à jour l'affichage visuel des numéros (#1, #2, #3...)
     */
    function updateVisualIndexes() {
        const allStops = wrapper.querySelectorAll('.stop-node-row');
        allStops.forEach((stop, i) => {
            // L'index visuel commence à 2 car le Départ fixe est le #1
            const visualNumber = i + 1; 
            const indicator = stop.querySelector('.stop-id-indicator');
            if (indicator) {
                indicator.textContent = `#${visualNumber}`;
            }
        });

        // Mise à jour du numéro du Terminus (il doit toujours être le dernier)
        const terminusIndicator = document.querySelector('#js-terminus-row .stop-id-indicator');
        if (terminusIndicator) {
            terminusIndicator.textContent = `#${allStops.length + 2}`;
        }
    }

    if (btnAdd) {
        btnAdd.addEventListener('click', () => {
            // 1. Préparer le contenu avec l'index technique unique
            let content = template.innerHTML
                .replace(/__INDEX__/g, runningIndex);
            
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = content.trim();
            const newRow = tempDiv.firstElementChild;

            // 2. Ajouter au wrapper
            wrapper.appendChild(newRow);
            runningIndex++;

            // 3. Re-numéroter visuellement
            updateVisualIndexes();

            // 4. Événement de suppression
            newRow.querySelector('.js-remove-row').addEventListener('click', (e) => {
                e.preventDefault();
                newRow.remove();
                // 5. Re-numéroter après suppression pour boucher les trous
                updateVisualIndexes();
            });
        });
    }

    // Initialisation pour les éléments existants au chargement
    updateVisualIndexes();
});
