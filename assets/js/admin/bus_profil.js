document.addEventListener('DOMContentLoaded', () => {
    const scope = document.querySelector('.agency-bus-profile-scope');
    if (!scope) return;

    // --- 1. Déclaration des pannes ---
    const btnTriggerPanic = scope.querySelector('#btn-trigger-panic');
    const busOperationalBox = scope.querySelector('#bus-operational-box');
    const btnCancelPanic = scope.querySelector('#btn-cancel-panic');
    const panicPanel = scope.querySelector('#panic-action-panel');
    const txtReason = scope.querySelector('#maintenance-reason');

    btnTriggerPanic?.addEventListener('click', () => {
        if (panicPanel) panicPanel.style.display = 'flex';
        if (busOperationalBox) busOperationalBox.style.display = 'none';
        txtReason?.focus();
    });

    btnCancelPanic?.addEventListener('click', () => {
        if (panicPanel) panicPanel.style.display = 'none';
        if (busOperationalBox) busOperationalBox.style.display = 'block';
        if (txtReason) txtReason.value = '';
    });

    // --- 2. Résolution des pannes ---
    const btnTriggerResolve = scope.querySelector('#btn-trigger-resolve');
    const btnCancelResolve = scope.querySelector('#btn-cancel-resolve');
    const busBrokenBox = scope.querySelector('#bus-broken-box');
    const resolvePanel = scope.querySelector('#resolve-action-panel');
    const resolveText = scope.querySelector('#resolution-reason');

    btnTriggerResolve?.addEventListener('click', () => {
        if (resolvePanel) resolvePanel.style.display = 'flex';
        if (busBrokenBox) busBrokenBox.style.display = 'none';
        resolveText?.focus();
    });

    btnCancelResolve?.addEventListener('click', () => {
        if (resolvePanel) resolvePanel.style.display = 'none';
        if (busBrokenBox) busBrokenBox.style.display = 'block';
        if (resolveText) resolveText.value = '';
    });

    // --- 3. Déclassement du bus ---
    const btnTriggerDecommission = scope.querySelector('#btn-trigger-decommission');
    const btnCancelDecommission = scope.querySelector('#btn-cancel-decommission');
    const decommissionPanel = scope.querySelector('#decommission-action-panel');

    btnTriggerDecommission?.addEventListener('click', () => {
        if (decommissionPanel) {
            decommissionPanel.classList.remove('hidden');
            decommissionPanel.style.display = 'flex';
        }
        if (btnTriggerDecommission) btnTriggerDecommission.style.display = 'none';
    });

    btnCancelDecommission?.addEventListener('click', () => {
        if (decommissionPanel) {
            decommissionPanel.classList.add('hidden');
            decommissionPanel.style.display = 'none';
        }
        if (btnTriggerDecommission) btnTriggerDecommission.style.display = 'block';
    });
});
