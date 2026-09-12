@extends('layout::app')

@section('title', 'Deleted SK Officials')

@push('styles')
    <link rel="stylesheet" href="{{ url('/shared/css/archive-row-actions.css') }}?v={{ @filemtime(public_path('shared/css/archive-row-actions.css')) ?: time() }}">
    @vite([
        'app/Modules/Deleted_SK_Officials/assets/css/deleted-sk-officials.css',
    ])
@endpush

@section('content')

<div id="mainContent" class="main-content-modern deleted-sk-off-page container-fluid">

    <div class="dso-page-header row">
        <div class="dso-header-left col-md-6">
            <h1 class="dso-page-title">Deleted SK Officials</h1>
            <p class="dso-page-subtitle">Deleted SK Official accounts are archived and stored here. Records remain available until restored.</p>
        </div>
        <div class="dso-header-right col-md-6">
            <select id="dsoYearFilter" class="dso-year-filter form-select">
                <option value="all" {{ ($currentYear ?? 'all') === 'all' ? 'selected' : '' }}>All Years</option>
                @foreach(($filterYears ?? []) as $year)
                    <option value="{{ $year }}" {{ (string)($currentYear ?? '') === (string)$year ? 'selected' : '' }}>{{ $year }}</option>
                @endforeach
            </select>
            <input type="text" id="dsoSearch" class="dso-search-input form-control" placeholder="Search by name or barangay…" value="{{ $currentSearch ?? '' }}">
        </div>
    </div>

    <!-- Filter Tabs + Dropdowns -->
    <div class="dso-filter-bar">
        <div class="dso-filter-tabs">
            <button class="dso-tab {{ ($currentFilter ?? 'all') === 'all' ? 'active' : '' }}" data-filter="all">All Deleted</button>
            <button class="dso-tab {{ ($currentFilter ?? '') === 'today' ? 'active' : '' }}" data-filter="today">Deleted Today</button>
            <button class="dso-tab {{ ($currentFilter ?? '') === 'week' ? 'active' : '' }}" data-filter="week">This Week</button>
            <button class="dso-tab {{ ($currentFilter ?? '') === 'month' ? 'active' : '' }}" data-filter="month">This Month</button>
        </div>
        <div class="dso-filter-dropdowns">
            <select id="dsoFilterPosition" class="dso-filter-select">
                <option value="">All Positions</option>
                @foreach(($filterPositions ?? []) as $position)
                    <option value="{{ $position }}" {{ ($currentPosition ?? '') === $position ? 'selected' : '' }}>{{ $position }}</option>
                @endforeach
            </select>
            <select id="dsoFilterBarangay" class="dso-filter-select">
                <option value="">All Barangays</option>
                @foreach(($filterBarangays ?? []) as $barangay)
                    <option value="{{ $barangay }}" {{ ($currentBarangay ?? '') === $barangay ? 'selected' : '' }}>{{ $barangay }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <!-- Table -->
    <div class="dso-table-card">
        <div class="dso-table-wrapper">
            <table class="dso-table">
                <thead>
                    <tr>
                        <th class="ar-th-sortable" data-sort-key="fullname" data-sort-type="text" aria-sort="none">
                            <button type="button" class="ar-sort-btn ar-sort-btn--fullname" id="dsoFullnameSortBtn" aria-haspopup="menu" aria-expanded="false" title="Sort Full Name">
                                <span class="table-fullname-label">
                                    Full Name
                                    <span class="table-col-hint">LN, FN, MN, Suffix</span>
                                </span>
                                <span class="ar-sort-icon" aria-hidden="true"></span>
                            </button>
                        </th>
                        <th>Position</th>
                        <th>Barangay</th>
                        <th>Municipality</th>
                        <th>Term</th>
                        <th>Date Deleted</th>
                        <th>Time Deleted</th>
                        <th>Deleted By</th>
                        <th class="dso-col-actions">Actions</th>
                    </tr>
                </thead>
                <tbody id="dsoTableBody">
                    @forelse($deletedOfficials as $official)
                        @php
                            $o = is_array($official) ? (object) $official : $official;
                            $name = $o->fullName ?? $o->full_name ?? '—';
                            $pos = $o->position ?? '—';
                            $brgy = $o->barangay ?? '—';
                            $muni = $o->municipality ?? 'Santa Cruz';
                            $term = $o->term ?? '—';
                            if ($term === '—') {
                                $tStart = $o->termStart ?? null;
                                $tEnd = $o->termEnd ?? null;
                                if ($tStart && $tEnd && $tStart !== '—' && $tEnd !== '—') {
                                    $term = $tStart . ' — ' . $tEnd;
                                } elseif (!empty($o->term_label)) {
                                    $term = $o->term_label;
                                }
                            }
                            $dDate = $o->deletedDate ?? '—';
                            $dTime = $o->deletedTime ?? '—';
                            $dBy = $o->deleted_by ?? '—';
                            $id = $o->id ?? $o->archive_id ?? '';
                        @endphp
                        <tr data-record-id="{{ $id }}">
                            <td class="dso-name-cell" title="{{ $name }}">{{ $name }}</td>
                            <td title="{{ $pos }}">{{ $pos }}</td>
                            <td title="{{ $brgy }}">{{ $brgy }}</td>
                            <td title="{{ $muni }}">{{ $muni }}</td>
                            <td title="{{ $term }}">{{ $term }}</td>
                            <td title="{{ $dDate }}"><span class="dso-deleted-badge">{{ $dDate }}</span></td>
                            <td title="{{ $dTime }}"><span class="dso-time-badge">{{ $dTime }}</span></td>
                            <td title="{{ $dBy }}">{{ $dBy }}</td>
                            <td class="dso-col-actions col-actions">
                                <div class="ar-row-actions-menu">
                                    <button type="button" class="ar-row-actions-trigger" aria-label="More actions" aria-haspopup="menu" aria-expanded="false">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="2"></circle><circle cx="12" cy="12" r="2"></circle><circle cx="19" cy="12" r="2"></circle></svg>
                                    </button>
                                    <div class="ar-row-actions-dropdown" role="menu" hidden>
                                        <button type="button" class="ar-row-actions-item" data-ar-action="view" data-id="{{ $id }}" role="menuitem"><i class="fas fa-eye" aria-hidden="true"></i><span>View</span></button>
                                        <button type="button" class="ar-row-actions-item" data-ar-action="restore" data-id="{{ $id }}" role="menuitem"><i class="fas fa-rotate-left" aria-hidden="true"></i><span>Restore</span></button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr class="dso-empty-row">
                            <td colspan="9">No deleted SK Officials found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="dso-page-footer pagination-footer" aria-label="Table pagination">
        <div class="pagination-footer-nav">
            <button type="button" class="pagination-arrow" id="dsoPrevBtn" disabled aria-label="Previous page">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 18l-6-6 6-6"/></svg>
            </button>
            <span class="pagination-page-label">Page</span>
            <input type="number" class="pagination-page-input" id="dsoPageInput" value="1" min="1" aria-label="Current page">
            <span class="pagination-page-of">of <span id="dsoTotalPages">{{ max(1, (int) ceil(count($deletedOfficials) / 10)) }}</span></span>
            <button type="button" class="pagination-arrow" id="dsoNextBtn" disabled aria-label="Next page">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 18l6-6-6-6"/></svg>
            </button>
        </div>
        <div class="pagination-footer-right">
            <select id="dsoRowsPerPageSelect" class="pagination-rows-select" aria-label="Rows per page">
                <option value="10">10 rows</option>
                <option value="50">50 rows</option>
                <option value="100">100 rows</option>
            </select>
            <span class="pagination-record-count" id="dsoPaginationInfo">{{ count($deletedOfficials) }} {{ count($deletedOfficials) === 1 ? 'record' : 'records' }}</span>
        </div>
    </div>

</div>

<!-- Restore Confirmation Modal -->
<div class="dso-modal-backdrop" id="dsoRestoreModal" style="display:none;">
    <div class="dso-modal-box">
        <div class="dso-modal-header">
            <h2 class="dso-modal-title">Restore SK Official?</h2>
        </div>
        <div class="dso-modal-body">
            <p class="dso-modal-message">Are you sure you want to restore this SK Official account?</p>
            <p class="dso-modal-name" id="dsoRestoreName"></p>
        </div>
        <div class="dso-modal-footer">
            <button type="button" class="dso-btn-cancel" id="dsoRestoreCancelBtn">Cancel</button>
            <button type="button" class="dso-btn-confirm" id="dsoRestoreConfirmBtn">Restore</button>
        </div>
    </div>
</div>

<!-- View Modal -->
<div class="dso-modal-backdrop" id="dsoViewModal" style="display:none;">
    <div class="dso-modal-box dso-view-modal-box" id="dsoViewModalBox">
        <div class="dso-modal-header dso-view-modal-header">
            <h2 class="dso-modal-title">View Details</h2>
            <div class="dso-view-controls modal-window-controls">
                <button type="button" class="modal-toggle-btn" id="dsoViewMaximizeBtn" aria-label="Fullscreen" title="Fullscreen">□</button>
                <button type="button" class="modal-toggle-btn" id="dsoViewRestoreBtn" hidden aria-label="Restore down" title="Restore down">⧉</button>
                <button type="button" class="dso-view-close modal-win-btn modal-win-btn-close" id="dsoViewClose" title="Close" aria-label="Close">&times;</button>
            </div>
        </div>
        <div class="dso-view-body" id="dsoViewBody"></div>
    </div>
</div>

<div id="dsoFullnameSortMenu" class="dso-sort-menu" hidden role="menu" aria-label="Sort full name"></div>

<script>
    window.__DSO_RETENTION_DAYS__ = {{ (int) ($retentionDays ?? 30) }};
    window.__INITIAL_DELETED_SK_OFFICIALS__ = {!! json_encode($deletedOfficials) !!};
    window.__INITIAL_DSO_FILTERS__ = {!! json_encode([
        'barangays' => $filterBarangays ?? [],
        'positions' => $filterPositions ?? [],
        'years' => $filterYears ?? [],
    ]) !!};
    window.__DSO_CURRENT_FILTERS__ = {!! json_encode([
        'filter' => $currentFilter ?? 'all',
        'year' => $currentYear ?? 'all',
        'barangay' => $currentBarangay ?? '',
        'position' => $currentPosition ?? '',
        'search' => $currentSearch ?? '',
    ]) !!};
</script>

@endsection

@push('scripts')
    <script src="{{ url('/shared/js/archive-row-actions.js') }}?v={{ @filemtime(public_path('shared/js/archive-row-actions.js')) ?: time() }}"></script>
    @vite([
        'app/Modules/Deleted_SK_Officials/assets/js/deleted-sk-officials.js',
    ])
@endpush
