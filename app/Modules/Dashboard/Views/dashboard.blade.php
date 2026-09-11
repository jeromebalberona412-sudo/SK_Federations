@extends('layout::app')

@section('title', 'Dashboard')

@section('content')
{{-- Page Header --}}
        <div class="dash-page-header">
            <div class="dash-page-header-left">
                <h1 class="dash-page-title">Dashboard</h1>
                <p class="dash-page-sub">
                    <span class="dash-page-sub-line">Welcome back,</span>
                    <strong class="dash-page-sub-name">{{ $user->name ?? 'SK Federation' }}</strong>
                    <span class="dash-page-sub-org">SK Federation</span>
                </p>
            </div>
            <div class="dash-page-filters">
                @php
                    $dashYear = (int) ($dashboardYear ?? now()->year);
                    $dashTermStart = $termStart ?? ($dateFrom ?? sprintf('%04d-01-01', $dashYear));
                    $dashTermEnd = $termEnd ?? now()->toDateString();
                    $dashToday = now()->timezone(config('app.timezone', 'Asia/Manila'))->toDateString();
                    $dashDateToCap = $dashToday <= $dashTermEnd ? $dashToday : $dashTermEnd;
                    $dashDateFrom = $dateFrom ?? $dashTermStart;
                    $dashDateTo = $dateTo ?? $dashDateToCap;
                    $dashDataEarliest = $dataEarliest ?? null;
                    $dashShowAll = !empty($showDateAll);
                @endphp
                <div class="dash-header-tools" id="dashHeaderTools">
                    <a href="{{ route('calendar') }}" class="dash-today-card" aria-label="Open calendar — Today's date">
                        <span class="dash-today-icon" aria-hidden="true"><i class="fas fa-calendar-alt"></i></span>
                        <div class="dash-today-text">
                            <span class="dash-today-label">Today is</span>
                            <span class="dash-today-value">{{ $todayLabel ?? now()->format('F j, Y | l') }}</span>
                        </div>
                    </a>
                    <div
                        class="dash-date-range-filter"
                        id="dashDateRangeFilter"
                        data-default-from="{{ $dashTermStart }}"
                        data-default-to="{{ $dashDateToCap }}"
                        data-term-start="{{ $dashTermStart }}"
                        data-term-end="{{ $dashTermEnd }}"
                        data-term-year="{{ $dashYear }}"
                        data-data-earliest="{{ $dashDataEarliest ?? '' }}"
                        data-today="{{ $dashToday }}"
                        data-range-mode="term"
                    >
                        <div class="dash-date-range-fields">
                            <label class="dash-date-field" for="dashDateFrom">
                                <span class="dash-date-field-label">From</span>
                                <input
                                    type="date"
                                    id="dashDateFrom"
                                    class="dash-date-input"
                                    value="{{ $dashDateFrom }}"
                                    min="{{ $dashTermStart }}"
                                    max="{{ $dashTermEnd }}"
                                    aria-label="Filter from date"
                                    title="From (term start)"
                                >
                            </label>
                            <span class="dash-date-range-sep" aria-hidden="true">–</span>
                            <label class="dash-date-field" for="dashDateTo">
                                <span class="dash-date-field-label">To</span>
                                <input
                                    type="date"
                                    id="dashDateTo"
                                    class="dash-date-input"
                                    value="{{ $dashDateTo }}"
                                    min="{{ $dashTermStart }}"
                                    max="{{ $dashTermEnd }}"
                                    aria-label="Filter to date"
                                    title="To (today / term end)"
                                >
                            </label>
                            @if($dashShowAll)
                            <button
                                type="button"
                                class="dash-date-all-btn"
                                id="dashDateAllBtn"
                                title="Show all data from the earliest record to today"
                                aria-label="Show all historical data"
                            >
                                All
                            </button>
                            @endif
                            <button
                                type="button"
                                class="dash-date-reset-btn"
                                id="dashDateResetBtn"
                                title="Reset to term start and today"
                                aria-label="Reset date filter to term start and today"
                            >
                                <i class="fas fa-undo" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="calendarReminderBanner" class="dash-reminder-banner" @if(empty($todayReminder)) hidden @endif>
            <div class="dash-reminder-icon">
                <i class="fas fa-calendar-day"></i>
            </div>
            <div class="dash-reminder-body">
                <span class="dash-reminder-label">Today's Reminder</span>
                <span class="dash-reminder-text" id="reminderText">
                    @if(!empty($todayReminder))
                        {{ $todayReminder['title'] }}
                    @endif
                </span>
            </div>
            <a href="{{ route('calendar') }}" class="dash-reminder-link">View Calendar</a>
        </div>

        {{-- ── STAT CARDS ── --}}
        <div class="dash-stats-grid">
            <a href="{{ route('kabataan-monitoring') }}" class="stat-card stat-card-link stat-card-clickable" data-no-loading data-stat-key="total_kabataan_registered">
                <div class="stat-icon blue"><i class="fas fa-users"></i></div>
                <div class="stat-info">
                    <div class="stat-value">{{ number_format($totalKabataanRegistered ?? 0) }}</div>
                    <div class="stat-label">Total Kabataan Registered</div>
                </div>
            </a>
            <a href="{{ route('accounts.officials.index') }}" class="stat-card stat-card-link stat-card-clickable" data-no-loading data-stat-key="total_sk_officials">
                <div class="stat-icon indigo"><i class="fas fa-user-tie"></i></div>
                <div class="stat-info">
                    <div class="stat-value">{{ number_format($totalSkOfficials ?? 0) }}</div>
                    <div class="stat-label">Total SK Officials</div>
                </div>
            </a>
            <a href="{{ route('accounts.federation.index') }}" class="stat-card stat-card-link stat-card-clickable" data-no-loading data-stat-key="total_sk_chairpersons">
                <div class="stat-icon teal"><i class="fas fa-crown"></i></div>
                <div class="stat-info">
                    <div class="stat-value">{{ number_format($totalSkChairpersons ?? 0) }}</div>
                    <div class="stat-label">Total SK Chairpersons</div>
                </div>
            </a>
            <a href="{{ route('barangay-monitoring') }}" class="stat-card stat-card-link stat-card-clickable" data-no-loading data-stat-key="total_barangays_abyip_submitted">
                <div class="stat-icon green"><i class="fas fa-file-invoice-dollar"></i></div>
                <div class="stat-info">
                    <div class="stat-value">{{ number_format($totalBarangaysAbyipSubmitted ?? 0) }}</div>
                    <div class="stat-label">Barangays with ABYIP Submissions</div>
                </div>
            </a>
            <a href="{{ route('auditlogs.index') }}" class="stat-card stat-card-link stat-card-clickable" data-no-loading data-stat-key="total_audit_logs">
                <div class="stat-icon purple"><i class="fas fa-clipboard-list"></i></div>
                <div class="stat-info">
                    <div class="stat-value">{{ number_format($totalAuditLogs ?? 0) }}</div>
                    <div class="stat-label">Total Audit Logs</div>
                </div>
            </a>
        </div>
        
        {{-- ── Purok + Quick Actions ── --}}
        <div class="dash-row dash-row--top-split">
            <div class="content-card dash-main-chart-card" id="purok-distribution-section">
                <div class="card-header kk-chart-section-header">
                    <div class="kk-chart-heading">
                        <h3>Kabataan per Purok/Sitio</h3>
                        <p class="kk-chart-subtitle">Youth population distribution</p>
                    </div>
                    <div class="kk-chart-header-actions">
                        <select id="purokBarangayFilter" class="kk-barangay-select" aria-label="Filter by barangay">
                            <option value="all">All Barangays</option>
                            @foreach ($barangays as $barangay)
                                <option value="{{ $barangay->id }}">{{ $barangay->name }}</option>
                            @endforeach
                        </select>
                        <select id="purokZoneFilter" class="kk-barangay-select" aria-label="Filter by purok or sitio" hidden>
                            <option value="all">All Purok/Sitio</option>
                        </select>
                    </div>
                </div>
                <div class="card-body dash-purok-card-body">
                    <div class="kk-chart-canvas-wrap kk-chart-canvas-wrap--purok" id="purokChartWrap">
                        <div class="purok-chart-scroll-inner" id="purokChartScrollInner">
                            <canvas id="purokDistributionChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="content-card dash-quick-actions-card">
                <div class="card-header dash-qa-header">
                    <h3><i class="fas fa-bolt" aria-hidden="true"></i> Quick Actions</h3>
                </div>
                <div class="card-body quick-actions-list" role="list">
                    <a href="{{ route('accounts.officials.index') }}" class="qa-item qa-item--blue" role="listitem" data-no-loading>
                        <span class="qa-item-icon"><i class="fas fa-user-plus"></i></span>
                        <span class="qa-item-label">Add SK Officials</span>
                        <span class="qa-item-chevron"><i class="fas fa-chevron-right"></i></span>
                    </a>
                    <a href="{{ route('barangay-monitoring') }}" class="qa-item qa-item--green" role="listitem" data-no-loading>
                        <span class="qa-item-icon"><i class="fas fa-clipboard-check"></i></span>
                        <span class="qa-item-label">Barangay Compliance</span>
                        <span class="qa-item-chevron"><i class="fas fa-chevron-right"></i></span>
                    </a>
                    <a href="{{ route('kabataan-monitoring') }}" class="qa-item qa-item--teal" role="listitem" data-no-loading>
                        <span class="qa-item-icon"><i class="fas fa-users"></i></span>
                        <span class="qa-item-label">KK Profiling Records</span>
                        <span class="qa-item-chevron"><i class="fas fa-chevron-right"></i></span>
                    </a>
                    <a href="{{ route('kabataan-monitoring') }}" class="qa-item qa-item--blue" role="listitem" data-no-loading>
                        <span class="qa-item-icon"><i class="fas fa-file-csv"></i></span>
                        <span class="qa-item-label">Generate Reports</span>
                        <span class="qa-item-chevron"><i class="fas fa-chevron-right"></i></span>
                    </a>
                    <a href="{{ route('barangay-logos.index') }}" class="qa-item qa-item--orange" role="listitem" data-no-loading>
                        <span class="qa-item-icon"><i class="fas fa-image"></i></span>
                        <span class="qa-item-label">Barangay Logos</span>
                        <span class="qa-item-chevron"><i class="fas fa-chevron-right"></i></span>
                    </a>
                    <a href="{{ route('community-feed') }}" class="qa-item qa-item--purple" role="listitem" data-no-loading>
                        <span class="qa-item-icon"><i class="fas fa-rss"></i></span>
                        <span class="qa-item-label">SK Community Feed</span>
                        <span class="qa-item-chevron"><i class="fas fa-chevron-right"></i></span>
                    </a>
                    <a href="{{ route('calendar') }}" class="qa-item qa-item--teal" role="listitem" data-no-loading>
                        <span class="qa-item-icon"><i class="fas fa-calendar-alt"></i></span>
                        <span class="qa-item-label">Calendar</span>
                        <span class="qa-item-chevron"><i class="fas fa-chevron-right"></i></span>
                    </a>
                </div>
            </div>
        </div>

        {{-- ── KK Profiling + ABYIP (side by side) ── --}}
        <div class="dash-row dash-row--kk-abyip">
            <div class="content-card dash-kk-chart-card" id="kk-profiling-section">
                <div class="card-header kk-chart-section-header">
                    <div class="kk-chart-heading">
                        <h3>KK Profiling by Month</h3>
                    </div>
                    <div class="kk-chart-header-actions">
                        <select id="kkProfilingBarangayFilter" class="kk-barangay-select" aria-label="Filter by barangay">
                            <option value="all">All Barangays</option>
                            @foreach ($barangays as $barangay)
                                <option value="{{ $barangay->id }}">{{ $barangay->name }}</option>
                            @endforeach
                        </select>
                        <select id="kkProfilingPeriodFilter" class="kk-barangay-select" aria-label="Filter by period">
                            <option value="monthly" selected>Monthly</option>
                            <option value="weekly">Weekly</option>
                        </select>
                        <select id="kkProfilingMonthFilter" class="kk-barangay-select kk-month-filter" hidden aria-label="Filter by month">
                            <option value="1">January</option>
                            <option value="2">February</option>
                            <option value="3">March</option>
                            <option value="4">April</option>
                            <option value="5">May</option>
                            <option value="6">June</option>
                            <option value="7">July</option>
                            <option value="8">August</option>
                            <option value="9">September</option>
                            <option value="10">October</option>
                            <option value="11">November</option>
                            <option value="12">December</option>
                        </select>
                    </div>
                </div>
                <p class="kk-chart-subtitle kk-chart-subtitle--center" id="kkProfilingChartSubtitle">Monthly submissions — approved, pending, and rejected</p>
                <div class="card-body">
                    <div class="kk-chart-canvas-wrap kk-chart-canvas-wrap--line">
                        <canvas id="kkProfilingMonthlyChart"></canvas>
                    </div>
                    <div class="kk-chart-filter-row">
                        <label class="kk-chart-filter-check kk-chart-filter-check--approved">
                            <input type="checkbox" id="filterKkApproved" checked>
                            <span class="kk-chart-filter-dot kk-chart-filter-dot--approved"></span>
                            <span>Approved</span>
                        </label>
                        <label class="kk-chart-filter-check kk-chart-filter-check--pending">
                            <input type="checkbox" id="filterKkPending" checked>
                            <span class="kk-chart-filter-dot kk-chart-filter-dot--pending"></span>
                            <span>Pending</span>
                        </label>
                        <label class="kk-chart-filter-check kk-chart-filter-check--rejected">
                            <input type="checkbox" id="filterKkRejected" checked>
                            <span class="kk-chart-filter-dot kk-chart-filter-dot--rejected"></span>
                            <span>Rejected</span>
                        </label>
                    </div>
                </div>
            </div>

            @include('dashboard::partials.dashboard-abyip-submissions')
        </div>

        {{-- ── Sex Distribution + Employment Status (municipality-wide) ── --}}
        <div class="dash-row dash-row--demographics">
            <div class="content-card dash-col-6" id="kabataan-sex-section">
                <div class="card-header dash-demo-chart-header">
                    <div class="kk-chart-heading">
                        <h3><i class="fas fa-venus-mars" style="color:#213F99;margin-right:8px;"></i>Sex Distribution</h3>
                        <p class="dash-demo-chart-sub">Male vs Female — Kabataan, SK Officials &amp; SK Federations</p>
                    </div>
                    <select id="sexDistributionRoleFilter" class="kk-barangay-select" aria-label="Filter by role">
                        <option value="all" selected>All</option>
                        <option value="kabataan">Kabataan</option>
                        <option value="officials">SK Officials</option>
                        <option value="federations">SK Federations</option>
                    </select>
                </div>
                <div class="card-body chart-body dash-demo-chart-body">
                    <canvas id="kabataanSexChart" aria-label="Sex distribution chart"></canvas>
                    <div class="dash-demo-legend" id="kabataanSexLegend"></div>
                    <p class="dash-empty-note" id="kabataanSexEmpty" hidden>No sex data available.</p>
                </div>
            </div>

            <div class="content-card dash-col-6" id="employment-status-section">
                <div class="card-header dash-demo-chart-header">
                    <div class="kk-chart-heading">
                        <h3><i class="fas fa-briefcase" style="color:#213F99;margin-right:8px;"></i>Employment Status Distribution</h3>
                        <p class="dash-demo-chart-sub">Current employment profile of registered Kabataan</p>
                    </div>
                    <select id="employmentChartFilter" class="kk-barangay-select" aria-label="Filter employment status">
                        <option value="all" selected>All Statuses</option>
                        <option value="Employed">Employed</option>
                        <option value="Unemployed">Unemployed</option>
                        <option value="Self-Employed">Self-Employed</option>
                        <option value="Currently looking for a Job">Currently looking for a Job</option>
                        <option value="Not Interested Looking for a Job">Not Interested Looking for a Job</option>
                    </select>
                </div>
                <div class="card-body chart-body dash-demo-chart-body">
                    <canvas id="employmentStatusChart" aria-label="Employment status distribution chart"></canvas>
                    <div class="dash-demo-legend" id="employmentStatusLegend"></div>
                    <p class="dash-empty-note" id="employmentStatusEmpty" hidden>No employment data available.</p>
                </div>
            </div>
        </div>

        {{-- ── ROW 2: Federation Officers + Recent Audit Activity ── --}}
        <div class="dash-row dash-row--balanced dash-row--fed-audit">
            <div class="content-card dash-col-6" id="federation-section">
                <div class="card-header">
                    <h3><i class="fas fa-sitemap" style="color:#213F99;margin-right:8px;"></i>Federation Officers</h3>
                </div>
                <div class="card-body federation-officers-body">
                    <table class="federation-officers-table">
                        <thead>
                            <tr>
                                <th>Position</th>
                                <th>
                                    Full Name
                                    <div class="table-col-hint">LN, FN, MN, Suffix</div>
                                </th>
                                <th>Barangay</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($federationOfficers as $officer)
                                <tr class="{{ $officer['name'] === 'Vacant' ? 'is-vacant' : '' }}">
                                    <td>{{ $officer['position'] }}</td>
                                    <td>{{ $officer['name'] }}</td>
                                    <td>{{ $officer['barangay'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="dash-col-6 dash-audit-row dash-audit-row--compact">
                @include('dashboard::partials.dashboard-audit-table')
            </div>
        </div>

        {{-- ── ROW 3: Top 5 Barangays + Recent Activity + Upcoming Events ── --}}
        <div class="dash-row">
            <div class="content-card dash-col-4" id="top-barangays-section">
                <div class="card-header">
                    <h3><i class="fas fa-trophy" style="color:#F7D31E;margin-right:8px;"></i>Top 5 Barangays by Youth Population</h3>
                </div>
                <div class="card-body top-barangays-body" id="dashTopBarangaysBody" data-records-url="{{ route('kabataan-monitoring') }}">
                    @forelse ($topBarangays as $row)
                        @php
                            $rankClass = in_array($row['rank'], [1, 2, 3], true) ? ' top-barangay-item--rank-' . $row['rank'] : '';
                        @endphp
                        <a href="{{ route('kabataan-monitoring') }}" class="top-barangay-item{{ $rankClass }}" style="text-decoration:none;cursor:pointer;">
                            <div class="top-barangay-rank top-barangay-rank--{{ $row['rank'] }}">{{ $row['rank'] }}</div>
                            <div class="top-barangay-info">
                                <div class="top-barangay-name">{{ $row['barangay'] }}</div>
                                <div class="top-barangay-count">{{ number_format($row['count']) }} youth registered</div>
                            </div>
                            <div class="top-barangay-bar-wrap">
                                @php
                                    $maxCount = max(1, $topBarangays[0]['count'] ?? 1);
                                    $width = round(($row['count'] / $maxCount) * 100);
                                @endphp
                                <div class="top-barangay-bar top-barangay-bar--{{ $row['rank'] }}" style="width: {{ $width }}%;"></div>
                            </div>
                        </a>
                    @empty
                        <p class="dash-empty-note">No youth registration data available yet.</p>
                    @endforelse
                </div>
            </div>

            <div class="dash-col-8">
                @include('dashboard::partials.dashboard-recent-activity')
            </div>
        </div>
@endsection

@push('scripts')
@php
    $employmentStatusDistributionData = $employmentStatusDistribution ?? [
        'labels' => [],
        'values' => [],
        'total' => 0,
    ];
    $sexDistributionKabataanData = $sexDistribution ?? ['labels' => ['Male', 'Female'], 'values' => [0, 0]];
    $sexDistributionOfficialsData = $sexDistributionOfficials ?? ['labels' => ['Male', 'Female'], 'values' => [0, 0]];
    $sexDistributionFederationsData = $sexDistributionFederations ?? ['labels' => ['Male', 'Female'], 'values' => [0, 0]];
@endphp
<script>
    window.__KK_BARANGAYS__ = @json($kkBarangayOptions);
    window.__KK_PROFILING_DATA_URL__ = @json(route('dashboard.kk-profiling-data'));
    window.__PUROK_DATA_URL__ = @json(Route::has('dashboard.purok-distribution') ? route('dashboard.purok-distribution') : url('/api/dashboard/purok-distribution'));
    window.__KK_PROFILING_INITIAL__ = @json($kkProfilingInitial ?? null);
    window.__PUROK_CHART_INITIAL__ = @json($purokChartInitial ?? null);
    window.__DASHBOARD_FEED__ = {
        recent_activity: @json($recentActivity ?? []),
        upcoming_events: @json($upcomingEvents ?? []),
        today_reminder: @json($todayReminder ?? null),
        activities_url: @json(route('dashboard.recent-activities')),
        feed_url: @json(Route::has('dashboard.feed') ? route('dashboard.feed') : url('/api/dashboard/feed')),
        stats_url: @json(Route::has('dashboard.stats') ? route('dashboard.stats') : url('/api/dashboard/stats')),
        date_from: @json($dateFrom ?? null),
        date_to: @json($dateTo ?? null),
        stats: {
            total_kabataan_registered: {{ (int) ($totalKabataanRegistered ?? 0) }},
            total_sk_officials: {{ (int) ($totalSkOfficials ?? 0) }},
            total_sk_chairpersons: {{ (int) ($totalSkChairpersons ?? 0) }},
            total_barangays_abyip_submitted: {{ (int) ($totalBarangaysAbyipSubmitted ?? 0) }},
            total_audit_logs: {{ (int) ($totalAuditLogs ?? 0) }},
        },
        sex_distribution: @json($sexDistributionKabataanData),
        sex_distribution_officials: @json($sexDistributionOfficialsData),
        sex_distribution_federations: @json($sexDistributionFederationsData),
        employment_status_distribution: @json($employmentStatusDistributionData),
        abyip_overview: @json($abyipOverview),
        top_barangays: @json($topBarangays ?? []),
    };
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
@vite([
    'app/Modules/Dashboard/assets/js/kkprofilingchart.js',
    'app/Modules/Dashboard/assets/js/dashboard-purok.js',
    'app/Modules/Dashboard/assets/js/dashboard-demographics.js',
    'app/Modules/Dashboard/assets/js/dashboard-activity.js',
    'app/Modules/Dashboard/assets/js/dashboard-audit.js',
])
<script>
        (() => {
            const heartbeatMs = {{ (int) config('sk_fed_auth.single_session.heartbeat_interval_seconds', 30) }} * 1000;
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
            let id = null;
            async function beat() {
                try {
                    await fetch("{{ route('skfed.heartbeat') }}", {
                        method: 'POST',
                        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                        credentials: 'same-origin', body: JSON.stringify({}),
                    });
                } catch (_) {}
            }
            beat();
            id = setInterval(beat, heartbeatMs);
            window.addEventListener('beforeunload', () => clearInterval(id));
        })();
    </script>
@endpush
