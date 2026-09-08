/**
 * "My Analytics" panel charts on My Line (Connectivity Analytics blueprint
 * Part A §2.4/2.7) — plan mix (donut), purchase cadence + spend + top-up
 * history (bar). Chart.js is dynamic-imported inside mountAll() so it never
 * lands in the main bundle; it shares the same lazy chunk as the per-eSIM
 * usage chart (usage-chart.js) since both import the identical 'chart.js'
 * specifier. Only mounted once, the first time a user opens the panel.
 */
export function registerLinesAnalyticsCharts() {
    const mounted = new WeakSet();
    const palette = ['#0A6E6E', '#D4A017', '#0D1B2A', '#2DD4BF', '#F59E0B', '#64748B', '#7C3AED', '#EC4899'];

    function config(type, labels, values) {
        if (type === 'donut') {
            return {
                type: 'doughnut',
                data: {
                    labels,
                    datasets: [{ data: values, backgroundColor: labels.map((_, i) => palette[i % palette.length]) }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 10 } } } },
                },
            };
        }

        return {
            type: 'bar',
            data: { labels, datasets: [{ data: values, backgroundColor: '#0A6E6E', borderRadius: 4, maxBarThickness: 28 }] },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false }, ticks: { font: { size: 10 }, maxTicksLimit: 6 } },
                    y: { beginAtZero: true, grid: { color: 'rgba(148,163,184,0.15)' }, ticks: { font: { size: 10 } } },
                },
            },
        };
    }

    window.NaaraLinesAnalytics = {
        mountAll(root) {
            const canvases = Array.from((root || document).querySelectorAll('[data-chart-type]'))
                .filter((canvas) => !mounted.has(canvas));
            if (!canvases.length) return;

            import('chart.js').then(({
                Chart, BarController, DoughnutController, BarElement, ArcElement,
                CategoryScale, LinearScale, Legend, Tooltip,
            }) => {
                Chart.register(BarController, DoughnutController, BarElement, ArcElement, CategoryScale, LinearScale, Legend, Tooltip);

                canvases.forEach((canvas) => {
                    if (mounted.has(canvas)) return;
                    mounted.add(canvas);

                    let payload;
                    try {
                        payload = JSON.parse(canvas.dataset.chart || '{}');
                    } catch {
                        payload = {};
                    }
                    const labels = payload.labels || [];
                    const values = payload.values || [];
                    if (!labels.length) return;

                    new Chart(canvas, config(canvas.dataset.chartType, labels, values));
                });
            }).catch(() => { /* bundle/CDN failure -> the panel's own empty-state text stays visible */ });
        },
    };
}
