@extends('layout::app')

@section('title', 'Audit Logs')

@push('styles')
    @vite(['app/Modules/AuditLog/assets/css/auditlogs.css'])
@endpush

@section('content')
<div id="mainContent" class="auditlog-page">
<div id="auditLogApp" class="auditlog-container" data-audit-routes='@json($routes)' data-date-bounds='@json($filterOptions['date_bounds'] ?? ['min' => '', 'max' => ''])'>
    <div class="page-header-modern-with-button">
        <div class="page-header-top">
            <h1 class="page-title-modern">Audit Logs</h1>
            <p class="page-subtitle-modern">Centralized activity trail across Admin, Federation, Officials, and Kabataan portals</p>
        </div>
    </div>

    <div class="audit-stats-grid" aria-label="Audit log totals">
        <div class="stat-card stat-card-azure">
            <div class="stat-card-top">
                <span class="stat-card-value">{{ number_format($stats['total_logs'] ?? 0) }}</span>
                <div class="stat-card-icon stat-icon-azure" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>
                </div>
            </div>
            <span class="stat-card-label">Total Audit Logs</span>
        </div>
        <div class="stat-card stat-card-emerald">
            <div class="stat-card-top">
                <span class="stat-card-value">{{ number_format($stats['month_activities'] ?? 0) }}</span>
                <div class="stat-card-icon stat-icon-emerald" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                </div>
            </div>
            <span class="stat-card-label">This Month</span>
        </div>
        <div class="stat-card stat-card-amber">
            <div class="stat-card-top">
                <span class="stat-card-value">{{ number_format($stats['week_activities'] ?? 0) }}</span>
                <div class="stat-card-icon stat-icon-amber" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v6l4 2"/></svg>
                </div>
            </div>
            <span class="stat-card-label">This Week</span>
        </div>
        <div class="stat-card stat-card-slate">
            <div class="stat-card-top">
                <span class="stat-card-value">{{ number_format($stats['today_activities'] ?? 0) }}</span>
                <div class="stat-card-icon stat-icon-red" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 8v4l3 2"/><circle cx="12" cy="12" r="9"/></svg>
                </div>
            </div>
            <span class="stat-card-label">This Day</span>
        </div>
    </div>

    <div class="page-header-filters">
        <form class="audit-filter-form" id="auditFilterForm" novalidate>
            <div class="audit-filter-grid">
                <div class="audit-filter-left">
                    <div class="filter-dropdown-container audit-filter-date">
                        <input type="date" id="auditDateFrom" class="filter-dropdown audit-date-input" aria-label="Date from" @if(!empty($filterOptions['date_bounds']['min'])) min="{{ $filterOptions['date_bounds']['min'] }}" @endif @if(!empty($filterOptions['date_bounds']['max'])) max="{{ $filterOptions['date_bounds']['max'] }}" @endif>
                    </div>
                    <div class="filter-dropdown-container audit-filter-date">
                        <input type="date" id="auditDateTo" class="filter-dropdown audit-date-input" aria-label="Date to" @if(!empty($filterOptions['date_bounds']['min'])) min="{{ $filterOptions['date_bounds']['min'] }}" @endif @if(!empty($filterOptions['date_bounds']['max'])) max="{{ $filterOptions['date_bounds']['max'] }}" @endif>
                    </div>
                    <div class="filter-dropdown-container">
                        <select id="auditRole" class="filter-dropdown form-select" aria-label="Filter by role">
                            <option value="">All Roles</option>
                            @foreach ($filterOptions['roles'] as $roleOption)
                                <option value="{{ $roleOption['value'] }}">{{ $roleOption['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-dropdown-container">
                        <select id="auditBarangay" class="filter-dropdown form-select" aria-label="Filter by barangay">
                            <option value="">All Barangays</option>
                            @foreach ($filterOptions['barangays'] as $barangayOption)
                                <option value="{{ $barangayOption['id'] }}">{{ $barangayOption['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-dropdown-container">
                        <select id="auditEventType" class="filter-dropdown form-select" aria-label="Filter by event type">
                            <option value="">All Event Types</option>
                            @foreach ($filterOptions['event_types'] as $eventType)
                                <option value="{{ $eventType }}">{{ $eventType }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="search-container">
                    <div class="search-input-wrap">
                        <input type="search" id="auditSearch" class="search-input form-control" placeholder="Search email, action, entity, IP…" aria-label="Search audit logs">
                        <button type="button" class="search-btn" id="auditSearchBtn" aria-label="Search">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"></circle>
                                <path d="m21 21-4.35-4.35"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="audit-table-card" aria-label="Audit logs table">
        <div class="audit-table-wrap" id="auditTableWrap">
            <div class="table-responsive audit-table-responsive">
                <table class="audit-table" id="auditLogsTable">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Event Type</th>
                            <th>IP Address</th>
                            <th class="audit-col-actions">View</th>
                        </tr>
                    </thead>
                    <tbody id="auditLogsTableBody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="audit-page-footer pagination-footer" aria-label="Table pagination">
    <div class="pagination-footer-nav">
        <button type="button" class="pagination-arrow" id="auditPrevBtn" disabled aria-label="Previous page">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 18l-6-6 6-6"/></svg>
        </button>
        <span class="pagination-page-label">Page</span>
        <input type="number" class="pagination-page-input" id="auditPageInput" value="1" min="1" aria-label="Current page">
        <span class="pagination-page-of">of <span id="auditTotalPages">1</span></span>
        <button type="button" class="pagination-arrow" id="auditNextBtn" disabled aria-label="Next page">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 18l6-6-6-6"/></svg>
        </button>
    </div>
    <div class="pagination-footer-right">
        <select id="auditPerPage" class="pagination-rows-select" aria-label="Rows per page">
            <option value="100" selected>100 rows</option>
            <option value="500">500 rows</option>
            <option value="1000">1000 rows</option>
        </select>
        <span class="pagination-record-count" id="auditPaginationInfo">0 records</span>
    </div>
</div>
</div>

<div class="audit-view-modal" id="auditDetailsModal" hidden>
    <div class="audit-view-modal-backdrop" data-close-modal></div>
    <div class="audit-view-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="auditModalTitle">
        <div class="audit-view-modal-header">
            <div>
                <h3 id="auditModalTitle">Audit Log Details</h3>
                <p id="auditModalSubtitle"></p>
            </div>
            <button type="button" class="audit-view-modal-close" data-close-modal aria-label="Close details">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="audit-view-modal-body">
            <div class="audit-view-detail-grid" id="auditDetailGrid"></div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    @vite(['app/Modules/AuditLog/assets/js/auditlogs.js'])
@endpush
