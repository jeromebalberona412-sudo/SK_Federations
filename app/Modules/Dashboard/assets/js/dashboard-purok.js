(function () {
    'use strict';

    let purokChart = null;
    let purokFetchTimer = null;
    let purokAbortController = null;
    let purokRequestSeq = 0;
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

    function getDashboardDateRange() {
        if (typeof window.getDashboardDateRange === 'function') {
            return window.getDashboardDateRange();
        }

        const fromInput = document.getElementById('dashDateFrom');
        const toInput = document.getElementById('dashDateTo');
        const today = new Date();
        const pad = function (n) { return String(n).padStart(2, '0'); };
        const defaultTo = today.getFullYear() + '-' + pad(today.getMonth() + 1) + '-' + pad(today.getDate());
        const defaultFrom = today.getFullYear() + '-01-01';
        let dateFrom = fromInput?.value || defaultFrom;
        let dateTo = toInput?.value || defaultTo;

        return { date_from: dateFrom, date_to: dateTo };
    }

    function getPurokFilters() {
        const range = getDashboardDateRange();
        return {
            barangay_id: document.getElementById('purokBarangayFilter')?.value || 'all',
            purok: document.getElementById('purokZoneFilter')?.value || 'all',
            date_from: range.date_from,
            date_to: range.date_to,
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

        const labels = data.labels || [];
        const counts = data.counts || [];
        const colors = labels.map(function (_, index) {
            return barColors[index % barColors.length];
        });

        if (purokChart) {
            purokChart.data.labels = labels;
            purokChart.data.datasets[0].data = counts;
            purokChart.data.datasets[0].backgroundColor = colors;
            purokChart.update('none');
            return;
        }

        purokChart = new window.Chart(canvas, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Kabataan',
                    data: counts,
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
                            minRotation: 0,
                        },
                    },
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 },
                        grid: { color: 'rgba(148,163,184,.25)' },
                    },
                },
            },
        });
    }

    function applyPurokPayload(payload, hasBarangay) {
        const data = payload?.data || payload || { labels: [], counts: [], zones: [] };
        if (hasBarangay) {
            fillZoneOptions(data.zones || [], getPurokFilters().purok);
        }
        renderPurokChart(data);
    }

    async function refreshPurokChart() {
        const endpoint = window.__PUROK_DATA_URL__;
        if (!endpoint) {
            return;
        }

        const requestSeq = ++purokRequestSeq;
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
        const cacheKey = 'sk_fed_purok_chart_v2_' + params.toString();
        const cached = window.SkLocalCache?.get(cacheKey);

        if (cached?.payload) {
            applyPurokPayload(cached.payload, hasBarangay);
        }

        if (purokAbortController) {
            purokAbortController.abort();
        }
        purokAbortController = typeof AbortController !== 'undefined' ? new AbortController() : null;

        try {
            const response = await fetch(endpoint + '?' + params.toString(), {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                signal: purokAbortController?.signal,
            });
            if (!response.ok || requestSeq !== purokRequestSeq) {
                return;
            }
            const payload = await response.json();
            if (requestSeq !== purokRequestSeq) {
                return;
            }
            window.SkLocalCache?.set(cacheKey, payload);
            applyPurokPayload(payload, hasBarangay);
        } catch (error) {
            if (error && error.name === 'AbortError') {
                return;
            }
            if (!cached?.payload) {
                return;
            }
        }
    }

    function scheduleRefresh() {
        window.clearTimeout(purokFetchTimer);
        purokFetchTimer = window.setTimeout(refreshPurokChart, 40);
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

        const initial = window.__PUROK_CHART_INITIAL__;
        if (initial) {
            const params = new URLSearchParams(getPurokFilters());
            const cacheKey = 'sk_fed_purok_chart_v2_' + params.toString();
            if (!window.SkLocalCache?.get(cacheKey)?.payload) {
                window.SkLocalCache?.set(cacheKey, { data: initial });
            }
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
