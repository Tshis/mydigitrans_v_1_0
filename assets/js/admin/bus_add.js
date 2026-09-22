// public/js/seatmap.js

document.addEventListener('DOMContentLoaded', () => {
    console.log('Script d\'interconnexion AJAX Seatmap chargé');
    
    const scope = document.querySelector('.admin-agency-bus-add-container');
    if (!scope) return;

    const layoutSelector = scope.querySelector('#bus-layout-selector');
    const htmlReceiver = scope.querySelector('#seatmap-html-receiver');
    const liveTitle = scope.querySelector('#seatmap-live-title');

    if (layoutSelector && htmlReceiver) {
        layoutSelector.addEventListener('change', () => {
            const selectedId = layoutSelector.value;
            const selectedText = layoutSelector.options[layoutSelector.selectedIndex].text;

            // Affichage d'un spinner propre pendant le chargement
            htmlReceiver.innerHTML = `
                <div class="text-center text-gray" style="padding: 40px 10px;">
                    <i class="fa-solid fa-spinner fa-spin text-primary" style="font-size: 24px; margin-bottom: 10px;"></i>
                    <br>Génération du plan d'aménagement...
                </div>
            `;

            // APPEL AJAX CHIRURGICAL VERS LE CONTROLLER SYMFONY
            fetch(`/admin/agency/bus/preview-layout/${selectedId}`)
                .then(response => {
                    if (!response.ok) throw new Error("Erreur de récupération du gabarit.");
                    return response.text(); // On extrait le HTML brut généré par le include() Twig
                })
                .then(htmlFragment => {
                    // Injection directe du partial Twig compilé dans la zone droite
                    htmlReceiver.innerHTML = htmlFragment;
                    
                    // Mise à jour du titre de la section avec le nom du modèle choisi
                    if (liveTitle) liveTitle.textContent = selectedText;

                    // Sécurité : Comme on est sur un formulaire d'enregistrement de bus,
                    // on désactive le clic sur les boutons radio pour qu'ils soient purement visuels
                    htmlReceiver.querySelectorAll('.seat-radio').forEach(radio => {
                        radio.setAttribute('disabled', 'disabled');
                        radio.style.cursor = 'default';
                    });
                })
                .catch(error => {
                    console.error(error);
                    htmlReceiver.innerHTML = `
                        <div class="text-center text-danger" style="padding: 30px 10px; font-size: 12px; background:rgba(220,53,69,0.05); border:1px solid rgba(220,53,69,0.15); border-radius:8px;">
                            <i class="fa-solid fa-circle-exclamation mb-5" style="font-size: 18px;"></i>
                            <br>Échec du chargement du plan. Veuillez réessayer.
                        </div>
                    `;
                });
        });
    }
});
