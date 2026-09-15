@extends('layout::app')

@section('title', 'Rejected ABYIP')

@push('styles')
    <link rel="stylesheet" href="{{ url('/shared/css/archive-row-actions.css') }}?v={{ @filemtime(public_path('shared/css/archive-row-actions.css')) ?: time() }}">
    <link rel="stylesheet" href="{{ url('/shared/css/sk-type-confirm.css') }}?v={{ @filemtime(public_path('shared/css/sk-type-confirm.css')) ?: time() }}">
    @vite([
        'app/Modules/Rejected_Abyip/assets/css/rejected-abyip.css',
    ])
@endpush

@section('content')

<div class="main-content-modern archive-posts-page rejected-abyip-page container-fluid">
    <section class="page-header-section">
        <div class="page-header-left">
            <h1 class="page-title">Rejected ABYIP</h1>
            <p class="page-subtitle">Rejected barangay ABYIP submissions. Restore within {{ (int) ($retentionDays ?? 30) }} days. After {{ (int) ($retentionDays ?? 30) }} days, records are automatically deleted.</p>
        </div>
        <div class="page-header-right">
            <input type="search" id="archiveSearch" class="filter-input" placeholder="Search rejected ABYIP…" autocomplete="off">
        </div>
    </section>

    <section class="page-content-section">
        <div class="table-card">
            <div class="table-wrapper">
                <table class="ap-archive-table ra-archive-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Barangay</th>
                            <th>CY</th>
                            <th>Rejected Date</th>
                            <th>Days Left</th>
                            <th class="col-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="archiveTableBody">
                        <tr class="ap-empty-row"><td colspan="6"><span class="ap-empty-message">No rejected ABYIP found.</span></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>

{{-- View Modal --}}
<div class="archive-view-backdrop" id="archiveViewModal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="archiveViewModalTitle">
    <div class="archive-view-box" id="archiveViewModalBox">
        <div class="archive-view-header">
            <h2 class="archive-view-title" id="archiveViewModalTitle">View Rejected ABYIP</h2>
            <div class="modal-window-controls">
                <button type="button" class="modal-toggle-btn" id="archiveViewMaximizeBtn" aria-label="Fullscreen" title="Fullscreen">□</button>
                <button type="button" class="modal-toggle-btn" id="archiveViewRestoreBtn" hidden aria-label="Restore down" title="Restore down">⧉</button>
                <button type="button" class="modal-win-btn" id="archiveViewClose" aria-label="Close">&times;</button>
            </div>
        </div>
        <div class="archive-view-body" id="archiveViewBody"></div>
    </div>
</div>

{{-- Restore Modal --}}
<div class="ap-modal-backdrop" id="apRestoreModal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="apRestoreModalTitle">
    <div class="ap-modal-box ap-modal-box--restore">
        <div class="ap-modal-header ap-modal-header--restore">
            <h2 class="ap-modal-title" id="apRestoreModalTitle">Restore ABYIP</h2>
        </div>
        <div class="ap-modal-body">
            <p class="ap-modal-message">Restore this rejected ABYIP back to pending review?</p>
            <p class="ap-modal-name" id="apRestoreTitle"></p>
        </div>
        <div class="ap-modal-footer">
            <button type="button" class="ap-btn-cancel" id="apRestoreCancelBtn">Cancel</button>
            <button type="button" class="ap-btn-restore" id="apRestoreConfirmBtn">Restore</button>
        </div>
    </div>
</div>

{{-- Delete Permanently Modal --}}
<div class="sk-type-confirm-backdrop" id="apPermanentDeleteModal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="apPermanentDeleteTitle">
    <div class="sk-type-confirm-modal">
        <div class="sk-type-confirm-header">
            <h3 id="apPermanentDeleteTitle">Delete Permanently</h3>
        </div>
        <div class="sk-type-confirm-body">
            <p class="sk-type-confirm-message" id="apPermanentDeleteMessage">Permanently delete this rejected ABYIP?</p>
            <p class="sk-type-confirm-desc">This action cannot be undone.</p>
            <label class="sk-type-confirm-label" for="apPermanentDeleteInput">Type DELETE to confirm</label>
            <input
                type="text"
                id="apPermanentDeleteInput"
                class="sk-type-confirm-input"
                placeholder="DELETE"
                maxlength="6"
                autocomplete="off"
                spellcheck="false"
                autocapitalize="characters"
                inputmode="text"
            >
            <p class="sk-type-confirm-hint-error" id="apPermanentDeleteError" style="display:none;">Please type "DELETE" to continue.</p>
        </div>
        <div class="sk-type-confirm-footer">
            <button type="button" class="sk-btn-cancel-confirm" id="apPermanentDeleteCancel">Cancel</button>
            <button type="button" class="sk-btn-action-confirm is-disabled" id="apPermanentDeleteConfirm" disabled>Delete Permanently</button>
        </div>
    </div>
</div>

@endsection

@push('scripts')
    <script>
        window.FedRejectedAbyipConfig = {
            dataUrl: @json(route('archived.rejected-abyip.data')),
            showUrl: @json(url('/archived/rejected-abyip')),
            restoreUrl: @json(url('/archived/rejected-abyip')),
            csrf: @json(csrf_token()),
            retentionDays: {{ (int) ($retentionDays ?? 30) }},
        };
    </script>
    <script src="{{ url('/shared/js/archive-row-actions.js') }}?v={{ @filemtime(public_path('shared/js/archive-row-actions.js')) ?: time() }}"></script>
    @vite([
        'app/Modules/Rejected_Abyip/assets/js/rejected-abyip.js',
    ])
@endpush
