@extends('layout::app')

@section('title', $barangay . ' - KK Profiling Records - SK OnePortal')

@push('main-class')
    km-main km-barangay-detail-main
@endpush

@push('main-attributes')
    data-detail-base="{{ url('/kabataan-monitoring') }}"
@endpush

@push('styles')
    @vite([
        'app/Modules/KabataanMonitoring/assets/css/kabataan-monitoring.css',
        'app/Modules/KabataanMonitoring/assets/css/kabataan-monitoring-responsive.css',
        'app/Modules/KabataanMonitoring/assets/css/kkprofiling.css',
        'app/Modules/KabataanMonitoring/assets/css/kk-profiling-form.css',
        'app/Modules/KabataanMonitoring/assets/css/kk-questionnaire-view.css',
    ])
@endpush

@section('content')
<div class="km-container km-barangay-detail-page">

    <section class="km-brgy-hero">
        <div class="km-brgy-hero-logo">
            @if(!empty($barangayLogoUrl))
                <img src="{{ $barangayLogoUrl }}" alt="{{ $barangay }} logo" onerror="this.hidden=true;this.nextElementSibling.hidden=false;">
            @endif
            <span class="km-brgy-hero-logo-fallback" @if(!empty($barangayLogoUrl)) hidden @endif>{{ strtoupper(mb_substr($barangay, 0, 1)) }}</span>
        </div>
        <div class="km-brgy-hero-copy">
            <p class="km-brgy-hero-eyebrow">KK Profiling Records</p>
            <h1 class="km-brgy-hero-title">{{ $barangay }}</h1>
            <p class="km-brgy-hero-subtitle">Youth profiling records for {{ $barangay }}</p>
        </div>
    </section>

    <section class="km-filter-bar km-filter-bar--detail" aria-label="Profiling filters">
        <div class="km-filter-bar-top">
            <div class="km-filter-dropdowns">
                <select id="km-brgy-year-filter" class="km-select" aria-label="Filter by year">
                    <option value="all">All Years</option>
                    @foreach($registrationYears as $year)
                        <option value="{{ $year }}">{{ $year }}</option>
                    @endforeach
                </select>
                <select id="km-brgy-status-filter" class="km-select" aria-label="Filter by KK Profiling review status">
                    <option value="all">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                </select>
                <select id="km-brgy-age-group-filter" class="km-select" aria-label="Filter by youth age group">
                    <option value="all">Youth Age Group</option>
                    <option value="child">Child Youth (15–17)</option>
                    <option value="core">Core Youth (18–24)</option>
                    <option value="young">Young Adult (25–30)</option>
                </select>
                <select id="km-brgy-purok-filter" class="km-select" aria-label="Filter by purok or zone">
                    <option value="all">Purok/Zone</option>
                </select>
                <select id="km-brgy-voter-filter" class="km-select" aria-label="Filter by registered voter">
                    <option value="all">Registered Voter</option>
                    <option value="Yes">Yes</option>
                    <option value="No">No</option>
                </select>
            </div>

            <div class="km-search-group km-search-group--compact">
                <input type="search" id="km-brgy-search" class="km-search-input" placeholder="Search..." aria-label="Search records">
                <button type="button" class="km-search-btn" onclick="performBarangaySearch()" aria-label="Search">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </div>

        <div class="km-filter-bar-actions">
            <div class="km-filter-bar-actions-right">
                <button type="button" class="km-btn km-btn--add-record" id="km-add-kk-record-btn">
                    <i class="fas fa-plus" aria-hidden="true"></i>
                    Add Kabataan
                </button>
                <button type="button" class="km-export-btn km-export-btn--csv" id="km-export-csv-btn">
                    <i class="fas fa-file-csv" aria-hidden="true"></i> Export CSV
                </button>
                <button type="button" class="km-batch-approve-btn" id="km-batch-approve-btn" disabled hidden>
                    <i class="fas fa-check" aria-hidden="true"></i>
                    <span class="km-bulk-action-label">Approve Selected</span>
                    <span class="km-bulk-action-count"></span>
                </button>
                <button type="button" class="km-batch-delete-btn" id="km-batch-delete-btn" disabled hidden>
                    <i class="fas fa-trash" aria-hidden="true"></i>
                    <span class="km-bulk-action-label">Delete Selected</span>
                    <span class="km-bulk-action-count"></span>
                </button>
                <button type="button" class="km-batch-print-btn" id="km-batch-print-btn" disabled>
                    <i class="fas fa-print"></i> Batch Print
                </button>
            </div>
        </div>
    </section>

    <div class="km-table-card">
        <div class="km-table-wrapper">
            <table class="km-table km-table--masterlist">
                <thead>
                    <tr>
                        <th class="km-col-check">
                            <input type="checkbox" id="km-select-all" aria-label="Select all records">
                        </th>
                        <th class="km-th-sortable" data-sort-key="respondent" data-sort-type="text" aria-sort="none">
                            <button type="button" class="km-sort-btn" aria-haspopup="menu" aria-expanded="false">
                                Respondent #
                                <span class="km-sort-icon" aria-hidden="true"></span>
                            </button>
                        </th>
                        <th class="km-th-sortable" data-sort-key="fullname" data-sort-type="text" aria-sort="none">
                            <button type="button" class="km-sort-btn km-sort-btn--fullname" aria-haspopup="menu" aria-expanded="false">
                                <span class="table-fullname-label">
                                    FULLNAME
                                    <span class="table-col-hint">LN, FN, MN, Suffix</span>
                                </span>
                                <span class="km-sort-icon" aria-hidden="true"></span>
                            </button>
                        </th>
                        <th>Age</th>
                        <th>Purok/Zone</th>
                        <th>Status</th>
                        <th>Registered Voter</th>
                        <th class="col-actions">Actions</th>
                    </tr>
                </thead>
                <tbody id="km-table-tbody">
                    <tr class="km-loading-row">
                        <td colspan="8">Loading records…</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p id="km-empty" class="km-empty km-empty--in-card" hidden>No profiles match your current filters.</p>
    </div>
</div>

<div class="km-page-pagination pagination-footer" aria-label="Table pagination">
    <div class="pagination-footer-nav">
        <button type="button" class="pagination-arrow" id="km-prev-btn" disabled aria-label="Previous page">
            <i class="fas fa-chevron-left"></i>
        </button>
        <span class="pagination-page-label">Page</span>
        <input type="number" class="pagination-page-input" id="km-page-input" value="1" min="1" aria-label="Current page">
        <span class="pagination-page-of">of <span id="km-total-pages">1</span></span>
        <button type="button" class="pagination-arrow" id="km-next-btn" disabled aria-label="Next page">
            <i class="fas fa-chevron-right"></i>
        </button>
    </div>
    <div class="pagination-footer-right">
        <select id="km-rows-per-page" class="pagination-rows-select" aria-label="Rows per page">
            <option value="10" selected>10 rows</option>
            <option value="25">25 rows</option>
            <option value="50">50 rows</option>
        </select>
        <span class="pagination-record-count" id="km-pagination-text">0 records</span>
    </div>
</div>

<div class="km-kkp-modal" id="kmKKPModal">
    <div class="km-kkp-modal-overlay" onclick="closeKKPModal()"></div>
    <div class="km-kkp-modal-content">
        <div class="km-kkp-modal-header">
            <h2><i class="fas fa-file-alt"></i> KK Survey Questionnaire</h2>
            <div class="km-kkp-modal-controls">
                <button type="button" class="km-kkp-modal-control-btn km-kkp-resize-btn" id="kmKKPFullscreenBtn" onclick="toggleKKPFullscreen()" title="Maximize" aria-label="Maximize">
                    <svg class="modal-win-icon-max" width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                        <rect x="2.5" y="2.5" width="11" height="11" rx="0.5"></rect>
                    </svg>
                    <svg class="modal-win-icon-restore" width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                        <path d="M5 3.5h7.5V11"></path>
                        <rect x="2.5" y="5.5" width="8" height="8" rx="0.5"></rect>
                    </svg>
                </button>
                <button type="button" class="km-kkp-modal-control-btn km-kkp-modal-close" onclick="closeKKPModal()" title="Close" aria-label="Close">&times;</button>
            </div>
        </div>
        <div class="km-kkp-modal-body kk-qs-body" id="kmKKPFormContainer">
            <div id="kmKKPViewRoot">
                <p class="km-kkp-loading">Loading questionnaire...</p>
            </div>
            @include('kabataan_monitoring::partials.kk-survey-edit')
            <section class="km-monitoring-document" id="kmMonitoringDocumentPanel" aria-labelledby="kmMonitoringDocumentTitle">
                <div class="km-monitoring-document-heading km-monitoring-document-header">
                    <div>
                        <h3 id="kmMonitoringDocumentTitle">Supporting PDF</h3>
                        <p class="km-monitoring-document-meta" id="kmMonitoringDocumentMeta">No PDF has been attached to this record.</p>
                    </div>
                    <div class="km-monitoring-document-links" id="kmMonitoringDocumentLinks" hidden>
                        <a class="km-document-action km-monitoring-document-link" id="kmMonitoringDocumentView" target="_blank" rel="noopener" hidden>
                            <i class="fas fa-eye" aria-hidden="true"></i> View PDF
                        </a>
                        <a class="km-document-action km-monitoring-document-link" id="kmMonitoringDocumentDownload" hidden>
                            <i class="fas fa-download" aria-hidden="true"></i> Download PDF
                        </a>
                    </div>
                </div>
                <div class="km-monitoring-document-upload" id="kmMonitoringDocumentUpload" hidden>
                    <label class="km-monitoring-document-upload-label" for="kmMonitoringDocumentInput">Attach or replace PDF (maximum 10 MB)</label>
                    <div class="km-monitoring-document-upload-controls km-monitoring-document-actions">
                        <input class="km-monitoring-document-input" type="file" id="kmMonitoringDocumentInput" accept=".pdf,application/pdf">
                        <button type="button" class="km-kkp-btn-save km-monitoring-document-upload-btn" id="kmMonitoringDocumentUploadBtn">
                            <i class="fas fa-upload" aria-hidden="true"></i> Upload PDF
                        </button>
                    </div>
                    <progress id="kmMonitoringDocumentProgress" max="100" value="0" aria-label="PDF upload progress" hidden></progress>
                </div>
                <p class="km-monitoring-document-status" id="kmMonitoringDocumentStatus" role="status" aria-live="polite" hidden></p>
            </section>
        </div>
        <div class="km-kkp-modal-footer" id="kmKKPEditFooter" hidden>
            <button type="button" class="km-kkp-btn-close" id="kmKKPEditCancelBtn">Cancel</button>
            <button type="button" class="km-kkp-btn-save" id="kmKKPEditSaveBtn">Save Changes</button>
        </div>
    </div>
</div>
<div class="km-kkp-modal" id="kmDeleteModal">
    <div class="km-kkp-modal-overlay" data-km-delete-close></div>
    <div class="km-delete-dialog" role="dialog" aria-modal="true" aria-labelledby="kmDeleteTitle">
        <div class="km-delete-header">
            <h3 id="kmDeleteTitle">Delete Kabataan Record?</h3>
            <button type="button" class="km-kkp-modal-control-btn km-kkp-modal-close" data-km-delete-close aria-label="Close">&times;</button>
        </div>
        <div class="km-delete-body">
            <p>This will remove <strong id="kmDeleteName">this record</strong> from Kabataan Monitoring. Type <strong>DELETE</strong> to continue.</p>
            <label class="km-type-confirm-label" for="kmDeleteConfirmInput">Confirmation</label>
            <input type="text" id="kmDeleteConfirmInput" class="km-type-confirm-input" placeholder="type DELETE to delete" autocomplete="off" autocapitalize="none" spellcheck="false">
            <p class="km-type-confirm-hint" id="kmDeleteConfirmHint" hidden>Please type “DELETE” to continue (any capitalization is fine).</p>
        </div>
        <div class="km-delete-footer">
            <button type="button" class="km-kkp-btn-close" data-km-delete-close>Cancel</button>
            <button type="button" class="km-btn-delete-confirm" id="kmDeleteConfirmBtn" disabled>Delete Record</button>
        </div>
    </div>
</div>
<div class="km-kkp-modal" id="kmExportModal">
    <div class="km-kkp-modal-overlay" data-km-export-close></div>
    <div class="km-delete-dialog" role="dialog" aria-modal="true" aria-labelledby="kmExportTitle">
        <div class="km-delete-header">
            <h3 id="kmExportTitle">Export Records</h3>
            <button type="button" class="km-kkp-modal-control-btn km-kkp-modal-close" data-km-export-close aria-label="Close">&times;</button>
        </div>
        <div class="km-delete-body">
            <p>Choose a date range to export. Leave both blank to export all currently filtered records.</p>
            <label class="km-type-confirm-label" for="kmExportStartDate">Start date</label>
            <input type="date" id="kmExportStartDate" class="km-type-confirm-input">
            <label class="km-type-confirm-label" for="kmExportEndDate">End date</label>
            <input type="date" id="kmExportEndDate" class="km-type-confirm-input">
        </div>
        <div class="km-delete-footer">
            <button type="button" class="km-kkp-btn-close" data-km-export-close>Cancel</button>
            <button type="button" class="km-export-btn km-export-btn--csv" id="kmExportConfirmCsvBtn">Export CSV</button>
        </div>
    </div>
</div>
<div class="km-kkp-modal" id="kmEditConfirmModal">
    <div class="km-kkp-modal-overlay" data-km-edit-confirm-close></div>
    <div class="km-delete-dialog" role="dialog" aria-modal="true" aria-labelledby="kmEditConfirmTitle">
        <div class="km-delete-header">
            <h3 id="kmEditConfirmTitle">Save Changes?</h3>
            <button type="button" class="km-kkp-modal-control-btn km-kkp-modal-close" data-km-edit-confirm-close aria-label="Close">&times;</button>
        </div>
        <div class="km-delete-body">
            <p id="kmEditConfirmMessage">Are you sure you want to edit this KK Profiling record? Type <strong>yes</strong> to confirm.</p>
            <label class="km-type-confirm-label" for="kmEditConfirmInput">Confirmation</label>
            <input type="text" id="kmEditConfirmInput" class="km-type-confirm-input" placeholder="type yes" autocomplete="off" autocapitalize="none" spellcheck="false">
            <p class="km-type-confirm-hint" id="kmEditConfirmHint" hidden>Please type “yes” to continue.</p>
        </div>
        <div class="km-delete-footer">
            <button type="button" class="km-kkp-btn-close" data-km-edit-confirm-close>Cancel</button>
            <button type="button" class="km-kkp-btn-save" id="kmEditConfirmBtn" disabled>Save Changes</button>
        </div>
    </div>
</div>
<div id="kmSortMenu" class="km-sort-menu" hidden role="menu" aria-label="Sort options"></div>

@include('kabataan_monitoring::partials.add-kabataan-modal')
@endsection

@push('scripts')
    <script>
        window.kmPageMode = 'barangay-detail';
        window.kmBarangay = @json($barangay);
        window.kmTenantId = @json((int) $user->tenant_id);
        window.kmPurokZones = @json($purokZones ?? []);
        window.kmInitialPayload = {
            data: @json($initialRecords ?? []),
            years: @json($registrationYears ?? []),
        };
        window.kmConfig = {
            dataUrl: @json(route('api.kabataan-monitoring.index')),
            batchUploadUrl: @json(route('api.kabataan-monitoring.batch-upload')),
            templateUrl: @json(route('kabataan-monitoring.batch-template', ['barangay' => $barangay])),
            storeUrl: @json(route('api.kabataan-monitoring.store')),
            questionnaireUrl: @json(url('/api/kabataan-monitoring/__ID__/questionnaire')),
            editUrl: @json(url('/api/kabataan-monitoring/__ID__/edit')),
            updateUrl: @json(url('/api/kabataan-monitoring/__ID__')),
            documentUploadUrl: @json(url('/api/kabataan-monitoring/__ID__/document')),
            documentUrl: @json(url('/api/kabataan-monitoring/__ID__/document')),
            documentDownloadUrl: @json(url('/api/kabataan-monitoring/__ID__/document/download')),
            destroyUrl: @json(url('/api/kabataan-monitoring/__ID__')),
            batchPrintUrl: @json(route('kabataan-monitoring.batch-print')),
            bulkApproveUrl: @json(route('api.kabataan-monitoring.bulk-approve', ['barangay' => $barangay])),
            bulkDeleteUrl: @json(route('api.kabataan-monitoring.bulk-delete', ['barangay' => $barangay])),
            printUrl: @json(url('/kabataan-monitoring/__ID__/print')),
            csrfToken: @json(csrf_token()),
            viewCssUrl: @json(\Illuminate\Support\Facades\Vite::asset('app/Modules/KabataanMonitoring/assets/css/kk-questionnaire-view.css')),
        };
    </script>
    <script src="{{ url('/shared/js/local-data-cache.js') }}?v={{ @filemtime(public_path('shared/js/local-data-cache.js')) ?: time() }}"></script>
    @vite([
        'app/Modules/KabataanMonitoring/assets/js/kabataan-monitoring.js',
        'app/Modules/KabataanMonitoring/assets/js/kabataan-monitoring-table.js',
        'app/Modules/KabataanMonitoring/assets/js/kabataan-monitoring-validation.js',
        'app/Modules/KabataanMonitoring/assets/js/kabataan-monitoring-edit.js',
        'app/Modules/KabataanMonitoring/assets/js/kabataan-monitoring-upload.js',
        'app/Modules/KabataanMonitoring/assets/js/kabataan-monitoring-excel-upload.js',
    ])
@endpush
