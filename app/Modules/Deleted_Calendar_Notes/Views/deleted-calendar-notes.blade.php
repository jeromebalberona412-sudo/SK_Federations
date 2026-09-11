@extends('layout::app')

@section('title', 'Deleted Calendar Notes')

@push('styles')
    <link rel="stylesheet" href="{{ url('/shared/css/table-page-footer.css') }}?v={{ @filemtime(public_path('shared/css/table-page-footer.css')) ?: time() }}">
    <link rel="stylesheet" href="{{ url('/shared/css/table-row-actions-menu.css') }}?v={{ @filemtime(public_path('shared/css/table-row-actions-menu.css')) ?: time() }}">
    <link rel="stylesheet" href="{{ url('/shared/css/sk-type-confirm.css') }}?v={{ @filemtime(public_path('shared/css/sk-type-confirm.css')) ?: time() }}">
    @vite([
        'app/Modules/Deleted_Calendar_Notes/assets/css/deleted-calendar-notes.css',
    ])
@endpush

@section('content')
    <div class="page-container deleted-kabataan-page deleted-calendar-notes-page has-table-page-footer">

        <section class="page-header-section">
            <div class="page-header-left">
                <h1 class="page-title">Deleted Calendar Notes</h1>
                <p class="page-subtitle">Calendar notes moved to trash. Restore within 30 days. After 30 days, they are permanently deleted.</p>
            </div>
            <div class="page-header-right">
                <input type="text" id="dcnSearch" class="filter-input" placeholder="Search by title or content…" aria-label="Search deleted calendar notes">
            </div>
        </section>

        <div class="restore-success-banner" id="dcnStatusBanner" style="display:none;" role="status" aria-live="polite">
            <span class="restore-banner-icon" id="dcnStatusBannerIcon">✓</span>
            <div class="dcn-status-banner-copy">
                <span class="restore-banner-text" id="dcnStatusBannerTitle"></span>
                <span class="dcn-status-banner-detail" id="dcnStatusBannerDetail" style="display:none;"></span>
            </div>
        </div>

        <div class="filter-tabs-row">
            <div class="filter-tabs-group">
                <button type="button" class="filter-tab active" data-filter="all">All Deleted</button>
                <button type="button" class="filter-tab" data-filter="today">Deleted Today</button>
                <button type="button" class="filter-tab" data-filter="week">This Week</button>
                <button type="button" class="filter-tab" data-filter="month">This Month</button>
            </div>
        </div>

        <section class="page-content-section">
            <div class="section-heading-row">
                <h2 class="section-title" id="dcnSectionLabel">All Deleted Notes</h2>
            </div>
            <div class="table-card">
                <div class="table-wrapper">
                    <table class="dk-table dcn-table">
                        <thead>
                            <tr>
                                <th class="dcn-col-title">Title</th>
                                <th class="dcn-col-date">Note Date</th>
                                <th class="dcn-col-content">Content</th>
                                <th class="dcn-col-deleted-date">Deleted Date</th>
                                <th class="dcn-col-deleted-time">Deleted Time</th>
                                <th class="dcn-col-auto">Auto Delete</th>
                                <th class="col-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="dcnTableBody">
                            <tr class="empty-state-row">
                                <td colspan="7">Loading deleted notes…</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="dcn-page-footer table-page-footer pagination-footer" aria-label="Table pagination">
                <div class="pagination-footer-nav">
                    <button type="button" class="pagination-arrow" id="dcnPrevBtn" disabled aria-label="Previous page">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 18l-6-6 6-6"/></svg>
                    </button>
                    <span class="pagination-page-label">Page</span>
                    <input type="number" class="pagination-page-input" id="dcnPageInput" value="1" min="1" aria-label="Current page">
                    <span class="pagination-page-of">of <span id="dcnTotalPages">1</span></span>
                    <button type="button" class="pagination-arrow" id="dcnNextBtn" disabled aria-label="Next page">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 18l6-6-6-6"/></svg>
                    </button>
                </div>
                <div class="pagination-footer-right">
                    <select id="dcnRowsPerPageSelect" class="pagination-rows-select" aria-label="Rows per page">
                        <option value="10">10 rows</option>
                        <option value="50">50 rows</option>
                        <option value="100">100 rows</option>
                    </select>
                    <span class="pagination-record-count" id="dcnPaginationInfo">0 records</span>
                </div>
            </div>
        </section>

    </div>

    <div class="restore-modal-backdrop" id="dcnViewModal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="dcnViewTitle">
        <div class="restore-modal-box view-modal-box dcn-view-modal-box">
            <div class="restore-modal-header view-modal-header">
                <div>
                    <h2 class="restore-modal-title" id="dcnViewTitle">Calendar Note</h2>
                    <span class="dk-view-subtitle">Deleted Calendar Note</span>
                </div>
                <div class="view-modal-controls">
                    <button type="button" class="view-modal-close" id="dcnViewClose" aria-label="Close">&times;</button>
                </div>
            </div>
            <div class="dk-deleted-meta">
                <div class="dk-deleted-meta-item">
                    <span class="dk-deleted-meta-label">Note Date</span>
                    <span class="dk-deleted-meta-value" id="dcnViewNoteDate">—</span>
                </div>
                <div class="dk-deleted-meta-item">
                    <span class="dk-deleted-meta-label">Deleted</span>
                    <span class="dk-deleted-meta-value" id="dcnViewDeleted">—</span>
                </div>
                <div class="dk-deleted-meta-item">
                    <span class="dk-deleted-meta-label">Auto Delete</span>
                    <span class="dk-deleted-meta-value" id="dcnViewAutoDelete">—</span>
                </div>
            </div>
            <div class="view-modal-body dcn-view-body">
                <h3 class="dcn-view-note-title" id="dcnViewNoteTitle"></h3>
                <pre class="dcn-view-note-content" id="dcnViewNoteContent"></pre>
            </div>
        </div>
    </div>

    <div class="restore-modal-backdrop" id="dcnRestoreModal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="dcnRestoreModalTitle">
        <div class="restore-modal-box">
            <div class="restore-modal-header">
                <h2 class="restore-modal-title" id="dcnRestoreModalTitle">Restore Note</h2>
            </div>
            <div class="restore-modal-body">
                <p class="restore-modal-message">Restore this calendar note back to the Calendar?</p>
                <p class="restore-modal-name" id="dcnRestoreName"></p>
            </div>
            <div class="restore-modal-footer">
                <button type="button" class="btn-cancel-restore" id="dcnRestoreCancelBtn">Cancel</button>
                <button type="button" class="btn-confirm-restore" id="dcnRestoreConfirmBtn">Restore</button>
            </div>
        </div>
    </div>

    <div class="restore-modal-backdrop" id="dcnRestoreConflictModal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="dcnRestoreConflictTitle">
        <div class="restore-modal-box dcn-conflict-modal-box">
            <div class="restore-modal-header">
                <h2 class="restore-modal-title" id="dcnRestoreConflictTitle">Cannot restore calendar note</h2>
            </div>
            <div class="restore-modal-body">
                <p class="restore-modal-message dcn-conflict-message" id="dcnRestoreConflictMessage">
                    A calendar note already exists for this date. Please delete or move the existing note before restoring this archived note.
                </p>
            </div>
            <div class="restore-modal-footer">
                <button type="button" class="btn-confirm-restore dcn-conflict-ok-btn" id="dcnRestoreConflictOkBtn">Got it</button>
            </div>
        </div>
    </div>

    <div class="sk-type-confirm-backdrop" id="dcnPermanentDeleteModal" style="display:none;">
        <div class="sk-type-confirm-modal" role="dialog" aria-modal="true" aria-labelledby="dcnPermanentDeleteTitle">
            <div class="sk-type-confirm-header">
                <h3 id="dcnPermanentDeleteTitle">Delete Permanently</h3>
            </div>
            <div class="sk-type-confirm-body">
                <p class="sk-type-confirm-message" id="dcnPermanentDeleteMessage">Permanently delete this calendar note?</p>
                <p class="sk-type-confirm-desc">This action cannot be undone.</p>
                <label class="sk-type-confirm-label" for="dcnPermanentDeleteInput">Type CONFIRM to confirm</label>
                <input type="text" id="dcnPermanentDeleteInput" class="sk-type-confirm-input" placeholder="CONFIRM" autocomplete="off" spellcheck="false" autocapitalize="characters" maxlength="7">
                <p class="sk-type-confirm-hint-error" id="dcnPermanentDeleteError" style="display:none;"></p>
            </div>
            <div class="sk-type-confirm-footer">
                <button type="button" class="sk-btn-cancel-confirm" id="dcnPermanentDeleteCancel">Cancel</button>
                <button type="button" class="sk-btn-action-confirm is-disabled" id="dcnPermanentDeleteConfirm" disabled>Delete Permanently</button>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ url('/shared/js/table-page-footer.js') }}?v={{ @filemtime(public_path('shared/js/table-page-footer.js')) ?: time() }}"></script>
    <script src="{{ url('/shared/js/table-row-actions-menu.js') }}?v={{ @filemtime(public_path('shared/js/table-row-actions-menu.js')) ?: time() }}"></script>
    @vite([
        'app/Modules/Deleted_Calendar_Notes/assets/js/deleted-calendar-notes.js',
    ])
@endpush
