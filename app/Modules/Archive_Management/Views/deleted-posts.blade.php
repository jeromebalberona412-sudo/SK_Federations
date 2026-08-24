@extends('layout::app')

@section('title', 'Deleted Posts')

@php
    $cssVersion = @filemtime(app_path('Modules/Archive_Management/assets/css/deleted-posts.css')) ?: time();
    $jsVersion = @filemtime(app_path('Modules/Archive_Management/assets/js/deleted-posts.js')) ?: time();
@endphp

@push('styles')
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

@endsection

@push('scripts')
    <script>
        window.FedArchiveConfig = {
            dataUrl: @json(route('archived.deleted-posts.data')),
            restoreUrl: @json(url('/archived/deleted-posts')),
            csrf: @json(csrf_token()),
        };
    </script>
    <script src="{{ url('/modules/archive-management/js/deleted-posts.js') }}?v={{ $jsVersion }}"></script>
@endpush
