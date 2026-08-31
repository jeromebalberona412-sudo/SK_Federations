@extends('layout::app')

@section('title', 'Reports - SK OnePortal')

@push('styles')
    @vite(['app/Modules/Reports/assets/css/reports.css'])
@endpush

@section('content')
<div class="reports-page">
    <section class="reports-header">
        <div>
            <h1>Reports</h1>
            <p>Review program and activity reports uploaded by barangay SK Officials.</p>
        </div>
        <a href="{{ route('barangay.abyip') }}" class="reports-abyip-link">
            <i class="fas fa-file-invoice-dollar"></i> Barangay ABYIP Review
        </a>
    </section>

    <section class="reports-filters">
        <div class="reports-filter-item">
            <label for="reportsSearch">Search</label>
            <input type="search" id="reportsSearch" placeholder="Search program, activity, barangay, or file...">
        </div>
        <div class="reports-filter-item">
            <label for="reportsBarangayFilter">Barangay</label>
            <select id="reportsBarangayFilter">
                <option value="">All barangays</option>
            </select>
        </div>
        <div class="reports-filter-item">
            <label for="reportsStatusFilter">Status</label>
            <select id="reportsStatusFilter">
                <option value="">All statuses</option>
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
            </select>
        </div>
    </section>

    <section class="reports-table-card">
        <table class="reports-table">
            <thead>
                <tr>
                    <th>Barangay</th>
                    <th>Program</th>
                    <th>Activity</th>
                    <th>File</th>
                    <th>Date Uploaded</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="reportsTableBody">
                <tr><td colspan="7" class="reports-empty">Loading reports...</td></tr>
            </tbody>
        </table>
    </section>
</div>
@endsection

@push('scripts')
<script>
    window.reportsConfig = {
        listUrl: @json(route('api.reports.index')),
    };
</script>
@vite(['app/Modules/Reports/assets/js/reports.js'])
@endpush
