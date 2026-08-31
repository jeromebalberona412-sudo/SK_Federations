@extends('layout::app')

@section('title', 'Deleted SK Officials')

@php
    $cssVersion = @filemtime(app_path('Modules/Archive_Management/assets/css/deleted-sk-officials.css')) ?: time();
    $jsVersion = @filemtime(app_path('Modules/Archive_Management/assets/js/deleted-sk-officials.js')) ?: time();
@endphp

@push('styles')
    <link rel="stylesheet" href="{{ url('/modules/archive-management/css/archive-row-actions.css') }}?v={{ $cssVersion }}">
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
            <div class="dso-view-controls modal-window-controls">
                <button type="button" class="modal-toggle-btn" id="dsoViewMaximizeBtn" aria-label="Fullscreen" title="Fullscreen">□</button>
                <button type="button" class="modal-toggle-btn" id="dsoViewRestoreBtn" hidden aria-label="Restore down" title="Restore down">⧉</button>
                <button type="button" class="dso-view-close modal-win-btn modal-win-btn-close" id="dsoViewClose" title="Close" aria-label="Close">&times;</button>
            </div>
        </div>
        <div class="dso-view-body" id="dsoViewBody"></div>
    </div>
</div>

<div id="arFullnameSortMenu" class="ar-sort-menu" hidden role="menu" aria-label="Sort full name"></div>

@endsection

@push('scripts')
    <script src="{{ url('/modules/archive-management/js/archive-fullname-sort.js') }}?v={{ $jsVersion }}"></script>
    <script src="{{ url('/modules/archive-management/js/archive-row-actions.js') }}?v={{ $jsVersion }}"></script>
    <script src="{{ url('/modules/archive-management/js/deleted-sk-officials.js') }}?v={{ $jsVersion }}"></script>
@endpush
