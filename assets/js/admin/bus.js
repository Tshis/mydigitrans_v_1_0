document.addEventListener('DOMContentLoaded', () => {
    // Éléments des filtres
    const selectBranch = document.getElementById('filter-branch');
    const selectStatus = document.getElementById('filter-status');
    const inputSearch = document.getElementById('search-bus');
    
    // Éléments de la pagination
    const selectPerPage = document.getElementById('per-page-select');
    const btnPrev = document.getElementById('btn-prev-page');
    const btnNext = document.getElementById('btn-next-page');
    const containerPages = document.getElementById('page-numbers-container');
    
    // Éléments de données
    const tableRows = Array.from(document.querySelectorAll('.agency-fleet-row'));
    const fallbackMessage = document.getElementById('no-bus-fallback');
    const visibleCounter = document.getElementById('visible-count');
    const totalCounter = document.getElementById('total-count');

    // Variables d'état de la pagination
    let currentPage = 1;
    let filteredRows = [];

    if(totalCounter) totalCounter.textContent = tableRows.length;

    // 1. Étape clé : Filtrage complet des bus
    function updateFleetState() {
        const branchValue = selectBranch.value;
        const statusValue = selectStatus.value;
        const searchValue = inputSearch.value.toLowerCase().trim();
        
        // On isole uniquement les lignes qui matchent nos critères
        filteredRows = tableRows.filter(row => {
            const rowBranch = row.getAttribute('data-branch');
            const rowStatus = row.getAttribute('data-status');
            
            let searchContent = '';
            row.querySelectorAll('.searchable-field, .agency-bus-meta strong, .agency-bus-meta p').forEach(el => {
                searchContent += el.textContent.toLowerCase() + ' ';
            });

            const matchBranch = (branchValue === 'all' || rowBranch === branchValue);
            const matchStatus = (statusValue === 'all' || rowStatus === statusValue);
            const matchSearch = (searchValue === '' || searchContent.includes(searchValue));

            return matchBranch && matchStatus && matchSearch;
        });

        if (visibleCounter) visibleCounter.textContent = filteredRows.length;
        
        // Si le filtre modifie les données, on repart à la première page
        currentPage = 1; 
        
        renderPagination();
    }

    // 2. Étape clé : Affichage segmenté par page
    function renderPagination() {
        const itemsPerPage = parseInt(selectPerPage.value);
        const totalItems = filteredRows.length;
        const totalPages = Math.ceil(totalItems / itemsPerPage) || 1;

        // Sécurité pour la page courante
        if (currentPage > totalPages) currentPage = totalPages;

        // Index de découpage
        const startIndex = (currentPage - 1) * itemsPerPage;
        const endIndex = startIndex + itemsPerPage;

        // CORRIGÉ : Masquer absolument TOUS les bus de la table d'abord
        tableRows.forEach(row => row.style.display = 'none');

        // CORRIGÉ : Afficher uniquement les bus de la plage de la page active
        filteredRows.slice(startIndex, endIndex).forEach(row => {
            row.style.display = '';
        });

        // CORRIGÉ : Gestion du fallback si aucun bus n'est visible
        fallbackMessage.style.display = (totalItems === 0) ? 'block' : 'none';

        // Activer / Désactiver les boutons Précédent et Suivant
        btnPrev.disabled = (currentPage === 1);
        btnNext.disabled = (currentPage === totalPages);

        // Générer dynamiquement les petits boutons de numéros
        containerPages.innerHTML = '';
        for (let i = 1; i <= totalPages; i++) {
            const pageButton = document.createElement('button');
            pageButton.classList.add('page-num');
            if (i === currentPage) pageButton.classList.add('active');
            pageButton.textContent = i;
            
            pageButton.addEventListener('click', () => {
                currentPage = i;
                renderPagination();
            });
            containerPages.appendChild(pageButton);
        }
    }

    // 3. Écouteurs d'événements pour les boutons Précédent / Suivant
    btnPrev.addEventListener('click', () => {
        if (currentPage > 1) {
            currentPage--;
            renderPagination();
        }
    });

    btnNext.addEventListener('click', () => {
        const itemsPerPage = parseInt(selectPerPage.value);
        const totalPages = Math.ceil(filteredRows.length / itemsPerPage);
        if (currentPage < totalPages) {
            currentPage++;
            renderPagination();
        }
    });

    // Écouteurs d'événements pour les filtres et changement de taille de page
    selectBranch.addEventListener('change', updateFleetState);
    selectStatus.addEventListener('change', updateFleetState);
    inputSearch.addEventListener('input', updateFleetState);
    selectPerPage.addEventListener('change', updateFleetState);

    // Lancement initial au chargement de la page
    updateFleetState();
});
