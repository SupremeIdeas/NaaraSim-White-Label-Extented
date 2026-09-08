/**
 * Lazy per-eSIM data-usage chart (Connectivity Analytics blueprint Part A
 * §2.6). Chart.js itself is dynamic-imported inside mount() — never part of
 * the main bundle — so it's only ever fetched the first time a user actually
 * opens a "Show usage" panel on My Line. Registers a small global (rather
 * than an alpine:init component) because the mount call is fired from an
 * inline Alpine @click handler in the Blade partial, on a canvas that may not
 * exist in the DOM until the panel is expanded.
 */
export function registerUsageCharts() {
    const chartsByCanvas = new WeakMap();

    window.NaaraUsageCharts = {
        mount(canvas) {
            if (!canvas || chartsByCanvas.has(canvas)) return;

            let points;
            try {
                points = JSON.parse(canvas.dataset.usage || '[]');
            } catch {
                points = [];
            }

            import('chart.js').then(({
                Chart, LineController, LineElement, PointElement,
                LinearScale, CategoryScale, Filler, Tooltip,
            }) => {
                if (chartsByCanvas.has(canvas)) return; // guard a double-click race
                Chart.register(LineController, LineElement, PointElement, LinearScale, CategoryScale, Filler, Tooltip);

                const chart = new Chart(canvas, {
                    type: 'line',
                    data: {
                        labels: points.map((p) => new Date(p.t).toLocaleDateString(undefined, { month: 'short', day: 'numeric' })),
                        datasets: [{
                            label: 'Data used (MB)',
                            data: points.map((p) => p.used_mb),
                            borderColor: '#0A6E6E',
                            backgroundColor: 'rgba(10, 110, 110, 0.15)',
                            fill: true,
                            tension: 0.35,
                            pointRadius: 0,
                            borderWidth: 2,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            x: { grid: { display: false }, ticks: { maxTicksLimit: 5, font: { size: 10 } } },
                            y: { beginAtZero: true, grid: { color: 'rgba(148,163,184,0.15)' }, ticks: { font: { size: 10 } } },
                        },
                    },
                });

                chartsByCanvas.set(canvas, chart);
            }).catch(() => { /* bundle/CDN failure -> the burn-rate text still shows */ });
        },
    };

    // A canvas is destroyed along with its Livewire-owned DOM on navigation;
    // explicitly tear down the Chart.js instance first so it drops its own
    // window resize listener instead of leaking across SPA navigations.
    document.addEventListener('livewire:navigating', () => {
        document.querySelectorAll('[data-usage-chart]').forEach((canvas) => {
            chartsByCanvas.get(canvas)?.destroy();
            chartsByCanvas.delete(canvas);
        });
    });
}
