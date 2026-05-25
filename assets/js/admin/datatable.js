
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.querySelector('.c-datatable_input');
    const selectFilters = document.querySelectorAll('.c-datatable_select');
    const filterBtn = document.querySelector('.c-datatable_filters .btn-primary');
    const tableRows = document.querySelectorAll('.custom-table tbody tr');

    // --- FONCTION DE FILTRAGE GLOBALE ---
    function filterTable() {
        const searchText = searchInput.value.toLowerCase().trim();
        
        // Récupération des valeurs des sélecteurs (index 0 = succursale, index 1 = rôle)
        const branchFilter = selectFilters[0].value.toLowerCase();
        const roleFilter = selectFilters[1].value.toLowerCase();

        tableRows.forEach(row => {
            // Extraction des données textuelles de la ligne
            const rowText = row.textContent.toLowerCase();
            const agentBranch = row.querySelector('.c-user-branch')?.textContent.toLowerCase() || '';
            const agentRole = row.querySelector('.badge-role')?.textContent.toLowerCase() || '';

            // Vérification des conditions
            const matchesSearch = rowText.includes(searchText);
            const matchesBranch = branchFilter === '' || agentBranch.includes(branchFilter);
            const matchesRole = roleFilter === '' || agentRole.includes(roleFilter);

            // Affichage ou masquage de la ligne
            if (matchesSearch && matchesBranch && matchesRole) {
                row.style.display = '';
            } else {
                row.style.style.display = 'none';
            }
        });
    }

    // Événements : Filtrage en temps réel sur la recherche et clic sur "Filtrer"
    searchInput.addEventListener('input', filterTable);
    if (filterBtn) {
        filterBtn.addEventListener('click', filterTable);
    }

    // --- LOGIQUE STATIQUE DE LA PAGINATION ---
    const paginationButtons = document.querySelectorAll('.c-datatable_pagination .btn');
    paginationButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            if (this.hasAttribute('disabled') || this.querySelector('i')) return;
            
            // Alterne l'état actif visuel des numéros
            paginationButtons.forEach(b => b.classList.remove('btn-primary'));
            paginationButtons.forEach(b => { if(!b.querySelector('i')) b.classList.add('btn-secondary'); });
            
            this.classList.remove('btn-secondary');
            this.classList.add('btn-primary');
            
            // Note : Dans un environnement réel avec Symfony, 
            // ce clic déclenchera une requête AJAX ou un rechargement de page (?page=2)
            console.log("Navigation vers la page : " + this.textContent);
        });
    });
});

