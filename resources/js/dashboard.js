/*
 * Dashboard-only entry point. Loaded via @vite() solely from
 * resources/views/dashboard/index.blade.php, so the ~200 KB Chart.js
 * bundle is fetched and parsed only on the dashboard - every other page
 * of the app now ships just Bootstrap (~70 KB).
 *
 * Chart data is passed through data-* attributes on the <canvas> (see the
 * Blade view), not through this file, so the chart config stays in sync
 * with the server-rendered data automatically.
 */
document.addEventListener('DOMContentLoaded', async () => {
    const canvas = document.getElementById('usersByRoleChart');
    if (!canvas) return;

    let labels = [];
    let values = [];
    try {
        labels = JSON.parse(canvas.dataset.labels || '[]');
        values = JSON.parse(canvas.dataset.values || '[]');
    } catch {
        return;
    }
    if (!labels.length) return;

    // Dynamic import: Vite splits chart.js into its own chunk that is only
    // downloaded when this line runs (i.e. only on the dashboard).
    const { default: Chart } = await import('chart.js/auto');

    new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels,
            datasets: [{
                data: values,
                backgroundColor: ['#0d6efd', '#198754', '#0dcaf0', '#ffc107', '#dc3545', '#6f42c1', '#20c997', '#fd7e14', '#6c757d', '#d63384'],
            }],
        },
        options: { plugins: { legend: { position: 'bottom' } } },
    });
});
