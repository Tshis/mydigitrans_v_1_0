// public/js/admin/layout_builder.js

document.addEventListener('DOMContentLoaded', () => {
    console.log('layout_builder système initialisé');
    const scope = document.querySelector('.admin-bus-layout-add-scope');
    if (!scope) return;

    const inputRows = scope.querySelector('#layout-main-rows');
    const inputCols = scope.querySelector('#layout-main-cols');
    const checkboxBackExtra = scope.querySelector('#layout-back-extra');
    const gridContainer = scope.querySelector('#layout-interactive-grid');
    const btnGenerate = scope.querySelector('#btn-generate-matrix');

    const aisleToggle = scope.querySelector('#layout-aisle-toggle');
    const aisleLogicBlock = scope.querySelector('#aisle-logic-block');
    const containerAisles = scope.querySelector('#aisles-container');
    const btnAddAisle = scope.querySelector('#btn-add-aisle');

    if (aisleToggle && aisleLogicBlock && containerAisles && btnAddAisle) {
        aisleToggle.addEventListener('change', () => {
            aisleLogicBlock.style.display = aisleToggle.checked ? 'block' : 'none';
        });

        btnAddAisle.addEventListener('click', () => {
            const block = document.createElement('div');
            block.className = 'field mb-10';
            block.style.display = 'flex';
            block.style.alignItems = 'center';
            block.style.gap = '10px';
            block.innerHTML = `
                <div style="flex: 1;">
                    <label class="admin-bus-layout-add-label">Insérer un couloir après la colonne :</label>
                    <input name="aisles[]" placeholder="Ex: 2" class="admin-bus-layout-add-input dynamic-aisle-pos" type="number" min="1" max="6" value="2" required style="height: 35px;">
                </div>
                <button type="button" class="btn-remove-aisle" style="background: rgba(217,119,6,0.1); color: #d97706; border: 1px solid rgba(217,119,6,0.2); padding: 6px 10px; border-radius: 6px; cursor: pointer; margin-top: 15px;"><i class="fa-solid fa-trash"></i></button>
            `;
            block.querySelector('.btn-remove-aisle').addEventListener('click', () => {
                block.remove();
                generateLayoutFromFormData();
            });
            containerAisles.appendChild(block);
        });
        btnAddAisle.click(); 
    }

    function generateLayoutFromFormData() {
        if (!gridContainer) return;
        const totalRows = parseInt(inputRows.value) || 10;
        const totalSeatCols = parseInt(inputCols.value) || 4;

        const aislePositions = [];
        if (aisleToggle && aisleToggle.checked) {
            scope.querySelectorAll('.dynamic-aisle-pos').forEach(input => {
                const val = parseInt(input.value);
                if (val > 0 && !aislePositions.includes(val)) aislePositions.push(val);
            });
            aislePositions.sort((a, b) => a - b);
        }

        const totalGridCols = totalSeatCols + aislePositions.length;
        gridContainer.innerHTML = '';
        gridContainer.style.setProperty('--grid-cols', totalGridCols);

        let seatNum = 1;

        for (let r = 1; r <= totalRows; r++) {
            let seatColIndex = 0;
            const currentAisles = [...aislePositions];

            for (let c = 1; c <= totalGridCols; c++) {
                const cell = document.createElement('div');
                cell.classList.add('cell-unit');

                const isBackRow = (r === totalRows);
                const isDefaultAisle = currentAisles.includes(seatColIndex);
                const isBackExtraChecked = checkboxBackExtra ? checkboxBackExtra.checked : false;

                if (isDefaultAisle && (!isBackRow || !isBackExtraChecked)) {
                    cell.setAttribute('data-type', 'aisle');
                    currentAisles.splice(currentAisles.indexOf(seatColIndex), 1);
                } else {
                    seatColIndex++;
                    cell.setAttribute('data-type', 'seat');
                    const spanNum = document.createElement('span');
                    spanNum.classList.add('-number');
                    spanNum.textContent = seatNum++;
                    cell.appendChild(spanNum);
                }
                gridContainer.appendChild(cell);
            }
        }
    }

    if (btnGenerate) {
        btnGenerate.addEventListener('click', generateLayoutFromFormData);
    }
    generateLayoutFromFormData();
});
