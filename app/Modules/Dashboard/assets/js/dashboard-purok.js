(function () {
    'use strict';

    let purokChart = null;
    let purokFetchTimer = null;
    const barColors = [
        'rgba(59,130,246,.85)',
        'rgba(34,197,94,.85)',
        'rgba(244,194,13,.85)',
        'rgba(239,68,68,.85)',
        'rgba(168,85,247,.85)',
        'rgba(20,184,166,.85)',
        'rgba(249,115,22,.85)',
        'rgba(99,102,241,.85)',
    ];

    function getDashboardYear() {
        const yearSelect = document.getElementById('yearSelect');
        return yearSelect?.value || 'all';
    }

    function getPurokFilters() {
        return {
            barangay_id: document.getElementById('purokBarangayFilter')?.value || 'all',
            purok: document.getElementById('purokZoneFilter')?.value || 'all',
            year: getDashboardYear(),
        };
    }

    function fillZoneOptions(zones, selected) {
        const zoneSelect = document.getElementById('purokZoneFilter');
        if (!zoneSelect) {
            return;
        }

        const current = selected || 'all';
        zoneSelect.innerHTML = '<option value="all">All Purok/Sitio</option>';
        (zones || []).forEach(function (zone) {
            const option = document.createElement('option');
            option.value = zone;
            option.textContent = zone;
            if (zone === current) {
                option.selected = true;
            }
            zoneSelect.appendChild(option);
        });
    }

    function renderPurokChart(data) {
        const canvas = document.getElementById('purokDistributionChart');
        if (!canvas || typeof window.Chart === 'undefined') {
            return;
        }

        if (purokChart) {
            purokChart.destroy();
            purokChart = null;
        }

        const labels = data.labels || [];
        const colors = labels.map(function (_, index) {
            return barColors[index % barColors.length];
        });

        purokChart = new window.Chart(canvas, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Kabataan',
                    data: data.counts || [],
                    backgroundColor: colors,
                    borderRadius: 8,
                    borderSkipped: false,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                return ' ' + context.parsed.y + ' youth';
                            },
                        },
                    },
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: {
                            font: { size: 9 },
                            maxRotation: 45,
                            minRotation: 25,
                            autoSkip: false,
                        },
                    },
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 },
                        grid: { color: 'rgba(0, 0, 0, 0.05)' },
                    },
                },
            },
        });
    }

    async function refreshPurokChart() {
        const endpoint = window.__PUROK_DATA_URL__;
        if (!endpoint) {
            return;
        }

        const barangayFilter = document.getElementById('purokBarangayFilter');
        const zoneSelect = document.getElementById('purokZoneFilter');
        const hasBarangay = Boolean(barangayFilter && barangayFilter.value !== 'all');
        if (zoneSelect) {
            zoneSelect.hidden = !hasBarangay;
            zoneSelect.disabled = !hasBarangay;
            if (!hasBarangay) {
                zoneSelect.value = 'all';
            }
        }

        const params = new URLSearchParams(getPurokFilters());

        try {
            const response = await fetch(endpoint + '?' + params.toString(), {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!response.ok) {
                return;
            }
            const payload = await response.json();
            const data = payload.data || { labels: [], counts: [], zones: [] };
            if (hasBarangay) {
                fillZoneOptions(data.zones || [], getPurokFilters().purok);
            }
            renderPurokChart(data);
        } catch (_) {
            renderPurokChart({ labels: [], counts: [] });
        }
    }

    function scheduleRefresh() {
        window.clearTimeout(purokFetchTimer);
        purokFetchTimer = window.setTimeout(refreshPurokChart, 80);
    }

    function boot() {
        if (!document.getElementById('purokDistributionChart')) {
            return;
        }

        document.getElementById('purokBarangayFilter')?.addEventListener('change', function () {
            const zoneSelect = document.getElementById('purokZoneFilter');
            if (zoneSelect) {
                zoneSelect.value = 'all';
            }
            scheduleRefresh();
        });
        document.getElementById('purokZoneFilter')?.addEventListener('change', scheduleRefresh);
        document.getElementById('yearSelect')?.addEventListener('change', scheduleRefresh);

        const initial = window.__PUROK_CHART_INITIAL__;
        if (initial) {
            renderPurokChart(initial);
        } else {
            refreshPurokChart();
        }
    }

    window.refreshPurokDistributionChart = refreshPurokChart;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
