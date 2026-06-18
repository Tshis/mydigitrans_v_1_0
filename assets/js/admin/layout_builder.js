document.addEventListener('DOMContentLoaded', () => {
    
    console.log('layout_builder chargé');

    const scope = document.querySelector('.admin-bus-layout-add-scope');
    if (!scope) return;

    // Éléments racines
    const inputRows = scope.querySelector('#layout-main-rows');
    const inputCols = scope.querySelector('#layout-main-cols');
    const checkboxBackExtra = scope.querySelector('#layout-back-extra');
    const gridContainer = scope.querySelector('#layout-interactive-grid');
    const btnGenerate = scope.querySelector('#btn-generate-matrix');

    // Gestion Brique 1 : Cellules spéciales
    const containerSpecialCells = scope.querySelector('#special-cells-container');
    const btnAddSpecialCell = scope.querySelector('#btn-add-special-cell');

    // Gestion Brique 2 : Couloirs
    const aisleToggle = scope.querySelector('#layout-aisle-toggle');
    const aisleLogicBlock = scope.querySelector('#aisle-logic-block');
    const containerAisles = scope.querySelector('#aisles-container');
    const btnAddAisle = scope.querySelector('#btn-add-aisle');

    // --- AUTOMATISATION DES CHAMPS REPETABLES (BOUTON + CELULLE) ---
    if (btnAddSpecialCell && containerSpecialCells) {
        btnAddSpecialCell.addEventListener('click', () => {
            const block = document.createElement('div');
            block.classList.add('js-special-cell', 'border-top-dashed');
            block.innerHTML = `
                <button type="button" class="btn-remove-dynamic-row mb-10"><i class="fa-solid fa-trash"></i> Retirer</button>
                <div class="field mb-20">
                  <label class="admin-bus-layout-add-label">Type de la cellule</label>
                  <select class="admin-bus-layout-add-select dynamic-ctx-type" name="specialPositionType[]">
                    <option >Faite votre choix</option>
                    <option value="driver" selected>Chauffeur</option>
                    <option value="wc">Toilettes (WC)</option>
                    <option value="door">Portière / Escalier</option>
                    <option value="vip">Siège Passager VIP</option>
                    <option value="aisle">Allée vide</option>
                  </select>
                </div>
                <div class="field-group mb-5">
                  <div class="field">
                    <label class="admin-bus-layout-add-label">La rangée numéro</label>
                    <input name="specialPositionRow[]" type="number" class="admin-bus-layout-add-input dynamic-ctx-row" value="1" min="1" max="25">
                  </div>
                  <div class="field">
                    <label class="admin-bus-layout-add-label">Colonne numéro</label>
                    <input name="specialPositionCol[]" type="number" class="admin-bus-layout-add-input dynamic-ctx-col" value="1" min="1" max="7">
                  </div>
                </div>
            `;
            block.querySelector('.btn-remove-dynamic-row').addEventListener('click', () => block.remove());
            containerSpecialCells.appendChild(block);
        });
        btnAddSpecialCell.click(); // Initialise une ligne par défaut (Chauffeur)
        console.log(document.querySelectorAll('.js-special-cell').length);
    }

    // --- AUTOMATISATION DES CHAMPS REPETABLES (BOUTON + COULOIR) ---
    if (aisleToggle && aisleLogicBlock) {
        aisleToggle.addEventListener('change', () => {
            aisleLogicBlock.style.display = aisleToggle.checked ? 'block' : 'none';
        });

        btnAddAisle.addEventListener('click', () => {
            const block = document.createElement('div');
            block.classList.add('field', 'mb-5', 'border-top-dashed');
            block.style.paddingTop = '10px';
            block.innerHTML = `
                <button type="button" class="btn-remove-dynamic-row"><i class="fa-solid fa-trash"></i></button>
                <label class="admin-bus-layout-add-label">Après la colonne :</label>
                <input name="aisles[]" placeholder="Ex : 2" class="admin-bus-layout-add-input dynamic-aisle-pos" type="number" min="1" max="7">
            `;
            block.querySelector('.btn-remove-dynamic-row').addEventListener('click', () => block.remove());
            containerAisles.appendChild(block);
        });
        btnAddAisle.click(); // Initialise une ligne par défaut (Allée centrale)
        const firstAisle = containerAisles.querySelector('.dynamic-aisle-pos');
        if(firstAisle) firstAisle.value = 2; // Valeur 2 par défaut
    }

    // --- ALGORITHME DE RENDU GRAPHIQUE SELON LES CRITÈRES CONFIGURÉS ---
    function generateLayoutFromFormData() {
        if (!gridContainer) return;

        const totalRows = parseInt(inputRows.value) || 0;
        const totalSeatCols = parseInt(inputCols.value) || 0;

        // A. Extraction des couloirs déclarés
        const aislePositions = [];
        if (aisleToggle.checked) {
            scope.querySelectorAll('.dynamic-aisle-pos').forEach(input => {
                const val = parseInt(input.value);
                if (val > 0) aislePositions.push(val);
            });
        }

        // Calcule le nombre total de colonnes réelles de la grille (Sièges + Couloirs)
        const totalGridCols = totalSeatCols + aislePositions.length;
        gridContainer.innerHTML = '';
        gridContainer.style.setProperty('--grid-cols', totalGridCols);

        // B. Extraction des cellules spéciales déclarées
        const specialCells = [];
        scope.querySelectorAll('.js-special-cell').forEach(cellBlock => {
            const type = cellBlock.querySelector('.dynamic-ctx-type').value;
            const r = parseInt(cellBlock.querySelector('.dynamic-ctx-row').value);
            const c = parseInt(cellBlock.querySelector('.dynamic-ctx-col').value);
            if (r > 0 && c > 0) { specialCells.push({ r: r, c: c, type: type }); }
        });

        // C. Boucle de génération de la matrice
        for (let r = 1; r <= totalRows; r++) {
            let seatColIndex = 0;
            let appliedAislesCount = 0;

            for (let c = 1; c <= totalGridCols; c++) {
                const cell = document.createElement('div');
                cell.classList.add('cell-unit');
                cell.setAttribute('data-row', r);

                // Vérification si cette colonne de la grille correspond à un couloir déclaré
                const isBackRow = (r === totalRows);
                const shouldDrawAisle = aislePositions.includes(seatColIndex);

                if (shouldDrawAisle && (!isBackRow || !checkboxBackExtra.checked)) {
                    cell.setAttribute('data-type', 'aisle');
                    gridContainer.appendChild(cell);
                    aislePositions.splice(aislePositions.indexOf(seatColIndex), 1); // Évite les doublons sur la même ligne
                    appliedAislesCount++;
                    continue;
                }

                seatColIndex++;
                cell.setAttribute('data-type', 'seat'); // Par défaut : siège normal
                cell.setAttribute('data-col', seatColIndex);

                // Écrase par le type spécial si correspondance trouvée dans le formulaire
                const matchSpecial = specialCells.find(item => item.r === r && item.c === seatColIndex);
                if (matchSpecial) {
                    cell.setAttribute('data-type', matchSpecial.type);
                }

                gridContainer.appendChild(cell);
            }
            
            // Recharge les positions des couloirs pour la rangée suivante
            if (aisleToggle.checked) {
                scope.querySelectorAll('.dynamic-aisle-pos').forEach(input => {
                    const val = parseInt(input.value);
                    if (val > 0 && !aislePositions.includes(val)) aislePositions.push(val);
                });
            }
        }
        reindexSeats();
    }

    function reindexSeats() {
        const cells = gridContainer.querySelectorAll('.cell-unit');
        let seatNum = 1;
        cells.forEach(cell => {
            const type = cell.getAttribute('data-type');
            if (type === 'seat' || type === 'vip') { cell.textContent = seatNum++; }
            else if (type === 'driver') { cell.textContent = 'CH'; }
            else if (type === 'wc') { cell.textContent = 'WC'; }
            else if (type === 'door') { cell.textContent = 'PORT'; }
            else { cell.textContent = ''; }
        });
    }

    btnGenerate.addEventListener('click', generateLayoutFromFormData);
    //generateLayoutFromFormData(); // Premier lancement automatique
    

   
    
    
});
