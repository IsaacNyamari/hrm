/**
 * Registered eagerly (imported from app.js, not pushed per-page) so it's
 * available before Alpine starts, regardless of how the Reports page was
 * reached — see the comment in app.js for why. Chart.js itself still loads
 * lazily and only once: nothing on the Reports page needs it until a chart
 * actually renders, so there's no reason to ship it globally.
 */

let chartJsPromise = null;

function loadChartJs() {
    if (window.Chart) {
        return Promise.resolve();
    }

    chartJsPromise ??= new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js';
        script.integrity = 'sha512-CQBWl4fJHWbryGE+Pc7UAxWMUMNMWzWxF4SQo9CgkJIN1kx6djDQZjh3Y8SZ1d+6I+1zze6Z7kHXO7q3UyZAWw==';
        script.crossOrigin = 'anonymous';
        script.referrerPolicy = 'no-referrer';
        script.onload = () => resolve();
        script.onerror = () => reject(new Error('Failed to load Chart.js'));
        document.head.appendChild(script);
    });

    return chartJsPromise;
}

function registerReportChart() {
    Alpine.data('reportChart', (chart) => ({
        // Not named `init` on purpose — Alpine treats that name as a
        // lifecycle hook and auto-calls it with no arguments, which
        // double-fires this and crashes on `el` being undefined.
        async renderChart(el) {
            await loadChartJs();

            const canvas = el.querySelector('canvas');
            const palette = ['#6366f1', '#06b6d4', '#f59e0b', '#ef4444', '#10b981', '#8b5cf6', '#ec4899', '#84cc16', '#14b8a6', '#f97316'];
            const isPie = chart.type === 'doughnut';

            const datasets = chart.datasets.map((ds, i) => ({
                label: ds.label,
                data: ds.data,
                backgroundColor: isPie
                    ? chart.labels.map((_, j) => palette[j % palette.length])
                    : palette[i % palette.length] + (chart.type === 'line' ? '26' : 'cc'),
                borderColor: isPie ? 'rgba(0,0,0,0.15)' : palette[i % palette.length],
                borderWidth: isPie ? 2 : (chart.type === 'line' ? 2 : 1),
                fill: chart.type === 'line',
                tension: 0.35,
                borderRadius: chart.type === 'bar' ? 6 : 0,
            }));

            new Chart(canvas.getContext('2d'), {
                type: chart.type,
                data: {labels: chart.labels, datasets},
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: isPie || datasets.length > 1,
                            position: 'bottom',
                            labels: {color: '#a1a1aa', boxWidth: 12, padding: 12},
                        },
                    },
                    scales: isPie ? {} : {
                        x: {ticks: {color: '#a1a1aa'}, grid: {display: false}},
                        y: {ticks: {color: '#a1a1aa'}, grid: {color: 'rgba(255,255,255,0.06)'}, beginAtZero: true},
                    },
                },
            });
        },
    }));
}

if (window.Alpine) {
    registerReportChart();
} else {
    document.addEventListener('alpine:init', registerReportChart);
}
