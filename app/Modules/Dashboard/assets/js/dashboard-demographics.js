/**
 * SK Federations Dashboard — municipality-wide sex (by role)
 * and Kabataan employment status charts.
 */
(function () {
    const FED_BLUE = '#213F99';
    const FED_RED = '#d0242b';
    const FED_YELLOW = '#F7D31E';
    const FED_GREEN = '#16a34a';
    const FED_PURPLE = '#7c3aed';
    // Male = federation blue, Female = pink
    const SEX_COLORS = [FED_BLUE, '#ec4899'];

    // Match dashboard legend reference: blue, green, yellow, red, purple
    const EMPLOYMENT_COLOR_BY_LABEL = {
        'Employed': FED_BLUE,
        'Unemployed': FED_GREEN,
        'Self-Employed': FED_YELLOW,
        'Currently looking for a Job': FED_RED,
        'Not Interested Looking for a Job': FED_PURPLE,
    };
    const EMPLOYMENT_FALLBACK = [FED_BLUE, FED_GREEN, FED_YELLOW, FED_RED, FED_PURPLE];

    let sexChart = null;
    let employmentChart = null;

    let sexByRole = {
        kabataan: { labels: ['Male', 'Female'], values: [0, 0] },
        officials: { labels: ['Male', 'Female'], values: [0, 0] },
        federations: { labels: ['Male', 'Female'], values: [0, 0] },
    };
    let employmentData = { labels: [], values: [], total: 0 };

    function combineSexRoles() {
        return {
            labels: ['Male', 'Female'],
            values: [
                Number(sexByRole.kabataan.values?.[0] || 0)
                    + Number(sexByRole.officials.values?.[0] || 0)
                    + Number(sexByRole.federations.values?.[0] || 0),
                Number(sexByRole.kabataan.values?.[1] || 0)
                    + Number(sexByRole.officials.values?.[1] || 0)
                    + Number(sexByRole.federations.values?.[1] || 0),
            ],
        };
    }

    function feedBootstrap() {
        return window.__DASHBOARD_FEED__ || {};
    }

    function sum(values) {
        return (values || []).reduce(function (total, value) {
            return total + Number(value || 0);
        }, 0);
    }

    function employmentColor(label, index) {
        return EMPLOYMENT_COLOR_BY_LABEL[label] || EMPLOYMENT_FALLBACK[index % EMPLOYMENT_FALLBACK.length];
    }

    function renderLegend(el, labels, values, colors) {
        if (!el) return;
        const total = sum(values) || 1;
        el.innerHTML = labels.map(function (label, index) {
            const count = Number(values[index] || 0);
            const pct = Math.round((count / total) * 100);
            const color = colors[index % colors.length];
            return (
                '<span class="dash-demo-legend-item">' +
                '<span class="dash-demo-legend-swatch" style="background:' + color + '"></span>' +
                '<span>' + label + ': <strong>' + count.toLocaleString() + '</strong> (' + pct + '%)</span>' +
                '</span>'
            );
        }).join('');
    }

    function setEmpty(emptyEl, chartCanvas, legendEl, isEmpty) {
        if (emptyEl) emptyEl.hidden = !isEmpty;
        const wrap = document.getElementById('employmentChartWrap');
        if (chartCanvas) chartCanvas.style.display = isEmpty ? 'none' : '';
        if (wrap && chartCanvas && chartCanvas.id === 'employmentStatusChart') {
            wrap.hidden = isEmpty;
        }
        if (legendEl) legendEl.hidden = isEmpty;
    }

    function currentSexDataset() {
        const role = document.getElementById('sexDistributionRoleFilter')?.value || 'all';
        const source = role === 'all' ? combineSexRoles() : (sexByRole[role] || sexByRole.kabataan);
        return {
            labels: source.labels || ['Male', 'Female'],
            values: (source.values || [0, 0]).map(function (value) { return Number(value || 0); }),
            colors: SEX_COLORS,
        };
    }

    function updateSexChart() {
        const canvas = document.getElementById('kabataanSexChart');
        const empty = document.getElementById('kabataanSexEmpty');
        const legend = document.getElementById('kabataanSexLegend');
        if (!canvas || typeof Chart === 'undefined') return;

        const dataset = currentSexDataset();
        const isEmpty = sum(dataset.values) <= 0;
        setEmpty(empty, canvas, legend, isEmpty);
        if (isEmpty) {
            if (sexChart) {
                sexChart.destroy();
                sexChart = null;
            }
            return;
        }

        renderLegend(legend, dataset.labels, dataset.values, dataset.colors);

        if (sexChart) {
            sexChart.data.labels = dataset.labels;
            sexChart.data.datasets[0].data = dataset.values;
            sexChart.data.datasets[0].backgroundColor = dataset.colors;
            sexChart.update('none');
            return;
        }

        sexChart = new Chart(canvas, {
            type: 'pie',
            data: {
                labels: dataset.labels,
                datasets: [{
                    data: dataset.values,
                    backgroundColor: dataset.colors,
                    borderWidth: 2,
                    borderColor: '#fff',
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                const total = sum(ctx.dataset.data) || 1;
                                const value = Number(ctx.raw || 0);
                                const pct = Math.round((value / total) * 100);
                                return ' ' + ctx.label + ': ' + value.toLocaleString() + ' (' + pct + '%)';
                            },
                        },
                    },
                },
            },
        });
    }

    function buildEmploymentDataset(filter) {
        const labels = employmentData.labels || [];
        const values = employmentData.values || [];
        if (filter && filter !== 'all') {
            const index = labels.findIndex(function (label) {
                return label === filter;
            });
            if (index < 0) {
                return { labels: [filter], values: [0], colors: [employmentColor(filter, 0)] };
            }
            return {
                labels: [labels[index]],
                values: [Number(values[index] || 0)],
                colors: [employmentColor(labels[index], index)],
            };
        }
        return {
            labels: labels,
            values: values.map(function (value) { return Number(value || 0); }),
            colors: labels.map(function (label, index) {
                return employmentColor(label, index);
            }),
        };
    }

    function updateEmploymentChart() {
        const canvas = document.getElementById('employmentStatusChart');
        const empty = document.getElementById('employmentStatusEmpty');
        const legend = document.getElementById('employmentStatusLegend');
        const filter = document.getElementById('employmentChartFilter')?.value || 'all';
        if (!canvas || typeof Chart === 'undefined') return;

        const dataset = buildEmploymentDataset(filter);
        const isEmpty = sum(dataset.values) <= 0;
        setEmpty(empty, canvas, legend, isEmpty);
        if (isEmpty) {
            if (employmentChart) {
                employmentChart.destroy();
                employmentChart = null;
            }
            return;
        }

        renderLegend(legend, dataset.labels, dataset.values, dataset.colors);

        if (employmentChart) {
            employmentChart.destroy();
            employmentChart = null;
        }

        const total = sum(dataset.values);
        const donutLabelsPlugin = {
            id: 'employmentDonutLabels',
            afterDraw: function (chart) {
                const context = chart.ctx;
                chart.data.datasets.forEach(function (chartDataset, datasetIndex) {
                    const meta = chart.getDatasetMeta(datasetIndex);
                    meta.data.forEach(function (arc, index) {
                        const value = chartDataset.data[index];
                        if (!value) return;
                        const pct = total > 0 ? Math.round((value / total) * 100) : 0;
                        if (pct < 5) return;
                        const angle = (arc.startAngle + arc.endAngle) / 2;
                        const radius = (arc.innerRadius + arc.outerRadius) / 2;
                        const x = arc.x + Math.cos(angle) * radius;
                        const y = arc.y + Math.sin(angle) * radius;
                        context.save();
                        context.textAlign = 'center';
                        context.textBaseline = 'middle';
                        context.fillStyle = '#fff';
                        context.font = 'bold 11px Segoe UI, sans-serif';
                        context.fillText(pct + '%', x, y);
                        context.restore();
                    });
                });
            },
        };

        employmentChart = new Chart(canvas, {
            type: 'doughnut',
            plugins: [donutLabelsPlugin],
            data: {
                labels: dataset.labels,
                datasets: [{
                    data: dataset.values,
                    backgroundColor: dataset.colors,
                    borderWidth: 2,
                    borderColor: '#fff',
                    hoverOffset: 6,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                cutout: '62%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                const chartTotal = sum(ctx.dataset.data) || 1;
                                const value = Number(ctx.raw || 0);
                                const pct = ((value / chartTotal) * 100).toFixed(1);
                                return ' ' + ctx.label + ': ' + value.toLocaleString() + ' (' + pct + '%)';
                            },
                        },
                    },
                },
            },
        });
    }

    function normalizeSexPayload(payload) {
        if (!payload || !Array.isArray(payload.values)) {
            return null;
        }
        return {
            labels: payload.labels || ['Male', 'Female'],
            values: payload.values,
        };
    }

    function applyDemographics(payload) {
        if (!payload) return;

        const kabataan = normalizeSexPayload(payload.sex_distribution);
        const officials = normalizeSexPayload(payload.sex_distribution_officials);
        const federations = normalizeSexPayload(payload.sex_distribution_federations);
        if (kabataan) sexByRole.kabataan = kabataan;
        if (officials) sexByRole.officials = officials;
        if (federations) sexByRole.federations = federations;
        if (kabataan || officials || federations) {
            updateSexChart();
        }

        if (payload.employment_status_distribution) {
            employmentData = {
                labels: payload.employment_status_distribution.labels || [],
                values: payload.employment_status_distribution.values || [],
                total: Number(payload.employment_status_distribution.total || 0),
            };
            updateEmploymentChart();
        }
    }

    window.applyDashboardDemographics = applyDemographics;

    document.addEventListener('DOMContentLoaded', function () {
        const feed = feedBootstrap();
        applyDemographics(feed);

        document.getElementById('sexDistributionRoleFilter')?.addEventListener('change', updateSexChart);
        document.getElementById('employmentChartFilter')?.addEventListener('change', updateEmploymentChart);
    });
})();
