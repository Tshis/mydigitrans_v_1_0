// public/js/admin/platform_subscription.js

document.addEventListener('DOMContentLoaded', () => {
    console.log('Interrupteur d\'onglets financiers connecté');
    const scope = document.querySelector('.admin-platform-subscription-scope');
    if (!scope) return;

    const tabsButtons = scope.querySelectorAll('.tab-nav-btn');
    const tabsPanels = scope.querySelectorAll('.tab-panel-content');

    tabsButtons.forEach(button => {
        button.addEventListener('click', () => {
            const targetId = button.getAttribute('data-target');

            // 1. Désactiver tous les boutons et panneaux
            tabsButtons.forEach(btn => btn.classList.remove('active'));
            tabsPanels.forEach(panel => panel.classList.remove('active'));

            // 2. Activer l'onglet courant
            button.classList.add('active');
            const targetPanel = scope.querySelector(`#${targetId}`);
            if (targetPanel) targetPanel.classList.add('active');
        });
    });
});
