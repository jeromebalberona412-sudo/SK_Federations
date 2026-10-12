document.addEventListener('DOMContentLoaded', function () {
    const initial = window.__DASHBOARD_FEED__?.programs || {};
    const charts = {};

    if (typeof window.Chart === 'undefined') {
        ['programStatusChart', 'programBarangayChart'].forEach(function (canvasId) {
            const canvas = document.getElementById(canvasId);
            const empty = document.getElementById(canvasId === 'programStatusChart' ? 'programStatusEmpty' : 'programBarangayEmpty');
            if (canvas && empty) {
                canvas.hidden = true;
                const canvasWrap = canvas.closest('.program-chart-canvas');
                if (canvasWrap) {
                    canvasWrap.hidden = true;
                }
                empty.textContent = 'Chart library unavailable. Refresh the page to try again.';
                empty.hidden = false;
            }
        });
        return;
    }

    function renderChart(key, data) {
        const config = key === 'status'
            ? {
                canvasId: 'programStatusChart',
                emptyId: 'programStatusEmpty',
                type: 'doughnut',
                colors: ['#198754', '#f59e0b', '#64748b', '#2563eb', '#dc2626'],
            }
            : {
                canvasId: 'programBarangayChart',
                emptyId: 'programBarangayEmpty',
                type: 'bar',
                colors: ['#1A3263'],
            };
        const canvas = document.getElementById(config.canvasId);
        const empty = document.getElementById(config.emptyId);
        const canvasWrap = canvas?.closest('.program-chart-canvas');
        const labels = Array.isArray(data?.labels) ? data.labels : [];
        const values = Array.isArray(data?.values) ? data.values.map(Number) : [];
        const hasData = labels.length > 0 && values.some((value) => value > 0);

        if (!canvas || !empty) {
            return;
        }

        canvas.hidden = !hasData;
        if (canvasWrap) {
            canvasWrap.hidden = !hasData;
        }
        empty.hidden = hasData;
        if (!hasData) {
            if (charts[key]) {
                charts[key].destroy();
                delete charts[key];
            }
            return;
        }

        const dataset = {
            label: key === 'status' ? 'Schedules' : 'Programs',
            data: values,
            backgroundColor: key === 'status'
                ? labels.map((label, index) => {
                    const normalized = String(label).toLowerCase();
                    if (normalized.includes('open')) return '#198754';
                    if (normalized.includes('closed')) return '#f59e0b';
                    return config.colors[index % config.colors.length];
                })
                : config.colors[0],
            borderWidth: key === 'status' ? 2 : 0,
            borderColor: '#ffffff',
            borderRadius: key === 'status' ? 0 : 6,
        };

        const options = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: key === 'status',
                    position: 'bottom',
                    labels: { usePointStyle: true, boxWidth: 8, padding: 16 },
                },
                tooltip: {
                    callbacks: {
                        label(context) {
                            const value = Number(context.parsed?.y ?? context.parsed ?? 0);
                            return ' ' + context.label + ': ' + value.toLocaleString();
                        },
                    },
                },
            },
        };

        if (key === 'status') {
            options.cutout = '62%';
        } else {
            options.indexAxis = 'y';
            options.scales = {
                x: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#e2e8f0' } },
                y: { grid: { display: false } },
            };
            options.plugins.legend.display = false;
        }

        if (charts[key]) {
            charts[key].data.labels = labels;
            charts[key].data.datasets = [dataset];
            charts[key].update();
            return;
        }

        charts[key] = new window.Chart(canvas, {
            type: config.type,
            data: { labels, datasets: [dataset] },
            options,
        });
    }

    function render(payload) {
        renderChart('status', payload?.status_chart);
        renderChart('barangay', payload?.barangay_chart);
    }

    render(initial);
    window.addEventListener('skfed:dashboard-programs', function (event) {
        render(event.detail || {});
    });
});
