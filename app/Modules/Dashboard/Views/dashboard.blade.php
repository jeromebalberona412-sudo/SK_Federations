@extends('layout::app')

@section('title', 'Dashboard')

@section('content')
{{-- Page Header --}}
        <div class="page-header">
            <h1>Dashboard</h1>
            <p>Welcome back, {{ $user->name ?? 'SK Official' }}</p>
        </div>

        <div id="calendarReminderBanner" class="dash-reminder-banner" @if(empty($todayReminder)) hidden @endif>
            <div class="dash-reminder-icon">
                <i class="fas fa-calendar-day"></i>
            </div>
            <div class="dash-reminder-body">
                <span class="dash-reminder-label">Today's Reminder</span>
                <span class="dash-reminder-text" id="reminderText">
                    @if(!empty($todayReminder))
                        {{ $todayReminder['date_label'] }} — {{ $todayReminder['title'] }}
                    @endif
                </span>
            </div>
            <a href="{{ route('calendar') }}" class="dash-reminder-link">View Calendar</a>
        </div>

        {{-- ── STAT CARDS ── --}}
        <div class="dash-stats-grid">
            <a href="{{ route('kabataan-monitoring') }}" class="stat-card stat-card-link stat-card-clickable">
                <div class="stat-icon blue"><i class="fas fa-users"></i></div>
                <div class="stat-info">
                    <div class="stat-value">{{ number_format($totalKabataanRegistered ?? 0) }}</div>
                    <div class="stat-label">Total Kabataan Registered</div>
                </div>
            </a>
            <a href="{{ route('accounts.officials.index') }}" class="stat-card stat-card-link stat-card-clickable">
                <div class="stat-icon indigo"><i class="fas fa-user-tie"></i></div>
                <div class="stat-info">
                    <div class="stat-value">{{ number_format($totalSkOfficials ?? 0) }}</div>
                    <div class="stat-label">Total SK Officials</div>
                </div>
            </a>
            <a href="{{ route('accounts.federation.index') }}" class="stat-card stat-card-link stat-card-clickable">
                <div class="stat-icon teal"><i class="fas fa-crown"></i></div>
                <div class="stat-info">
                    <div class="stat-value">{{ number_format($totalSkChairpersons ?? 0) }}</div>
                    <div class="stat-label">Total SK Chairpersons</div>
                </div>
            </a>
            <a href="{{ route('barangay-monitoring') }}" class="stat-card stat-card-link stat-card-clickable" data-no-loading>
                <div class="stat-icon green"><i class="fas fa-file-invoice-dollar"></i></div>
                <div class="stat-info">
                    <div class="stat-value">{{ number_format($totalBarangaysAbyipSubmitted ?? 0) }}</div>
                    <div class="stat-label">Barangays with ABYIP Submissions</div>
                </div>
            </a>
            <a href="{{ route('auditlogs.index') }}" class="stat-card stat-card-link stat-card-clickable">
                <div class="stat-icon purple"><i class="fas fa-clipboard-list"></i></div>
                <div class="stat-info">
                    <div class="stat-value">{{ number_format($totalAuditLogs ?? 0) }}</div>
                    <div class="stat-label">Total Audit Logs</div>
                </div>
            </a>
        </div>
        
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                document.querySelectorAll('.stat-card-clickable').forEach(function(card) {
                    card.addEventListener('click', function(e) {
                        e.preventDefault();
                        const label = this.querySelector('.stat-label').textContent.trim();
                        if (typeof LoadingScreen !== 'undefined') {
                            LoadingScreen.show('Loading ' + label, 'Please wait...');
                        }
                        setTimeout(() => { window.location.href = this.href; }, 300);
                    });
                });
            });
        </script>

        {{-- ── QUICK ACTIONS ── --}}
        <div class="content-card" style="margin-bottom:24px;">
            <div class="card-header">
                <h3><i class="fas fa-bolt" style="color:#213F99;margin-right:8px;"></i>Quick Actions</h3>
            </div>
            <div class="card-body" style="padding:20px;overflow-x:auto;">
                <div style="display:flex;gap:12px;flex-wrap:wrap;min-width:fit-content;">
                    <a href="{{ route('accounts.officials.index') }}" class="qa-btn" style="display:inline-flex;align-items:center;gap:8px;padding:12px 20px;background:#213F99;color:#fff;border-radius:8px;text-decoration:none;font-size:14px;font-weight:500;white-space:nowrap;transition:all 0.2s ease;box-shadow:0 1px 3px rgba(33,63,153,0.3);">
                        <i class="fas fa-user-plus"></i>
                        <span>Add SK Officials</span>
                    </a>
                    <a href="{{ route('barangay-monitoring') }}" class="qa-btn" data-no-loading style="display:inline-flex;align-items:center;gap:8px;padding:12px 20px;background:#2563eb;color:#fff;border-radius:8px;text-decoration:none;font-size:14px;font-weight:500;white-space:nowrap;transition:all 0.2s ease;box-shadow:0 1px 3px rgba(37,99,235,0.3);">
                        <i class="fas fa-file-invoice-dollar"></i>
                        <span>Barangay ABYIP</span>
                    </a>
                    <a href="{{ route('barangay-logos.index') }}" class="qa-btn" style="display:inline-flex;align-items:center;gap:8px;padding:12px 20px;background:#2d52c4;color:#fff;border-radius:8px;text-decoration:none;font-size:14px;font-weight:500;white-space:nowrap;transition:all 0.2s ease;box-shadow:0 1px 3px rgba(45,82,196,0.3);">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>Barangay Logo</span>
                    </a>
                </div>
            </div>
        </div>
        
        <style>
            .qa-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                opacity: 0.9;
            }
            
            @media (max-width: 768px) {
                .card-body > div[style*="display:flex"] {
                    flex-wrap: nowrap !important;
                    overflow-x: auto !important;
                    padding-bottom: 8px;
                }
                .qa-btn {
                    flex-shrink: 0;
                }
            }
        </style>

        {{-- ── ROW 1: KK Profiling + Sex Distribution ── --}}
        <div class="dash-row dash-row--charts">
            <div class="content-card dash-col-8" id="kk-profiling-section">
                <div class="card-header kk-chart-section-header">
                    <div>
                        <h3><i class="fas fa-chart-line" style="color:#213F99;margin-right:8px;"></i>KK Profiling by Month</h3>
                        <p class="kk-chart-subtitle" id="kkProfilingChartSubtitle">KK Profiling</p>
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
                <div class="card-body">
                    <div class="kk-chart-canvas-wrap">
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

            <div class="content-card dash-col-4">
                <div class="card-header dash-sex-chart-header">
                    <h3><i class="fas fa-venus-mars" style="color:#213F99;margin-right:8px;"></i>Sex Distribution of SK Officials</h3>
                    <div class="dash-sex-chart-filters">
                        <select id="sexDistributionTypeFilter" class="kk-barangay-select" aria-label="Filter by group">
                            <option value="all">All</option>
                            <option value="officials" selected>SK Officials</option>
                            <option value="kabataan">Kabataan</option>
                        </select>
                        <select id="sexDistributionSexFilter" class="kk-barangay-select" aria-label="Filter by sex">
                            <option value="all" selected>All</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                        </select>
                    </div>
                </div>
                <div class="card-body chart-body dash-sex-chart-body">
                    <canvas id="sexChart"></canvas>
                </div>
            </div>
        </div>

        {{-- ── ROW 2: Federation Officers + Recent Audit Activity ── --}}
        <div class="dash-row dash-row--balanced">
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
                <div class="card-body top-barangays-body">
                    @forelse ($topBarangays as $row)
                        <a href="{{ route('kabataan-monitoring') }}" class="top-barangay-item" style="text-decoration:none;cursor:pointer;">
                            <div class="top-barangay-rank">{{ $row['rank'] }}</div>
                            <div class="top-barangay-info">
                                <div class="top-barangay-name">{{ $row['barangay'] }}</div>
                                <div class="top-barangay-count">{{ number_format($row['count']) }} youth registered</div>
                            </div>
                            <div class="top-barangay-bar-wrap">
                                @php
                                    $maxCount = max(1, $topBarangays[0]['count'] ?? 1);
                                    $width = round(($row['count'] / $maxCount) * 100);
                                @endphp
                                <div class="top-barangay-bar" style="width: {{ $width }}%;"></div>
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
    $kkProfilingJsVersion = @filemtime(app_path('Modules/Dashboard/assets/js/kkprofilingchart.js')) ?: time();
    $dashAuditJsVersion = @filemtime(app_path('Modules/Dashboard/assets/js/dashboard-audit.js')) ?: time();
    $dashActivityJsVersion = @filemtime(app_path('Modules/Dashboard/assets/js/dashboard-activity.js')) ?: time();
@endphp
<script>
    window.__KK_BARANGAYS__ = @json($kkBarangayOptions);
    window.__KK_PROFILING_DATA_URL__ = @json(route('dashboard.kk-profiling-data'));
    window.__DASHBOARD_FEED__ = {
        recent_activity: @json($recentActivity ?? []),
        upcoming_events: @json($upcomingEvents ?? []),
        today_reminder: @json($todayReminder ?? null),
        activities_url: @json(route('dashboard.recent-activities')),
    };
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="{{ url('/modules/dashboard/js/kkprofilingchart.js') }}?v={{ $kkProfilingJsVersion }}"></script>
<script src="{{ url('/modules/dashboard/js/dashboard-activity.js') }}?v={{ $dashActivityJsVersion }}"></script>
<script src="{{ url('/modules/dashboard/js/dashboard-audit.js') }}?v={{ $dashAuditJsVersion }}"></script>
<script>
        const fedBlue = '#213F99', fedRed = '#d0242b';
        @php
            $sexDistributionOfficialsData = $sexDistributionOfficials ?? ['labels' => ['Male', 'Female'], 'values' => [0, 0]];
            $sexDistributionKabataanData = $sexDistribution ?? ['labels' => ['Male', 'Female'], 'values' => [0, 0]];
        @endphp
        const sexDistributionOfficials = @json($sexDistributionOfficialsData);
        const sexDistributionKabataan = @json($sexDistributionKabataanData);

        function combineSexDistribution(first, second) {
            const labels = ['Male', 'Female'];
            const values = labels.map(function (label, index) {
                return Number(first.values?.[index] || 0) + Number(second.values?.[index] || 0);
            });
            return { labels: labels, values: values };
        }

        const sexDistributionAll = combineSexDistribution(sexDistributionOfficials, sexDistributionKabataan);

        function getSexDistributionByType(type) {
            if (type === 'kabataan') {
                return sexDistributionKabataan;
            }
            if (type === 'all') {
                return sexDistributionAll;
            }
            return sexDistributionOfficials;
        }

        function applySexFilter(source, sexFilter) {
            const labels = source.labels || ['Male', 'Female'];
            const values = source.values || [0, 0];
            if (sexFilter === 'male') {
                return { labels: ['Male'], values: [values[0] || 0], colors: [fedBlue] };
            }
            if (sexFilter === 'female') {
                return { labels: ['Female'], values: [values[1] || 0], colors: [fedRed] };
            }
            return { labels: labels, values: values, colors: sexChartColors };
        }

        function updateSexChart() {
            const typeFilter = document.getElementById('sexDistributionTypeFilter')?.value || 'officials';
            const sexFilter = document.getElementById('sexDistributionSexFilter')?.value || 'all';
            const source = getSexDistributionByType(typeFilter);
            const filtered = applySexFilter(source, sexFilter);
            sexChart.data.labels = filtered.labels;
            sexChart.data.datasets[0].data = filtered.values;
            sexChart.data.datasets[0].backgroundColor = filtered.colors || sexChartColors;
            sexChart.update();
        }

        let sexChartLabels = sexDistributionOfficials.labels || ['Male', 'Female'];
        let sexChartValues = sexDistributionOfficials.values || [0, 0];
        const sexChartColors = [fedBlue, fedRed];

        const sexChart = new Chart(document.getElementById('sexChart'), {
            type: 'pie',
            data: {
                labels: sexChartLabels,
                datasets: [{
                    data: sexChartValues,
                    backgroundColor: sexChartColors,
                    borderWidth: 2,
                    borderColor: '#fff',
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { font: { family: 'Inter', size: 13 }, padding: 16 },
                    },
                },
            },
        });

        document.getElementById('sexDistributionTypeFilter')?.addEventListener('change', updateSexChart);
        document.getElementById('sexDistributionSexFilter')?.addEventListener('change', updateSexChart);
</script>
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
