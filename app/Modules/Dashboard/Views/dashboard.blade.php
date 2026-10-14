@extends('layout::app')

@section('title', 'Dashboard')

@section('content')
@if (session('success'))
    <div class="dash-flash dash-flash-success" role="status">{{ session('success') }}</div>
@endif
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
                    $dashTermStart = $termStart ?? sprintf('%04d-01-01', $dashYear);
                    $dashTermEnd = $termEnd ?? sprintf('%04d-12-31', $dashYear);
                    $dashToday = now()->timezone(config('app.timezone', 'Asia/Manila'))->toDateString();
                    $dashDateFrom = $dateFrom ?? sprintf('%04d-01-01', $dashYear);
                    $dashDateTo = $dateTo ?? sprintf('%04d-12-31', $dashYear);
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
                        data-default-from="{{ $dashDateFrom }}"
                        data-default-to="{{ $dashDateTo }}"
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
                                    title="From (January 1 of the current year; selectable dates are official term dates only)"
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
                                    title="To (December 31 of the current year; selectable dates are official term dates only)"
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
                                title="Reset to January 1 – December 31 of the current year"
                                aria-label="Reset date filter to January 1 through December 31 of the current year"
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

        <section class="dashboard-statistics-section" aria-labelledby="dashboardStatisticsTitle">
            <div class="dashboard-statistics-heading">
                <h2 id="dashboardStatisticsTitle">Dashboard Statistics</h2>
                <p>Municipal, profiling, audit, and program totals.</p>
            </div>
        <div class="dash-stats-grid">
            <a href="{{ route('kabataan-monitoring') }}" class="stat-card stat-card-blue" data-no-loading data-stat-key="total_kk" title="Kabataan registrations across all barangays">
                <div class="stat-card-top">
                    <span class="stat-card-value">{{ number_format($totalKkProfiles ?? 0) }}</span>
                    <div class="stat-card-icon stat-icon-blue">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                    </div>
                </div>
                <span class="stat-card-label">Total Kabataan Registered</span>
            </a>
            <a href="{{ route('kabataan-monitoring') }}" class="stat-card stat-card-green" data-no-loading data-stat-key="kk_approved" title="Approved KK profiles across all barangays">
                <div class="stat-card-top">
                    <span class="stat-card-value">{{ number_format($kkApproved ?? 0) }}</span>
                    <div class="stat-card-icon stat-icon-green">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    </div>
                </div>
                <span class="stat-card-label">KK Profiling Approved</span>
            </a>
            <a href="{{ route('kabataan-monitoring') }}" class="stat-card stat-card-yellow" data-no-loading data-stat-key="kk_pending" title="Pending KK profiling submissions across all barangays">
                <div class="stat-card-top">
                    <span class="stat-card-value">{{ number_format($kkPending ?? 0) }}</span>
                    <div class="stat-card-icon stat-icon-yellow">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    </div>
                </div>
                <span class="stat-card-label">KK Profiling Pending</span>
            </a>
            <a href="{{ route('accounts.officials.index') }}" class="stat-card stat-card-indigo" data-no-loading data-stat-key="total_sk_officials" title="SK officials across all barangays">
                <div class="stat-card-top">
                    <span class="stat-card-value">{{ number_format($totalSkOfficials ?? 0) }}</span>
                    <div class="stat-card-icon stat-icon-indigo">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                    </div>
                </div>
                <span class="stat-card-label">Total SK Officials</span>
            </a>
            <a href="{{ route('accounts.federation.index') }}" class="stat-card stat-card-purple" data-no-loading data-stat-key="total_sk_chairpersons" title="SK chairpersons across all barangays">
                <div class="stat-card-top">
                    <span class="stat-card-value">{{ number_format($totalSkChairpersons ?? 0) }}</span>
                    <div class="stat-card-icon stat-icon-purple">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2l2.4 4.8L20 8l-4 3.9.9 5.6L12 15.8 7.1 17.5 8 11.9 4 8l5.6-1.2L12 2z"></path></svg>
                    </div>
                </div>
                <span class="stat-card-label">Total SK Chairpersons</span>
            </a>
            <a href="{{ route('barangay-monitoring') }}" class="stat-card stat-card-green" data-no-loading data-stat-key="total_barangays_abyip_submitted" title="Barangays that submitted an ABYIP">
                <div class="stat-card-top">
                    <span class="stat-card-value">{{ number_format($totalBarangaysAbyipSubmitted ?? 0) }}</span>
                    <div class="stat-card-icon stat-icon-green">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="8" y1="13" x2="16" y2="13"></line><line x1="8" y1="17" x2="16" y2="17"></line></svg>
                    </div>
                </div>
                <span class="stat-card-label">Barangays with ABYIP</span>
            </a>
            <a href="{{ route('auditlogs.index') }}" class="stat-card stat-card-slate" data-no-loading data-stat-key="total_audit_logs" title="Audit logs for the municipality">
                <div class="stat-card-top">
                    <span class="stat-card-value">{{ number_format($totalAuditLogs ?? 0) }}</span>
                    <div class="stat-card-icon stat-icon-slate">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
                    </div>
                </div>
                <span class="stat-card-label">Total Audit Logs</span>
            </a>
            @include('dashboard::partials.dashboard-program-stat-cards')
        </div>
        </section>

        {{-- ── Purok + Quick Actions ── --}}
        <div class="dash-row dash-row--top-split">
            <div class="content-card dash-main-chart-card" id="purok-distribution-section">
                <div class="card-header kk-chart-section-header">
                    <div class="kk-chart-heading">
                        <h3>Kabataan per Purok/Sitio</h3>
                        <p class="kk-chart-subtitle">Youth population distribution</p>
                        <span class="kk-demo-total" id="purokDistributionTotal">
                            <span class="kk-demo-total-label">Total</span>
                            <span class="kk-demo-total-value">0</span>
                        </span>
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

        {{-- ── KK Profiling, Sex Distribution, and Employment Status ── --}}
        <div class="dash-row dash-row--kk-sex-employment">
            <div class="content-card dash-kk-chart-card" id="kk-profiling-section">
                <div class="card-header kk-chart-section-header">
                    <div class="kk-chart-heading">
                        <h3>KK Profiling by Month</h3>
                        <p class="kk-chart-subtitle kk-chart-subtitle--center" id="kkProfilingChartSubtitle">Monthly submissions — approved, pending, and rejected</p>
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
                    <span class="kk-demo-total" id="kkProfilingTotal">
                        <span class="kk-demo-total-label">Total</span>
                        <span class="kk-demo-total-value">0</span>
                    </span>
                </div>
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
            <div class="content-card dash-col-6" id="kabataan-sex-section">
                <div class="card-header dash-demo-chart-header">
                    <div class="kk-chart-heading">
                        <h3><i class="fas fa-venus-mars" style="color:#213F99;margin-right:8px;"></i>Sex Distribution</h3>
                        <p class="dash-demo-chart-sub">Male vs Female — Kabataan, SK Officials &amp; SK Federations</p>
                    </div>
                    <div class="kk-chart-header-actions">
                        <select id="sexDistributionRoleFilter" class="kk-barangay-select" aria-label="Filter by role">
                            <option value="all" selected>All</option>
                            <option value="kabataan">Kabataan</option>
                            <option value="officials">SK Officials</option>
                            <option value="federations">SK Federations</option>
                        </select>
                    </div>
                    <span class="kk-demo-total" id="kabataanSexTotal">
                        <span class="kk-demo-total-label">Total</span>
                        <span class="kk-demo-total-value">0</span>
                    </span>
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
                    <div class="kk-chart-header-actions">
                        <select id="employmentChartFilter" class="kk-barangay-select" aria-label="Filter employment status">
                            <option value="all" selected>All Statuses</option>
                            <option value="Employed">Employed</option>
                            <option value="Unemployed">Unemployed</option>
                            <option value="Self-Employed">Self-Employed</option>
                            <option value="Currently looking for a Job">Currently looking for a Job</option>
                            <option value="Not Interested Looking for a Job">Not Interested Looking for a Job</option>
                        </select>
                    </div>
                    <span class="kk-demo-total" id="employmentStatusTotal">
                        <span class="kk-demo-total-label">Total</span>
                        <span class="kk-demo-total-value">0</span>
                    </span>
                </div>
                <div class="card-body chart-body dash-demo-chart-body">
                    <div class="chart-canvas-wrap chart-canvas-wrap--employment" id="employmentChartWrap">
                        <canvas id="employmentStatusChart" aria-label="Employment status distribution chart"></canvas>
                    </div>
                    <div class="dash-demo-legend" id="employmentStatusLegend"></div>
                    <p class="dash-empty-note" id="employmentStatusEmpty" hidden>No employment data available.</p>
                </div>
            </div>
        </div>

        @include('dashboard::partials.dashboard-program-statistics')

        @include('dashboard::partials.dashboard-abyip-submissions')

        @include('dashboard::partials.dashboard-youth-demographics')

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
            total_kk: {{ (int) ($totalKkProfiles ?? 0) }},
            kk_approved: {{ (int) ($kkApproved ?? 0) }},
            kk_pending: {{ (int) ($kkPending ?? 0) }},
            total_programs: {{ (int) data_get($programDashboard ?? [], 'stats.total_programs', $totalPrograms ?? 0) }},
            open_programs: {{ (int) data_get($programDashboard ?? [], 'stats.open_programs', 0) }},
            closed_programs: {{ (int) data_get($programDashboard ?? [], 'stats.closed_programs', 0) }},
            upcoming_programs: {{ (int) data_get($programDashboard ?? [], 'stats.upcoming_programs', 0) }},
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
        youth_demographics: @json($youthDemographics ?? null),
        abyip_overview: @json($abyipOverview),
        top_barangays: @json($topBarangays ?? []),
        programs: @json($programDashboard ?? []),
    };
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    // One chart theme for the whole SK Federations dashboard. Chart.js otherwise
    // falls back to Helvetica and a near-black (rgba(0,0,0,0.8)) tooltip, which
    // is what produced the dark panel over the Purok/Sitio chart and made the
    // tooltip typography differ from the rest of the page.
    (function () {
        if (typeof window.Chart === 'undefined' || !window.Chart.defaults) {
            return;
        }
        var defaults = window.Chart.defaults;
        defaults.font.family = 'Inter, system-ui, sans-serif';
        defaults.color = '#475569';
        var tooltip = defaults.plugins && defaults.plugins.tooltip;
        if (tooltip) {
            tooltip.backgroundColor = '#2F4E85';
            tooltip.borderColor = '#2D4C7C';
            tooltip.borderWidth = 1;
            tooltip.titleColor = '#ffffff';
            tooltip.bodyColor = '#ffffff';
            tooltip.padding = 10;
            tooltip.cornerRadius = 8;
        }
    })();
</script>
@php
    $dashboardVite = \App\Support\ViteManifest::existing([
        'app/Modules/Dashboard/assets/js/kkprofilingchart.js',
        'app/Modules/Dashboard/assets/js/dashboard-purok.js',
        'app/Modules/Dashboard/assets/css/dashboard-youth-demographics.css',
        'app/Modules/Dashboard/assets/css/dashboard-programs.css',
        'app/Modules/Dashboard/assets/js/dashboard-youth-demographics.js',
        'app/Modules/Dashboard/assets/js/dashboard-demographics.js',
        'app/Modules/Dashboard/assets/js/dashboard-activity.js',
        'app/Modules/Dashboard/assets/js/dashboard-audit.js',
        'app/Modules/Dashboard/assets/js/dashboard-programs.js',
    ]);
@endphp
@if ($dashboardVite !== [])
    @vite($dashboardVite)
@endif
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
