document.addEventListener('DOMContentLoaded', () => {
    // On cible le conteneur scope pour s'assurer de ne pas interférer avec d'autres pages
    const scope = document.querySelector('.agency-bus-profile-scope');
    if (!scope) return; // Si on n'est pas sur la page profil, on arrête le script

    // Récupération des éléments à l'intérieur du scope

    //--- Declaration des pannes ---
    const btnTriggerPanic = scope.querySelector('#btn-trigger-panic');
    const busOperationalBox = scope.querySelector('#bus-operational-box');
    const btnCancelPanic = scope.querySelector('#btn-cancel-panic');
    const btnConfirmMaintenance = scope.querySelector('#btn-confirm-maintenance');
    
    const panicPanel = scope.querySelector('#panic-action-panel');
    const txtReason = scope.querySelector('#maintenance-reason');
    const globalStatusBadge = scope.querySelector('#current-global-status');
    const maintenanceBoxText = scope.querySelector('#maintenance-display-text');

    // 1. Déclencher l'affichage du volet de panne
    btnTriggerPanic.addEventListener('click', () => {
        panicPanel.style.display = 'flex'; // Aligné avec la structure flex du SCSS
        busOperationalBox.style.display = 'none';
        txtReason.focus();
    });

    // 2. Annuler et masquer le volet
    btnCancelPanic.addEventListener('click', () => {
        panicPanel.style.display = 'none';
        busOperationalBox.style.display = 'block';
        txtReason.value = ''; // Reset du texte
    });

    // 3. Confirmer la panne et basculer l'état du bus
    btnConfirmMaintenance.addEventListener('click', () => {
        const reasonText = txtReason.value.trim();

        if (reasonText === '') {
            alert('Veuillez renseigner le motif de la panne avant de valider l\'immobilisation.');
            txtReason.focus();
            return;
        }

        // Mutation visuelle immédiate du badge d'en-tête (Rouge/Primary)
        globalStatusBadge.className = 'agency-bus-state-badge agency-bus-state-badge--maintenance';
        globalStatusBadge.textContent = 'En maintenance';

        // Mise à jour de la zone de texte technique
        maintenanceBoxText.innerHTML = `⚠️ <strong>Immobilisé au garage :</strong> ${reasonText}`;
        maintenanceBoxText.style.color = '#e74c3c';

        // Nettoyage de l'interface
        panicPanel.style.display = 'none';
        btnTriggerPanic.style.display = 'none'; // Reste masqué pendant la panne
    });

    //--- Reparation des pannes ---
    const btnTriggerResolve = scope.querySelector('#btn-trigger-resolve');
    const btnCancelResolve = scope.querySelector('#btn-cancel-resolve');
    const btnConfirmResolve = scope.querySelector('#btn-confirm-resolve');
     const busBrokenBox = scope.querySelector('#bus-broken-box');
    
    const resolvePanel = scope.querySelector('#resolve-action-panel');
    const resolveText = scope.querySelector('#resolution-reason');

    
    // 1. Déclencher l'affichage du volet de resolution
    btnTriggerResolve.addEventListener('click', () => {
        resolvePanel.style.display = 'flex'; // Aligné avec la structure flex du SCSS
        busBrokenBox.style.display = 'none';
        resolveText.focus();
    });

    // 2. Annuler et masquer le volet
    btnCancelResolve.addEventListener('click', () => {
        resolvePanel.style.display = 'none';
        busBrokenBox.style.display = 'block';
        resolveText.value = ''; // Reset du texte
    });

    // 3. Confirmer la panne et basculer l'état du bus
    btnConfirmResolve.addEventListener('click', () => {
        const reasonText = resolveText.value.trim();

        if (reasonText === '') {
            alert('Veuillez renseigner le motif de la panne avant de valider l\'immobilisation.');
            txtReason.focus();
            return;
        }

        // Mutation visuelle immédiate du badge d'en-tête (Rouge/Primary)
        globalStatusBadge.className = 'agency-bus-state-badge agency-bus-state-badge--maintenance';
        globalStatusBadge.textContent = 'En maintenance';

        // Mise à jour de la zone de texte technique
        maintenanceBoxText.innerHTML = `⚠️ <strong>Immobilisé au garage :</strong> ${reasonText}`;
        maintenanceBoxText.style.color = '#e74c3c';

        // Nettoyage de l'interface
        panicPanel.style.display = 'none';
        btnTriggerPanic.style.display = 'none'; // Reste masqué pendant la panne
    });

    // --- SÉLECTEURS DÉCLASSEMENT ---
    const btnTriggerDecommission = scope.querySelector('#btn-trigger-decommission');
    const btnCancelDecommission = scope.querySelector('#btn-cancel-decommission');
    const btnConfirmDecommission = scope.querySelector('#btn-confirm-decommission');
    const decommissionPanel = scope.querySelector('#decommission-action-panel');
    const selectDecommissionReason = scope.querySelector('#decommission-reason-select');

    // --- WORKFLOW 3 : DÉCLASSER LE BUS ---

    // Ouvrir le volet de déclassement
    btnTriggerDecommission.addEventListener('click', () => {
        decommissionPanel.classList.remove('hidden');
        decommissionPanel.style.display = 'flex';
        btnTriggerDecommission.style.display = 'none';
    });

    // Annuler l'action
    btnCancelDecommission.addEventListener('click', () => {
        decommissionPanel.classList.add('hidden');
        decommissionPanel.style.display = 'none';
        btnTriggerDecommission.style.display = 'block';
    });

    // Confirmer définitivement la radiation
    btnConfirmDecommission.addEventListener('click', () => {
        const selectedText = selectDecommissionReason.options[selectDecommissionReason.selectedIndex].text;
        
        // Double sécurité : Demande une confirmation textuelle pour éviter les clics accidentels
        const safetyCheck = confirm(`🛑 ATTENTION : Êtes-vous sûr de vouloir radier définitivement ce véhicule pour le motif suivant : "${selectedText}" ?\n\nCette action est irréversible.`);
        
        if (safetyCheck) {
            // 1. Changement visuel immédiat de l'en-tête globale
            globalStatusBadge.className = 'agency-bus-state-badge'; // Reset des modificateurs
            globalStatusBadge.style.backgroundColor = '#2c3e50';   // Couleur anthracite (Inactif)
            globalStatusBadge.style.color = '#ffffff';
            globalStatusBadge.textContent = 'Inactif / Déclassé';

            // 2. Verrouillage complet des autres formulaires (le bus n'est plus modifiable)
            const allInputsAndButtons = scope.querySelectorAll('input, select, textarea, button:not(#btn-trigger-back)');
            allInputsAndButtons.forEach(element => {
                element.disabled = true;
                element.style.opacity = '0.5';
                element.style.cursor = 'not-allowed';
            });

            // 3. Masquage du panneau technique et de validation
            decommissionPanel.style.display = 'none';
            
            // Notification de succès visuel
            alert(`Le bus a été retiré de la flotte active (${selectedText}).`);
            
            // Optionnel : Envoi des données en AJAX vers Symfony
            console.log("Bus déclassé définitivement. Motif :", selectDecommissionReason.value);
        }
    });

});
