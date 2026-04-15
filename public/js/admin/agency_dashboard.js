document.addEventListener('DOMContentLoaded', () => {
    
    // --- 📊 GRAPH 1 : REVENUS (Barres) ---
    const ctxRev = document.getElementById('revenueChart');
    if (ctxRev) {
        new Chart(ctxRev.getContext('2d'), {
            type: 'bar',
            data: {
                labels: ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'],
                datasets: [{
                    label: 'Ventes (FC)',
                    data: [1200000, 1900000, 1500000, 2500000, 2200000, 3000000, 2800000],
                    backgroundColor: '#2563eb',
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } }
            }
        });
    }

    // --- 🥧 GRAPH 2 : RÉPARTITION (Pie) ---
    const ctxPie = document.getElementById('branchPieChart');
    if (ctxPie) {
        new Chart(ctxPie.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Kinshasa', 'Matadi', 'Boma', 'Muanda'],
                datasets: [{
                    data: [45, 30, 15, 10],
                    backgroundColor: ['#2563eb', '#10b981', '#f59e0b', '#6366f1']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: { legend: { position: 'bottom' } }
            }
        });
    }

    // --- 🖱️ INTERACTION : FILTRES & MODALE ---
    
    // Filtrage simple (Changement de titre au clic sur succursale)
    document.querySelectorAll('.branch-row').forEach(row => {
        row.onclick = () => {
            const name = row.cells[0].innerText;
            const sales = row.getAttribute('data-sales');
            document.getElementById('total-revenue').innerText = sales;
            alert('Vue filtrée pour : ' + name);
        };
    });

    // Gestion Modale Bus
    const modal = document.getElementById('busModal');
    document.querySelectorAll('.bus-item').forEach(item => {
        item.onclick = () => {
            const title = item.getAttribute('data-route') + ' (' + item.getAttribute('data-time') + ')';
            document.getElementById('modalTitle').innerText = title;
            modal.style.display = 'block';
        };
    });

    document.querySelector('.close-modal').onclick = () => modal.style.display = 'none';
    window.onclick = (e) => { if (e.target == modal) modal.style.display = 'none'; };
});
