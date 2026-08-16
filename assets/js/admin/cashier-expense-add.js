/**
 * Mydigitrans SaaS - Module Finances
 * Logique de contrôle des formulaires de dépenses selon le type et le financement
 */
document.addEventListener('DOMContentLoaded', () => {
    const typeSelect = document.getElementById('expense-type-select');
    const branchWrapper = document.getElementById('branch-field-wrapper');
    const fundingWrapper = document.getElementById('funding-source-wrapper');
    const sourceSelect = document.getElementById('expense-funding-source');
    const cashWrapper = document.getElementById('cash-session-field-wrapper');
    const cashSelect = document.getElementById('expense-cash-session');
    
    // Champs spécifiques abonnements
    const subscriptionDatesWrapper = document.getElementById('subscription-dates-wrapper');
    const validFromInput = document.getElementById('valid-from-input');
    const validUntilInput = document.getElementById('valid-until-input');

    function toggleFormState() {
        const isSubscription = typeSelect.value === 'subscription';

        if (isSubscription) {
            // Un abonnement SaaS affiche les dates et est forcément hors caisse
            if (subscriptionDatesWrapper) subscriptionDatesWrapper.style.display = 'grid';
            if (validFromInput) validFromInput.required = true;
            if (validUntilInput) validUntilInput.required = true;

            if (branchWrapper) branchWrapper.style.display = 'none';
            if (fundingWrapper) fundingWrapper.style.display = 'none';
            if (cashWrapper) cashWrapper.style.display = 'none';
            
            if (sourceSelect) sourceSelect.value = 'bank';
            if (cashSelect) {
                cashSelect.required = false;
                cashSelect.value = '';
            }
        } else {
            // Frais opérationnels classiques
            if (subscriptionDatesWrapper) subscriptionDatesWrapper.style.display = 'none';
            if (validFromInput) validFromInput.required = false;
            if (validUntilInput) validUntilInput.required = false;

            if (branchWrapper) branchWrapper.style.display = 'block';
            if (fundingWrapper) fundingWrapper.style.display = 'block';
            
            // Évaluation secondaire du compartiment de financement
            handleFundingSourceState();
        }
    }

    function handleFundingSourceState() {
        if (typeSelect.value === 'subscription') return;

        if (sourceSelect && sourceSelect.value === 'cash_register') {
            if (cashWrapper) cashWrapper.style.display = 'block';
            if (cashSelect) cashSelect.required = true;
        } else {
            if (cashWrapper) cashWrapper.style.display = 'none';
            if (cashSelect) {
                cashSelect.required = false;
                cashSelect.value = '';
            }
        }
    }

    // Assignation des écouteurs de flux
    if (typeSelect) typeSelect.addEventListener('change', toggleFormState);
    if (sourceSelect) sourceSelect.addEventListener('change', handleFundingSourceState);

    // Initialisation au chargement de la vue
    toggleFormState();
});
