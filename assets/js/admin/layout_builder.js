document.addEventListener('DOMContentLoaded', () => {
    const scope = document.querySelector('.agency-layout-manager-scope');
    if (!scope) return;

    const btnGenerate = scope.querySelector('#btn-generate-matrix');
    const inputRows = scope.querySelector('#layout-rows');
    const inputCols = scope.querySelector('#layout-cols');
    const selectAisle = scope.querySelector('#layout-aisle-pos');
    const checkboxBackExtra = scope.querySelector('#layout-back-extra');
    const gridContainer = scope.querySelector('#layout-interactive-grid');

    // Éléments du panneau contextuel
    const contextPanel = scope.querySelector('#cell-context-panel');
    const labelR = scope.querySelector('#ctx-r');
    const labelC = scope.querySelector('#ctx-c');
    const selectType = scope.querySelector('#ctx-type-select');
    const inputLabel = scope.querySelector('#ctx-label-input');
    const btnApplyCtx = scope.querySelector('#btn-apply-ctx');

    const btnSubmitLayout = scope.querySelector('#btn-submit-layout');
    const inputLayoutName = scope.querySelector('#layout-name');

    let activeCell = null;

    // 1. Génération géométrique de l'autocar (Prend en compte le couloir et le fond)
    function buildLayoutGrid() {
        const rows = parseInt(inputRows.value);
        const totalSeatCols = parseInt(inputCols.value);
        const aislePosition = parseInt(selectAisle.value); // Ex: après la col 2
        
        // La largeur totale de la grille = sièges + 1 colonne pour le couloir
        const totalGridCols = totalSeatCols + 1;
        
        gridContainer.innerHTML = '';
        gridContainer.style.setProperty('--grid-cols', totalGridCols);
        contextPanel.style.display = 'none';
        activeCell = null;

        let seatCounter = 1;

        for (let r = 1; r <= rows; r++) {
            let seatColIndex = 0; // Compteur pour savoir où on en est par rapport au couloir

            for (let c = 1; c <= totalGridCols; c++) {
                const cell = document.createElement('div');
                cell.classList.add('cell-unit');
                cell.setAttribute('data-row', r);
                cell.setAttribute('data-grid-col', c);

                // --- CAS A : DETECTION DU COULOIR (AISLE) ---
                // Le couloir s'insère si on a dépassé le nombre de colonnes de sièges spécifié
                // Exception : Si ahasBackExtraSeat est vrai, la toute dernière rangée n'a pas de couloir
                const isBackRow = (r === rows);
                const isAisleColumn = (c === (aislePosition + 1));

                if (isAisleColumn && (!isBackRow || !checkboxBackExtra.checked)) {
                    cell.setAttribute('data-type', 'aisle');
                    cell.setAttribute('data-seat-col', '0');
                    gridContainer.appendChild(cell);
                    continue; // On passe à la case suivante, pas de numéro de siège
                }

                // Incrémentation de la colonne de siège réelle
                seatColIndex++;
                cell.setAttribute('data-seat-col', seatColIndex);

                // --- CAS B : CONFIGURATION DE LA CABINE AVANT (RANGÉE 1) ---
                if (r === 1) {
                    if (seatColIndex === 1) {
                        cell.setAttribute('data-type', 'driver');
                    } else {
                        cell.setAttribute('data-type', 'aisle'); // Vide par défaut à côté du chauffeur
                    }
                } else {
                    cell.setAttribute('data-type', 'seat'); // Siège standard par défaut
                }

                // ÉVÉNEMENT : Clic pour ouvrir le panneau d'attribution des options spéciales
                cell.addEventListener('click', () => {
                    if (activeCell) activeCell.classList.remove('active-editing');
                    activeCell = cell;
                    cell.classList.add('active-editing');

                    labelR.textContent = r;
                    labelC.textContent = seatColIndex;
                    selectType.value = cell.getAttribute('data-type');
                    inputLabel.value = cell.getAttribute('data-custom-label') || '';

                    contextPanel.style.display = 'block';
                });

                gridContainer.appendChild(cell);
            }
        }
        reindexSeats();
    }

    // 2. Application des modifications du popover
    btnApplyCtx.addEventListener('click', () => {
        if (!activeCell) return;

        const newType = selectType.value;
        const customText = inputLabel.value.trim();

        activeCell.setAttribute('data-type', newType);
        if (customText !== '') {
            activeCell.setAttribute('data-custom-label', customText);
        } else {
            activeCell.removeAttribute('data-custom-label');
        }

        activeCell.classList.remove('active-editing');
        contextPanel.style.display = 'none';
        activeCell = null;

        reindexSeats();
    });

    // 3. Algorithme de calcul successif des numéros de sièges passagers
    function reindexSeats() {
        const cells = gridContainer.querySelectorAll('.cell-unit');
        let seatNum = 1;

        cells.forEach(cell => {
            const type = cell.getAttribute('data-type');
            const customLabel = cell.getAttribute('data-custom-label');

            if (type === 'seat' || type === 'vip') {
                cell.textContent = customLabel ? customLabel : seatNum++;
            } else if (type === 'driver') {
                cell.textContent = customLabel ? customLabel : 'CH';
            } else if (type === 'wc') {
                cell.textContent = customLabel ? customLabel : 'WC';
            } else if (type === 'door') {
                cell.textContent = customText ? customText : 'PORT';
            } else {
                cell.textContent = '';
            }
        });
    }

    // 4. ENVOI AJAX FETCH VERS SYMFONY (STOCKAGE SESSION)
    btnSubmitLayout.addEventListener('click', () => {
        const nameValue = inputLayoutName.value.trim();
        if (nameValue === '') { alert('Veuillez donner un nom à votre modèle.'); inputLayoutName.focus(); return; }

        const cells = gridContainer.querySelectorAll('.cell-unit');
        const matrixData = [];

        cells.forEach(cell => {
            matrixData.push({
                row: parseInt(cell.getAttribute('data-row')),
                seat_col: parseInt(cell.getAttribute('data-seat-col')),
                type: cell.getAttribute('data-type'),
                label: cell.textContent
            });
        });

        const payload = {
            name: nameValue,
            rows: inputRows.value,
            columns: inputCols.value,
            aisles: [selectAisle.value], // Format tableau JSON conforme à ton MCD
            hasBackExtraSeat: checkboxBackExtra.checked,
            matrix: matrixData
        };

        // Envoi asynchrone vers le contrôleur de session
        fetch('/admin/agency/layout/save-ajax', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(response => response.json())
        .then(res => {
            if (res.success) {
                alert(`Gabarit "${res.name}" enregistré en session avec succès !`);
                window.location.reload(); // Rafraîchit pour voir la liste se mettre à jour
            }
        });
    });

    btnGenerate.addEventListener('click', buildLayoutGrid);
    buildLayoutGrid(); // Lancement automatique initial
});
