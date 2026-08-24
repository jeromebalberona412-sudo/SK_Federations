@extends('layout::app')

@section('title', 'Archived SK Federation Records')

@php
    $cssVersion = @filemtime(app_path('Modules/Archive_Management/assets/css/SK_federation.css')) ?: time();
    $jsVersion = @filemtime(app_path('Modules/Archive_Management/assets/js/SK_federation.js')) ?: time();
@endphp

@push('styles')
    <link rel="stylesheet" href="{{ url('/modules/archive-management/css/SK_federation.css') }}?v={{ $cssVersion }}">
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
                        <th>Full Name<div class="table-col-hint">LN, FN, MN, Suffix</div></th>
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
            <div class="arfed-view-controls">
                <button type="button" class="arfed-view-toggle" id="arfedViewToggle" aria-label="Maximize">□</button>
                <button type="button" class="arfed-view-close" id="arfedViewClose" aria-label="Close">&times;</button>
            </div>
        </div>
        <div class="arfed-view-body" id="arfedViewBody"></div>
        <div class="arfed-modal-footer">
            <button type="button" class="arfed-page-btn" id="arfedViewCloseFooter" aria-label="Close">&times;</button>
        </div>
    </div>
</div>

@endsection

@push('scripts')
    <script src="{{ url('/modules/archive-management/js/SK_federation.js') }}?v={{ $jsVersion }}"></script>
@endpush
