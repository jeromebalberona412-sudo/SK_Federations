@extends('layout::app')

@section('title', 'Calendar - SK OnePortal')

@push('styles')
    @vite(['app/Modules/Calendar/assets/css/calendar.css'])
@endpush

@section('content')
    <div class="calendar-page-container">
        <section class="calendar-header-section">
            <div class="calendar-header-left">
                <h1 class="calendar-title">Calendar</h1>
                <p class="calendar-subtitle">View and annotate your monthly schedule.</p>
            </div>
            <div class="calendar-header-right">
                <span id="calendarMonthLabel" class="calendar-current-date"></span>
                <button type="button" id="calendarPrevBtn" class="calendar-nav-btn" aria-label="Previous">&laquo; Prev</button>
                <button type="button" id="calendarNextBtn" class="calendar-nav-btn" aria-label="Next">Next &raquo;</button>
                <button type="button" id="calendarJumpBtn" class="calendar-jump-btn" aria-label="Jump to date">Jump to date</button>
            </div>
        </section>

        <section class="calendar-main-section">
            <div class="calendar-legend">
                <span class="legend-item"><span class="legend-dot has-notes"></span>Day with notes</span>
                <span class="legend-item"><span class="legend-dot today"></span>Today</span>
            </div>

            <div class="calendar-grid" id="calendarGrid"></div>
        </section>
    </div>
@endsection

@push('scripts')
    <script>
        window.__SK_FED_CALENDAR_BOOT__ = @json($calendarBoot);
    </script>
    @vite(['app/Modules/Calendar/assets/js/calendar.js'])
@endpush
