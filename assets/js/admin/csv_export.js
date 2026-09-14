// public/js/csv_export.js

/**
 * Génère le téléchargement CSV en adaptant dynamiquement le nom du fichier selon le segment
 */
function executeAdvancedExport(reportType) {
    const table = document.querySelector('.js-datatable-export');
    if (!table) {
        console.error("Table de données introuvable.");
        return;
    }

    let csvContent = [];
    const rows = table.querySelectorAll('tr');

    rows.forEach((row) => {
        const cells = row.querySelectorAll('th, td');
        let rowData = [];

        // On exporte proprement en excluant la colonne 7 (Actions)
        for (let i = 0; i < cells.length - 1; i++) {
            let cellText = cells[i].innerText || cells[i].textContent;
            cellText = cellText.replace(/\n/g, " | ").trim();
            cellText = cellText.replace(/"/g, '""');
            rowData.push('"' + cellText + '"');
        }
        csvContent.push(rowData.join(';'));
    });

    const csvString = "\uFEFF" + csvContent.join('\n');
    const blob = new Blob([csvString], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement("a");
    const dateStamp = new Date().toISOString().slice(0, 10);
    
    // Le nom du fichier s'adapte dynamiquement (ex: mydigitrans_report_cargo_2026-09-14.csv)
    link.href = URL.createObjectURL(blob);
    link.setAttribute("download", `mydigitrans_report_${reportType}_${dateStamp}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
