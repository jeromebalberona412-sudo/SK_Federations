@extends('layout::app')

@section('title', 'Federation Turnover')

@push('styles')
    <link rel="stylesheet" href="{{ url('/modules/turnover/css/turnover.css') }}?v={{ $cssVersion }}">
@endpush

@section('content')
<div class="page-header page-header-modern-with-button">
    <div>
        <h1>Federation Turnover</h1>
        <p>Securely transfer Federation President and Vice President administrative access to newly elected officers.</p>
    </div>
</div>

@if(!empty($progress))
<div class="content-card turnover-progress-card">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-tasks"></i> Turnover Progress</h2>
    </div>
    <div class="card-body">
        <div class="turnover-progress-steps">
            @foreach($progress as $stage)
                <div class="turnover-progress-step turnover-progress-step--{{ $stage['status'] }}">
                    <div class="turnover-progress-dot"></div>
                    <span>{{ $stage['label'] }}</span>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endif

<div class="content-card" id="turnoverRegistrationCard">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-user-plus"></i> Register Incoming Officers</h2>
    </div>
    <div class="card-body">
        @if($activeTurnover && $activeTurnover->status === 'pending_registration')
            <div class="turnover-wizard" id="turnoverWizard">
                <div class="turnover-wizard-steps">
                    <button type="button" class="turnover-wizard-tab active" data-step="1">1. President</button>
                    <button type="button" class="turnover-wizard-tab" data-step="2">2. Vice President</button>
                    <button type="button" class="turnover-wizard-tab" data-step="3">3. Review</button>
                </div>

                <form id="turnoverRegistrationForm" novalidate>
                    @csrf
                    @include('turnover::partials.officer-form', ['prefix' => 'president', 'title' => 'Incoming Federation President', 'step' => 1, 'barangays' => $barangays])
                    @include('turnover::partials.officer-form', ['prefix' => 'vice_president', 'title' => 'Incoming Federation Vice President', 'step' => 2, 'barangays' => $barangays])

                    <div class="turnover-form-step" data-step="3" hidden>
                        <h3 class="turnover-section-title">Review Registration</h3>
                        <p class="turnover-help-text">Please review the information before submitting. Account setup emails will be sent to both officers.</p>
                        <div id="turnoverReviewSummary" class="turnover-review-summary"></div>
                    </div>

                    <div class="turnover-form-actions">
                        <button type="button" class="btn-secondary-modern" id="turnoverPrevBtn" hidden>Back</button>
                        <button type="button" class="btn-primary-modern" id="turnoverNextBtn">Next</button>
                        <button type="submit" class="btn-primary-modern" id="turnoverSubmitBtn" hidden>Submit Registration</button>
                    </div>
                </form>
            </div>
        @elseif($activeTurnover && in_array($activeTurnover->status, ['pending_account_setup', 'pending_confirmation']))
            <div class="turnover-status-panel">
                <div class="turnover-status-icon"><i class="fas fa-hourglass-half"></i></div>
                <h3>
                    @if($activeTurnover->status === 'pending_account_setup')
                        Pending Account Setup
                    @else
                        Pending Confirmation
                    @endif
                </h3>
                <p>Incoming officers have been registered. They must set their password before turnover can be finalized.</p>
                @if($activeTurnover->status === 'pending_account_setup')
                    <div class="to-alert to-alert--info to-turnover-setup-hint">
                        <i class="fas fa-info-circle"></i>
                        <span>The original setup email is valid for <strong>24 hours</strong>. If it expired, incoming officers can use <strong>Forgot Password</strong> on the <a href="{{ route('password.request', [], false) }}" class="to-inline-link">Federation login page</a> with their registered email to complete account setup.</span>
                    </div>
                @endif
                @if($activeTurnover->registrations?->count())
                    <ul class="turnover-registration-list">
                        @foreach($activeTurnover->registrations as $registration)
                            <li>
                                <strong>{{ $registration->position }}</strong> — {{ $registration->display_complete_name }}
                                <span class="turnover-badge turnover-badge--{{ $registration->status }}">{{ $registration->status_label }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @else
            <div class="turnover-empty-state">
                <i class="fas fa-exchange-alt"></i>
                @if($context['show_start_notice'] ?? false)
                    <h3>Start Federation Turnover</h3>
                    <p>The current federation term is about to end. Begin the turnover process to register incoming Federation President and Vice President.</p>
                    <button type="button" class="btn-primary-modern" id="startTurnoverBtn">Start Turnover Process</button>
                @else
                    <h3>No Active Turnover</h3>
                    <p>Start turnover from the dashboard when your federation term is nearing its end.</p>
                    <a href="{{ route('dashboard') }}" class="btn-primary-modern">Back to Dashboard</a>
                @endif
            </div>
        @endif
    </div>
</div>

@if($activeTurnover && $activeTurnover->status === 'pending_confirmation' && ($context['show_complete_card'] ?? false))
<div class="content-card turnover-complete-card" id="turnoverCompleteCard">
    <div class="card-body">
        <div class="turnover-notice turnover-notice--complete">
            <div class="turnover-notice-icon"><i class="fas fa-check-double"></i></div>
            <div class="turnover-notice-body">
                <h3>Complete Federation Turnover</h3>
                <p>Both incoming Federation Officers have completed account setup. You may now transfer administrative access.</p>
            </div>
            <button type="button" class="btn-danger-modern" id="openCompleteTurnoverModal">Complete Turnover</button>
        </div>
    </div>
</div>
@endif

@include('turnover::partials.complete-modal')

<div id="turnoverToast" class="turnover-toast" hidden></div>
@endsection

@push('scripts')
    <script>
        window.turnoverConfig = {
            registerUrl: @json(route('turnover.register')),
            startUrl: @json(route('turnover.start')),
            completeUrl: @json($activeTurnover ? route('turnover.complete', $activeTurnover) : ''),
            csrfToken: @json(csrf_token()),
        };
    </script>
    <script src="{{ url('/modules/turnover/js/turnover.js') }}?v={{ $jsVersion }}"></script>
@endpush
