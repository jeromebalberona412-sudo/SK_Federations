let kkProfilingMonthlyChart = null;
let kkProfilingCurrentData = null;
const kkProfilingDailyCounts = new Map();
let kkProfilingDateCoverage = [];
let kkProfilingBarangayWired = false;
let kkProfilingDateRangeWired = false;
let kkProfilingFetchTimer = null;
let kkProfilingAbortController = null;
let kkProfilingRequestSeq = 0;
let kkProfilingBooted = false;

function getSelectedBarangayName(barangayId) {
    if (barangayId === 'all') {
        return 'All Barangays';
    }

    const selectedOption = document.getElementById('kkProfilingBarangayFilter')?.selectedOptions?.[0];
    return selectedOption?.textContent?.trim() || 'Barangay';
}

function getTodayIsoDate() {
    const today = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    return `${today.getFullYear()}-${pad(today.getMonth() + 1)}-${pad(today.getDate())}`;
}

function isValidIsoDate(value) {
    return /^\d{4}-\d{2}-\d{2}$/.test(String(value || ''));
}

function getDashboardDateRange() {
    const fromInput = document.getElementById('dashDateFrom');
    const toInput = document.getElementById('dashDateTo');
    const filter = document.getElementById('dashDateRangeFilter');
    const termStart = filter?.dataset.termStart || filter?.dataset.defaultFrom || `${new Date().getFullYear()}-01-01`;
    const termEnd = filter?.dataset.termEnd || filter?.dataset.defaultTo || `${new Date().getFullYear()}-12-31`;
    const defaultFrom = filter?.dataset.defaultFrom || termStart;
    const defaultTo = filter?.dataset.defaultTo || termEnd;
    const today = filter?.dataset.today || getTodayIsoDate();
    const earliest = filter?.dataset.dataEarliest || '';
    const rangeMode = filter?.dataset.rangeMode === 'all' ? 'all' : 'term';

    let dateFrom = fromInput?.value || defaultFrom;
    let dateTo = toInput?.value || defaultTo;

    if (!isValidIsoDate(dateFrom) || !isValidIsoDate(dateTo)) {
        return { date_from: defaultFrom, date_to: defaultTo, range_mode: rangeMode, valid: false };
    }

    if (rangeMode === 'all') {
        const minBound = isValidIsoDate(earliest) ? earliest : termStart;
        const maxBound = today;
        if (dateFrom < minBound) {
            dateFrom = minBound;
            if (fromInput) fromInput.value = dateFrom;
        }
        if (dateTo > maxBound) {
            dateTo = maxBound;
            if (toInput) toInput.value = dateTo;
        }
        if (fromInput && toInput) {
            fromInput.min = minBound;
            fromInput.max = maxBound;
            toInput.min = dateFrom;
            toInput.max = maxBound;
        }
    } else if (termStart && termEnd && (dateFrom < termStart || dateTo > termEnd || dateFrom > dateTo)) {
        if (dateFrom < termStart) {
            dateFrom = termStart;
        }
        if (dateTo > termEnd) {
            dateTo = termEnd;
        }
        if (dateFrom > dateTo) {
            dateTo = dateFrom;
        }
        if (fromInput) fromInput.value = dateFrom;
        if (toInput) toInput.value = dateTo;
        if (fromInput && toInput) {
            fromInput.min = termStart;
            fromInput.max = termEnd;
            toInput.min = dateFrom;
            toInput.max = termEnd;
        }
    } else if (fromInput && toInput) {
        fromInput.min = termStart || '';
        fromInput.max = termEnd || '';
        toInput.min = dateFrom || termStart || '';
        toInput.max = termEnd || '';
    }

    if (dateFrom && dateTo && dateFrom > dateTo) {
        const tmp = dateFrom;
        dateFrom = dateTo;
        dateTo = tmp;
        if (fromInput) fromInput.value = dateFrom;
        if (toInput) toInput.value = dateTo;
    }

    return { date_from: dateFrom, date_to: dateTo, range_mode: rangeMode, valid: true };
}

function normalizeKkChartSeries(values) {
    return Array.isArray(values)
        ? values.map(function (value) {
            if (value === null || value === undefined || value === '') return null;
            const numericValue = Number(value);
            return Number.isFinite(numericValue) ? numericValue : null;
        })
        : [];
}

function rememberKkProfilingDailyData(data) {
    if (!Array.isArray(data?.daily) || !isValidIsoDate(data.date_from) || !isValidIsoDate(data.date_to)) {
        return;
    }

    kkProfilingDailyCounts.forEach(function (_counts, date) {
        if (date >= data.date_from && date <= data.date_to) {
            kkProfilingDailyCounts.delete(date);
        }
    });
    data.daily.forEach(function (day) {
        if (!isValidIsoDate(day?.date)) {
            return;
        }

        kkProfilingDailyCounts.set(day.date, {
            approved: Number(day.approved) || 0,
            pending: Number(day.pending) || 0,
            rejected: Number(day.rejected) || 0,
        });
    });

    kkProfilingDateCoverage.push([data.date_from, data.date_to]);
    kkProfilingDateCoverage.sort((left, right) => left[0].localeCompare(right[0]));
    kkProfilingDateCoverage = kkProfilingDateCoverage.reduce(function (merged, range) {
        const previous = merged[merged.length - 1];
        if (previous && range[0] <= previous[1]) {
            previous[1] = previous[1] > range[1] ? previous[1] : range[1];
        } else {
            merged.push(range.slice());
        }
        return merged;
    }, []);
}

function getKkProfilingDataForDateRange(range) {
    const isCovered = kkProfilingDateCoverage.some(([dateFrom, dateTo]) => (
        range.date_from >= dateFrom && range.date_to <= dateTo
    ));
    if (!isCovered) {
        return null;
    }

    const firstYear = Number(range.date_from.slice(0, 4));
    const lastYear = Number(range.date_to.slice(0, 4));
    const multipleYears = firstYear !== lastYear;
    const labels = [];
    const monthIndexes = new Map();

    for (let year = firstYear; year <= lastYear; year += 1) {
        for (let month = 0; month < 12; month += 1) {
            const key = `${year}-${String(month + 1).padStart(2, '0')}`;
            monthIndexes.set(key, labels.length);
            labels.push(new Date(Date.UTC(year, month, 1)).toLocaleString('en-US', {
                month: multipleYears ? 'short' : 'long',
                timeZone: 'UTC',
            }) + (multipleYears ? ` ${year}` : ''));
        }
    }

    const approved = Array(labels.length).fill(0);
    const pending = Array(labels.length).fill(0);
    const rejected = Array(labels.length).fill(0);

    kkProfilingDailyCounts.forEach(function (counts, date) {
        if (date < range.date_from || date > range.date_to) {
            return;
        }

        const index = monthIndexes.get(date.slice(0, 7));
        if (index === undefined) {
            return;
        }
        approved[index] += counts.approved;
        pending[index] += counts.pending;
        rejected[index] += counts.rejected;
    });

    return {
        date_from: range.date_from,
        date_to: range.date_to,
        labels,
        approved,
        pending,
        rejected,
    };
}

function getKkProfilingDateRange() {
    const fromInput = document.getElementById('kkProfilingDateFrom');
    const toInput = document.getElementById('kkProfilingDateTo');
    const today = getTodayIsoDate();
    let dateFrom = fromInput?.value || `${today.slice(0, 4)}-01-01`;
    let dateTo = toInput?.value || today;

    if (!isValidIsoDate(dateFrom) || dateFrom > today) {
        dateFrom = `${today.slice(0, 4)}-01-01`;
    }
    if (fromInput) {
        fromInput.value = dateFrom;
        fromInput.max = today;
    }
    if (!isValidIsoDate(dateTo) || dateTo > today) {
        dateTo = today;
    }
    if (dateFrom > dateTo) {
        [dateFrom, dateTo] = [dateTo, dateFrom];
    }
    if (fromInput) fromInput.value = dateFrom;
    if (toInput) {
        toInput.value = dateTo;
        toInput.min = dateFrom;
        toInput.max = today;
    }

    return {
        date_from: dateFrom,
        date_to: dateTo,
    };
}

window.getDashboardDateRange = getDashboardDateRange;

let dashboardDateRefreshTimer = null;

function refreshDashboardDateFilteredViews() {
    window.clearTimeout(dashboardDateRefreshTimer);
    dashboardDateRefreshTimer = window.setTimeout(function () {
        const range = getDashboardDateRange();
        if (range.valid === false) {
            return;
        }
        if (typeof window.refreshDashboardStats === 'function') {
            window.refreshDashboardStats(true);
        }
        if (typeof window.refreshPurokDistributionChart === 'function') {
            window.refreshPurokDistributionChart();
        }
        document.dispatchEvent(new CustomEvent('dashboard:date-range-changed'));
    }, 180);
}

function setDashboardRangeMode(mode) {
    const filter = document.getElementById('dashDateRangeFilter');
    const allBtn = document.getElementById('dashDateAllBtn');
    if (filter) filter.dataset.rangeMode = mode === 'all' ? 'all' : 'term';
    if (allBtn) allBtn.classList.toggle('is-active', mode === 'all');
}

function resetDashboardDateRange() {
    const fromInput = document.getElementById('dashDateFrom');
    const toInput = document.getElementById('dashDateTo');
    const filter = document.getElementById('dashDateRangeFilter');
    if (!fromInput || !toInput) {
        return;
    }

    setDashboardRangeMode('term');
    const defaultFrom = filter?.dataset.defaultFrom || filter?.dataset.termStart || `${new Date().getFullYear()}-01-01`;
    const defaultTo = filter?.dataset.defaultTo || filter?.dataset.termEnd || `${new Date().getFullYear()}-12-31`;
    const termStart = filter?.dataset.termStart || defaultFrom;
    const termEnd = filter?.dataset.termEnd || defaultTo;

    fromInput.value = defaultFrom;
    toInput.value = defaultTo;
    fromInput.min = termStart || '';
    fromInput.max = termEnd || '';
    toInput.min = termStart || '';
    toInput.max = termEnd || '';

    refreshDashboardDateFilteredViews();
}

function applyDashboardAllDateRange() {
    const fromInput = document.getElementById('dashDateFrom');
    const toInput = document.getElementById('dashDateTo');
    const filter = document.getElementById('dashDateRangeFilter');
    if (!fromInput || !toInput || !filter) {
        return;
    }

    const earliest = filter.dataset.dataEarliest || filter.dataset.termStart || '';
    const today = filter.dataset.today || getTodayIsoDate();
    if (!isValidIsoDate(earliest)) {
        return;
    }

    setDashboardRangeMode('all');
    fromInput.value = earliest;
    toInput.value = today;
    fromInput.min = earliest;
    fromInput.max = today;
    toInput.min = earliest;
    toInput.max = today;
    refreshDashboardDateFilteredViews();
}

function getKkProfilingFilters() {
    const barangayFilter = document.getElementById('kkProfilingBarangayFilter');
    const range = getKkProfilingDateRange();

    return {
        barangay_id: barangayFilter?.value || 'all',
        date_from: range.date_from,
        date_to: range.date_to,
    };
}

function seedKkProfilingCacheFromInitial() {
    const initial = window.__KK_PROFILING_INITIAL__;
    if (!initial) {
        return;
    }
    rememberKkProfilingDailyData(initial);
    if (!window.SkLocalCache) {
        return;
    }
    const params = new URLSearchParams(getKkProfilingFilters());
    const cacheKey = 'sk_fed_kk_chart_v7_' + params.toString();
    if (!window.SkLocalCache.get(cacheKey)?.payload) {
        window.SkLocalCache.set(cacheKey, initial);
    }
}

async function fetchKkProfilingData() {
    const endpoint = window.__KK_PROFILING_DATA_URL__;
    if (!endpoint) {
        setKkProfilingChartError(true);
        return null;
    }

    const requestSeq = ++kkProfilingRequestSeq;
    const params = new URLSearchParams(getKkProfilingFilters());
    const cacheKey = 'sk_fed_kk_chart_v7_' + params.toString();
    const cached = window.SkLocalCache?.get(cacheKey);
    const cachedPayload = cached?.payload || null;
    if (cachedPayload) {
        rememberKkProfilingDailyData(cachedPayload);
    }

    if (kkProfilingAbortController) {
        kkProfilingAbortController.abort();
    }
    const abortController = typeof AbortController !== 'undefined' ? new AbortController() : null;
    kkProfilingAbortController = abortController;

    const fetchFresh = async () => {
        const response = await fetch(`${endpoint}?${params.toString()}`, {
            cache: 'no-store',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            signal: abortController?.signal,
        });
        if (!response.ok) {
            throw new Error(`KK profiling request failed with status ${response.status}.`);
        }
        const payload = await response.json();
        const data = payload.data;
        if (!data || !Array.isArray(data.labels)) {
            throw new Error('KK profiling response did not contain chart data.');
        }
        if (data && requestSeq === kkProfilingRequestSeq) {
            rememberKkProfilingDailyData(data);
            window.SkLocalCache?.set(cacheKey, data);
            setKkProfilingChartError(false);
        }
        return data;
    };

    // Instant paint from local cache, then revalidate in background.
    if (cachedPayload) {
        fetchFresh()
            .then((data) => {
                if (!data || requestSeq !== kkProfilingRequestSeq) {
                    return;
                }
                const barangayFilter = document.getElementById('kkProfilingBarangayFilter');
                const range = getKkProfilingDateRange();
                applyKkProfilingChartData(
                    getKkProfilingDataForDateRange(range) || data,
                    getSelectedBarangayName(barangayFilter?.value || 'all')
                );
            })
            .catch((error) => {
                if (error?.name !== 'AbortError' && requestSeq === kkProfilingRequestSeq) {
                    console.error(error);
                }
            });
        return cachedPayload;
    }

    try {
        return await fetchFresh();
    } catch (error) {
        if (error && error.name === 'AbortError') {
            return null;
        }
        console.error(error);
        setKkProfilingChartError(true);
        return null;
    }
}

function setKkProfilingChartError(hasError) {
    const canvas = document.getElementById('kkProfilingMonthlyChart');
    const emptyState = canvas?.parentElement?.querySelector('[data-chart-empty-state]');
    const errorState = canvas?.parentElement?.querySelector('[data-chart-error]');

    if (errorState) errorState.hidden = !hasError;
    if (hasError && emptyState) emptyState.hidden = true;
}

function formatKkChartDate(iso) {
    if (!isValidIsoDate(iso)) {
        return '';
    }

    const year = Number(iso.slice(0, 4));
    const month = Number(iso.slice(5, 7));
    const day = Number(iso.slice(8, 10));
    const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const monthLabel = monthNames[month - 1] || '';

    return `${monthLabel} ${day}, ${year}`.trim();
}

function updateKkProfilingSubtitle(data) {
    const subtitle = document.getElementById('kkProfilingChartSubtitle');
    if (!subtitle || !data) {
        return;
    }

    const fromLabel = formatKkChartDate(data.date_from);
    const toLabel = formatKkChartDate(data.date_to);
    subtitle.textContent = fromLabel && toLabel ? `${fromLabel} – ${toLabel}` : '';
}

function syncKkProfilingChartAppearance(labelCount) {
    updateKkProfilingTotal();

    if (!kkProfilingMonthlyChart) {
        return;
    }

    const denseLabels = labelCount >= 12;
    const veryDenseLabels = labelCount > 24;
    const xTicks = kkProfilingMonthlyChart.options.scales.x.ticks;
    xTicks.font = { size: veryDenseLabels ? 9 : 11 };
    xTicks.maxRotation = denseLabels ? 45 : 0;
    xTicks.minRotation = denseLabels ? 30 : 0;
    xTicks.autoSkip = false;
    xTicks.autoSkipPadding = denseLabels ? 8 : 4;
    xTicks.maxTicksLimit = Math.max(12, labelCount);

    kkProfilingMonthlyChart.data.datasets.forEach((dataset) => {
        dataset.pointRadius = veryDenseLabels ? 2 : 4;
    });
}

function updateKkProfilingTotal() {
    const valueEl = document.querySelector('#kkProfilingTotal .kk-demo-total-value');
    if (!valueEl) {
        return;
    }

    const current = kkProfilingCurrentData;
    const series = current
        ? [current.monthlyApproved, current.monthlyRejected, current.monthlyPending]
        : [];
    const total = series.reduce(function (sum, values) {
        return sum + normalizeKkChartSeries(values).reduce(function (inner, value) {
            return inner + (value || 0);
        }, 0);
    }, 0);

    valueEl.textContent = total.toLocaleString();
}

function updateKkProfilingChartVisibility(seriesHasData) {
    const canvas = document.getElementById('kkProfilingMonthlyChart');
    const emptyState = canvas?.parentElement?.querySelector('[data-chart-empty-state]');
    const filterRow = document.getElementById('kkProfilingSeriesFilters');
    const seriesControls = [
        document.getElementById('filterKkApproved'),
        document.getElementById('filterKkRejected'),
        document.getElementById('filterKkPending'),
    ];
    const visibleSeries = seriesControls.map((checkbox) => Boolean(checkbox?.checked));
    const labels = kkProfilingCurrentData?.labels || [];
    const series = [
        kkProfilingCurrentData?.monthlyApproved || [],
        kkProfilingCurrentData?.monthlyRejected || [],
        kkProfilingCurrentData?.monthlyPending || [],
    ];
    const hasVisibleData = labels.some(function (_label, monthIndex) {
        return series.some(function (values, seriesIndex) {
            const value = Number(values[monthIndex]);
            return visibleSeries[seriesIndex] && Number.isFinite(value) && value > 0;
        });
    });

    if (kkProfilingMonthlyChart) {
        kkProfilingMonthlyChart.data.labels = labels;
        kkProfilingMonthlyChart.data.datasets.forEach(function (dataset, seriesIndex) {
            dataset.data = labels.map(function (_label, monthIndex) {
                const value = Number(series[seriesIndex][monthIndex]);
                return visibleSeries[seriesIndex] && Number.isFinite(value) && value >= 0 ? value : null;
            });
            dataset.hidden = !seriesHasData[seriesIndex] || !visibleSeries[seriesIndex];
        });
    }

    if (canvas) canvas.hidden = !hasVisibleData;
    if (emptyState) emptyState.hidden = hasVisibleData;
    if (filterRow) filterRow.hidden = false;

    return hasVisibleData;
}

function applyKkProfilingChartData(data, barangayName) {
    if (!kkProfilingMonthlyChart || !data) {
        return;
    }

    const labels = Array.isArray(data.labels) ? data.labels : [];
    const approved = normalizeKkChartSeries(data.approved);
    const rejected = normalizeKkChartSeries(data.rejected);
    const pending = normalizeKkChartSeries(data.pending);
    kkProfilingCurrentData = {
        barangayName,
        labels,
        monthlyApproved: approved,
        monthlyRejected: rejected,
        monthlyPending: pending,
    };

    const seriesHasData = [approved, rejected, pending].map(function (values) {
        return values.some(function (value) {
            const numericValue = Number(value);
            return Number.isFinite(numericValue) && numericValue > 0;
        });
    });
    const canvas = document.getElementById('kkProfilingMonthlyChart');
    const errorState = canvas?.parentElement?.querySelector('[data-chart-error]');
    if (errorState) errorState.hidden = true;

    const hasVisibleData = updateKkProfilingChartVisibility(seriesHasData);
    syncKkProfilingChartAppearance(kkProfilingMonthlyChart.data.labels.length);
    kkProfilingMonthlyChart.update('none');
    if (hasVisibleData) kkProfilingMonthlyChart.resize();
    updateKkProfilingSubtitle(data);
}

async function refreshKkProfilingChart() {
    const barangayFilter = document.getElementById('kkProfilingBarangayFilter');
    const barangayId = barangayFilter?.value || 'all';
    const request = fetchKkProfilingData();
    const requestSeq = kkProfilingRequestSeq;
    const data = await request;

    if (!data || requestSeq !== kkProfilingRequestSeq) {
        return;
    }

    const range = getKkProfilingDateRange();
    applyKkProfilingChartData(
        getKkProfilingDataForDateRange(range) || data,
        getSelectedBarangayName(barangayId)
    );
}

function initKkProfilingMonthlyChart() {
    const canvas = document.getElementById('kkProfilingMonthlyChart');
    if (!canvas || !window.Chart) {
        return;
    }

    seedKkProfilingCacheFromInitial();

    if (kkProfilingMonthlyChart) {
        kkProfilingMonthlyChart.destroy();
    }

    const initial = window.__KK_PROFILING_INITIAL__ || null;
    const labels = Array.isArray(initial?.labels) ? initial.labels : [];
    const approved = normalizeKkChartSeries(initial?.approved);
    const rejected = normalizeKkChartSeries(initial?.rejected);
    const pending = normalizeKkChartSeries(initial?.pending);

    kkProfilingMonthlyChart = new window.Chart(canvas, {
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    label: 'Approved',
                    data: approved,
                    borderColor: '#22c55e',
                    backgroundColor: 'rgba(34, 197, 94, 0.1)',
                    borderWidth: 2.5,
                    pointRadius: 4,
                    pointBackgroundColor: '#22c55e',
                    tension: 0.4,
                    fill: false,
                    spanGaps: false,
                },
                {
                    label: 'Rejected',
                    data: rejected,
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239, 68, 68, 0.08)',
                    borderWidth: 2.5,
                    pointRadius: 4,
                    pointBackgroundColor: '#ef4444',
                    tension: 0.4,
                    fill: false,
                    spanGaps: false,
                },
                {
                    label: 'Pending',
                    data: pending,
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245, 158, 11, 0.08)',
                    borderWidth: 2.5,
                    pointRadius: 4,
                    pointBackgroundColor: '#f59e0b',
                    tension: 0.4,
                    fill: false,
                    spanGaps: false,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    // Colors come from the dashboard-wide Chart.js defaults
                    // (see the chart theme bootstrap in dashboard.blade.php).
                },
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: {
                        font: { size: 11 },
                        color: '#6b7280',
                        maxRotation: 45,
                        minRotation: 0,
                        autoSkip: false,
                        autoSkipPadding: 4,
                        maxTicksLimit: 12,
                    },
                },
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0, 0, 0, 0.05)' },
                    ticks: { color: '#6b7280', precision: 0 },
                },
            },
        },
    });

    wireKkProfilingBarangayFilter();
    wireKkProfilingDateRange();
    wireKkProfilingDatasetFilters();
    wireDashboardDateRangeFilter();
    wireKkProfilingExport();
    wireKkProfilingLiveRefresh();

    if (initial) {
        applyKkProfilingChartData(initial, 'All Barangays');
    } else {
        applyKkProfilingChartData({ labels: [], approved: [], rejected: [], pending: [] }, 'All Barangays');
        refreshKkProfilingChart();
    }
}

function wireDashboardDateRangeFilter() {
    const fromInput = document.getElementById('dashDateFrom');
    const toInput = document.getElementById('dashDateTo');
    const resetBtn = document.getElementById('dashDateResetBtn');
    if (!fromInput || !toInput) {
        return;
    }
    if (fromInput.dataset.kkWired === 'true') {
        return;
    }

    const onChange = () => {
        refreshDashboardDateFilteredViews();
    };

    // change only — avoids double refresh with input while typing/picking dates
    fromInput.addEventListener('change', onChange);
    toInput.addEventListener('change', onChange);
    resetBtn?.addEventListener('click', function () {
        resetDashboardDateRange();
    });
    document.getElementById('dashDateAllBtn')?.addEventListener('click', function () {
        applyDashboardAllDateRange();
    });
    fromInput.dataset.kkWired = 'true';
    toInput.dataset.kkWired = 'true';
}

function scheduleKkProfilingRefresh() {
    window.clearTimeout(kkProfilingFetchTimer);
    kkProfilingFetchTimer = window.setTimeout(() => {
        refreshKkProfilingChart();
    }, 40);
}

function wireKkProfilingBarangayFilter() {
    if (kkProfilingBarangayWired) {
        return;
    }

    const barangayFilter = document.getElementById('kkProfilingBarangayFilter');
    if (!barangayFilter) {
        return;
    }

    barangayFilter.addEventListener('change', scheduleKkProfilingRefresh);
    kkProfilingBarangayWired = true;
}

function wireKkProfilingDateRange() {
    if (kkProfilingDateRangeWired) {
        return;
    }

    const fromInput = document.getElementById('kkProfilingDateFrom');
    const toInput = document.getElementById('kkProfilingDateTo');
    if (!fromInput || !toInput) {
        return;
    }

    const handleRangeChange = () => {
        const range = getKkProfilingDateRange();
        const localData = getKkProfilingDataForDateRange(range);
        const barangayFilter = document.getElementById('kkProfilingBarangayFilter');
        if (localData) {
            applyKkProfilingChartData(
                localData,
                getSelectedBarangayName(barangayFilter?.value || 'all')
            );
        }
        scheduleKkProfilingRefresh();
    };
    fromInput.addEventListener('change', handleRangeChange);
    toInput.addEventListener('change', handleRangeChange);
    kkProfilingDateRangeWired = true;
}

function wireKkProfilingDatasetFilters() {
    const cbApproved = document.getElementById('filterKkApproved');
    const cbRejected = document.getElementById('filterKkRejected');
    const cbPending = document.getElementById('filterKkPending');

    function applyDatasetFilter() {
        if (!kkProfilingMonthlyChart) {
            return;
        }

        const values = [
            kkProfilingCurrentData?.monthlyApproved,
            kkProfilingCurrentData?.monthlyRejected,
            kkProfilingCurrentData?.monthlyPending,
        ];
        const seriesHasData = values.map((series) => normalizeKkChartSeries(series).some((value) => value > 0));
        const hasVisibleData = updateKkProfilingChartVisibility(seriesHasData);
        syncKkProfilingChartAppearance(kkProfilingMonthlyChart.data.labels.length);
        kkProfilingMonthlyChart.update('none');
        if (hasVisibleData) kkProfilingMonthlyChart.resize();
    }

    [cbApproved, cbRejected, cbPending].forEach((checkbox) => {
        if (!checkbox) {
            return;
        }

        checkbox.addEventListener('change', applyDatasetFilter);
    });
}

function wireKkProfilingLiveRefresh() {
    window.setInterval(function () {
        if (!document.hidden) {
            refreshKkProfilingChart();
        }
    }, 15000);
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            refreshKkProfilingChart();
        }
    });
}

window.refreshKkProfilingChart = refreshKkProfilingChart;

function wireKkProfilingExport() {
    const exportBtn = document.getElementById('kkProfilingExportBtn');
    if (!exportBtn || exportBtn.dataset.wired === 'true') {
        return;
    }

    exportBtn.addEventListener('click', () => {
        window.exportKkProfilingChart();
    });
    exportBtn.dataset.wired = 'true';
}

window.exportKkProfilingChart = function exportKkProfilingChart() {
    if (!kkProfilingCurrentData) {
        alert('Chart data is still loading. Please wait a moment and try again.');
        return;
    }

    const {
        barangayName,
        labels,
        monthlyApproved,
        monthlyRejected,
        monthlyPending,
    } = kkProfilingCurrentData;

    const headers = ['Month', 'Barangay', 'Approved', 'Pending', 'Rejected'];
    let csvContent = `${headers.join(',')}\n`;

    labels.forEach((label, index) => {
        csvContent += [
            `"${label}"`,
            `"${barangayName}"`,
            monthlyApproved[index] ?? 0,
            monthlyPending[index] ?? 0,
            monthlyRejected[index] ?? 0,
        ].join(',');
        csvContent += '\n';
    });

    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const slug = barangayName.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
    const url = URL.createObjectURL(blob);

    link.setAttribute('href', url);
    link.setAttribute('download', `kk_profiling_${slug || 'data'}.csv`);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
};

function tryBootKkProfilingChart(attempt = 0) {
    if (kkProfilingBooted) {
        return;
    }

    if (document.getElementById('kkProfilingMonthlyChart') && typeof window.Chart !== 'undefined') {
        kkProfilingBooted = true;
        initKkProfilingMonthlyChart();
        return;
    }

    if (attempt < 20) {
        window.setTimeout(() => tryBootKkProfilingChart(attempt + 1), 50);
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', tryBootKkProfilingChart);
} else {
    tryBootKkProfilingChart();
}
