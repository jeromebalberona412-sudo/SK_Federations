@extends('layout::app')

@section('title', 'Kabataan Monitoring - SK OnePortal')

@push('main-class')
    km-main
@endpush

@push('main-attributes')
    data-detail-base="{{ url('/kabataan-monitoring') }}"
@endpush

@push('styles')
    <link rel="stylesheet" href="{{ url('/modules/kabataan-monitoring/css/kabataan-monitoring.css') }}?v={{ time() }}">
@endpush

@section('content')
<div class="km-container">

            <section class="km-page-header">
                <h1 class="km-page-title">Kabataan Monitoring</h1>
            </section>

            <section class="km-filter-bar" aria-label="Kabataan filters">
                <div class="km-search-group km-search-group--index">
                    <input
                        type="search"
                        id="km-search"
                        class="km-search-input"
                        placeholder="Search barangay"
                        aria-label="Search barangay"
                    >
                    <button type="button" class="km-search-btn" aria-label="Search barangay">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </section>

            <div id="km-brgy-cards"></div>
            <p id="km-empty" class="km-empty" hidden>No barangays match your search.</p>

        </div>
@endsection

@push('scripts')
<script>
    window.kmConfig = {
        dataUrl: @json(route('api.kabataan-monitoring.index')),
    };
</script>
    <script src="{{ url('/modules/kabataan-monitoring/js/kabataan-monitoring.js') }}?v={{ time() }}"></script>
@endpush
