@if(!empty($turnoverModal['show']))
@php
    $tm = $turnoverModal;
    $activeTurnover = $tm['active_turnover'] ?? null;
    $context = $tm['context'] ?? [];
    $progress = $tm['progress'] ?? [];
    $barangays = $tm['barangays'] ?? collect();
    $today = now()->toDateString();
    $termEndDefault = now()->addYears(4)->toDateString();
    $dobMin = now()->subYears(24)->toDateString();
    $dobMax = now()->subYears(18)->toDateString();
    $canClose = !($tm['portal_locked'] ?? false);
@endphp

<div id="federationTurnoverOverlay" class="to-overlay {{ ($tm['portal_locked'] ?? false) ? 'to-overlay--locked' : '' }}" aria-hidden="false">
    <div class="to-modal" id="federationTurnoverModal" role="dialog" aria-modal="true" aria-labelledby="toModalTitle">
        <div class="to-modal-header">
            <div class="to-modal-header-text">
                <span class="to-modal-eyebrow"><i class="fas fa-exchange-alt"></i> Federation Turnover</span>
                <h2 id="toModalTitle">Transfer Federation Leadership</h2>
                <p>Securely transfer Federation President and Vice President administrative access to newly elected officers.</p>
            </div>
            <div class="to-modal-controls">
                <button type="button" class="to-win-btn" id="toModalToggleSizeBtn" title="Maximize" aria-label="Maximize">
                    <i class="fas fa-expand" id="toModalToggleSizeIcon" aria-hidden="true"></i>
                </button>
                @if($canClose)
                    <button type="button" class="to-win-btn to-win-btn-close" id="toModalCloseBtn" title="Close" aria-label="Close">&times;</button>
                @endif
            </div>
        </div>

        <div class="to-modal-body">
            @if(($tm['portal_locked'] ?? false))
                <div class="to-alert to-alert--danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <span>Your federation term has ended. Complete turnover to restore full portal access.</span>
                </div>
            @elseif(($context['leadership']['term_end'] ?? $context['term_end'] ?? null))
                @php $termEndDisplay = $context['leadership']['term_end'] ?? $context['term_end']; @endphp
                <div class="to-alert to-alert--info">
                    <i class="fas fa-info-circle"></i>
                    <span>Current term ends on {{ \Carbon\Carbon::parse($termEndDisplay)->format('F j, Y') }}. Register incoming officers to begin turnover.</span>
                </div>
            @endif

            @if(!empty($progress))
            <div class="turnover-progress-steps to-progress">
                @foreach($progress as $stage)
                    <div class="turnover-progress-step turnover-progress-step--{{ $stage['status'] }}">
                        <div class="turnover-progress-dot"></div>
                        <span>{{ $stage['label'] }}</span>
                    </div>
                @endforeach
            </div>
            @endif

            @if($activeTurnover && $activeTurnover->status === 'pending_registration')
                <div class="to-reg-mode-tabs" id="turnoverRegModeTabs">
                    <button type="button" class="to-reg-mode-tab active" data-mode="manual"><i class="fas fa-keyboard"></i> Manual Entry</button>
                    <button type="button" class="to-reg-mode-tab" data-mode="batch"><i class="fas fa-file-excel"></i> Batch Upload (Excel)</button>
                </div>

                <div id="turnoverManualPane">
                <div class="turnover-wizard" id="turnoverWizard">
                    <div class="turnover-wizard-steps">
                        <button type="button" class="turnover-wizard-tab active" data-step="1">1. President</button>
                        <button type="button" class="turnover-wizard-tab" data-step="2">2. Vice President</button>
                        <button type="button" class="turnover-wizard-tab" data-step="3">3. Review</button>
                    </div>

                    <form id="turnoverRegistrationForm" novalidate>
                        @csrf
                        @include('turnover::partials.officer-form', [
                            'prefix' => 'president',
                            'title' => 'Incoming Federation President',
                            'step' => 1,
                            'barangays' => $barangays,
                            'term_start_default' => $today,
                            'term_end_default' => $termEndDefault,
                            'dob_min' => $dobMin,
                            'dob_max' => $dobMax,
                        ])
                        @include('turnover::partials.officer-form', [
                            'prefix' => 'vice_president',
                            'title' => 'Incoming Federation Vice President',
                            'step' => 2,
                            'barangays' => $barangays,
                            'term_start_default' => $today,
                            'term_end_default' => $termEndDefault,
                            'dob_min' => $dobMin,
                            'dob_max' => $dobMax,
                        ])

                        <div class="turnover-form-step" data-step="3" hidden>
                            <h3 class="turnover-section-title">Review Registration</h3>
                            <p class="turnover-help-text">Account setup emails will be sent to both officers after submission.</p>
                            <div id="turnoverReviewSummary" class="turnover-review-summary"></div>
                        </div>

                        <div class="turnover-form-actions">
                            <button type="button" class="btn-secondary-modern" id="turnoverPrevBtn" hidden>Back</button>
                            <button type="button" class="to-btn-primary" id="turnoverNextBtn">Next</button>
                            <button type="submit" class="to-btn-primary" id="turnoverSubmitBtn" hidden>Submit Registration</button>
                        </div>
                    </form>
                </div>
                </div>

                @include('turnover::partials.batch-upload')
            @elseif($activeTurnover && in_array($activeTurnover->status, ['pending_account_setup', 'pending_confirmation']))
                <div class="turnover-status-panel">
                    <div class="turnover-status-icon"><i class="fas fa-hourglass-half"></i></div>
                    <h3>{{ $activeTurnover->status === 'pending_account_setup' ? 'Pending Account Setup' : 'Pending Confirmation' }}</h3>
                    <p>Incoming officers must set their password before turnover can be finalized.</p>
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
                    <div class="turnover-status-icon"><i class="fas fa-user-shield"></i></div>
                    <h3>Start Federation Turnover</h3>
                    <p>Register the incoming Federation President and Vice President to begin the secure turnover process.</p>
                    <button type="button" class="to-btn-primary" id="startTurnoverBtn">Start Turnover Process</button>
                </div>
            @endif

            @if($activeTurnover && $activeTurnover->status === 'pending_confirmation' && ($context['show_complete_card'] ?? false))
                <div class="to-complete-section">
                    <div class="to-alert to-alert--success">
                        <i class="fas fa-check-double"></i>
                        <span>Both incoming officers completed account setup. You may now transfer administrative access.</span>
                    </div>
                    <button type="button" class="btn-danger-modern" id="openCompleteTurnoverModal">Complete Turnover</button>
                </div>
            @endif
        </div>
    </div>
</div>

@include('turnover::partials.complete-modal')
<div id="turnoverToast" class="turnover-toast" hidden></div>

<script>
    window.turnoverConfig = {
        showModal: true,
        portalLocked: @json($tm['portal_locked'] ?? false),
        canClose: @json($canClose),
        registerUrl: @json(route('turnover.register')),
        startUrl: @json(route('turnover.start')),
        completeUrl: @json($activeTurnover ? route('turnover.complete', $activeTurnover) : ''),
        csrfToken: @json(csrf_token()),
        today: @json($today),
        termEndDefault: @json($termEndDefault),
        dobMin: @json($dobMin),
        dobMax: @json($dobMax),
        batchTemplateUrl: @json(route('turnover.batch-template')),
        barangays: @json($barangays->map(fn ($b) => ['id' => $b->id, 'name' => $b->name])->values()),
        batchMaxRows: 2,
    };
</script>
@endif
