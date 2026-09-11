@php
    $abyipStats = $abyipOverview['stats'] ?? [];
    $abyipRecent = $abyipOverview['recent'] ?? [];
    $abyipYear = (int) ($abyipStats['fiscal_year'] ?? now()->year);
@endphp

<div class="content-card dash-kk-chart-card dash-abyip-card" id="dash-abyip-section">
    <div class="card-header kk-chart-section-header">
        <div class="kk-chart-heading">
            <h3>ABYIP Program Submissions</h3>
            <p class="kk-chart-subtitle" id="dashAbyipSubtitle">Barangays that submitted ABYIP — {{ $abyipStats['fiscal_year_label'] ?? ('CY '.$abyipYear) }}</p>
        </div>
        <div class="kk-chart-header-actions">
            <a href="{{ route('barangay-monitoring') }}" class="dash-abyip-view-all" data-no-loading>
                View All <i class="fas fa-arrow-right" aria-hidden="true"></i>
            </a>
        </div>
    </div>
    <div class="card-body dash-abyip-body">
        <ul class="dash-abyip-list" id="dashAbyipList" aria-label="ABYIP submissions" @if(count($abyipRecent) === 0) hidden @endif>
            @foreach($abyipRecent as $row)
                @php
                    $statusClass = match ($row['status'] ?? 'pending') {
                        'approved' => 'is-approved',
                        'rejected' => 'is-rejected',
                        default => 'is-pending',
                    };
                    $statusLabel = match ($row['status'] ?? 'pending') {
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        default => 'Pending',
                    };
                    $detailUrl = $row['detail_url'] ?? (
                        ! empty($row['slug'])
                            ? route('barangay-monitoring.show', ['barangay' => $row['slug']])
                            : route('barangay-monitoring')
                    );
                @endphp
                <li>
                    <a href="{{ $detailUrl }}" class="dash-abyip-item" data-no-loading>
                        <span class="dash-abyip-item-barangay">{{ $row['barangay'] }}</span>
                        <span class="dash-abyip-item-date">{{ $row['date_submitted'] ?? '—' }}</span>
                        <span class="dash-abyip-status {{ $statusClass }}">{{ $statusLabel }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
        <p class="dash-empty-note" id="dashAbyipEmpty" @if(count($abyipRecent) > 0) hidden @endif>
            No ABYIP submissions yet for {{ $abyipStats['fiscal_year_label'] ?? ('CY '.$abyipYear) }}.
        </p>
    </div>
</div>
