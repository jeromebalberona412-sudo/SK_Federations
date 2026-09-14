@extends('layout::app')

@section('title', 'Archived SK Officials Records')

@push('styles')
    <link rel="stylesheet" href="{{ url('/shared/css/archive-row-actions.css') }}?v={{ @filemtime(public_path('shared/css/archive-row-actions.css')) ?: time() }}">
    @vite([
        'app/Modules/SK_Officials_Records/assets/css/SK_officials.css',
    ])
@endpush

@section('content')

<div id="mainContent" class="main-content-modern aroff-page container-fluid">

    <div class="aroff-page-header">
        <div class="aroff-header-left">
            <h1 class="aroff-page-title">Archived SK Officials Records</h1>
            <p class="aroff-page-subtitle">Previous completed SK Official terms — read-only historical archive.</p>
        </div>
    </div>

    <div class="aroff-filters" role="search" aria-label="Archive filters">
        <div class="aroff-filter-field aroff-filter-field--search">
            <label for="aroffSearch">Search</label>
            <input type="text" id="aroffSearch" class="aroff-search-input form-control" placeholder="Search by name or position…">
        </div>
        <div class="aroff-filter-field">
            <label for="aroffBarangayFilter">Barangay</label>
            <select id="aroffBarangayFilter" class="aroff-year-filter form-select" aria-label="Filter by barangay">
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
        <div class="aroff-filter-field">
            <label for="aroffPositionFilter">Position</label>
            <select id="aroffPositionFilter" class="aroff-year-filter form-select" aria-label="Filter by position">
                <option value="all">All Positions</option>
                @foreach(($initialFilters['positions'] ?? []) as $position)
                    <option value="{{ is_array($position) ? ($position['value'] ?? '') : $position }}">{{ is_array($position) ? ($position['label'] ?? '') : $position }}</option>
                @endforeach
            </select>
        </div>
        <div class="aroff-filter-field">
            <label for="aroffTermFilter">Term</label>
            <select id="aroffTermFilter" class="aroff-term-filter form-select" aria-label="Filter by term">
                <option value="all">All Terms</option>
                @foreach(($initialFilters['terms'] ?? []) as $term)
                    <option value="{{ is_array($term) ? ($term['value'] ?? '') : $term }}">{{ is_array($term) ? ($term['label'] ?? '') : $term }}</option>
                @endforeach
            </select>
        </div>
        <div class="aroff-filter-field aroff-filter-field--actions">
            <label>&nbsp;</label>
            <button type="button" id="aroffClearFiltersBtn" class="aroff-clear-filters-btn">Clear Filters</button>
        </div>
    </div>

    <div class="aroff-table-card">
        <div class="aroff-table-wrapper">
            <table class="aroff-table">
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
                        <th>Position</th>
                        <th>Barangay</th>
                        <th>Term Start</th>
                        <th>Term End</th>
                        <th>Status</th>
                        <th>Archived Date</th>
                        <th class="aroff-col-actions">Actions</th>
                    </tr>
                </thead>
                <tbody id="aroffTableBody">
                    @forelse(($initialRecords ?? []) as $record)
                        <tr>
                            <td class="aroff-name-cell">{{ trim(collect([$record['lastName'] ?? '', $record['firstName'] ?? '', $record['middleName'] ?? '', $record['suffix'] ?? ''])->filter()->implode(', ')) }}</td>
                            <td style="text-align:center;">{{ $record['position'] ?? '—' }}</td>
                            <td style="text-align:center;">{{ $record['barangay'] ?? '—' }}</td>
                            <td style="text-align:center;">{{ $record['termStart'] ?? '—' }}</td>
                            <td style="text-align:center;">{{ $record['termEnd'] ?? '—' }}</td>
                            <td style="text-align:center;"><span class="aroff-completed-badge">{{ $record['status'] ?? 'EXPIRED' }}</span></td>
                            <td style="text-align:center;">{{ $record['archivedAt'] ?? '—' }}</td>
                            <td class="aroff-col-actions">
                                <div class="aroff-action-btns">
                                    <button type="button" class="aroff-btn-view" data-aroff-view="{{ $record['id'] }}" aria-label="View record">View</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr class="aroff-empty-row"><td colspan="8">No previous-term records found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="aroff-page-footer pagination-footer" aria-label="Table pagination">
        <div class="pagination-footer-nav">
            <button type="button" class="pagination-arrow" id="aroffPrevBtn" disabled aria-label="Previous page">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 18l-6-6 6-6"/></svg>
            </button>
            <span class="pagination-page-label">Page</span>
            <input type="number" class="pagination-page-input" id="aroffPageInput" value="1" min="1" aria-label="Current page">
            <span class="pagination-page-of">of <span id="aroffTotalPages">1</span></span>
            <button type="button" class="pagination-arrow" id="aroffNextBtn" disabled aria-label="Next page">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 18l6-6-6-6"/></svg>
            </button>
        </div>
        <div class="pagination-footer-right">
            <select id="aroffRowsPerPageSelect" class="pagination-rows-select" aria-label="Rows per page">
                <option value="10">10 rows</option>
                <option value="50">50 rows</option>
                <option value="100">100 rows</option>
            </select>
            <span class="pagination-record-count" id="aroffPaginationInfo">{{ count($initialRecords ?? []) }} records</span>
        </div>
    </div>

</div>

<div class="aroff-modal-backdrop" id="aroffViewModal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="aroffViewModalTitle">
    <div class="aroff-modal-box aroff-view-modal-box" id="aroffViewModalBox">
        <div class="aroff-modal-header aroff-view-modal-header">
            <h2 class="aroff-modal-title" id="aroffViewModalTitle">Archived SK Official Details</h2>
            <div class="aroff-view-controls modal-window-controls">
                <button type="button" class="modal-toggle-btn" id="aroffViewMaximizeBtn" aria-label="Fullscreen" title="Fullscreen">□</button>
                <button type="button" class="modal-toggle-btn" id="aroffViewRestoreBtn" hidden aria-label="Restore down" title="Restore down">⧉</button>
                <button type="button" class="aroff-view-close modal-win-btn" id="aroffViewClose" aria-label="Close">&times;</button>
            </div>
        </div>
        <div class="aroff-view-body" id="aroffViewBody"></div>
    </div>
</div>

<div id="arFullnameSortMenu" class="ar-sort-menu" hidden role="menu" aria-label="Sort full name"></div>

@endsection

@push('scripts')
    <script>
        window.__SK_OFFICIALS_RECORDS_CONFIG__ = {
            dataUrl: @json(route('archived.sk-officials-records.data')),
            restoreUrl: @json(url('/manage-archive/sk-officials-records')),
        };
        window.__INITIAL_SK_OFFICIALS_RECORDS__ = @json($initialRecords ?? []);
        window.__INITIAL_SK_OFFICIALS_FILTERS__ = @json($initialFilters ?? []);
    </script>
    <script src="{{ url('/shared/js/archive-row-actions.js') }}?v={{ @filemtime(public_path('shared/js/archive-row-actions.js')) ?: time() }}"></script>
    <script src="{{ url('/shared/js/archive-fullname-sort.js') }}?v={{ @filemtime(public_path('shared/js/archive-fullname-sort.js')) ?: time() }}"></script>
    @vite([
        'app/Modules/SK_Officials_Records/assets/js/SK_officials.js',
    ])
@endpush
