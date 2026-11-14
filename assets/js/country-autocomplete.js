document.addEventListener('DOMContentLoaded', () => {
    const initCountrySelects = (root = document) => {
        if (typeof TomSelect === 'undefined') {
            return;
        }

        root.querySelectorAll('select.js-country-autocomplete:not(.ts-hidden-accessible)').forEach((selectEl) => {
            // Ignorer les éléments situés à l'intérieur d'une balise <template>
            if (selectEl.closest('template')) {
                return;
            }

            new TomSelect(selectEl, {
                create: false,
                allowEmptyOption: true,
                placeholder: 'Rechercher un pays...',
                maxOptions: 300,
                render: {
                    no_results: function (data, escape) {
                        return '<div class="no-results">Aucun pays trouvé pour "' + escape(data.input) + '"</div>';
                    }
                }
            });
        });
    };

    // 1. Initialisation au chargement de la page
    initCountrySelects();

    // 2. Observation des ajouts dynamiques dans le DOM (ex: "Ajouter une escale")
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (node.nodeType === Node.ELEMENT_NODE) {
                    initCountrySelects(node);
                }
            });
        });
    });

    observer.observe(document.body, { childList: true, subtree: true });
});
