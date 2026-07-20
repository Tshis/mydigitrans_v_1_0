document.addEventListener('DOMContentLoaded', () => {
    // Cible toutes les tables autonomes du projet
    const targetTables = document.querySelectorAll('table.js-datatable');

    targetTables.forEach((table, index) => {
        const tableBody = table.querySelector('tbody');
        if (!tableBody) return;

        const allRows = Array.from(tableBody.querySelectorAll('tr:not(.no-result-row)'));
        let currentPage = 1;
        let rowsPerPage = 10;
        let filteredRows = [...allRows];

        // --- ÉTAPE 1 : INJECTION DES CONTENEURS AUTONOMES ---
        
        // Création de l'enveloppe de carte globale (.table-card)
        const tableCard = document.createElement('div');
        tableCard.className = 'table-card';
        table.parentNode.insertBefore(tableCard, table);

        // Zone de recherche supérieure
        const searchZone = document.createElement('div');
        searchZone.className = 'table-search-bar-zone';
        searchZone.innerHTML = `
            <div class="search-input-wrapper">
                <i class="fa-solid fa-magnifying-glass search-icon"></i>
                <input type="text" id="custom-search-${index}" placeholder="Rechercher dans le tableau..." autocomplete="off">
            </div>
        `;
        tableCard.appendChild(searchZone);

        // Conteneur de scroll hermétique isolé
        const scrollWrapper = document.createElement('div');
        scrollWrapper.className = 'table-responsive-wrapper';
        tableCard.appendChild(scrollWrapper);

        // Déplacement de la table à l'intérieur de sa capsule de scroll
        scrollWrapper.appendChild(table);

        // Zone de pagination inférieure
        const paginationZone = document.createElement('div');
        paginationZone.className = 'table-pagination';
        paginationZone.innerHTML = `
            <div class="per-page-selector">
                <label for="per_page-${index}">Éléments par page :</label>
                <select id="per_page-${index}">
                    <option value="10" selected>10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
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

        // Liaisons des éléments dynamiques
        const searchInput = searchZone.querySelector('input');
        const perPageSelect = paginationZone.querySelector('select');
        const prevBtn = paginationZone.querySelector(`#prev-${index}`);
        const nextBtn = paginationZone.querySelector(`#next-${index}`);
        const pagesContainer = paginationZone.querySelector(`#pages-${index}`);

        // --- ÉTAPE 2 : LOGIQUE FILTRAGE ET RACHRAÎCHISSEMENT ---

        function updateTableDisplay() {
            const totalRows = filteredRows.length;
            const totalPages = Math.ceil(totalRows / rowsPerPage) || 1;

            if (currentPage > totalPages) currentPage = totalPages;

            const startIndex = (currentPage - 1) * rowsPerPage;
            const endIndex = startIndex + rowsPerPage;

            // Masquage global via classe CSS
            allRows.forEach(row => row.classList.add('is-hidden'));

            // Affichage restrictif de la tranche active
            filteredRows.slice(startIndex, endIndex).forEach(row => {
                row.classList.remove('is-hidden');
            });

            // Ligne d'alerte en cas de tableau vide
            let emptyMessage = tableBody.querySelector('.no-result-row');
            if (totalRows === 0) {
                if (!emptyMessage) {
                    emptyMessage = document.createElement('tr');
                    emptyMessage.className = 'no-result-row';
                    emptyMessage.innerHTML = `
                        <td colspan="20" style="text-align: center; padding: 2.5rem; color: #a0aec0;">
                            <i class="fa-solid fa-circle-question" style="font-size: 1.6rem; margin-bottom: 8px; display: block;"></i>
                            Aucune correspondance trouvée.
                        </td>
                    `;
                    tableBody.appendChild(emptyMessage);
                }
            } else if (emptyMessage) {
                emptyMessage.remove();
            }

            renderPaginationControls(totalPages);
        }

        function renderPaginationControls(totalPages) {
            pagesContainer.innerHTML = '';
            prevBtn.disabled = currentPage === 1;
            nextBtn.disabled = currentPage === totalPages;

            // Nombre de boutons visibles maximum autour de la page courante
            const maxVisibleButtons = 3; 
            let startPage = Math.max(1, currentPage - 1);
            let endPage = Math.min(totalPages, startPage + maxVisibleButtons - 1);

            // Ajustement si on est proche de la fin
            if (endPage - startPage + 1 < maxVisibleButtons) {
                startPage = Math.max(1, endPage - maxVisibleButtons + 1);
            }

            // 1. Toujours afficher la PREMIÈRE PAGE si on est loin du début
            if (startPage > 1) {
                createPageButton(1);
                if (startPage > 2) {
                    createEllipsis();
                }
            }

            // 2. Afficher les pages centrales (autour de la page active)
            for (let i = startPage; i <= endPage; i++) {
                createPageButton(i);
            }

            // 3. Toujours afficher la DERNIÈRE PAGE si on est loin de la fin
            if (endPage < totalPages) {
                if (endPage < totalPages - 1) {
                    createEllipsis();
                }
                createPageButton(totalPages);
            }

            // Fonction interne pour générer un bouton de page clean
            function createPageButton(pageNumber) {
                const pageBtn = document.createElement('button');
                pageBtn.type = 'button';
                pageBtn.className = `page-item ${pageNumber === currentPage ? 'active' : ''}`;
                pageBtn.textContent = pageNumber;

                pageBtn.addEventListener('click', () => {
                    currentPage = pageNumber;
                    updateTableDisplay();
                });

                pagesContainer.appendChild(pageBtn);
            }

            // Fonction interne pour générer l'indicateur graphique "..."
            function createEllipsis() {
                const ellipsis = document.createElement('span');
                ellipsis.className = 'pagination-ellipsis';
                ellipsis.textContent = '...';
                ellipsis.style.paddingInline = '6px';
                ellipsis.style.color = 'var(--gray)';
                ellipsis.style.fontSize = '12px';
                ellipsis.style.fontWeight = '600';
                pagesContainer.appendChild(ellipsis);
            }
        }


        // --- ÉTAPE 3 : ÉCOUTEURS ---

        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase().trim();
            filteredRows = allRows.filter(row => row.textContent.toLowerCase().includes(query));
            currentPage = 1;
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

        updateTableDisplay();
    });
});
