document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('fare-modal');
    const btnOpen = document.getElementById('js-open-fare-modal');
    const btnClose = document.getElementById('js-close-fare-modal');

    if (btnOpen) {
        btnOpen.addEventListener('click', (e) => {
            e.preventDefault();
            modal.style.display = 'flex'; // On force le flex pour le centrage
            document.body.style.overflow = 'hidden'; // Empêche le scroll du fond
        });
    }

    if (btnClose) {
        btnClose.addEventListener('click', () => {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        });
    }

    // Fermer si on clique à l'extérieur de la card
    window.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    });
});
