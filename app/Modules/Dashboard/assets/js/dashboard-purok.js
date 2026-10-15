(function () {
    'use strict';

    let purokChart = null;
    let purokFetchTimer = null;
    let purokAbortController = null;
    let purokRequestSeq = 0;
    let purokLastPayload = null;

    // Single flat blue for every bar, matching the SK Officials dashboard. The
    // bar keeps this exact colour while hovered — no hover tint, so the graph
    // stays one static colour. The tooltip box is the blue hover element and is
    // themed dashboard-wide in dashboard.blade.php.
    const BAR_COLOR = 'rgba(37, 99, 235, 0.88)';
    const BAR_BORDER_COLOR = 'rgba(37, 99, 235, 0.2)';

    // Grid lines only — the chart deliberately paints no background of its own
    // so the white card shows through instead of a dark panel.
    const PLOT_GRID_COLOR = 'rgba(47, 78, 133, 0.16)';

    // One blue per bar. The count has to come from labels.length — passing the
    // array itself produced an empty colour list, and Chart.js then fell back
    // to its own default rgba(0,0,0,0.1), which painted the whole graph black.
    function colorsForLabels(count) {
        return Array.from({ length: count || 0 }, function () { return BAR_COLOR; });
    }

    function setPurokTotal(counts) {
        const valueEl = document.querySelector('#purokDistributionTotal .kk-demo-total-value');
        if (!valueEl) {
            return;
        }
        const total = (Array.isArray(counts) ? counts : []).reduce(function (sum, value) {
            return sum + Number(value || 0);
        }, 0);
        valueEl.textContent = total.toLocaleString();
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

    function wrapAxisLabel(label, maxChars, fullName) {
        const text = String(label || '').trim();
        if (!text) {
            return '';
        }

        if (fullName) {
            // Keep the full barangay name; wrap long names onto up to 2 lines.
            const limit = Math.max(12, maxChars || 14);
            if (text.length <= limit) {
                return text;
            }
            const words = text.split(/\s+/);
            if (words.length === 1) {
                return text;
            }
            const lines = [];
            let current = '';
            words.forEach(function (word) {
                const next = current ? (current + ' ' + word) : word;
                if (next.length <= limit || current === '') {
                    current = next;
                    return;
                }
                lines.push(current);
                current = word;
            });
            if (current) {
                lines.push(current);
            }
            return lines.slice(0, 2);
        }

        const limit = Math.max(8, maxChars || 11);
        if (text.length <= limit) {
            return text;
        }

        const words = text.split(/\s+/);
        if (words.length === 1) {
            return text.slice(0, limit - 1) + '…';
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
            current = word.length > limit ? word.slice(0, limit - 1) + '…' : word;
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

    function setPurokEmptyState(show) {
        const wrap = document.getElementById('purokChartWrap');
        const canvas = document.getElementById('purokDistributionChart');
        if (!wrap) {
            return;
        }

        let empty = document.getElementById('purokChartEmpty');
        if (!empty) {
            empty = document.createElement('p');
            empty.id = 'purokChartEmpty';
            empty.className = 'dash-empty-note purok-chart-empty';
            empty.textContent = 'No youth population data for this filter.';
            wrap.appendChild(empty);
        }

        empty.hidden = !show;
        if (canvas) {
            canvas.hidden = !!show;
        }
        wrap.classList.toggle('is-empty', !!show);
    }

    function sizePurokChartWrap(labels) {
        const wrap = document.getElementById('purokChartWrap');
        const inner = document.getElementById('purokChartScrollInner');
        const canvas = document.getElementById('purokDistributionChart');
        if (!wrap || !canvas) {
            return;
        }

        const labelList = labels || [];
        const count = Math.max(1, labelList.length);
        const narrow = isNarrowViewport();
        const allBarangays = isAllBarangaysMode();
        const hostWidth = wrap.clientWidth || 320;

        // Extra height so angled full names don't look cramped when scrolling.
        wrap.style.height = allBarangays
            ? (narrow ? '340px' : '320px')
            : (narrow ? '300px' : '280px');
        wrap.style.minHeight = wrap.style.height;

        // Even column width keeps scroll looking clean (not stretched/blurry).
        const col = allBarangays
            ? (narrow ? 88 : 92)
            : (narrow ? 72 : 64);
        const contentWidth = Math.max(hostWidth, count * col);
        const needsScroll = contentWidth > hostWidth + 8;

        if (inner) {
            inner.style.width = needsScroll ? contentWidth + 'px' : '100%';
            inner.style.minWidth = needsScroll ? contentWidth + 'px' : '';
            inner.style.height = '100%';
        }

        canvas.style.minWidth = '';
        canvas.style.width = '100%';
        canvas.style.height = '100%';
        wrap.classList.toggle('is-scroll-x', needsScroll);
    }

    function buildPurokChartOptions(labelCount) {
        const narrow = isNarrowViewport();
        const count = Math.max(1, labelCount || 0);
        const allBarangays = isAllBarangaysMode();
        const dense = count > 10;
        const labelChars = allBarangays
            ? (narrow ? 13 : 15)
            : (narrow ? 10 : 12);

        return {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            devicePixelRatio: Math.max(window.devicePixelRatio || 1, 2),
            layout: {
                padding: {
                    top: 18,
                    right: 12,
                    bottom: allBarangays ? 10 : 6,
                    left: 6,
                },
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
                    border: { display: false },
                    ticks: {
                        autoSkip: false,
                        font: {
                            size: allBarangays ? 10 : (narrow ? 10 : 11),
                            weight: '600',
                            family: 'Inter, system-ui, sans-serif',
                        },
                        color: '#475569',
                        maxRotation: allBarangays || dense ? 38 : 20,
                        minRotation: allBarangays || dense ? 32 : 0,
                        padding: 8,
                        callback: function (value) {
                            const label = this.getLabelForValue
                                ? this.getLabelForValue(value)
                                : String(this.chart?.data?.labels?.[value] ?? value);
                            return wrapAxisLabel(label, labelChars, allBarangays);
                        },
                    },
                },
                y: {
                    beginAtZero: true,
                    grace: '10%',
                    border: { display: false },
                    ticks: {
                        stepSize: 1,
                        precision: 0,
                        font: {
                            size: 11,
                            weight: '500',
                            family: 'Inter, system-ui, sans-serif',
                        },
                        color: '#64748b',
                        padding: 8,
                    },
                    grid: {
                        color: PLOT_GRID_COLOR,
                        drawBorder: false,
                    },
                },
            },
            datasets: {
                bar: {
                    categoryPercentage: 0.68,
                    barPercentage: 0.78,
                },
            },
        };
    }

    function renderPurokChart(data) {
        const canvas = document.getElementById('purokDistributionChart');
        if (!canvas || typeof window.Chart === 'undefined') {
            return;
        }

        const labels = data.labels || [];
        const counts = data.counts || [];
        const allBarangays = isAllBarangaysMode();
        const isEmpty = labels.length === 0;

        setPurokEmptyState(isEmpty);
        setPurokTotal(counts);
        if (isEmpty) {
            if (purokChart) {
                purokChart.destroy();
                purokChart = null;
            }
            return;
        }

        const colors = colorsForLabels(labels.length);
        sizePurokChartWrap(labels);
        const options = buildPurokChartOptions(labels.length);

        if (purokChart) {
            purokChart.destroy();
            purokChart = null;
        }

        purokChart = new window.Chart(canvas, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Kabataan',
                    data: counts,
                    backgroundColor: colors,
                    hoverBackgroundColor: colors,
                    borderColor: BAR_BORDER_COLOR,
                    borderWidth: 0,
                    borderRadius: {
                        topLeft: 6,
                        topRight: 6,
                        bottomLeft: 2,
                        bottomRight: 2,
                    },
                    borderSkipped: false,
                    maxBarThickness: allBarangays ? 34 : 42,
                    categoryPercentage: 0.72,
                    barPercentage: 0.82,
                }],
            },
            options: options,
        });

        // Ensure sharp paint after scroll-width sizing.
        requestAnimationFrame(function () {
            if (purokChart && typeof purokChart.resize === 'function') {
                purokChart.resize();
            }
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

    function syncPurokZoneVisibility() {
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
        return hasBarangay;
    }

    function seedPurokCacheFromInitial() {
        const initial = window.__PUROK_CHART_INITIAL__;
        if (!initial || !window.SkLocalCache) {
            return null;
        }

        const params = new URLSearchParams(getPurokFilters());
        const cacheKey = 'sk_fed_purok_chart_v10_' + params.toString();
        const wrapped = initial.data ? initial : { data: initial };
        if (!window.SkLocalCache.get(cacheKey)?.payload) {
            window.SkLocalCache.set(cacheKey, wrapped);
        }
        return wrapped;
    }

    async function refreshPurokChart() {
        const endpoint = window.__PUROK_DATA_URL__;
        if (!endpoint) {
            return;
        }

        const requestSeq = ++purokRequestSeq;
        const hasBarangay = syncPurokZoneVisibility();
        const params = new URLSearchParams(getPurokFilters());
        const cacheKey = 'sk_fed_purok_chart_v10_' + params.toString();
        const cached = window.SkLocalCache?.get(cacheKey);

        if (purokAbortController) {
            purokAbortController.abort();
        }
        purokAbortController = typeof AbortController !== 'undefined' ? new AbortController() : null;

        const fetchFresh = async function () {
            const response = await fetch(endpoint + '?' + params.toString(), {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                signal: purokAbortController?.signal,
            });
            if (!response.ok || requestSeq !== purokRequestSeq) {
                return null;
            }
            const payload = await response.json();
            if (requestSeq !== purokRequestSeq) {
                return null;
            }
            window.SkLocalCache?.set(cacheKey, payload);
            return payload;
        };

        // Instant paint from cache, then revalidate in background.
        if (cached?.payload) {
            applyPurokPayload(cached.payload, hasBarangay);
            fetchFresh()
                .then(function (payload) {
                    if (!payload || requestSeq !== purokRequestSeq) {
                        return;
                    }
                    applyPurokPayload(payload, hasBarangay);
                })
                .catch(function () {});
            return;
        }

        try {
            const payload = await fetchFresh();
            if (payload) {
                applyPurokPayload(payload, hasBarangay);
            }
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

    function scheduleBackgroundRevalidate() {
        const run = function () {
            refreshPurokChart();
        };
        if (typeof window.requestIdleCallback === 'function') {
            window.requestIdleCallback(run, { timeout: 1800 });
            return;
        }
        window.setTimeout(run, 500);
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

        const initial = seedPurokCacheFromInitial() || window.__PUROK_CHART_INITIAL__;
        if (initial) {
            applyPurokPayload(initial, syncPurokZoneVisibility());
            scheduleBackgroundRevalidate();
            return;
        }

        refreshPurokChart();
    }

    window.refreshPurokDistributionChart = refreshPurokChart;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
