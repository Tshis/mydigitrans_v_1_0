// public/js/admin/layout_builder.js

document.addEventListener('DOMContentLoaded', () => {
    console.log('Nouveau layout_builder interactif initialisé');
    const scope = document.querySelector('.admin-bus-layout-add-scope');
    if (!scope) return;

    // Éléments racines
    const inputRows = scope.querySelector('#layout-main-rows');
    const inputCols = scope.querySelector('#layout-main-cols');
    const checkboxBackExtra = scope.querySelector('#layout-back-extra');
    const gridContainer = scope.querySelector('#layout-interactive-grid');
    const btnGenerate = scope.querySelector('#btn-generate-matrix');

    // Conteneur masqué des inputs pour ton contrôleur PHP d'origine
    const containerSpecialCells = scope.querySelector('#special-cells-container');

    // Gestion Brique 2 : Couloirs
    const aisleToggle = scope.querySelector('#layout-aisle-toggle');
    const aisleLogicBlock = scope.querySelector('#aisle-logic-block');
    const containerAisles = scope.querySelector('#aisles-container');
    const btnAddAisle = scope.querySelector('#btn-add-aisle');

    // Panneau de modification contextuelle (Jumeau de l'agence)
    const cellCustomizerPanel = scope.querySelector('#cell-customizer-panel');
    const dropdownCellType = scope.querySelector('#cell-type-dropdown');
    const btnApplyCell = scope.querySelector('#btn-apply-cell-changes');
    const labelR = scope.querySelector('#selected-r');
    const labelC = scope.querySelector('#selected-c');

    let activeCell = null;
    
    // Registre centralisé en mémoire pour stocker tes clics personnalisés
    let specialCellsRegistry = []; 
    

    // --- AUTOMATISATION DES COULOIRS MULTIPLES (TON CODE DE BASE FIABILISÉ) ---
    if (aisleToggle && aisleLogicBlock) {
        aisleToggle.addEventListener('change', () => {
            aisleLogicBlock.style.display = aisleToggle.checked ? 'block' : 'none';
        });
        
        btnAddAisle.addEventListener('click', () => {
            const block = document.createElement('div');
            block.classList.add('field', 'mb-5', 'border-top-dashed');
            block.style.paddingTop = '10px';
            block.innerHTML = `
                <button type="button" class="btn-remove-dynamic-row" style="background: rgba(217,119,6,0.1); color: #d97706; border: 1px solid rgba(217,119,6,0.2); padding: 4px 8px; border-radius: 6px; cursor: pointer; float: right; margin-top: -5px;"><i class="fa-solid fa-trash"></i></button>
                <label class="admin-bus-layout-add-label">Après la colonne :</label>
                <input name="aisles[]" placeholder="Ex : 2" class="admin-bus-layout-add-input dynamic-aisle-pos" type="number" min="1" max="7" value="2">
            `;
            block.querySelector('.btn-remove-dynamic-row').addEventListener('click', () => {
                block.remove();
                generateLayoutFromFormData();
            });
            containerAisles.appendChild(block);
        });
        
        btnAddAisle.click(); // Initialise l'allée centrale par défaut après la colonne 2
    }

    // --- ALGORITHME DE RENDU GRAPHIQUE ROBUSTE ET SUR-MESURE ---
       // --- ALGORITHME DE RENDU GRAPHIQUE ROBUSTE ET SUR-MESURE ---
    function generateLayoutFromFormData() {
        if (!gridContainer) return;
        
        const totalRows = parseInt(inputRows.value) || 10;
        const totalSeatCols = parseInt(inputCols.value) || 4;

        // Extraction des couloirs de circulation déclarés à gauche
        const aislePositions = [];
        if (aisleToggle && aisleToggle.checked) {
            scope.querySelectorAll('.dynamic-aisle-pos').forEach(input => {
                const val = parseInt(input.value);
                if (val > 0) aislePositions.push(val);
            });
            aislePositions.sort((a, b) => a - b);
        }

        // Calcule le nombre total de colonnes physiques de la grille de dessin (Sièges + Allées)
        const totalGridCols = totalSeatCols + aislePositions.length;
        gridContainer.innerHTML = '';
        gridContainer.style.setProperty('--grid-cols', totalGridCols);

        let seatNum = 1; // Compteur unique et continu pour la numérotation des places passagers

        // Boucle de génération de la matrice
        for (let r = 1; r <= totalRows; r++) {
            let seatColIndex = 0;
            const currentAisles = [...aislePositions];

            for (let c = 1; c <= totalGridCols; c++) {
                const cell = document.createElement('div');
                cell.classList.add('cell-unit');
                
                // Coordonnées physiques absolues de la grille (Infaillibles pour le ciblage JS)
                cell.setAttribute('data-grid-row', r);
                cell.setAttribute('data-grid-col', c);

                const isBackRow = (r === totalRows);
                const isDefaultAisle = currentAisles.includes(seatColIndex);
                
                // CORRECTION DE SÉCURITÉ : On vérifie si la checkbox existe avant de lire son état (.checked)
                const hasBackExtraChecked = checkboxBackExtra ? checkboxBackExtra.checked : false;
                
                let assignedType = 'seat';

                // On vérifie d'abord si la colonne est structurellement une allée par défaut
                if (isDefaultAisle && (!isBackRow || !hasBackExtraChecked)) {
                    assignedType = 'aisle';
                    currentAisles.splice(currentAisles.indexOf(seatColIndex), 1);
                } else {
                    seatColIndex++;
                }

                // On grave les coordonnées logiques théoriques de ton contrôleur PHP
                cell.setAttribute('data-row', r);
                cell.setAttribute('data-col', seatColIndex);
                cell.setAttribute('data-type', assignedType);

                // --- RECOUVREMENT PRIORITAIRE PAR LE REGISTRE DE CLICS ---
                const matchSaved = specialCellsRegistry.find(item => item.gridR === r && item.gridC === c);
                if (matchSaved) {
                    cell.setAttribute('data-type', matchSaved.type);
                    cell.setAttribute('data-col', seatColIndex); 
                }

                // --- RENDU CONTENU : ICONES OU NUMÉROTATION CONTINUE ---
                const currentLiveType = cell.getAttribute('data-type');
                
                if (currentLiveType === 'seat' || currentLiveType === 'vip') {
                    const spanNum = document.createElement('span');
                    spanNum.classList.add('-number');
                    spanNum.textContent = seatNum++;
                    cell.appendChild(spanNum);
                } else {
                    const icon = document.createElement('i');
                    if (currentLiveType === 'driver') icon.className = 'fa-solid fa-id-card';
                    else if (currentLiveType === 'wc') icon.className = 'fa-solid fa-restroom';
                    else if (currentLiveType === 'door') icon.className = 'fa-solid fa-door-open';
                    else if (currentLiveType === 'cashier') icon.className = 'fa-solid fa-hand-holding-dollar';
                    else if (currentLiveType === 'porter') icon.className = 'fa-solid fa-image-portrait';
                    
                    if (currentLiveType !== 'aisle') {
                        cell.appendChild(icon);
                    }
                }

                // RENDRE TOUTES LES CASES SANS EXCEPTION INTERACTIVES ET CLIQUABLES
                cell.addEventListener('click', () => {
                    activeCell = cell;
                    labelR.textContent = cell.getAttribute('data-row');
                    labelC.textContent = cell.getAttribute('data-col');
                    dropdownCellType.value = cell.getAttribute('data-type');
                    cellCustomizerPanel.style.display = 'block';
                });

                gridContainer.appendChild(cell);
            }
        }

        // On synchronise les modifications avec le formulaire invisible pour PHP
        serializeSpecialCellsToForm();
        serializeAislesToForm();
    }

    // --- APPLICATION ET ENREGISTREMENT DE TON CHOIX COMPTABLE ---
    if (btnApplyCell) {
        btnApplyCell.addEventListener('click', () => {
            if (activeCell) {
                const newType = dropdownCellType.value;
                const gridR = parseInt(activeCell.getAttribute('data-grid-row'));
                const gridC = parseInt(activeCell.getAttribute('data-grid-col'));
                const defaultType = activeCell.getAttribute('data-default-type');

                // On met à jour le type visuel
                activeCell.setAttribute('data-type', newType);

                // On nettoie l'ancien enregistrement pour ces coordonnées de grille précises
                specialCellsRegistry = specialCellsRegistry.filter(item => !(item.gridR === gridR && item.gridC === gridC));
                
                // On n'enregistre une exception que si le nouveau type dévie de l'état structurel de base du bus
                if (newType !== defaultType) {
                    specialCellsRegistry.push({
                        gridR: gridR,
                        gridC: gridC,
                        r: parseInt(activeCell.getAttribute('data-row')),
                        c: parseInt(activeCell.getAttribute('data-col')),
                        type: newType
                    });
                }

                // On redessine proprement la grille : la numérotation et le maillage se recalculent à la perfection
                generateLayoutFromFormData();
                
                cellCustomizerPanel.style.display = 'none';
            }
        });
    }

    // --- GENERATEUR D'INPUTS CACHÉS CONFORME À TON CONTROLEUR PHP ---
    // --- GENERATEUR D'INPUTS CACHÉS CONFORME À TON CONTROLEUR PHP ---
    function serializeSpecialCellsToForm() {
        if (!containerSpecialCells) return;
        containerSpecialCells.innerHTML = ''; // Nettoie le conteneur masqué à gauche pour éviter les doublons

        specialCellsRegistry.forEach(item => {
            // Si l'utilisateur a modifié une cellule (comme un couloir bouché en siège),
            // on génère la structure de champs parallèles attendue par ton foreach PHP
            containerSpecialCells.insertAdjacentHTML('beforeend', `
                <div class="js-special-cell">
                    <input type="hidden" name="specialPositionType[]" value="${item.type}">
                    <input type="hidden" name="specialPositionRow[]" value="${item.r}">
                    <input type="hidden" name="specialPositionCol[]" value="${item.c}">
                </div>
            `);
        });
    }

    function serializeAislesToForm() {
        if (!containerAisles) return;
        containerAisles.innerHTML = '';
        if (aisleToggle && aisleToggle.checked) {
            scope.querySelectorAll('.dynamic-aisle-pos').forEach(input => {
                const val = parseInt(input.value);
                if (val > 0) {
                    containerAisles.insertAdjacentHTML('beforeend', `<input type="hidden" name="aisles[]" value="${val}">`);
                }
            });
        }
    }

    if (btnGenerate) {
        btnGenerate.addEventListener('click', () => {
            specialCellsRegistry = []; // Réinitialise l'atelier si l'admin modifie globalement les dimensions du bus
            generateLayoutFromFormData();
        });
    }

    // Premier rendu automatique de la carrosserie au chargement initial de la page
    generateLayoutFromFormData();
});
