@extends('layout::app')

@section('title', 'Deleted Posts')

@php
    $cssVersion = @filemtime(app_path('Modules/Archive_Management/assets/css/deleted-posts.css')) ?: time();
    $jsVersion = @filemtime(app_path('Modules/Archive_Management/assets/js/deleted-posts.js')) ?: time();
@endphp

@push('styles')
    <link rel="stylesheet" href="{{ url('/modules/archive-management/css/archive-row-actions.css') }}?v={{ $cssVersion }}">
    <link rel="stylesheet" href="{{ url('/modules/archive-management/css/deleted-posts.css') }}?v={{ $cssVersion }}">
@endpush

@section('content')

<div class="main-content-modern archive-posts-page container-fluid">
    <section class="page-header-section">
        <div class="page-header-left">
            <h1 class="page-title">Deleted Posts</h1>
            <p class="page-subtitle">Archived SK Federation community feed posts. Restore within 30 days. After 30 days, records are automatically deleted.</p>
        </div>
        <div class="page-header-right">
            <input type="search" id="archiveSearch" class="filter-input" placeholder="Search archived posts…" autocomplete="off">
        </div>
    </section>

    <section class="page-content-section">
        <div class="table-card">
            <div class="table-wrapper">
                <table class="ap-archive-table">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Posted By</th>
                            <th>Posted Date</th>
                            <th>Archived Date</th>
                            <th>Days Left</th>
                            <th class="col-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="archiveTableBody">
                        <tr><td colspan="6">Loading archived posts…</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="pagination-container" id="archivePagination"></div>
        </div>
    </section>
</div>

<div class="archive-view-backdrop" id="archiveViewModal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="archiveViewModalTitle">
    <div class="archive-view-box" id="archiveViewModalBox">
        <div class="archive-view-header">
            <h2 class="archive-view-title" id="archiveViewModalTitle">View Post</h2>
            <div class="modal-window-controls">
                <button type="button" class="modal-toggle-btn" id="archiveViewMaximizeBtn" aria-label="Fullscreen" title="Fullscreen">□</button>
                <button type="button" class="modal-toggle-btn" id="archiveViewRestoreBtn" hidden aria-label="Restore down" title="Restore down">⧉</button>
                <button type="button" class="modal-win-btn" id="archiveViewClose" aria-label="Close">&times;</button>
            </div>
        </div>
        <div class="archive-view-body" id="archiveViewBody"></div>
    </div>
</div>

<div class="dso-modal-backdrop" id="apRestoreModal" style="display:none;">
    <div class="dso-modal-box">
        <div class="dso-modal-header">
            <h2 class="dso-modal-title">Restore Post</h2>
        </div>
        <div class="dso-modal-body">
            <p class="dso-modal-message">Restore this post back to the SK Community Feed?</p>
            <p class="dso-modal-name" id="apRestoreTitle"></p>
        </div>
        <div class="dso-modal-footer">
            <button type="button" class="dso-btn-cancel" id="apRestoreCancelBtn">Cancel</button>
            <button type="button" class="dso-btn-confirm" id="apRestoreConfirmBtn">Restore</button>
        </div>
    </div>
</div>

@endsection

@push('scripts')
    <script>
        window.FedArchiveConfig = {
            dataUrl: @json(route('archived.deleted-posts.data')),
            restoreUrl: @json(url('/archived/deleted-posts')),
            csrf: @json(csrf_token()),
        };
    </script>
    <script src="{{ url('/modules/archive-management/js/archive-row-actions.js') }}?v={{ $jsVersion }}"></script>
    <script src="{{ url('/modules/archive-management/js/deleted-posts.js') }}?v={{ $jsVersion }}"></script>
@endpush
