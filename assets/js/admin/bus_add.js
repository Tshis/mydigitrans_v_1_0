document.addEventListener('DOMContentLoaded', () => {
    const btnToggleCustom = document.getElementById('btn-toggle-custom-layout');
    const customBuilderColumn = document.getElementById('custom-layout-builder-column');
    const busLayoutSelector = document.getElementById('bus-layout-selector');
    const previewLayoutZone = document.getElementById('preview-layout-zone');

    const btnGenerateBuilder = document.getElementById('btn-generate-builder-grid');
    const inputRows = document.getElementById('builder-rows');
    const inputCols = document.getElementById('builder-cols');
    const gridContainer = document.getElementById('layout-builder-grid');

    // Éléments du Popover contextuel
    const customizerPanel = document.getElementById('cell-customizer-panel');
    const labelR = document.getElementById('selected-r');
    const labelC = document.getElementById('selected-c');
    const dropdownType = document.getElementById('cell-type-dropdown');
    const inputCustomLabel = document.getElementById('cell-label-input');
    const btnApplyCell = document.getElementById('btn-apply-cell-changes');

    const btnSaveCustomLayout = document.getElementById('btn-save-custom-layout');
    const hiddenJsonInput = document.getElementById('custom-layout-json');

    let activeCell = null;

    if (btnToggleCustom && customBuilderColumn) {
        customBuilderColumn.style.display = 'none';
        btnToggleCustom.addEventListener('click', () => {
            if (customBuilderColumn.style.display === 'none') {
                customBuilderColumn.style.display = 'block';
                btnToggleCustom.innerHTML = '<i class="fa-solid fa-xmark"></i> Annuler le plan';
                btnToggleCustom.style.backgroundColor = '#e74c3c';
                btnToggleCustom.style.color = '#fff';
                busLayoutSelector.disabled = true;
                if (previewLayoutZone) previewLayoutZone.style.display = 'none';
                buildInteractiveGrid();
            } else {
                closeCustomBuilder();
            }
        });
    }

    function closeCustomBuilder() {
        customBuilderColumn.style.display = 'none';
        btnToggleCustom.innerHTML = '<i class="fa-solid fa-plus"></i> Sur-mesure';
        btnToggleCustom.style.backgroundColor = '';
        btnToggleCustom.style.color = '';
        busLayoutSelector.disabled = false;
        customizerPanel.style.display = 'none';
        activeCell = null;
    }

    // RESOLUTION : Génération de la grille avec application de la variable de style pour CSS Grid
    function buildInteractiveGrid() {
        if (!gridContainer) return;
        const rows = parseInt(inputRows.value) || 10;
        const cols = parseInt(inputCols.value) || 4;
        
        gridContainer.innerHTML = '';
        
        // CORRECTION DE L'ALIGNEMENT : On pousse le nombre de colonnes dans la variable CSS
        gridContainer.style.setProperty('--grid-cols', cols);
        customizerPanel.style.display = 'none';
        activeCell = null;

        for (let r = 1; r <= rows; r++) {
            for (let c = 1; c <= cols; c++) {
                const cell = document.createElement('div');
                cell.classList.add('builder-cell');
                cell.setAttribute('data-type', 'seat');
                cell.setAttribute('data-row', r);
                cell.setAttribute('data-col', c);

                // Gabarit initial intelligent (Conducteur en haut à gauche et allée centrale)
                if (r === 1 && c === 1) cell.setAttribute('data-type', 'driver');
                const middleCol = Math.ceil(cols / 2);
                if (c === middleCol && r > 1 && r < rows) cell.setAttribute('data-type', 'aisle');

                // ÉVÉNEMENT CLIC : Remplissage des variables du Popover
                cell.addEventListener('click', () => {
                    if (activeCell) activeCell.classList.remove('is-editing');
                    activeCell = cell;
                    cell.classList.add('is-editing');

                    labelR.textContent = cell.getAttribute('data-row');
                    labelC.textContent = cell.getAttribute('data-col');
                    dropdownType.value = cell.getAttribute('data-type');
                    inputCustomLabel.value = cell.getAttribute('data-custom-text') || '';

                    customizerPanel.style.display = 'block';
                });

                gridContainer.appendChild(cell);
            }
        }
        reindexBuilderSeats();
    }

    // RESOLUTION : Validation et application instantanée des types de sièges spéciaux choisis
    btnApplyCell.addEventListener('click', () => {
        if (!activeCell) return;

        const newType = dropdownType.value;
        const customText = inputCustomLabel.value.trim();

        activeCell.setAttribute('data-type', newType);
        if (customText !== '') {
            activeCell.setAttribute('data-custom-text', customText);
        } else {
            activeCell.removeAttribute('data-custom-text');
        }

        activeCell.classList.remove('is-editing');
        customizerPanel.style.display = 'none';
        activeCell = null;

        reindexBuilderSeats(); // Déclenche le recalcul automatique
    });

    // Algorithme d'indexation dynamique des libellés et numéros
    function reindexBuilderSeats() {
        const cells = gridContainer.querySelectorAll('.builder-cell');
        let currentNum = 1;

        cells.forEach(cell => {
            const type = cell.getAttribute('data-type');
            const customText = cell.getAttribute('data-custom-text');

            if (type === 'seat' || type === 'vip') {
                cell.textContent = customText ? customText : currentNum++;
            } else if (type === 'driver') {
                cell.textContent = customText ? customText : 'CH';
            } else if (type === 'wc') {
                cell.textContent = customText ? customText : 'WC';
            } else if (type === 'door') {
                cell.textContent = customText ? customText : 'PORT';
            } else {
                cell.textContent = ''; // Pour les allées vides
            }
        });
    }

    if (btnGenerateBuilder) btnGenerateBuilder.addEventListener('click', buildInteractiveGrid);

    // Envoi final du gabarit à Symfony
    if (btnSaveCustomLayout) {
        btnSaveCustomLayout.addEventListener('click', () => {
            const cells = gridContainer.querySelectorAll('.builder-cell');
            const layoutData = [];

            cells.forEach(cell => {
                layoutData.push({
                    row: parseInt(cell.getAttribute('data-row')),
                    col: parseInt(cell.getAttribute('data-col')),
                    type: cell.getAttribute('data-type'),
                    label: cell.textContent
                });
            });

            hiddenJsonInput.value = JSON.stringify({
                rows: parseInt(inputRows.value),
                cols: parseInt(inputCols.value),
                matrix: layoutData
            });

            const newOption = document.createElement('option');
            newOption.value = "custom_generated";
            newOption.textContent = `Gabarit sur-mesure créé (${inputRows.value}x${inputCols.value})`;
            newOption.selected = true;
            busLayoutSelector.appendChild(newOption);

            customBuilderColumn.style.display = 'none';
            busLayoutSelector.disabled = false;
            alert('Votre gabarit de bus personnalisé a été enregistré !');
        });
    }
});
