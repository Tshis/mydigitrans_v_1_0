/**
 * Mydigitrans SaaS - Module Exploitation
 * Gestion des RouteStops géographiques libres avec recalcul du stopOrder en direct
 */
document.addEventListener('DOMContentLoaded', () => {
    const btnAddStop = document.getElementById('js-add-stop-row');
    const container = document.getElementById('stops-dynamic-container');
    const terminusRow = document.getElementById('js-terminus-row');
    const template = document.getElementById('stop-row-template');

    let runningIndex = 1;

    function reindexStopOrders() {
        const allRows = container.querySelectorAll('.stop-node-row');
        
        allRows.forEach((row, idx) => {
            const orderValue = idx + 1;
            
            const indicator = row.querySelector('.stop-id-indicator');
            if (indicator) indicator.innerText = `#${orderValue}`;

            const hiddenOrderInput = row.querySelector('input[name*="[stopOrder]"]');
            if (hiddenOrderInput) hiddenOrderInput.value = orderValue;
            
            row.setAttribute('data-stop-order', orderValue);
        });
    }

    if (btnAddStop && container && terminusRow && template) {
        btnAddStop.addEventListener('click', () => {
            let htmlContent = template.innerHTML;
            htmlContent = htmlContent.replace(/__INDEX__/g, runningIndex);
            runningIndex++;

            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = htmlContent;
            const newRow = tempDiv.firstElementChild;

            container.insertBefore(newRow, terminusRow);
            reindexStopOrders();

            newRow.querySelector('.js-remove-row').addEventListener('click', () => {
                newRow.remove();
                reindexStopOrders();
            });
        });
    }
});
