/**
 * Municipality-wide youth demographics. Colors match the SK Officials dashboard.
 */
(function () {
    const DEMO_COLORS = [
        '#3b82f6', '#22c55e', '#f59e0b', '#ef4444', '#a855f7',
        '#14b8a6', '#f97316', '#6366f1', '#84cc16', '#ec4899', '#0ea5e9',
    ];

    const YES_NO_COLORS = {
        Yes: '#22c55e',
        No: '#ef4444',
    };

    const EDUCATION_ORDER = [
        'Elementary Level',
        'Elementary Grad',
        'High school level',
        'High school Grad',
        'Vocational Grad',
        'College Level',
        'College Grad',
        'Masters Level',
        'Masters Grad',
        'Doctorate Level',
        'Doctorate Graduate',
    ];

    const AGE_ORDER = [
        'Child Youth (15-17 yrs old)',
        'Core Youth (18-24 yrs old)',
        'Young Adult (25-30 yrs old)',
    ];

    const chartRegistry = {};
    let lastDemographics = null;
    let filtersBound = false;
    const filters = {
        civil: 'all',
        age: 'all',
        education: 'all',
        classification: 'all',
        needs: 'all',
        sk: 'all',
        national: 'all',
    };

    function esc(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function destroyChart(key) {
        if (chartRegistry[key]) {
            chartRegistry[key].destroy();
            chartRegistry[key] = null;
        }
    }

    function filterItems(items, selected) {
        const list = Array.isArray(items) ? items : [];
        if (!selected || selected === 'all') {
            return list;
        }
        return list.filter(function (item) {
            return String(item.label) === String(selected);
        });
    }

    function itemsToSeries(items) {
        const list = Array.isArray(items) ? items : [];
        return {
            labels: list.map(function (item) { return item.label; }),
            values: list.map(function (item) { return Number(item.count || 0); }),
        };
    }

    function colorForLabel(label, index, colorMap) {
        if (colorMap && Object.prototype.hasOwnProperty.call(colorMap, label)) {
            return colorMap[label];
        }
        return DEMO_COLORS[index % DEMO_COLORS.length];
    }

    function renderDonut(canvasId, legendId, key, items, colorMap) {
        const canvas = document.getElementById(canvasId);
        if (!canvas || typeof Chart === 'undefined') {
            return;
        }

        destroyChart(key);
        const series = itemsToSeries(items);
        const total = series.values.reduce(function (sum, n) { return sum + n; }, 0);
        const legend = document.getElementById(legendId);
        const colors = series.labels.map(function (label, index) {
            return colorForLabel(label, index, colorMap);
        });

        chartRegistry[key] = new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: series.labels,
                datasets: [{
                    data: series.values,
                    backgroundColor: colors,
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
                            label: function (context) {
                                const pct = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : '0.0';
                                return ' ' + context.label + ': ' + context.parsed + ' (' + pct + '%)';
                            },
                        },
                    },
                },
            },
        });

        if (legend) {
            legend.innerHTML = series.labels.map(function (label, index) {
                const isNo = String(label).toLowerCase() === 'no';
                const count = series.values[index].toLocaleString();
                return '<div class="donut-legend-item' + (isNo ? ' donut-legend-item--no' : '') + '">' +
                    '<div class="donut-legend-box" style="background:' + colors[index] + ';"></div>' +
                    '<span class="donut-legend-label">' +
                    '<span class="donut-legend-name">' + esc(label) + '</span>' +
                    ' <span class="donut-legend-count">(' + count + ')</span>' +
                    '</span></div>';
            }).join('');
        }
    }

    function renderBar(canvasId, key, items, options) {
        const canvas = document.getElementById(canvasId);
        if (!canvas || typeof Chart === 'undefined') {
            return;
        }

        destroyChart(key);
        const series = itemsToSeries(items);
        const opts = options && typeof options === 'object' ? options : {};
        const xTicks = {
            precision: 0,
            maxRotation: typeof opts.xMaxRotation === 'number' ? opts.xMaxRotation : 25,
            minRotation: 0,
            color: '#334155',
            font: { size: 11, weight: '600' },
            autoSkip: false,
        };
        if (typeof opts.xTickCallback === 'function') {
            xTicks.callback = opts.xTickCallback;
        }

        chartRegistry[key] = new Chart(canvas, {
            type: 'bar',
            data: {
                labels: series.labels,
                datasets: [{
                    data: series.values,
                    backgroundColor: series.labels.map(function (_label, index) {
                        return DEMO_COLORS[index % DEMO_COLORS.length];
                    }),
                    borderRadius: 6,
                    maxBarThickness: opts.maxBarThickness || 36,
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
                                return ' ' + context.parsed.y;
                            },
                        },
                    },
                },
                scales: {
                    x: { ticks: xTicks, grid: { display: false } },
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0, color: '#0f172a', font: { size: 11, weight: '500' } },
                        grid: { color: 'rgba(148, 163, 184, 0.2)' },
                    },
                },
            },
        });
    }

    function orderItems(items, order) {
        const map = {};
        (Array.isArray(items) ? items : []).forEach(function (item) {
            map[String(item.label)] = Number(item.count || 0);
        });
        return order.map(function (label) {
            return { label: label, count: map[label] || 0 };
        });
    }

    function renderRankedBars(listId, items, order, emptyMessage) {
        const list = document.getElementById(listId);
        if (!list) {
            return;
        }

        const rows = orderItems(items, order);
        const total = rows.reduce(function (sum, row) { return sum + row.count; }, 0);
        const max = rows.reduce(function (current, row) { return Math.max(current, row.count); }, 0);

        if (rows.length === 0) {
            list.innerHTML = '<p class="dash-empty-msg">' + esc(emptyMessage || 'No data available.') + '</p>';
            return;
        }

        list.innerHTML = rows.map(function (row, index) {
            const pct = total > 0 ? ((row.count / total) * 100) : 0;
            const width = max > 0 ? Math.max((row.count / max) * 100, row.count > 0 ? 4 : 0) : 0;
            const color = DEMO_COLORS[index % DEMO_COLORS.length];
            return '<div class="kk-edu-row" role="listitem">' +
                '<div class="kk-edu-label" title="' + esc(row.label) + '">' + esc(row.label) + '</div>' +
                '<div class="kk-edu-track" aria-hidden="true">' +
                '<div class="kk-edu-fill" style="width:' + width.toFixed(2) + '%;background:' + color + ';"></div>' +
                '</div>' +
                '<div class="kk-edu-stat">' +
                '<span class="kk-edu-count">' + row.count.toLocaleString() + '</span>' +
                '<span class="kk-edu-pct">(' + pct.toFixed(1) + '%)</span>' +
                '</div></div>';
        }).join('');
    }

    function setDemoTotal(elementId, items) {
        const root = document.getElementById(elementId);
        const valueEl = root ? root.querySelector('.kk-demo-total-value') : null;
        if (!valueEl) {
            return;
        }
        const total = (Array.isArray(items) ? items : []).reduce(function (sum, item) {
            return sum + Number(item.count || 0);
        }, 0);
        valueEl.textContent = total.toLocaleString();
    }

    function paint(data) {
        const civilItems = filterItems(data.civil_status, filters.civil);
        setDemoTotal('kkDemoCivilTotal', civilItems);
        renderDonut(
            'chartDemoCivilStatus',
            'kkDemoCivilLegend',
            'civil',
            civilItems
        );

        const ageItems = orderItems(
            filterItems(data.age_groups, filters.age),
            filters.age === 'all' ? AGE_ORDER : [filters.age]
        );
        setDemoTotal('kkDemoAgeTotal', ageItems);
        renderBar(
            'chartDemoAgeGroup',
            'age',
            ageItems,
            {
                maxBarThickness: 48,
                xMaxRotation: 0,
                xTickCallback: function (value) {
                    const label = this.getLabelForValue(value);
                    if (label.indexOf('Child Youth') === 0) return ['Child Youth', '(15-17)'];
                    if (label.indexOf('Core Youth') === 0) return ['Core Youth', '(18-24)'];
                    if (label.indexOf('Young Adult') === 0) return ['Young Adult', '(25-30)'];
                    return label;
                },
            }
        );

        const educationItems = orderItems(
            filterItems(data.educational_background, filters.education),
            filters.education === 'all' ? EDUCATION_ORDER : [filters.education]
        );
        setDemoTotal('kkDemoEducationTotal', educationItems);
        renderRankedBars(
            'kkDemoEducationList',
            educationItems,
            educationItems.map(function (item) { return item.label; }),
            'No educational background data.'
        );

        const classificationItems = filterItems(data.youth_classification, filters.classification);
        setDemoTotal('kkDemoClassificationTotal', classificationItems);
        renderBar(
            'chartDemoClassification',
            'classification',
            classificationItems
        );

        const needsItems = filterItems(data.specific_needs, filters.needs);
        setDemoTotal('kkDemoNeedsTotal', needsItems);
        renderBar(
            'chartDemoSpecificNeeds',
            'needs',
            needsItems
        );

        const skItems = filterItems(data.sk_voter, filters.sk);
        setDemoTotal('kkDemoSkVoterTotal', skItems);
        renderDonut(
            'chartDemoSkVoter',
            'kkDemoSkVoterLegend',
            'sk',
            skItems,
            YES_NO_COLORS
        );

        const nationalItems = filterItems(data.national_voter, filters.national);
        setDemoTotal('kkDemoNationalVoterTotal', nationalItems);
        renderDonut(
            'chartDemoNationalVoter',
            'kkDemoNationalVoterLegend',
            'national',
            nationalItems,
            YES_NO_COLORS
        );
    }

    function applyYouthDemographics(demographics) {
        const data = demographics && typeof demographics === 'object' ? demographics : null;
        lastDemographics = data || {
            total: 0,
            has_data: false,
            civil_status: [],
            age_groups: [],
            educational_background: [],
            youth_classification: [],
            specific_needs: [],
            sk_voter: [],
            national_voter: [],
        };

        const emptyMsg = document.getElementById('kkDemoEmptyMsg');
        const grid = document.getElementById('kkDemoChartsGrid');
        const hasData = !!lastDemographics.has_data || Number(lastDemographics.total || 0) > 0;
        if (emptyMsg) {
            emptyMsg.hidden = hasData;
        }
        if (grid) {
            grid.classList.toggle('kk-demo-charts-grid--muted', !hasData);
        }
        paint(lastDemographics);
    }

    function bindFilters() {
        if (filtersBound) {
            return;
        }
        filtersBound = true;
        [
            ['filterDemoCivil', 'civil'],
            ['filterDemoAge', 'age'],
            ['filterDemoEducation', 'education'],
            ['filterDemoClassification', 'classification'],
            ['filterDemoNeeds', 'needs'],
            ['filterDemoSkVoter', 'sk'],
            ['filterDemoNationalVoter', 'national'],
        ].forEach(function (pair) {
            const el = document.getElementById(pair[0]);
            if (!el) {
                return;
            }
            el.addEventListener('change', function () {
                filters[pair[1]] = el.value || 'all';
                if (lastDemographics) {
                    paint(lastDemographics);
                }
            });
        });
    }

    window.applyYouthDemographics = applyYouthDemographics;

    document.addEventListener('DOMContentLoaded', function () {
        bindFilters();
        const feed = window.__DASHBOARD_FEED__ || {};
        applyYouthDemographics(feed.youth_demographics || null);
    });
})();
