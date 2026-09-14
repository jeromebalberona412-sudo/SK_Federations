@extends('layout::app')

@section('title', 'Archived SK Federation Records')

@push('styles')
    <link rel="stylesheet" href="{{ url('/shared/css/archive-row-actions.css') }}?v={{ @filemtime(public_path('shared/css/archive-row-actions.css')) ?: time() }}">
    @vite([
        'app/Modules/SK_Federation_Records/assets/css/SK_federation.css',
    ])
@endpush

@section('content')

<div id="mainContent" class="main-content-modern arfed-page container-fluid">

    <div class="arfed-page-header">
        <div class="arfed-header-left">
            <h1 class="arfed-page-title">Archived SK Federation Records</h1>
            <p class="arfed-page-subtitle">Previous completed federation officer terms — read-only historical archive.</p>
        </div>
    </div>

    <div class="arfed-filters" role="search" aria-label="Archive filters">
        <div class="arfed-filter-field arfed-filter-field--search">
            <label for="arfedSearch">Search</label>
            <input type="text" id="arfedSearch" class="arfed-search-input form-control" placeholder="Search by name or position…">
        </div>
        <div class="arfed-filter-field">
            <label for="arfedBarangayFilter">Barangay</label>
            <select id="arfedBarangayFilter" class="arfed-year-filter form-select" aria-label="Filter by barangay">
                <option value="all">All Barangays</option>
                @foreach(($initialFilters['barangays'] ?? []) as $barangay)
                    @php
                        $value = is_array($barangay) ? ($barangay['value'] ?? '') : $barangay;
                        $label = is_array($barangay) ? ($barangay['label'] ?? $value) : $barangay;
                    @endphp
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="arfed-filter-field">
            <label for="arfedPositionFilter">Position</label>
            <select id="arfedPositionFilter" class="arfed-year-filter form-select" aria-label="Filter by position">
                <option value="all">All Positions</option>
                @foreach(($initialFilters['positions'] ?? []) as $position)
                    <option value="{{ is_array($position) ? ($position['value'] ?? '') : $position }}">{{ is_array($position) ? ($position['label'] ?? '') : $position }}</option>
                @endforeach
            </select>
        </div>
        <div class="arfed-filter-field">
            <label for="arfedTermFilter">Term</label>
            <select id="arfedTermFilter" class="arfed-term-filter form-select" aria-label="Filter by term">
                <option value="all">All Terms</option>
                @foreach(($initialFilters['terms'] ?? []) as $term)
                    <option value="{{ is_array($term) ? ($term['value'] ?? '') : $term }}">{{ is_array($term) ? ($term['label'] ?? '') : $term }}</option>
                @endforeach
            </select>
        </div>
        <div class="arfed-filter-field arfed-filter-field--actions">
            <label>&nbsp;</label>
            <button type="button" id="arfedClearFiltersBtn" class="arfed-clear-filters-btn">Clear Filters</button>
        </div>
    </div>

    <div class="arfed-table-card">
        <div class="arfed-table-wrapper">
            <table class="arfed-table">
                <thead>
                    <tr>
                        <th class="ar-th-sortable" data-sort-key="fullname" data-sort-type="text" aria-sort="none">
                            <button type="button" class="ar-sort-btn ar-sort-btn--fullname" aria-haspopup="menu" aria-expanded="false">
                                <span class="table-fullname-label">
                                    Full Name
                                    <span class="table-col-hint">LN, FN, MN, Suffix</span>
                                </span>
                                <span class="ar-sort-icon" aria-hidden="true"></span>
                            </button>
                        </th>
                        <th>Federation Position</th>
                        <th>Term Start</th>
                        <th>Term End</th>
                        <th>Status</th>
                        <th>Archived Date</th>
                        <th class="arfed-col-actions">Actions</th>
                    </tr>
                </thead>
                <tbody id="arfedTableBody">
                    @forelse(($initialRecords ?? []) as $record)
                        <tr>
                            <td class="arfed-name-cell">{{ trim(collect([$record['lastName'] ?? '', $record['firstName'] ?? '', $record['middleName'] ?? '', $record['suffix'] ?? ''])->filter()->implode(', ')) }}</td>
                            <td style="text-align:center;">{{ $record['federationPosition'] ?? $record['position'] ?? '—' }}</td>
                            <td style="text-align:center;">{{ $record['termStart'] ?? '—' }}</td>
                            <td style="text-align:center;">{{ $record['termEnd'] ?? '—' }}</td>
                            <td style="text-align:center;"><span class="arfed-completed-badge">{{ $record['status'] ?? $record['termStatus'] ?? 'EXPIRED' }}</span></td>
                            <td style="text-align:center;">{{ $record['archivedAt'] ?? '—' }}</td>
                            <td class="arfed-col-actions">
                                <div class="arfed-action-btns">
                                    <button type="button" class="arfed-btn-view" data-arfed-view="{{ $record['id'] }}" aria-label="View record">View</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr class="arfed-empty-row"><td colspan="7">No previous-term records found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="arfed-page-footer pagination-footer" aria-label="Table pagination">
        <div class="pagination-footer-nav">
            <button type="button" class="pagination-arrow" id="arfedPrevBtn" disabled aria-label="Previous page">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 18l-6-6 6-6"/></svg>
            </button>
            <span class="pagination-page-label">Page</span>
            <input type="number" class="pagination-page-input" id="arfedPageInput" value="1" min="1" aria-label="Current page">
            <span class="pagination-page-of">of <span id="arfedTotalPages">1</span></span>
            <button type="button" class="pagination-arrow" id="arfedNextBtn" disabled aria-label="Next page">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 18l6-6-6-6"/></svg>
            </button>
        </div>
        <div class="pagination-footer-right">
            <select id="arfedRowsPerPageSelect" class="pagination-rows-select" aria-label="Rows per page">
                <option value="10">10 rows</option>
                <option value="50">50 rows</option>
                <option value="100">100 rows</option>
            </select>
            <span class="pagination-record-count" id="arfedPaginationInfo">{{ count($initialRecords ?? []) }} records</span>
        </div>
    </div>

</div>

<div class="arfed-modal-backdrop" id="arfedViewModal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="arfedViewModalTitle">
    <div class="arfed-modal-box arfed-view-modal-box" id="arfedViewModalBox">
        <div class="arfed-modal-header arfed-view-modal-header">
            <h2 class="arfed-modal-title" id="arfedViewModalTitle">Archived SK Federation Details</h2>
            <div class="arfed-view-controls modal-window-controls">
                <button type="button" class="modal-toggle-btn" id="arfedViewMaximizeBtn" aria-label="Fullscreen" title="Fullscreen">□</button>
                <button type="button" class="modal-toggle-btn" id="arfedViewRestoreBtn" hidden aria-label="Restore down" title="Restore down">⧉</button>
                <button type="button" class="arfed-view-close modal-win-btn" id="arfedViewClose" aria-label="Close">&times;</button>
            </div>
        </div>
        <div class="arfed-view-body" id="arfedViewBody"></div>
    </div>
</div>

<div id="arFullnameSortMenu" class="ar-sort-menu" hidden role="menu" aria-label="Sort full name"></div>

@endsection

@push('scripts')
    <script>
        window.__SK_FEDERATION_RECORDS_CONFIG__ = {
            dataUrl: @json(route('archived.sk-federation-records.data')),
            restoreUrl: @json(url('/manage-archive/sk-federation-records')),
        };
        window.__INITIAL_SK_FEDERATION_RECORDS__ = @json($initialRecords ?? []);
        window.__INITIAL_SK_FEDERATION_FILTERS__ = @json($initialFilters ?? []);
    </script>
    <script src="{{ url('/shared/js/archive-row-actions.js') }}?v={{ @filemtime(public_path('shared/js/archive-row-actions.js')) ?: time() }}"></script>
    <script src="{{ url('/shared/js/archive-fullname-sort.js') }}?v={{ @filemtime(public_path('shared/js/archive-fullname-sort.js')) ?: time() }}"></script>
    @vite([
        'app/Modules/SK_Federation_Records/assets/js/SK_federation.js',
    ])
@endpush
