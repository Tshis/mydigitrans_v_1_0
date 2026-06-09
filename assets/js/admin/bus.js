document.addEventListener('DOMContentLoaded', () => {
    alert("hjkfjj")
    // 1. Récupération des éléments du DOM
    const selectBranch = document.getElementById('filter-branch');
    const selectStatus = document.getElementById('filter-status');
    const inputSearch = document.getElementById('search-bus');
    
    const tableRows = document.querySelectorAll('.agency-fleet-row');
    const fallbackMessage = document.getElementById('no-bus-fallback');
    
    const visibleCounter = document.getElementById('visible-count');
    const totalCounter = document.getElementById('total-count');

    // Initialisation du compteur total
    if(totalCounter) totalCounter.textContent = tableRows.length;

    // 2. Fonction principale de filtrage
    function filterFleet() {
        const branchValue = selectBranch.value;
        const statusValue = selectStatus.value;
        const searchValue = inputSearch.value.toLowerCase().trim();
        
        let visibleCount = 0;

        tableRows.forEach(row => {
            // Lecture des attributs de données de la ligne
            const rowBranch = row.getAttribute('data-branch');
            const rowStatus = row.getAttribute('data-status');
            
            // Collecte du contenu textuel des champs de recherche (Chauffeur, plaque...)
            let searchContent = '';
            row.querySelectorAll('.searchable-field, .agency-bus-meta strong, .agency-bus-meta p').forEach(el => {
                searchContent += el.textContent.toLowerCase() + ' ';
            });

            // Validation des critères (Vrai ou Faux)
            const matchBranch = (branchValue === 'all' || rowBranch === branchValue);
            const matchStatus = (statusValue === 'all' || rowStatus === statusValue);
            const matchSearch = (searchValue === '' || searchContent.includes(searchValue));

            // Si toutes les conditions sont remplies, on affiche la ligne
            if (matchBranch && matchStatus && matchSearch) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none'; // Sinon on la masque
            }
        });

        // 3. Mise à jour des compteurs et gestion du fallback si vide
        if (visibleCounter) visibleCounter.textContent = visibleCount;
        
        if (visibleCount === 0) {
            fallbackMessage.style.display = 'block';
        } else {
            fallbackMessage.style.display = 'none';
        }
    }

    // 4. Écouteurs d'événements (Déclenchement instantané)
    selectBranch.addEventListener('change', filterFleet);
    selectStatus.addEventListener('change', filterFleet);
    inputSearch.addEventListener('input', filterFleet);
});
