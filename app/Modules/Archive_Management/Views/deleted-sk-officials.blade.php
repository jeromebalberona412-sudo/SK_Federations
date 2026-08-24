@extends('layout::app')

@section('title', 'Deleted SK Officials')

@php
    $cssVersion = @filemtime(app_path('Modules/Archive_Management/assets/css/deleted-sk-officials.css')) ?: time();
    $jsVersion = @filemtime(app_path('Modules/Archive_Management/assets/js/deleted-sk-officials.js')) ?: time();
@endphp

@push('styles')
    <link rel="stylesheet" href="{{ url('/modules/archive-management/css/deleted-sk-officials.css') }}?v={{ $cssVersion }}">
@endpush

@section('content')

<div id="mainContent" class="main-content-modern deleted-sk-off-page container-fluid">

    <div class="dso-page-header row">
        <div class="dso-header-left col-md-6">
            <h1 class="dso-page-title">Deleted SK Officials</h1>
            <p class="dso-page-subtitle">Records removed from the SK Officials list.</p>
        </div>
        <div class="dso-header-right col-md-6">
            <select id="dsoYearFilter" class="dso-year-filter form-select">
                <option value="all">All Years</option>
            </select>
            <select id="dsoFilterTerm" class="dso-term-filter form-select">
                <option value="">All Terms</option>
            </select>
            <input type="text" id="dsoSearch" class="dso-search-input form-control" placeholder="Search by name or barangay…">
        </div>
    </div>

    <!-- Filter Tabs + Dropdowns -->
    <div class="dso-filter-bar">
        <div class="dso-filter-tabs">
            <button class="dso-tab active" data-filter="all">All Deleted</button>
            <button class="dso-tab" data-filter="today">Deleted Today</button>
            <button class="dso-tab" data-filter="week">This Week</button>
            <button class="dso-tab" data-filter="month">This Month</button>
        </div>
        <div class="dso-filter-dropdowns">
            <select id="dsoFilterPosition" class="dso-filter-select">
                <option value="">All Positions</option>
            </select>
            <select id="dsoFilterBarangay" class="dso-filter-select">
                <option value="">All Barangays</option>
            </select>
        </div>
    </div>

    <!-- Table -->
    <div class="dso-table-card">
        <div class="dso-table-wrapper">
            <table class="dso-table">
                <thead>
                    <tr>
                        <th>Full Name<div class="table-col-hint">LN, FN, MN, Suffix</div></th>
                        <th>Position</th>
                        <th>Barangay</th>
                        <th>Municipality</th>
                        <th>Term</th>
                        <th>Date Deleted</th>
                        <th>Time Deleted</th>
                        <th class="dso-col-actions">Actions</th>
                    </tr>
                </thead>
                <tbody id="dsoTableBody"></tbody>
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
            <span class="pagination-page-of">of <span id="dsoTotalPages">1</span></span>
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
            <span class="pagination-record-count" id="dsoPaginationInfo">0 records</span>
        </div>
    </div>

</div>

<!-- Restore Confirmation Modal -->
<div class="dso-modal-backdrop" id="dsoRestoreModal" style="display:none;">
    <div class="dso-modal-box">
        <div class="dso-modal-header">
            <h2 class="dso-modal-title">Restore Record</h2>
        </div>
        <div class="dso-modal-body">
            <p class="dso-modal-message">Restore this record back to the SK Officials list?</p>
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
            <div class="dso-view-controls">
                <button type="button" class="dso-view-toggle modal-win-btn modal-win-btn-maximize" id="dsoViewToggle" title="Maximize" aria-label="Maximize">
                    <svg id="dsoViewToggleIcon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"></path>
                    </svg>
                </button>
                <button type="button" class="dso-view-close modal-win-btn modal-win-btn-close" id="dsoViewClose" title="Close" aria-label="Close">&times;</button>
            </div>
        </div>
        <div class="dso-view-body" id="dsoViewBody"></div>
        <div class="dso-modal-footer">
            <button type="button" class="dso-btn-cancel" id="dsoViewCloseFooter">Close</button>
        </div>
    </div>
</div>

@endsection

@push('scripts')
    <script src="{{ url('/modules/archive-management/js/deleted-sk-officials.js') }}?v={{ $jsVersion }}"></script>
@endpush
