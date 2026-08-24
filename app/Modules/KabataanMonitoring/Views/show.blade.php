@extends('layout::app')

@section('title', 'Kabataan Profile - SK OnePortal')

@push('main-class')
    km-main
@endpush

@push('main-attributes')
    data-detail-base="{{ url('/kabataan-monitoring') }}" data-kabataan-slug="{{ $kabataan }}"
@endpush

@push('styles')
    <link rel="stylesheet" href="{{ url('/modules/kabataan-monitoring/css/kabataan-monitoring.css') }}">
@endpush

@section('content')
<div class="km-container">
            <section class="km-profile-hero" id="km-profile-hero"></section>

            <section class="km-detail-grid" id="km-detail-grid" hidden>
                <article class="km-panel">
                    <div class="km-panel-head">
                        <h2>Participation Metrics</h2>
                    </div>
                    <div class="km-metric-grid" id="km-metric-grid"></div>
                </article>

                <article class="km-panel">
                    <div class="km-panel-head">
                        <h2>Current Programs</h2>
                    </div>
                    <div class="km-list" id="km-program-list"></div>
                </article>

                <article class="km-panel">
                    <div class="km-panel-head">
                        <h2>Monitoring Recommendations</h2>
                    </div>
                    <ul class="km-reco-list" id="km-reco-list"></ul>
                </article>

                <article class="km-panel">
                    <div class="km-panel-head">
                        <h2>Intervention Timeline</h2>
                    </div>
                    <div class="km-timeline" id="km-timeline"></div>
                </article>
            </section>

            <section class="km-panel km-not-found" id="km-not-found" hidden>
                <div class="km-panel-head">
                    <h2>Profile Not Found</h2>
                    <p>The requested kabataan profile is not available in this prototype dataset.</p>
                </div>
                <a class="km-btn" href="{{ route('kabataan-monitoring') }}">
                    <i class="fas fa-arrow-left"></i> Back to Kabataan Monitoring
                </a>
            </section>
        </div>
@endsection

@push('scripts')
<script>
    window.kmPageMode = 'show';
    window.kmConfig = { dataUrl: @json(route('api.kabataan-monitoring.index')) };
</script>
<script src="{{ url('/shared/js/loading.js') }}"></script>
    <script src="{{ url('/modules/kabataan-monitoring/js/kabataan-monitoring.js') }}"></script>
@endpush
