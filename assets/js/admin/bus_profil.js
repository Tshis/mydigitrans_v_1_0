document.addEventListener('DOMContentLoaded', () => {
    // On cible le conteneur scope pour s'assurer de ne pas interférer avec d'autres pages
    const scope = document.querySelector('.agency-bus-profile-scope');
    if (!scope) return; // Si on n'est pas sur la page profil, on arrête le script

    // Récupération des éléments à l'intérieur du scope
    const btnTriggerPanic = scope.querySelector('#btn-trigger-panic');
    const btnCancelPanic = scope.querySelector('#btn-cancel-panic');
    const btnConfirmMaintenance = scope.querySelector('#btn-confirm-maintenance');
    
    const panicPanel = scope.querySelector('#panic-action-panel');
    const txtReason = scope.querySelector('#maintenance-reason');
    const globalStatusBadge = scope.querySelector('#current-global-status');
    const maintenanceBoxText = scope.querySelector('#maintenance-display-text');

    // 1. Déclencher l'affichage du volet de panne
    btnTriggerPanic.addEventListener('click', () => {
        panicPanel.style.display = 'flex'; // Aligné avec la structure flex du SCSS
        btnTriggerPanic.style.display = 'none';
        txtReason.focus();
    });

    // 2. Annuler et masquer le volet
    btnCancelPanic.addEventListener('click', () => {
        panicPanel.style.display = 'none';
        btnTriggerPanic.style.display = 'inline-flex';
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
});
