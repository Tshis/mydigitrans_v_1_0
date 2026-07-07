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
                if (busLayoutSelector) {
                    busLayoutSelector.disabled = true;
                    busLayoutSelector.value = ''; // Réinitialise l'ancienne sélection par défaut
                }
                if (previewLayoutZone) previewLayoutZone.style.display = 'none';
                buildInteractiveGrid();
            } else {
                closeCustomBuilder();
            }
        });
    }

    function closeCustomBuilder() {
        if (!customBuilderColumn) return;
        customBuilderColumn.style.display = 'none';
        if (btnToggleCustom) {
            btnToggleCustom.innerHTML = '<i class="fa-solid fa-plus"></i> Sur-mesure';
            btnToggleCustom.style.backgroundColor = '';
            btnToggleCustom.style.color = '';
        }
        if (busLayoutSelector) busLayoutSelector.disabled = false;
        if (customizerPanel) customizerPanel.style.display = 'none';
        activeCell = null;
    }

    function buildInteractiveGrid() {
        if (!gridContainer) return;
        const rows = parseInt(inputRows.value) || 10;
        const cols = parseInt(inputCols.value) || 4;
        gridContainer.innerHTML = '';

        // Application de la colonne dynamique à la variable CSS Grid de ta matrice
        gridContainer.style.setProperty('--grid-cols', cols);
        if (customizerPanel) customizerPanel.style.display = 'none';
        activeCell = null;

        for (let r = 1; r <= rows; r++) {
            for (let c = 1; c <= cols; c++) {
                const cell = document.createElement('div');
                cell.classList.add('cell-unit'); // Utilise ta classe CSS .cell-unit (40px)
                cell.setAttribute('data-type', 'seat');
                cell.setAttribute('data-row', r);
                cell.setAttribute('data-col', c);

                // Gabarit initial pré-configuré par défaut
                if (r === 1 && c === 1) cell.setAttribute('data-type', 'driver');
                
                const middleCol = Math.ceil(cols / 2);
                if (c === middleCol && r > 1 && r < rows) cell.setAttribute('data-type', 'aisle');

                // Événement d'édition au clic sur une case
                cell.addEventListener('click', () => {
                    if (activeCell) activeCell.classList.remove('is-editing');
                    activeCell = cell;
                    cell.classList.add('is-editing'); // Déclenche l'animation orange/jaune .is-editing
                    
                    if (labelR) labelR.textContent = cell.getAttribute('data-row');
                    if (labelC) labelC.textContent = cell.getAttribute('data-col');
                    if (dropdownType) dropdownType.value = cell.getAttribute('data-type');
                    if (customizerPanel) customizerPanel.style.display = 'block';
                });
                gridContainer.appendChild(cell);
            }
        }
        reindexBuilderSeats();
    }

    if (btnApplyCell) {
        btnApplyCell.addEventListener('click', () => {
            if (!activeCell) return;
            const newType = dropdownType.value;
            activeCell.setAttribute('data-type', newType);
            
            activeCell.classList.remove('is-editing');
            if (customizerPanel) customizerPanel.style.display = 'none';
            activeCell = null;
            reindexBuilderSeats(); // Recalcule les libellés et injecte les icônes Font Awesome
        });
    }

    // Indexation dynamique : Injection des balises HTML et des icônes exactes requises par ton SCSS
    function reindexBuilderSeats() {
        if (!gridContainer) return;
        const cells = gridContainer.querySelectorAll('.cell-unit');
        let currentNum = 1;

        cells.forEach(cell => {
            const type = cell.getAttribute('data-type');
            cell.innerHTML = ''; // Nettoie le contenu précédent

            if (type === 'seat' || type === 'vip') {
                // Structure exacte du Twig pour les sièges : span.-number
                const spanNum = document.createElement('span');
                spanNum.classList.add('-number');
                spanNum.textContent = currentNum++;
                cell.appendChild(spanNum);

            } else {
                // Injection des icônes Font Awesome autonomes
                const icon = document.createElement('i');
                
                if (type === 'driver') {
                    icon.className = 'fa-solid fa-id-card';
                    cell.appendChild(icon);
                } else if (type === 'wc') {
                    icon.className = 'fa-solid fa-restroom';
                    cell.appendChild(icon);
                } else if (type === 'door') {
                    icon.className = 'fa-solid fa-door-open';
                    cell.appendChild(icon);
                } else if (type === 'cashier') {
                    icon.className = 'fa-solid fa-hand-holding-dollar';
                    cell.appendChild(icon);
                } else if (type === 'porter') {
                    icon.className = 'fa-solid fa-image-portrait';
                    cell.appendChild(icon);
                }
                // Pour 'aisle', l'élément reste vide. Le SCSS applique les bordures pointillées automatiquement.
            }
        });
    }

    if (btnGenerateBuilder) btnGenerateBuilder.addEventListener('click', buildInteractiveGrid);

    // Enregistrement final de la structure sur-mesure
    if (btnSaveCustomLayout) {
        btnSaveCustomLayout.addEventListener('click', () => {
            const cells = gridContainer.querySelectorAll('.cell-unit');
            const layoutData = [];
            
            cells.forEach(cell => {
                const labelText = cell.querySelector('.-number') ? cell.querySelector('.-number').textContent : '';
                layoutData.push({
                    row: parseInt(cell.getAttribute('data-row')),
                    col: parseInt(cell.getAttribute('data-col')),
                    type: cell.getAttribute('data-type'),
                    label: labelText
                });
            });
            
            if (hiddenJsonInput) {
                hiddenJsonInput.value = JSON.stringify({
                    rows: parseInt(inputRows.value),
                    cols: parseInt(inputCols.value),
                    matrix: layoutData
                });
            }

            // Soumission unifiée et immédiate de tout le formulaire à Symfony
            const mainForm = document.getElementById('agency-add-bus-form');
            if (mainForm) {
                mainForm.submit();
            } else {
                alert('Gabarit enregistré ! Veuillez valider le formulaire.');
            }
        });
    }
});
