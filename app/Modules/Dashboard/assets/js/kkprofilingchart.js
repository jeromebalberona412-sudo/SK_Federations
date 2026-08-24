let kkProfilingMonthlyChart = null;
let kkProfilingCurrentData = null;
let kkProfilingFiltersWired = false;
let kkProfilingBarangayWired = false;
let kkProfilingPeriodWired = false;
let kkProfilingMonthWired = false;
let kkProfilingFetchTimer = null;

function getSelectedBarangayName(barangayId) {
    if (barangayId === 'all') {
        return 'All Barangays';
    }

    const barangays = window.__KK_BARANGAYS__ || [];
    const match = barangays.find((barangay) => String(barangay.id) === String(barangayId));

    return match?.name || 'Barangay';
}

function getDashboardSelectedYear() {
    const yearSelect = document.getElementById('yearSelect');
    const selectedYear = yearSelect?.value;

    if (selectedYear && selectedYear !== 'all') {
        return selectedYear;
    }

    return String(new Date().getFullYear());
}

function getKkProfilingFilters() {
    const barangayFilter = document.getElementById('kkProfilingBarangayFilter');
    const periodFilter = document.getElementById('kkProfilingPeriodFilter');
    const monthFilter = document.getElementById('kkProfilingMonthFilter');
    const period = periodFilter?.value || 'monthly';
    const now = new Date();

    return {
        barangay_id: barangayFilter?.value || 'all',
        period,
        year: getDashboardSelectedYear(),
        month: period === 'weekly' ? String(monthFilter?.value || (now.getMonth() + 1)) : '',
    };
}

async function fetchKkProfilingData() {
    const endpoint = window.__KK_PROFILING_DATA_URL__;
    if (!endpoint) {
        return null;
    }

    const params = new URLSearchParams(getKkProfilingFilters());

    try {
        const response = await fetch(`${endpoint}?${params.toString()}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            return null;
        }

        const payload = await response.json();
        return payload.data || null;
    } catch (error) {
        return null;
    }
}

function updateKkProfilingSubtitle(data) {
    const subtitle = document.getElementById('kkProfilingChartSubtitle');
    if (!subtitle || !data) {
        return;
    }

    if (data.period === 'weekly') {
        const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        const monthLabel = monthNames[(data.month || 1) - 1] || 'Selected month';
        subtitle.textContent = `Weekly submissions for ${monthLabel} ${data.year} — approved, pending, and rejected`;
        return;
    }

    subtitle.textContent = `Monthly submissions for ${data.year} — approved, pending, and rejected`;
}

function applyKkProfilingChartData(data, barangayName) {
    if (!kkProfilingMonthlyChart || !data) {
        return;
    }

    kkProfilingCurrentData = {
        barangayName,
        period: data.period,
        year: data.year,
        labels: data.labels || [],
        monthlyApproved: data.approved || [],
        monthlyRejected: data.rejected || [],
        monthlyPending: data.pending || [],
    };

    kkProfilingMonthlyChart.data.labels = kkProfilingCurrentData.labels;
    kkProfilingMonthlyChart.data.datasets[0].data = kkProfilingCurrentData.monthlyApproved;
    kkProfilingMonthlyChart.data.datasets[1].data = kkProfilingCurrentData.monthlyRejected;
    kkProfilingMonthlyChart.data.datasets[2].data = kkProfilingCurrentData.monthlyPending;
    kkProfilingMonthlyChart.update();
    updateKkProfilingSubtitle(data);
}

async function refreshKkProfilingChart() {
    const barangayFilter = document.getElementById('kkProfilingBarangayFilter');
    const barangayId = barangayFilter?.value || 'all';
    const data = await fetchKkProfilingData();

    if (!data) {
        return;
    }

    applyKkProfilingChartData(data, getSelectedBarangayName(barangayId));
}

function initKkProfilingMonthlyChart() {
    const canvas = document.getElementById('kkProfilingMonthlyChart');
    if (!canvas || !window.Chart) {
        return;
    }

    if (kkProfilingMonthlyChart) {
        kkProfilingMonthlyChart.destroy();
    }

    kkProfilingMonthlyChart = new window.Chart(canvas, {
        type: 'line',
        data: {
            labels: [],
            datasets: [
                {
                    label: 'Approved',
                    data: [],
                    borderColor: '#22c55e',
                    backgroundColor: 'rgba(34, 197, 94, 0.1)',
                    borderWidth: 2.5,
                    pointRadius: 4,
                    pointBackgroundColor: '#22c55e',
                    tension: 0.4,
                    fill: true,
                },
                {
                    label: 'Rejected',
                    data: [],
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239, 68, 68, 0.08)',
                    borderWidth: 2.5,
                    pointRadius: 4,
                    pointBackgroundColor: '#ef4444',
                    tension: 0.4,
                    fill: true,
                },
                {
                    label: 'Pending',
                    data: [],
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245, 158, 11, 0.08)',
                    borderWidth: 2.5,
                    pointRadius: 4,
                    pointBackgroundColor: '#f59e0b',
                    tension: 0.4,
                    fill: true,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(10, 17, 34, 0.96)',
                    borderColor: 'rgba(88, 130, 222, 0.7)',
                    borderWidth: 1,
                    titleColor: '#e8f1ff',
                    bodyColor: '#d8e6ff',
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

    const monthFilter = document.getElementById('kkProfilingMonthFilter');
    if (monthFilter) {
        monthFilter.value = String(new Date().getMonth() + 1);
    }

    wireKkProfilingBarangayFilter();
    wireKkProfilingPeriodFilter();
    wireKkProfilingMonthFilter();
    wireKkProfilingExport();
    wireKkProfilingDatasetFilters();
    toggleKkProfilingMonthFilter();
    refreshKkProfilingChart();
}

function scheduleKkProfilingRefresh() {
    window.clearTimeout(kkProfilingFetchTimer);
    kkProfilingFetchTimer = window.setTimeout(() => {
        refreshKkProfilingChart();
    }, 200);
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

function wireKkProfilingPeriodFilter() {
    if (kkProfilingPeriodWired) {
        return;
    }

    const periodFilter = document.getElementById('kkProfilingPeriodFilter');
    if (!periodFilter) {
        return;
    }

    periodFilter.addEventListener('change', () => {
        toggleKkProfilingMonthFilter();
        scheduleKkProfilingRefresh();
    });
    kkProfilingPeriodWired = true;
}

function wireKkProfilingMonthFilter() {
    if (kkProfilingMonthWired) {
        return;
    }

    const monthFilter = document.getElementById('kkProfilingMonthFilter');
    if (!monthFilter) {
        return;
    }

    monthFilter.addEventListener('change', scheduleKkProfilingRefresh);
    kkProfilingMonthWired = true;
}

function toggleKkProfilingMonthFilter() {
    const periodFilter = document.getElementById('kkProfilingPeriodFilter');
    const monthFilter = document.getElementById('kkProfilingMonthFilter');
    if (!periodFilter || !monthFilter) {
        return;
    }

    monthFilter.hidden = periodFilter.value !== 'weekly';
}

function wireKkProfilingDatasetFilters() {
    const cbApproved = document.getElementById('filterKkApproved');
    const cbRejected = document.getElementById('filterKkRejected');
    const cbPending = document.getElementById('filterKkPending');

    function applyDatasetFilter() {
        if (!kkProfilingMonthlyChart) {
            return;
        }

        if (cbApproved) {
            kkProfilingMonthlyChart.data.datasets[0].hidden = !cbApproved.checked;
        }
        if (cbRejected) {
            kkProfilingMonthlyChart.data.datasets[1].hidden = !cbRejected.checked;
        }
        if (cbPending) {
            kkProfilingMonthlyChart.data.datasets[2].hidden = !cbPending.checked;
        }

        kkProfilingMonthlyChart.update();
    }

    [cbApproved, cbRejected, cbPending].forEach((checkbox) => {
        if (!checkbox) {
            return;
        }

        checkbox.addEventListener('change', applyDatasetFilter);
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
        return;
    }

    const {
        barangayName,
        period,
        year,
        labels,
        monthlyApproved,
        monthlyRejected,
        monthlyPending,
    } = kkProfilingCurrentData;

    const periodLabel = period === 'weekly' ? 'Week' : 'Month';
    const headers = [periodLabel, 'Barangay', 'Year', 'Approved', 'Pending', 'Rejected'];
    let csvContent = `${headers.join(',')}\n`;

    labels.forEach((label, index) => {
        csvContent += [
            `"${label}"`,
            `"${barangayName}"`,
            year,
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
    link.setAttribute('download', `kk_profiling_${period}_${slug || 'data'}.csv`);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
};

function tryBootKkProfilingChart() {
    if (!document.getElementById('kkProfilingMonthlyChart') || typeof window.Chart === 'undefined') {
        return;
    }

    initKkProfilingMonthlyChart();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', tryBootKkProfilingChart);
} else {
    tryBootKkProfilingChart();
}
