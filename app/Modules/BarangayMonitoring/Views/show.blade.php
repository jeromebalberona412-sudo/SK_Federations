@extends('layout::app')

@section('title', ($barangayData['name'] ?? 'Barangay') . ' - Barangay Monitoring')

@push('main-class')
    bm-main
@endpush

@push('styles')
    <link rel="stylesheet" href="{{ url('/modules/barangay-monitoring/css/barangay-monitoring.css') }}?v={{ time() }}">
    <link rel="stylesheet" href="{{ url('/modules/barangay-abyip/css/barangay_abyip.css') }}?v={{ time() }}">
@endpush

@section('content')
<div class="bm-container bm-show-page" id="bmShowApp"
     data-barangay-slug="{{ $barangayData['slug'] }}"
     data-barangay-name="{{ $barangayData['name'] }}">

    <a class="bm-back-link" href="{{ route('barangay-monitoring') }}" data-no-loading>
        <i class="fas fa-arrow-left"></i>
        <span>Back to All Barangays</span>
    </a>

    <header class="bm-show-hero">
        <div class="bm-show-hero-brand">
            <div class="bm-show-hero-logo">
                @if(!empty($barangayData['logo_url']))
                    <img src="{{ $barangayData['logo_url'] }}" alt="{{ $barangayData['name'] }} logo" onerror="this.hidden=true;this.nextElementSibling.hidden=false;">
                @endif
                <span class="bm-show-hero-logo-fallback" @if(!empty($barangayData['logo_url'])) hidden @endif>{{ strtoupper(mb_substr($barangayData['name'], 0, 1)) }}</span>
            </div>
            <div class="bm-show-hero-main">
                <h1 class="bm-show-title">{{ $barangayData['name'] }}</h1>
                <p class="bm-show-subtitle">
                    <i class="fas fa-map-marker-alt"></i>
                    {{ $barangayData['name'] }}, {{ $barangayData['municipality'] }}
                </p>
                @if(!empty($barangayData['submitted_by']))
                    <p class="bm-show-submitted-by">
                        <i class="fas fa-user"></i>
                        Submitted by: {{ $barangayData['submitted_by'] }}
                        @if(!empty($barangayData['submitted_by_role']))
                            <span class="bm-submitted-role">({{ $barangayData['submitted_by_role'] }})</span>
                        @endif
                    </p>
                @endif
            </div>
        </div>
    </header>

    @if(!empty($barangayData['warnings']))
        @foreach($barangayData['warnings'] as $warning)
        <div class="bm-alert bm-alert-{{ $warning['type'] }}" role="alert">
            <div class="bm-alert-icon">
                <i class="fas fa-{{ $warning['type'] === 'critical' ? 'exclamation-triangle' : 'exclamation-circle' }}"></i>
            </div>
            <div class="bm-alert-content">
                <strong>{{ $warning['title'] }}</strong>
                <p>{{ $warning['message'] }}</p>
            </div>
        </div>
        @endforeach
    @endif

    @if(!empty($barangayData['abyip_schedule']))
        @php $schedule = $barangayData['abyip_schedule']; @endphp
        <section class="bm-card bm-schedule-banner">
            <div class="bm-card-head">
                <h3><i class="fas fa-calendar-alt"></i> {{ $schedule['title'] ?? 'ABYIP Submission' }}</h3>
                <span class="bm-schedule-status">{{ $schedule['status_label'] ?? '—' }}</span>
            </div>
            <div class="bm-schedule-grid">
                <div class="bm-schedule-item">
                    <p class="bm-schedule-label">Start</p>
                    <p class="bm-schedule-value">{{ $schedule['date_start'] ?? '—' }}</p>
                </div>
                <div class="bm-schedule-item">
                    <p class="bm-schedule-label">Deadline</p>
                    <p class="bm-schedule-value">{{ $schedule['deadline'] ?? '—' }}</p>
                </div>
                <div class="bm-schedule-item">
                    <p class="bm-schedule-label">Original Deadline</p>
                    <p class="bm-schedule-value">{{ $schedule['original_deadline'] ?? '—' }}</p>
                </div>
            </div>
        </section>
    @endif

    <section class="bm-card bm-table-card">
        <div class="bm-table-filters">
            <div class="bm-search-wrap">
                <i class="fas fa-search"></i>
                <input type="search" id="abyipSearchInput" placeholder="Search reports..." aria-label="Search ABYIP reports">
            </div>
            <select id="abyipFilterYear" aria-label="Filter by year">
                <option value="all">All Years</option>
                @foreach(collect($barangayData['abyip']['reports'] ?? [])->pluck('fiscal_year')->unique()->sortDesc() as $year)
                    <option value="{{ $year }}">{{ $year }}</option>
                @endforeach
            </select>
            <select id="abyipFilterStatus" aria-label="Filter by status">
                <option value="all">All Status</option>
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
            </select>
        </div>

        <div class="bm-table-wrap">
            <table class="bm-table bm-data-table bm-data-table--abyip" id="abyipTable">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Calendar Year</th>
                        <th>Date Submitted</th>
                        <th>Time Submitted</th>
                        <th>Submitted By</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($barangayData['abyip']['reports'] ?? [] as $report)
                    <tr class="bm-abyip-row" data-year="{{ $report['fiscal_year'] ?? '' }}" data-status="{{ $report['status'] ?? 'pending' }}" data-id="{{ $report['id'] }}">
                        <td class="bm-cell-strong" data-label="Title">{{ $report['name'] ?? 'N/A' }}</td>
                        <td data-label="Calendar Year">{{ $report['fiscal_year'] ?? '—' }}</td>
                        <td data-label="Date Submitted">{{ !empty($report['date_submitted']) ? date('M d, Y', strtotime($report['date_submitted'])) : '—' }}</td>
                        <td data-label="Time Submitted">{{ !empty($report['date_submitted']) ? date('h:i A', strtotime($report['date_submitted'])) : '—' }}</td>
                        <td data-label="Submitted By">{{ $report['submitted_by'] ?? '—' }}</td>
                        <td data-label="Role">{{ $report['submitted_by_role'] ?? '—' }}</td>
                        <td data-label="Status">
                            <span class="bm-status-pill bm-status-{{ $report['status'] ?? 'pending' }}">
                                {{ ucfirst($report['status'] ?? 'pending') }}
                            </span>
                        </td>
                        <td data-label="Actions" class="bm-col-actions">
                            <div class="bm-row-actions">
                                @php $reportStatus = strtolower($report['status'] ?? 'pending'); @endphp
                                @if(in_array($reportStatus, ['pending', 'approved'], true))
                                <div class="bm-actions-menu row-actions-menu" data-report-id="{{ $report['id'] }}" data-report-status="{{ $reportStatus }}">
                                    <button type="button" class="bm-actions-toggle row-actions-trigger" aria-label="More actions" aria-haspopup="true" aria-expanded="false">
                                        <i class="fas fa-ellipsis-h" aria-hidden="true"></i>
                                    </button>
                                    <div class="bm-actions-dropdown row-actions-dropdown" role="menu" hidden>
                                        <button type="button" class="bm-actions-item row-actions-item row-actions-item-view bm-actions-view" data-abyip-view="{{ $report['id'] }}" role="menuitem">
                                            <i class="fas fa-eye" aria-hidden="true"></i>
                                            <span>View</span>
                                        </button>
                                        @if($reportStatus === 'pending')
                                            <button type="button" class="bm-actions-item row-actions-item row-actions-item-approve bm-actions-approve" data-abyip-approve="{{ $report['id'] }}" role="menuitem">
                                                <i class="fas fa-check" aria-hidden="true"></i>
                                                <span>Approve</span>
                                            </button>
                                            <button type="button" class="bm-actions-item row-actions-item row-actions-item-danger bm-actions-reject" data-abyip-reject="{{ $report['id'] }}" role="menuitem">
                                                <i class="fas fa-times" aria-hidden="true"></i>
                                                <span>Reject</span>
                                            </button>
                                        @elseif($reportStatus === 'approved')
                                            <button type="button" class="bm-actions-item row-actions-item row-actions-item-revoke bm-actions-revoke" data-abyip-revoke="{{ $report['id'] }}" role="menuitem">
                                                <i class="fas fa-undo" aria-hidden="true"></i>
                                                <span>Revoke</span>
                                            </button>
                                        @endif
                                    </div>
                                </div>
                                @else
                                <button type="button" class="bm-actions-item row-actions-item row-actions-item-view bm-view-btn" data-abyip-view="{{ $report['id'] }}">
                                    <i class="fas fa-eye" aria-hidden="true"></i>
                                    <span>View</span>
                                </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr class="bm-empty-row-tr">
                        <td colspan="8" class="bm-empty-row">
                            <i class="fas fa-inbox"></i>
                            <span>No ABYIP reports submitted yet</span>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>

<div id="viewModal" class="view-modal">
    <div class="view-modal-content">
        <div class="view-modal-header">
            <h3 class="view-modal-title">
                <i class="fas fa-file-invoice-dollar"></i>
                ABYIP Submission Details
            </h3>
            <div class="view-modal-controls">
                <button type="button" class="view-modal-control-btn view-modal-resize-btn" onclick="toggleFullscreen()" title="Maximize" aria-label="Maximize" id="fullscreenBtn">
                    <svg class="modal-win-icon-max" width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                        <rect x="2.5" y="2.5" width="11" height="11" rx="0.5"></rect>
                    </svg>
                    <svg class="modal-win-icon-restore" width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                        <path d="M5 3.5h7.5V11"></path>
                        <rect x="2.5" y="5.5" width="8" height="8" rx="0.5"></rect>
                    </svg>
                </button>
                <button type="button" class="view-modal-control-btn view-modal-close-btn" onclick="closeViewModal()" title="Close" aria-label="Close">&times;</button>
            </div>
        </div>
        <div class="view-modal-body">
            <div class="submission-meta-grid">
                <div class="meta-item">
                    <span class="meta-label">Barangay</span>
                    <span class="meta-value" id="modalBarangay">{{ $barangayData['name'] }}</span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Calendar Year</span>
                    <span class="meta-value" id="modalFiscalYear">-</span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Date Submitted</span>
                    <span class="meta-value" id="modalDateSubmitted">-</span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Submitted Time</span>
                    <span class="meta-value" id="modalSubmittedTime">-</span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Submitted By</span>
                    <span class="meta-value" id="modalSubmittedBy">-</span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Role</span>
                    <span class="meta-value" id="modalSubmittedRole">-</span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Status</span>
                    <span class="meta-value">
                        <span class="status-badge status-pending" id="modalStatus">Pending</span>
                    </span>
                </div>
                <div class="meta-item meta-item-full">
                    <span class="meta-label">Title</span>
                    <span class="meta-value" id="modalTitle">-</span>
                </div>
            </div>

            <div id="modalRejectionReason" class="modal-rejection-notice" style="display:none;"></div>

            <div class="submission-preview-section">
                <h4 class="preview-section-title">Uploaded ABYIP Document</h4>
                <div id="abyipPreviewMount" class="abyip-preview-mount">
                    <p class="preview-loading">Select View to load document preview.</p>
                </div>
            </div>
        </div>

        <div class="view-modal-footer" id="viewModalFooter">
            <div class="modal-actions modal-actions-view-only" id="modalActions">
                <button type="button" class="action-btn cancel-btn" onclick="closeViewModal()">Close</button>
            </div>
        </div>
    </div>
</div>

<div id="approveModal" class="view-modal bm-confirm-modal">
    <div class="bm-kk-modal-box">
        <div class="bm-kk-modal-header">
            <h2 class="bm-kk-modal-title">Approve ABYIP Submission</h2>
            <button type="button" class="bm-kk-modal-close" onclick="closeApproveModal()" aria-label="Close">&times;</button>
        </div>
        <div class="bm-kk-modal-body">
            <p>Are you sure you want to approve this ABYIP submission?</p>
        </div>
        <div class="bm-kk-modal-footer">
            <button type="button" class="bm-kk-btn-approve approve-submit-btn" onclick="confirmApproval()">Confirm Approve</button>
        </div>
    </div>
</div>

<div id="rejectModal" class="view-modal bm-reject-modal">
    <div class="view-modal-content bm-reject-modal-content">
        <div class="view-modal-header">
            <h3 class="view-modal-title">Reject ABYIP Submission</h3>
            <div class="view-modal-controls">
                <button type="button" class="view-modal-control-btn" onclick="closeRejectModal()" title="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <div class="view-modal-body">
            <div class="rejection-form" id="rejectForm">
                <p class="form-help">Type <strong>Confirm</strong> below, then select a rejection reason.</p>
                <div class="form-group">
                    <label for="abyipRejectConfirm">Confirmation <span class="required">*</span></label>
                    <input type="text" id="abyipRejectConfirm" class="form-control" maxlength="20" placeholder="Confirm" autocomplete="off">
                    <span class="error-message" id="rejectConfirmError"></span>
                </div>
                <div class="form-group">
                    <label>Rejection Reason <span class="required">*</span></label>
                    <div class="revoke-reason-options">
                        <label class="revoke-check"><input type="checkbox" id="rejectReasonWrongPdf"> Wrong PDF file</label>
                        <label class="revoke-check"><input type="checkbox" id="rejectReasonOther"> Other</label>
                    </div>
                </div>
                <div class="form-group" id="rejectReasonFieldWrap" style="display:none;">
                    <label for="abyipRejectReason">Other Reason <span class="required">*</span></label>
                    <textarea id="abyipRejectReason" class="form-control revoke-reason-input" rows="3" maxlength="100" placeholder="Explain the rejection reason..."></textarea>
                    <span class="error-message" id="rejectReasonError"></span>
                </div>
                <div class="form-actions">
                    <button type="button" class="form-btn cancel-btn" onclick="closeRejectModal()">Cancel</button>
                    <button type="button" class="form-btn submit-btn reject-submit-btn" onclick="submitRejection()" disabled>Submit Rejection</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="revokeModal" class="view-modal bm-revoke-modal">
    <div class="bm-kk-modal-box bm-kk-modal-box--compact">
        <div class="bm-kk-modal-header">
            <h2 class="bm-kk-modal-title">Revoke ABYIP Approval</h2>
            <button type="button" class="bm-kk-modal-close" onclick="closeRevokeModal()" aria-label="Close">&times;</button>
        </div>
        <div class="bm-kk-modal-body bm-kk-modal-body--form">
            <div class="rejection-form" id="revokeForm">
                <p class="form-help">Type <strong>Confirm</strong> below to continue. The submission will return to <strong>Pending</strong> status.</p>
                <div class="form-group">
                    <label for="abyipRevokeConfirm">Confirmation <span class="required">*</span></label>
                    <input type="text" id="abyipRevokeConfirm" class="form-control" maxlength="20" placeholder="Confirm" autocomplete="off">
                    <span class="error-message" id="revokeConfirmError"></span>
                </div>
                <div class="form-group">
                    <label>Reason</label>
                    <div class="revoke-reason-options">
                        <label class="revoke-check"><input type="checkbox" id="revokeReasonAccidental"> Accidentally approved</label>
                        <label class="revoke-check"><input type="checkbox" id="revokeReasonOther"> Other</label>
                    </div>
                </div>
                <div class="form-group" id="revokeReasonFieldWrap" style="display:none;">
                    <label for="abyipRevokeReason">Revoke Reason <span class="required">*</span></label>
                    <textarea id="abyipRevokeReason" class="form-control revoke-reason-input" rows="3" maxlength="100" placeholder="Explain why this approval is being revoked..."></textarea>
                    <span class="error-message" id="revokeReasonError"></span>
                </div>
            </div>
        </div>
        <div class="bm-kk-modal-footer bm-kk-modal-footer--split">
            <button type="button" class="bm-kk-btn-cancel" onclick="closeRevokeModal()">Cancel</button>
            <button type="button" class="bm-kk-btn-revoke revoke-submit-btn" onclick="submitRevocation()" disabled>Revoke Approval</button>
        </div>
    </div>
</div>

<div id="toast" class="toast bm-page-toast">
    <div class="toast-content">
        <i class="toast-icon fas fa-check-circle" aria-hidden="true"></i>
        <span class="toast-message"></span>
    </div>
</div>
@endsection

@push('scripts')
    <script>
        window.barangayMonitoringShowConfig = {
            showUrl: @json(url('/api/barangay-abyip/__ID__')),
            approveUrl: @json(url('/api/barangay-abyip/__ID__/approve')),
            rejectUrl: @json(url('/api/barangay-abyip/__ID__/reject')),
            revokeUrl: @json(url('/api/barangay-abyip/__ID__/revoke')),
            csrfToken: @json(csrf_token()),
            abyipReports: @json($barangayData['abyip']['reports'] ?? []),
            barangayName: @json($barangayData['name'] ?? ''),
        };
    </script>
    <script src="{{ url('/modules/barangay-monitoring/js/barangay-monitoring-show.js') }}?v={{ time() }}"></script>
@endpush
