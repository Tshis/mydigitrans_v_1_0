document.addEventListener('DOMContentLoaded', () => {
    // 1. Récupération des éléments du DOM
    const searchInput = document.querySelector('.c-datatable_input');
    const branchSelect = document.querySelectorAll('.c-datatable_select')[0]; // Premier select
    const roleSelect = document.querySelectorAll('.c-datatable_select')[1];   // Deuxième select
    const filterButton = document.querySelector('.c-datatable_filters .btn-primary');
    const rows = document.querySelectorAll('.custom-table tbody .user-row');

    // 2. Fonction principale de filtrage
    function filterAgents() {
        const searchText = searchInput.value.toLowerCase().trim();
        const selectedBranch = branchSelect.value.toLowerCase().trim();
        const selectedRole = roleSelect.value.toLowerCase().trim();

        rows.forEach(row => {
            // Extraction des données de la ligne de l'agent
            const agentName = row.querySelector('.c-user-meta strong')?.textContent.toLowerCase() || '';
            const agentMatricule = row.querySelector('.c-user-matricule')?.textContent.toLowerCase() || '';
            const agentPhone = row.querySelector('.c-user-phone')?.textContent.toLowerCase() || '';
            const agentEmail = row.querySelector('.c-user-email')?.textContent.toLowerCase() || '';
            
            const agentBranch = row.querySelector('.c-user-branch')?.textContent.toLowerCase() || '';
            
            // Récupération de la classe ou du texte du rôle
            const roleBadge = row.querySelector('.badge-role');
            const agentRoleText = roleBadge?.textContent.toLowerCase() || '';
            // Permet aussi de matcher avec la valeur HTML "ROLE_GUICHETIER" si présente dans les classes
            const agentRoleClass = roleBadge?.className.toLowerCase() || ''; 

            // Conditions de correspondance
            const matchesSearch = agentName.includes(searchText) || 
                                  agentMatricule.includes(searchText) || 
                                  agentPhone.includes(searchText) || 
                                  agentEmail.includes(searchText);

            const matchesBranch = selectedBranch === '' || agentBranch.includes(selectedBranch);
            
            // Vérifie si le texte du rôle correspond, ou si la classe contient la valeur du select (ex: guichetier)
            const matchesRole = selectedRole === '' || 
                                agentRoleText.includes(selectedRole.replace('role_', '').toLowerCase()) ||
                                agentRoleClass.includes(selectedRole.toLowerCase());

            // Affichage ou masquage de la ligne
            if (matchesSearch && matchesBranch && matchesRole) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });

        // Optionnel : Mettre à jour le compteur de la datatable (ex: Affichage de 1 à X agents)
        updateCounter();
    }

    // 3. Fonction pour mettre à jour le texte du pied de page
    function updateCounter() {
        const visibleRows = Array.from(rows).filter(row => row.style.display !== 'none').length;
        const infoSpan = document.querySelector('.c-datatable_info');
        if (infoSpan) {
            infoSpan.textContent = `Affichage de ${visibleRows} sur ${rows.length} agents filtrés`;
        }
    }

    // 4. Écouteurs d'événements (Live Search au clavier)
    searchInput.addEventListener('input', filterAgents);

    // 5. Filtrage au clic sur le bouton "Filtrer" (Comme prévu dans votre HTML)
    if (filterButton) {
        filterButton.addEventListener('click', filterAgents);
    }

    // Alternative : Filtrage instantané lors du changement des selects (optionnel mais recommandé)
    branchSelect.addEventListener('change', filterAgents);
    roleSelect.addEventListener('change', filterAgents);
});
