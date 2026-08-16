/**
 * Mydigitrans SaaS - Module Finances
 * Gestion de l'affichage de la modale d'audit et remboursement partiel
 */
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('refund-modal');
    const btnOpen = document.getElementById('js-open-refund');
    const btnClose = document.getElementById('js-close-refund');
    const refundTypeSelect = document.getElementById('refund-type');
    const partialAmountWrapper = document.getElementById('partial-amount-wrapper');
    const partialAmountInput = document.getElementById('refund-amount-input');

    if (btnOpen && modal) {
        btnOpen.addEventListener('click', () => {
            modal.style.display = 'flex';
        });
    }

    if (btnClose && modal) {
        btnClose.addEventListener('click', () => {
            modal.style.display = 'none';
        });
    }

    if (refundTypeSelect) {
        refundTypeSelect.addEventListener('change', () => {
            if (refundTypeSelect.value === 'partial') {
                partialAmountWrapper.style.display = 'block';
                if (partialAmountInput) partialAmountInput.required = true;
            } else {
                partialAmountWrapper.style.display = 'none';
                if (partialAmountInput) {
                    partialAmountInput.required = false;
                    partialAmountInput.value = '';
                }
            }
        });
    }
});
