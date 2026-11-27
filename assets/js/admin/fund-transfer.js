// public/js/admin/fund-transfer.js

document.addEventListener('DOMContentLoaded', () => {
    const typeSelect = document.querySelector('[name$="[destinationType]"]') || document.getElementById('js-transfer-dest-type');
    const bankGroup = document.getElementById('js-bank-fields');
    const branchField = document.getElementById('js-branch-field');
    const branchSelect = branchField?.querySelector('select');
    const bankInputs = bankGroup ? Array.from(bankGroup.querySelectorAll('input')) : [];

    function toggleDestinationFields() {
        if (!typeSelect) return;
        const isBank = typeSelect.value === 'bank';

        bankGroup?.classList.toggle('is-hidden', !isBank);
        branchField?.classList.toggle('is-hidden', isBank);

        if (branchSelect) {
            branchSelect.required = !isBank;
            if (isBank) {
                branchSelect.value = '';
                branchSelect.tomselect?.clear(true);
            }
        }

        bankInputs.forEach((input) => {
            input.required = isBank;
            if (!isBank) input.value = '';
        });
    }

    typeSelect?.addEventListener('change', toggleDestinationFields);
    toggleDestinationFields();

    // --- MODALE DE CONFIRMATION DE RÉCEPTION DE TRANSFERT ---
    const modal = document.getElementById('js-receive-transfer-modal');
    const modalRefEl = document.getElementById('js-modal-transfer-ref');
    const modalAmountEl = document.getElementById('js-modal-transfer-amount');
    const btnCancel = document.getElementById('js-btn-cancel-receive');
    const btnConfirm = document.getElementById('js-btn-confirm-receive');
    let pendingForm = null;

    document.querySelectorAll('.js-open-receive-modal').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            pendingForm = btn.closest('form');
            if (!pendingForm || !modal) return;

            if (modalRefEl) modalRefEl.textContent = btn.dataset.ref || '';
            if (modalAmountEl) modalAmountEl.textContent = btn.dataset.amount || '';
            modal.classList.remove('is-hidden');
        });
    });

    btnCancel?.addEventListener('click', () => {
        pendingForm = null;
        modal?.classList.add('is-hidden');
    });

    modal?.addEventListener('click', (e) => {
        if (e.target === modal) {
            pendingForm = null;
            modal.classList.add('is-hidden');
        }
    });

    btnConfirm?.addEventListener('click', () => {
        if (!pendingForm) return;
        btnConfirm.disabled = true;
        pendingForm.submit();
    });
});
