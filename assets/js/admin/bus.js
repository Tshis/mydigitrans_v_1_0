document.addEventListener('DOMContentLoaded', () => {
    const scope = document.querySelector('.agency-bus-profile-scope');
    if (!scope) return;

    // Boutons et boîtes principales
    const btnTriggerPanic = scope.querySelector('#btn-trigger-panic');
    const btnCancelPanic = scope.querySelector('#btn-cancel-panic');
    const btnConfirmMaintenance = scope.querySelector('#btn-confirm-maintenance');
    const btnTriggerResolve = scope.querySelector('#btn-trigger-resolve');
    const btnCancelResolve = scope.querySelector('#btn-cancel-resolve');
    const btnConfirmResolution = scope.querySelector('#btn-confirm-resolution');
    
    const boxOperational = scope.querySelector('#bus-operational-box');
    const boxBroken = scope.querySelector('#bus-broken-box');
    const panelPanic = scope.querySelector('#panic-action-panel');
    const panelResolve = scope.querySelector('#resolve-action-panel');
    
    const txtReason = scope.querySelector('#maintenance-reason');
    const txtResolution = scope.querySelector('#resolution-reason');
    const currentFaultText = scope.querySelector('#current-fault-text');
    const globalStatusBadge = scope.querySelector('#current-global-status');
    const cardTitle = scope.querySelector('#maintenance-card-title');

    // --- PHASE 1 : SIGNALEMENT DE PANNE ---
    btnTriggerPanic.addEventListener('click', () => {
        panelPanic.style.display = 'flex';
        boxOperational.style.display = 'none';
        txtReason.focus();
    });

    btnCancelPanic.addEventListener('click', () => {
        panelPanic.style.display = 'none';
        boxOperational.style.display = 'block';
        txtReason.value = '';
    });

    btnConfirmMaintenance.addEventListener('click', () => {
        const reasonText = txtReason.value.trim();
        if (reasonText === '') { alert('Veuillez saisir la nature de la panne.'); txtReason.focus(); return; }

        // Changement d'état global du bus -> Maintenance
        globalStatusBadge.className = 'agency-bus-state-badge agency-bus-state-badge--maintenance';
        globalStatusBadge.textContent = 'En maintenance';
        cardTitle.style.color = '#e74c3c';

        // Bascule des conteneurs
        currentFaultText.textContent = reasonText;
        boxBroken.style.display = 'block';
        panelPanic.style.display = 'none';
        txtReason.value = '';
    });

    // --- PHASE 2 : RÉSOLUTION DE PANNE (NOUVEAU) ---
    btnTriggerResolve.addEventListener('click', () => {
        panelResolve.style.display = 'flex';
        boxBroken.style.display = 'none';
        txtResolution.focus();
    });

    btnCancelResolve.addEventListener('click', () => {
        panelResolve.style.display = 'none';
        boxBroken.style.display = 'block';
        txtResolution.value = '';
    });

    btnConfirmResolution.addEventListener('click', () => {
        const resolutionText = txtResolution.value.trim();
        if (resolutionText === '') { alert('Veuillez documenter le rapport de réparation.'); txtResolution.focus(); return; }

        // Retour à l'état global disponible -> Disponible / En route
        globalStatusBadge.className = 'agency-bus-state-badge agency-bus-state-badge--available';
        globalStatusBadge.textContent = 'Disponible au garage';
        cardTitle.style.color = '#2ece71';

        // Restauration de l'affichage opérationnel
        boxOperational.style.display = 'block';
        panelResolve.style.display = 'none';
        txtResolution.value = '';
    });
});
