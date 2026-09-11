(function () {
    'use strict';

    let purokChart = null;
    let purokFetchTimer = null;
    let purokAbortController = null;
    let purokRequestSeq = 0;
    let purokLastPayload = null;

    // Same palette as SK Officials dashboard Kabataan bar chart.
    const BAR_COLORS = [
        'rgba(59,130,246,.85)',
        'rgba(34,197,94,.85)',
        'rgba(244,194,13,.85)',
        'rgba(239,68,68,.85)',
        'rgba(168,85,247,.85)',
        'rgba(20,184,166,.85)',
        'rgba(249,115,22,.85)',
        'rgba(99,102,241,.85)',
        'rgba(236,72,153,.85)',
        'rgba(14,165,233,.85)',
        'rgba(132,204,22,.85)',
        'rgba(245,158,11,.85)',
        'rgba(244,63,94,.85)',
        'rgba(139,92,246,.85)',
        'rgba(6,182,212,.85)',
        'rgba(234,179,8,.85)',
    ];

    function colorsForCount(count) {
        const colors = [];
        for (let i = 0; i < count; i += 1) {
            colors.push(BAR_COLORS[i % BAR_COLORS.length]);
        }
        return colors;
    }

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

        return {
            date_from: fromInput?.value || defaultFrom,
            date_to: toInput?.value || defaultTo,
        };
    }

    function getPurokFilters() {
        const range = getDashboardDateRange();
        return {
            barangay_id: document.getElementById('purokBarangayFilter')?.value || 'all',
            purok: document.getElementById('purokZoneFilter')?.value || 'all',
            date_from: range.date_from,
            date_to: range.date_to,
            range_mode: range.range_mode || 'term',
        };
    }

    function isAllBarangaysMode() {
        const barangayFilter = document.getElementById('purokBarangayFilter');
        return !barangayFilter || barangayFilter.value === 'all';
    }

    function isNarrowViewport() {
        return window.matchMedia('(max-width: 768px)').matches;
    }

    function wrapAxisLabel(label, maxChars) {
        const text = String(label || '').trim();
        if (!text) {
            return '';
        }

        const limit = Math.max(8, maxChars || 11);
        if (text.length <= limit) {
            return text;
        }

        const words = text.split(/\s+/);
        if (words.length === 1) {
            const chunks = [];
            for (let i = 0; i < text.length; i += limit) {
                chunks.push(text.slice(i, i + limit));
            }
            return chunks.slice(0, 2);
        }

        const lines = [];
        let current = '';
        words.forEach(function (word) {
            const next = current ? (current + ' ' + word) : word;
            if (next.length <= limit) {
                current = next;
                return;
            }
            if (current) {
                lines.push(current);
            }
            current = word.length > limit ? word.slice(0, limit) : word;
        });
        if (current) {
            lines.push(current);
        }
        return lines.slice(0, 2);
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

    function sizePurokChartWrap(labels) {
        const wrap = document.getElementById('purokChartWrap');
        const canvas = document.getElementById('purokDistributionChart');
        if (!wrap || !canvas) {
            return;
        }

        const count = Math.max(1, (labels || []).length);
        const narrow = isNarrowViewport();
        const allBarangays = isAllBarangaysMode();
        // Give All Barangays more room so each name stays readable.
        const col = allBarangays
            ? (narrow ? 92 : 84)
            : (narrow ? 76 : 68);
        const minWidth = Math.max(wrap.clientWidth || 320, count * col);
        wrap.style.height = narrow ? '300px' : '280px';
        wrap.style.minHeight = wrap.style.height;

        if (minWidth > (wrap.clientWidth || 0) + 8) {
            wrap.classList.add('is-scroll-x');
            canvas.style.minWidth = minWidth + 'px';
        } else {
            wrap.classList.remove('is-scroll-x');
            canvas.style.minWidth = '';
        }
        canvas.style.width = '100%';
        canvas.style.height = '100%';
    }

    function buildPurokChartOptions(labelCount) {
        const narrow = isNarrowViewport();
        const count = Math.max(1, labelCount || 0);
        const allBarangays = isAllBarangaysMode();
        const dense = count > 10;
        const labelChars = allBarangays ? (narrow ? 9 : 11) : (narrow ? 10 : 12);

        return {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            devicePixelRatio: Math.max(window.devicePixelRatio || 1, 2),
            layout: {
                padding: { top: 16, right: 8, bottom: 4, left: 4 },
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        title: function (items) {
                            const item = items && items[0];
                            return item ? String(item.label || '') : '';
                        },
                        label: function (context) {
                            return ' ' + context.parsed.y + ' youth';
                        },
                    },
                },
            },
            scales: {
                x: {
                    offset: true,
                    grid: { display: false },
                    ticks: {
                        autoSkip: false,
                        font: {
                            size: allBarangays ? (narrow ? 8 : 9) : (narrow ? 10 : 11),
                            weight: '600',
                        },
                        color: '#4b5563',
                        maxRotation: dense || allBarangays ? 35 : 25,
                        minRotation: dense || allBarangays ? 25 : 0,
                        padding: allBarangays ? 4 : 6,
                        callback: function (value) {
                            const label = this.getLabelForValue
                                ? this.getLabelForValue(value)
                                : String(this.chart?.data?.labels?.[value] ?? value);
                            return wrapAxisLabel(label, labelChars);
                        },
                    },
                },
                y: {
                    beginAtZero: true,
                    grace: '8%',
                    ticks: {
                        stepSize: 1,
                        precision: 0,
                        font: {
                            size: allBarangays ? 9 : 11,
                            weight: '500',
                        },
                        color: '#6b7280',
                    },
                    grid: { color: 'rgba(0,0,0,.05)' },
                },
            },
            datasets: {
                bar: {
                    categoryPercentage: count > 18 ? 0.7 : 0.65,
                    barPercentage: 0.82,
                },
            },
        };
    }

    const purokBarValuePlugin = {
        id: 'purokBarValues',
        afterDatasetsDraw: function (chart) {
            const ctx = chart.ctx;
            const dataset = chart.data.datasets[0];
            if (!dataset) {
                return;
            }
            const allBarangays = isAllBarangaysMode();
            const meta = chart.getDatasetMeta(0);
            ctx.save();
            ctx.textAlign = 'center';
            ctx.textBaseline = 'bottom';
            ctx.fillStyle = '#111827';
            ctx.font = allBarangays
                ? '600 9px Inter, system-ui, sans-serif'
                : '700 11px Inter, system-ui, sans-serif';
            meta.data.forEach(function (bar, index) {
                const value = Number(dataset.data[index] || 0);
                if (!bar || !Number.isFinite(value) || value <= 0) {
                    return;
                }
                const { x, y } = bar.getProps(['x', 'y'], true);
                ctx.fillText(String(value), x, Math.max(12, y - 3));
            });
            ctx.restore();
        },
    };

    function renderPurokChart(data) {
        const canvas = document.getElementById('purokDistributionChart');
        if (!canvas || typeof window.Chart === 'undefined') {
            return;
        }

        const labels = data.labels || [];
        const counts = data.counts || [];
        const colors = colorsForCount(labels.length);

        sizePurokChartWrap(labels);
        const options = buildPurokChartOptions(labels.length);

        if (purokChart) {
            purokChart.destroy();
            purokChart = null;
        }

        purokChart = new window.Chart(canvas, {
            type: 'bar',
            plugins: [purokBarValuePlugin],
            data: {
                labels: labels,
                datasets: [{
                    label: 'Kabataan',
                    data: counts,
                    backgroundColor: colors,
                    borderRadius: 8,
                    borderSkipped: false,
                    maxBarThickness: 44,
                }],
            },
            options: options,
        });
    }

    function applyPurokPayload(payload, hasBarangay) {
        const data = payload?.data || payload || { labels: [], counts: [], zones: [] };
        purokLastPayload = data;
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
        const cacheKey = 'sk_fed_purok_chart_v7_' + params.toString();
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
        }
    }

    function scheduleRefresh() {
        window.clearTimeout(purokFetchTimer);
        purokFetchTimer = window.setTimeout(refreshPurokChart, 40);
    }

    function onViewportChange() {
        if (!purokLastPayload) {
            return;
        }
        renderPurokChart(purokLastPayload);
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

        let resizeTimer = null;
        window.addEventListener('resize', function () {
            window.clearTimeout(resizeTimer);
            resizeTimer = window.setTimeout(onViewportChange, 120);
        });

        if (typeof MutationObserver !== 'undefined') {
            const sidebarObserver = new MutationObserver(function () {
                window.clearTimeout(resizeTimer);
                resizeTimer = window.setTimeout(onViewportChange, 160);
            });
            sidebarObserver.observe(document.body, { attributes: true, attributeFilter: ['class'] });
        }

        const initial = window.__PUROK_CHART_INITIAL__;
        if (initial) {
            const params = new URLSearchParams(getPurokFilters());
            const cacheKey = 'sk_fed_purok_chart_v7_' + params.toString();
            if (!window.SkLocalCache?.get(cacheKey)?.payload) {
                window.SkLocalCache?.set(cacheKey, { data: initial });
            }
            purokLastPayload = initial;
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
