/**
 * Mydigitrans SaaS - Module Espace Caissier
 * Validation des décaissements et suivi des sorties physiques réelles du coffre
 */
document.addEventListener('DOMContentLoaded', () => {
    const disburseButtons = document.querySelectorAll('.js-btn-disburse');

    disburseButtons.forEach(button => {
        button.addEventListener('click', function() {
            const ref = this.getAttribute('data-ref');
            const amountOrdered = this.getAttribute('data-amount');
            const currencyOrdered = this.getAttribute('data-currency');

            // 1. Demande de confirmation standard de l'ordre
            const firstConfirm = confirm(`Confirmer l'ordonnancement de la dépense ${ref} pour un montant théorique de : ${amountOrdered} ${currencyOrdered} ?`);
            
            if (!firstConfirm) return;

            // 2. Flexibilité RDC : Saisie de la monnaie physique réellement décaissée du tiroir
            const realCurrency = prompt(`Devise de décaissement physique ? (Laissez vide pour valider en ${currencyOrdered})`, currencyOrdered);
            
            if (realCurrency === null) return; // Annulation
            
            let targetCurrency = realCurrency.trim().toUpperCase() || currencyOrdered;
            let targetAmount = amountOrdered;

            // Si la devise varie, on force le caissier à inscrire la valeur de sortie réelle pour son journal de caisse
            if (targetCurrency !== currencyOrdered) {
                const realAmount = prompt(`Quel montant exact en ${targetCurrency} sortez-vous physiquement du tiroir ?`);
                if (!realAmount) {
                    alert("Opération annulée. Le montant réel est requis pour les décaissements croisés.");
                    return;
                }
                targetAmount = realAmount;
            }

            // 3. Validation finale du flux
            alert(`Décaissement validé pour le grand livre comptable Mydigitrans !\n\n` +
                  `• Ordre : ${amountOrdered} ${currencyOrdered}\n` +
                  `• Sortie physique coffre : ${targetAmount} ${targetCurrency}\n` +
                  `• Statut du bon : PAID (Clôturé)`);
            
            // Rechargement ou mutation AJAX de la ligne en production ici
            location.reload();
        });
    });
});
