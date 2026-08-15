document.addEventListener('DOMContentLoaded', () => {
    const registerNameInput = document.getElementById('register-name-input');
    const registerCodeInput = document.getElementById('register-code-input');
    const branchSelect = document.getElementById('register-branch-select');
    const branchStatic = document.getElementById('register-branch-static');
    const typeSelect = document.getElementById('register-type-select');

    const summaryName = document.getElementById('summary-register-name');
    const summaryCode = document.getElementById('summary-register-code');
    const summaryBranch = document.getElementById('summary-register-branch');
    const summaryType = document.getElementById('summary-register-type');

    if (registerNameInput) {
        registerNameInput.addEventListener('input', (e) => {
            summaryName.textContent = e.target.value.trim() || 'Non défini';
        });
    }

    if (registerCodeInput) {
        registerCodeInput.addEventListener('input', (e) => {
            summaryCode.textContent = e.target.value.toUpperCase().replace(/\s+/g, '-') || '-';
        });
    }

    // Gestion de la succursale selon le rôle
    if (branchSelect) {
        // Mode Admin Général : Changement dynamique
        branchSelect.addEventListener('change', () => {
            const selectedOption = branchSelect.options[branchSelect.selectedIndex];
            summaryBranch.textContent = selectedOption.getAttribute('data-branch-name');
        });
    } else if (branchStatic) {
        // Mode Gérant de Succursale : Valeur figée par défaut
        summaryBranch.textContent = branchStatic.getAttribute('data-branch-name');
    }

    if (typeSelect) {
        typeSelect.addEventListener('change', () => {
            const selectedOption = typeSelect.options[typeSelect.selectedIndex];
            summaryType.textContent = selectedOption.getAttribute('data-type-label');
        });
    }
});
