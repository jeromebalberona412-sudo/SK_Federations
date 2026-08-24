@extends('layout::app')

@section('title', 'Barangay ABYIP - SK OnePortal')

@push('styles')
    <link rel="stylesheet" href="{{ url('/modules/barangay-abyip/css/barangay_abyip.css') }}?v={{ time() }}">
@endpush

@section('content')
<div class="abyip-container">
    <div class="abyip-header">
        <div>
            <h2 class="abyip-title">Barangay ABYIP Submissions</h2>
            <p class="abyip-subtitle">
                <i class="fas fa-file-invoice-dollar"></i>
                Review and approve ABYIP uploads from barangay SK Officials
            </p>
        </div>
        <a href="{{ route('barangay-monitoring') }}" class="back-button" data-no-loading>
            <i class="fas fa-arrow-left"></i> Back to Barangay Monitoring
        </a>
    </div>

    <div class="filters-container">
        <input type="search" id="abyipSearchInput" class="filter-search" placeholder="Search title, barangay, or submitter..." oninput="filterAbyipSubmissions()">
        <select id="barangayFilter" onchange="filterAbyipSubmissions()" class="filter-select">
            <option value="all">All Barangays</option>
        </select>
        <select id="dateFilter" onchange="filterAbyipSubmissions()" class="filter-select">
            <option value="all">All Time</option>
            <option value="today">Today</option>
            <option value="week">This Week</option>
            <option value="month">This Month</option>
        </select>
    </div>

    <div class="table-container">
        <table class="abyip-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Barangay</th>
                    <th>Date Submitted</th>
                    <th>Submitted By</th>
                    <th>Submitted Time</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="abyipTableBody">
                <tr><td colspan="7" class="empty-row">Loading submissions...</td></tr>
            </tbody>
        </table>
    </div>

    <div class="pagination-container">
        <div class="pagination-info">
            Showing <span id="abyipStart">0</span> to <span id="abyipEnd">0</span> of <span id="abyipTotal">0</span> submissions
        </div>
        <div class="pagination-buttons">
            <button type="button" onclick="prevAbyipPage()" class="pagination-button">
                <i class="fas fa-chevron-left"></i> Previous
            </button>
            <button type="button" onclick="nextAbyipPage()" class="pagination-button">
                Next <i class="fas fa-chevron-right"></i>
            </button>
        </div>
    </div>
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
                    <span class="meta-value" id="modalBarangay">-</span>
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
            <div class="rejection-form" id="rejectForm" style="display:none;">
                <h4 class="form-title"><i class="fas fa-times-circle"></i> Reject Submission</h4>
                <div class="form-group">
                    <label>Rejection Reason <span class="required">*</span></label>
                    <div class="checkbox-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="rejectReason" value="Incomplete Information" onchange="handleRejectReasonChange()">
                            <span>Incomplete Information</span>
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="rejectReason" value="Invalid Documents" onchange="handleRejectReasonChange()">
                            <span>Invalid Documents</span>
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="rejectReason" value="Does Not Meet Requirements" onchange="handleRejectReasonChange()">
                            <span>Does Not Meet Requirements</span>
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="rejectReason" value="Budget Constraints" onchange="handleRejectReasonChange()">
                            <span>Budget Constraints</span>
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" id="otherReasonCheckbox" name="rejectReason" value="Other" onchange="handleRejectReasonChange()">
                            <span>Other</span>
                        </label>
                    </div>
                    <span class="error-message" id="rejectReasonError"></span>
                </div>
                <div class="form-group" id="otherReasonGroup" style="display:none;">
                    <label for="otherReason">Please Specify <span class="required">*</span></label>
                    <textarea id="otherReason" class="form-control" rows="3" maxlength="500" placeholder="Please specify the reason..."></textarea>
                    <span class="error-message" id="otherReasonError"></span>
                </div>
                <div class="form-actions">
                    <button type="button" class="form-btn cancel-btn" onclick="hideRejectForm()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="button" class="form-btn submit-btn reject-submit-btn" onclick="submitRejection()">
                        <i class="fas fa-ban"></i> Submit Rejection
                    </button>
                </div>
            </div>

            <div class="modal-actions" id="modalActions">
                <button type="button" class="action-btn approve-btn" onclick="submitApproval()">
                    <i class="fas fa-check-circle"></i> Approve
                </button>
                <button type="button" class="action-btn reject-btn" onclick="showRejectForm()">
                    <i class="fas fa-times-circle"></i> Reject
                </button>
            </div>
        </div>
    </div>
</div>

<div id="toast" class="toast">
    <div class="toast-content">
        <i class="toast-icon fas fa-check-circle"></i>
        <span class="toast-message"></span>
    </div>
</div>
@endsection

@push('scripts')
<script>
    window.barangayAbyipConfig = {
        listUrl: @json(route('api.barangay-abyip.index')),
        showUrl: @json(url('/api/barangay-abyip/__ID__')),
        fileUrl: @json(url('/api/barangay-abyip/__ID__/file')),
        approveUrl: @json(url('/api/barangay-abyip/__ID__/approve')),
        rejectUrl: @json(url('/api/barangay-abyip/__ID__/reject')),
    };
</script>
<script src="{{ url('/modules/barangay-abyip/js/barangay_abyip.js') }}?v={{ time() }}"></script>
@endpush
