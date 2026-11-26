(function () {
    'use strict';
    if (!window.Chart || !window.DASH) { return; }
    var D = window.DASH;
    var navy = '#0b2a5b', blue = '#1d4ea0', sky = '#6fa0e0', gray = '#9aa5b8';
    var palette = ['#0b2a5b', '#1d4ea0', '#3b7ddd', '#6fa0e0', '#9bbbea', '#5b6b8c', '#8d9ab5', '#2f9e8f', '#c7d3ea'];
    Chart.defaults.font.family = 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif';
    Chart.defaults.maintainAspectRatio = false;

    new Chart(document.getElementById('chCat'), {
        type: 'bar',
        data: { labels: D.cat.labels, datasets: [{ label: 'Jumlah unit', data: D.cat.values, backgroundColor: blue, borderRadius: 4 }] },
        options: { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { precision: 0 } } } }
    });

    new Chart(document.getElementById('chCond'), {
        type: 'doughnut',
        data: { labels: D.cond.labels, datasets: [{ data: D.cond.values, backgroundColor: ['#2e9e5b', '#f2a93b', '#d64545'], borderWidth: 0 }] },
        options: { cutout: '62%', plugins: { legend: { position: 'bottom' } } }
    });

    new Chart(document.getElementById('chMonth'), {
        type: 'bar',
        data: {
            labels: D.month.labels,
            datasets: [
                { label: 'Masuk', data: D.month.in, backgroundColor: '#2e9e5b', borderRadius: 4 },
                { label: 'Keluar', data: D.month.out, backgroundColor: '#d64545', borderRadius: 4 }
            ]
        },
        options: { plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
    });
})();
