(() => {
    function initRouteAddForm() {
        const wrapper = document.getElementById('js-stops-wrapper');
        const addButton = document.getElementById('js-add-stop-row');
        const template = document.getElementById('stop-row-template');

        if (!wrapper || !addButton || !template) {
            return;
        }

        // Évite la double initialisation si Turbo recharge le script
        if (wrapper.dataset.initialized === 'true') {
            return;
        }
        wrapper.dataset.initialized = 'true';

        // Recalcule les numéros (#1, #2...) et synchronise les attributs name/id/for pour Symfony
        function reindexStops() {
            const rows = wrapper.querySelectorAll('.stop-node-row.intermediate');

            rows.forEach((row, idx) => {
                const indicator = row.querySelector('.stop-id-indicator');
                if (indicator) {
                    indicator.textContent = `#${idx + 1}`;
                }

                row.querySelectorAll('input, select, textarea').forEach((field) => {
                    if (field.name) {
                        field.name = field.name.replace(/\[stops\]\[\d+\]/, `[stops][${idx}]`);
                    }
                    if (field.id) {
                        field.id = field.id.replace(/_stops_\d+_/, `_stops_${idx}_`);
                    }
                });

                row.querySelectorAll('label').forEach((label) => {
                    const htmlFor = label.getAttribute('for');
                    if (htmlFor) {
                        label.setAttribute('for', htmlFor.replace(/_stops_\d+_/, `_stops_${idx}_`));
                    }
                });
            });

            wrapper.dataset.index = String(rows.length);
        }

        // Ajout d'une nouvelle escale intermédiaire depuis le prototype Symfony (__name__)
        addButton.addEventListener('click', () => {
            const currentIndex = parseInt(wrapper.dataset.index || '0', 10);
            const html = template.innerHTML.replace(/__name__/g, String(currentIndex));

            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = html.trim();
            const newRow = tempDiv.firstElementChild;

            if (!newRow) {
                return;
            }

            wrapper.appendChild(newRow);
            reindexStops();
        });

        // Délégation d'événement pour la suppression (fonctionne avant ET après erreur de validation)
        wrapper.addEventListener('click', (event) => {
            const removeBtn = event.target.closest('.js-remove-row');
            if (!removeBtn) {
                return;
            }

            const row = removeBtn.closest('.stop-node-row.intermediate');
            if (row) {
                row.remove();
                reindexStops();
            }
        });

        // Recalage initial au chargement (y compris après un réaffichage avec erreurs Symfony)
        reindexStops();
    }

    document.addEventListener('DOMContentLoaded', initRouteAddForm);
    document.addEventListener('turbo:load', initRouteAddForm);
})();
