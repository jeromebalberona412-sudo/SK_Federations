@extends('layout::app')

@section('title', 'Archived SK Officials Records')

@php
    $cssVersion = @filemtime(app_path('Modules/Archive_Management/assets/css/SK_officials.css')) ?: time();
    $jsVersion = @filemtime(app_path('Modules/Archive_Management/assets/js/SK_officials.js')) ?: time();
@endphp

@push('styles')
    <link rel="stylesheet" href="{{ url('/modules/archive-management/css/SK_officials.css') }}?v={{ $cssVersion }}">
@endpush

@section('content')

<div id="mainContent" class="main-content-modern aroff-page container-fluid">

    <div class="aroff-page-header row">
        <div class="aroff-header-left col-md-6">
            <h1 class="aroff-page-title">Archived SK Officials Records</h1>
            <p class="aroff-page-subtitle">Completed term records — read-only historical archive.</p>
        </div>
        <div class="aroff-header-right col-md-6">
            <select id="aroffYearFilter" class="aroff-year-filter form-select">
                <option value="all">All Years</option>
            </select>
            <select id="aroffTermFilter" class="aroff-term-filter form-select">
                <option value="all">All Terms</option>
            </select>
            <input type="text" id="aroffSearch" class="aroff-search-input form-control" placeholder="Search by name or position…">
        </div>
    </div>

    <!-- Table Card -->
    <div class="aroff-table-card">
        <div class="aroff-table-wrapper">
            <table class="aroff-table">
                <thead>
                    <tr>
                        <th>Full Name<div class="table-col-hint">LN, FN, MN, Suffix</div></th>
                        <th>Position</th>
                        <th>Term</th>
                        <th>Status</th>
                        <th class="aroff-col-actions">Actions</th>
                    </tr>
                </thead>
                <tbody id="aroffTableBody"></tbody>
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
            <span class="pagination-record-count" id="aroffPaginationInfo">0 records</span>
        </div>
    </div>

</div>

<!-- View Modal -->
<div class="aroff-modal-backdrop" id="aroffViewModal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="aroffViewModalTitle">
    <div class="aroff-modal-box aroff-view-modal-box" id="aroffViewModalBox">
        <div class="aroff-modal-header aroff-view-modal-header">
            <h2 class="aroff-modal-title" id="aroffViewModalTitle">View Details</h2>
            <div class="aroff-view-controls">
                <button type="button" class="aroff-view-toggle" id="aroffViewToggle" aria-label="Maximize">□</button>
                <button type="button" class="aroff-view-close" id="aroffViewClose" aria-label="Close">&times;</button>
            </div>
        </div>
        <div class="aroff-view-body" id="aroffViewBody"></div>
        <div class="aroff-modal-footer">
            <button type="button" class="aroff-page-btn" id="aroffViewCloseFooter" aria-label="Close">&times;</button>
        </div>
    </div>
</div>

@endsection

@push('scripts')
    <script src="{{ url('/modules/archive-management/js/SK_officials.js') }}?v={{ $jsVersion }}"></script>
@endpush
