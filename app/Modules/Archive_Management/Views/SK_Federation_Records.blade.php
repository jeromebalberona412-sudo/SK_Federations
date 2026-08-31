@extends('layout::app')

@section('title', 'Archived SK Federation Records')

@push('styles')
    @vite([
        'app/Modules/Archive_Management/assets/css/archive-row-actions.css',
        'app/Modules/Archive_Management/assets/css/SK_federation.css',
    ])
@endpush

@section('content')

<div id="mainContent" class="main-content-modern arfed-page container-fluid">

    <div class="arfed-page-header row">
        <div class="arfed-header-left col-md-6">
            <h1 class="arfed-page-title">Archived SK Federation Records</h1>
            <p class="arfed-page-subtitle">Completed term records — read-only historical archive.</p>
        </div>
        <div class="arfed-header-right col-md-6">
            <select id="arfedYearFilter" class="arfed-year-filter form-select">
                <option value="all">All Years</option>
            </select>
            <select id="arfedTermFilter" class="arfed-term-filter form-select">
                <option value="all">All Terms</option>
            </select>
            <input type="text" id="arfedSearch" class="arfed-search-input form-control" placeholder="Search by name or position…">
        </div>
    </div>

    <!-- Table Card -->
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
                        <th>Position</th>
                        <th>Term Served</th>
                        <th>Status</th>
                        <th class="arfed-col-actions">Actions</th>
                    </tr>
                </thead>
                <tbody id="arfedTableBody"></tbody>
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
            <span class="pagination-record-count" id="arfedPaginationInfo">0 records</span>
        </div>
    </div>

</div>

<!-- View Modal -->
<div class="arfed-modal-backdrop" id="arfedViewModal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="arfedViewModalTitle">
    <div class="arfed-modal-box arfed-view-modal-box" id="arfedViewModalBox">
        <div class="arfed-modal-header arfed-view-modal-header">
            <h2 class="arfed-modal-title" id="arfedViewModalTitle">View Details</h2>
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
    @vite([
        'app/Modules/Archive_Management/assets/js/archive-fullname-sort.js',
        'app/Modules/Archive_Management/assets/js/archive-row-actions.js',
        'app/Modules/Archive_Management/assets/js/SK_federation.js',
    ])
@endpush
