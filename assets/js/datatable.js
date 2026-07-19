document.addEventListener('DOMContentLoaded', () => {
    
    // On cible uniquement les tables qui ont explicitement la classe .js-datatable
    const targetTables = document.querySelectorAll('table.js-datatable');

    targetTables.forEach((table, index) => {
        const tableBody = table.querySelector('tbody');
        if (!tableBody) return;

        const allRows = Array.from(tableBody.querySelectorAll('tr:not(.no-result-row)'));
        let currentPage = 1;
        let rowsPerPage = 10;
        let filteredRows = [...allRows];

        // --- ÉTAPE A : ENCAPSULATION ET CRÉATION DU COMPOSANT (DOM MANIPULATION) ---
        
        // 1. Création de l'enveloppe de carte globale (.table-card)
        const tableCard = document.createElement('div');
        tableCard.className = 'table-card';
        table.parentNode.insertBefore(tableCard, table);

        // 2. Création de la zone de recherche supérieure
        const searchZone = document.createElement('div');
        searchZone.className = 'table-search-bar-zone';
        searchZone.innerHTML = `
            <div class="search-input-wrapper">
                <i class="fa-solid fa-magnifying-glass search-icon"></i>
                <input type="text" id="custom-search-${index}" placeholder="Rechercher..." autocomplete="off">
            </div>
        `;
        tableCard.appendChild(searchZone);

        // 3. Création du conteneur de scroll hermétique pour la table
        const scrollWrapper = document.createElement('div');
        scrollWrapper.className = 'table-responsive-wrapper';
        tableCard.appendChild(scrollWrapper);

        // 4. Déplacement de la table à l'INTÉRIEUR du conteneur de scroll
        scrollWrapper.appendChild(table);

        // 5. Création du bloc de pagination inférieur
        const paginationZone = document.createElement('div');
        paginationZone.className = 'table-pagination';
        paginationZone.innerHTML = `
            <div class="per-page-selector">
                <label for="per_page-${index}">Éléments par page :</label>
                <select id="per_page-${index}">
                    <option value="10" selected>10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
            <div class="pagination-nav">
                <button type="button" class="nav-btn" id="prev-${index}">
                    <i class="fa-solid fa-chevron-left"></i> Précédent
                </button>
                <div class="page-numbers" id="pages-${index}"></div>
                <button type="button" class="nav-btn" id="next-${index}">
                    Suivant <i class="fa-solid fa-chevron-right"></i>
                </button>
            </div>
        `;
        tableCard.appendChild(paginationZone);

        // Récupération des éléments pour les lier aux événements
        const searchInput = searchZone.querySelector('input');
        const perPageSelect = paginationZone.querySelector('select');
        const prevBtn = paginationZone.querySelector(`#prev-${index}`);
        const nextBtn = paginationZone.querySelector(`#next-${index}`);
        const pagesContainer = paginationZone.querySelector(`#pages-${index}`);

        // --- ÉTAPE B : LOGIQUE D'AFFICHAGE ET DE FILTRAGE ---

        function updateTableDisplay() {
            const totalRows = filteredRows.length;
            const totalPages = Math.ceil(totalRows / rowsPerPage) || 1;

            if (currentPage > totalPages) currentPage = totalPages;

            const startIndex = (currentPage - 1) * rowsPerPage;
            const endIndex = startIndex + rowsPerPage;

            // 1. On masque toutes les lignes en leur ajoutant une classe CSS
            allRows.forEach(row => {
                row.classList.add('is-hidden');
            });

            // 2. On affiche uniquement les lignes actives en retirant cette classe
            filteredRows.slice(startIndex, endIndex).forEach(row => {
                row.classList.remove('is-hidden');
            });

            // 3. Message d'erreur "Aucun résultat"
            let emptyMessage = tableBody.querySelector('.no-result-row');
            if (totalRows === 0) {
                if (!emptyMessage) {
                    emptyMessage = document.createElement('tr');
                    emptyMessage.className = 'no-result-row';
                    emptyMessage.innerHTML = `
                        <td colspan="10" style="text-align: center; padding: 30px;">
                            <i class="fa-solid fa-circle-question" style="font-size: 1.5rem; margin-bottom: 8px; display: block;"></i>
                            Aucun résultat trouvé pour cette recherche.
                        </td>
                    `;
                    tableBody.appendChild(emptyMessage);
                }
            } else if (emptyMessage) {
                emptyMessage.remove();
            }

            // Génération des numéros de pages
            renderPaginationControls(totalPages);
        }

        function renderPaginationControls(totalPages) {
            pagesContainer.innerHTML = '';
            prevBtn.disabled = currentPage === 1;
            nextBtn.disabled = currentPage === totalPages;

            for (let i = 1; i <= totalPages; i++) {
                const pageBtn = document.createElement('button');
                pageBtn.type = 'button';
                pageBtn.className = `page-item ${i === currentPage ? 'active' : ''}`;
                pageBtn.textContent = i;

                pageBtn.addEventListener('click', () => {
                    currentPage = i;
                    updateTableDisplay();
                });

                pagesContainer.appendChild(pageBtn);
            }
        }

        // --- ÉTAPE C : ÉCOUTEURS D'ÉVÉNEMENTS ---

        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase().trim();
            
            // Filtre les lignes du tableau dont le contenu textuel contient le mot recherché
            filteredRows = allRows.filter(row => row.textContent.toLowerCase().includes(query));
            
            currentPage = 1; // Renvoyer l'utilisateur à la première page de résultats
            updateTableDisplay();
        });

        perPageSelect.addEventListener('change', (e) => {
            rowsPerPage = parseInt(e.target.value, 10);
            currentPage = 1;
            updateTableDisplay();
        });

        prevBtn.addEventListener('click', () => {
            if (currentPage > 1) { currentPage--; updateTableDisplay(); }
        });

        nextBtn.addEventListener('click', () => {
            const totalPages = Math.ceil(filteredRows.length / rowsPerPage) || 1;
            if (currentPage < totalPages) { currentPage++; updateTableDisplay(); }
        });

        // Premier rendu au chargement de la page
        updateTableDisplay();
    });
});
