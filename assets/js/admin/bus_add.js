// public/js/admin/bus_add2.js

document.addEventListener('DOMContentLoaded', () => {
    const scope = document.querySelector('.admin-agency-bus-add-container');
    if (!scope) {
        console.error("Conteneur racine .admin-agency-bus-add-container introuvable.");
        return;
    }

    // Racines du Concepteur Tactile
    const customColumn = scope.querySelector('#custom-layout-builder-column');
    const btnToggleCustom = scope.querySelector('#btn-toggle-custom-layout');
    const busLayoutSelector = scope.querySelector('#bus-layout-selector');
    const btnGenerateBuilder = scope.querySelector('#btn-generate-builder-grid');
    const builderGrid = scope.querySelector('#layout-builder-grid');
    
    // Panneau de modification contextuelle
    const cellCustomizerPanel = scope.querySelector('#cell-customizer-panel');
    const dropdownCellType = scope.querySelector('#cell-type-dropdown');
    const btnApplyCell = scope.querySelector('#btn-apply-cell-changes');
    const labelR = scope.querySelector('#selected-r');
    const labelC = scope.querySelector('#selected-c');
    
    // Éléments du formulaire parent
    const containerAisles = scope.querySelector('#aisles-container');

    let activeCell = null;
    const defaultAislePositions = Array.of(2); // Allée centrale par défaut

    if (customColumn) customColumn.style.display = 'none';

    if (btnToggleCustom && customColumn) {

        btnToggleCustom.addEventListener('click', () => {
            if (customColumn.style.display === 'none') {
                customColumn.style.display = 'block';
                btnToggleCustom.innerHTML = '<i class="fa-solid fa-angle-left"></i> Utiliser standards';
                generateBuilderGrid();
                busLayoutSelector.setAttribute('disabled','disabled');
            } else {
                busLayoutSelector.removeAttribute('disabled','disabled');
                customColumn.style.display = 'none';
                btnToggleCustom.innerHTML = '<i class="fa-solid fa-plus"></i> Sur-mesure';
                builderGrid.innerHTML = '';
            }
        });
    }

    if (btnGenerateBuilder) {
        btnGenerateBuilder.addEventListener('click', generateBuilderGrid);
    }

    function generateBuilderGrid() {
        if (!builderGrid) return;
        const rows = parseInt(scope.querySelector('#builder-rows').value) || 10;
        const cols = parseInt(scope.querySelector('#builder-cols').value) || 4;

        const workingAisles = Array.from(defaultAislePositions);
        const totalGridCols = cols + workingAisles.length;

        builderGrid.innerHTML = '';
        builderGrid.style.setProperty('--grid-cols', totalGridCols);

        for (let r = 1; r <= rows; r++) {
            let seatColIndex = 0;
            for (let c = 1; c <= totalGridCols; c++) {
                const cell = document.createElement('div');
                cell.classList.add('cell-unit');
                cell.setAttribute('data-grid-row', r);

                const isBackRow = (r === rows);
                const isDefaultAisle = workingAisles.includes(seatColIndex);
                
                let currentType = 'seat';
                
                if (isDefaultAisle && !isBackRow) {
                    currentType = 'aisle';
                    workingAisles.splice(workingAisles.indexOf(seatColIndex), 1);
                } else {
                    seatColIndex++;
                }

                cell.setAttribute('data-type', currentType);
                cell.setAttribute('data-row', r);
                cell.setAttribute('data-col', seatColIndex);

                // Au départ, les allées par défaut (allée centrale) n'envoient rien
                // Seules les modifications utilisateur généreront des inputs
                cell.innerHTML = `<span class="-number"></span>`;

                cell.addEventListener('click', () => {
                    activeCell = cell;
                    labelR.textContent = r;
                    labelC.textContent = seatColIndex;
                    dropdownCellType.value = cell.getAttribute('data-type');
                    cellCustomizerPanel.style.display = 'block';
                });

                builderGrid.appendChild(cell);
            }

            defaultAislePositions.forEach(val => {
                if (!workingAisles.includes(val)) workingAisles.push(val);
            });
        }
        reindexAndRenderIcons();
        serializeAislesToForm();
    }

    if (btnApplyCell) {
        btnApplyCell.addEventListener('click', () => {
            if (activeCell) {
                const newType = dropdownCellType.value;
                const r = activeCell.getAttribute('data-row');
                const c = activeCell.getAttribute('data-col');
                
                activeCell.setAttribute('data-type', newType);

                // CORRECTION HISTORIQUE : Si l'utilisateur force une allée "aisle" ou un type spécial, 
                // on l'envoie au contrôleur pour qu'elle soit mémorisée fidèlement !
                if (newType !== 'seat') {
                    activeCell.innerHTML = `
                        <input type="hidden" name="specialPositionType[]" value="${newType}">
                        <input type="hidden" name="specialPositionRow[]" value="${r}">
                        <input type="hidden" name="specialPositionCol[]" value="${c}">
                        <span class="-number"></span>
                    `;
                } else {
                    // Si on repasse en siège normal, on nettoie les inputs
                    activeCell.innerHTML = `<span class="-number"></span>`;
                }

                reindexAndRenderIcons();
                cellCustomizerPanel.style.display = 'none';
            }
        });
    }

    function reindexAndRenderIcons() {
        const cells = builderGrid.querySelectorAll('.cell-unit');
        let seatNum = 1;

        cells.forEach(cell => {
            const type = cell.getAttribute('data-type');
            
            const inputType = cell.querySelector('input[name="specialPositionType[]"]');
            const inputRow = cell.querySelector('input[name="specialPositionRow[]"]');
            const inputCol = cell.querySelector('input[name="specialPositionCol[]"]');
            
            if (!inputType && (type !== 'seat' && type !== 'aisle')) return;

            cell.innerHTML = '';
            if (inputType) {
                cell.appendChild(inputType);
                cell.appendChild(inputRow);
                cell.appendChild(inputCol);
            }

            if (type === 'seat' || type === 'vip') {
                const spanNum = document.createElement('span');
                spanNum.classList.add('-number');
                spanNum.textContent = seatNum++;
                cell.appendChild(spanNum);
            } else {
                const icon = document.createElement('i');
                if (type === 'driver') icon.className = 'fa-solid fa-id-card';
                else if (type === 'wc') icon.className = 'fa-solid fa-restroom';
                else if (type === 'door') icon.className = 'fa-solid fa-door-open';
                else if (type === 'cashier') icon.className = 'fa-solid fa-hand-holding-dollar';
                else if (type === 'porter') icon.className = 'fa-solid fa-image-portrait';
                
                if (type !== 'aisle') cell.appendChild(icon);
            }
        });
    }

    function serializeAislesToForm() {
        if (!containerAisles) return;
        containerAisles.innerHTML = '';
        defaultAislePositions.forEach(val => {
            containerAisles.insertAdjacentHTML('beforeend', `<input type="hidden" name="aisles[]" value="${val}">`);
        });
    }
});
