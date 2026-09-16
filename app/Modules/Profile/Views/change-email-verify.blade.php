@extends('profile::layouts.account-auth')

@section('title', 'Verify Email Change')

@section('card-class', 'ce-verify-card sk-fed-compact-card')

@push('styles')
    @vite(['app/Modules/Profile/assets/css/change-email.css'])
@endpush

@push('scripts-before')
    <script>
        window.ceResendCooldown = {{ (int) $resendCooldown }};
        window.ceFreshVerification = {{ ($freshVerification ?? false) ? 'true' : 'false' }};
    </script>
@endpush

@push('scripts')
    @vite(['app/Modules/Profile/assets/js/change-email-verify.js'])
@endpush

@section('content')
    <div
        id="ceVerifySection"
        class="ce-verify-shell"
        data-status-url="{{ route('change-email.verify.status', [], false) }}"
        data-dashboard-url="{{ route('dashboard', [], false) }}"
        data-success-url="{{ route('change-email.success', [], false) }}"
    >
        <div class="card-header">
            <h2 class="card-title">Verify Email Change</h2>
            <p class="card-subtitle">We sent a confirmation link to your new email address. This page will take you to the dashboard after it is confirmed.</p>
        </div>

        <div class="ce-verify-content">
            <div class="cp-listening-badge" id="ceListeningBadge">
                <span class="cp-listening-dot"></span>
                Listening for email confirmation…
            </div>

            @if ($errors->any())
                <div class="sk-alert sk-alert-error">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            @if (session('error'))
                <div class="sk-alert sk-alert-error">{{ session('error') }}</div>
            @endif

            @if (session('status'))
                <div class="ce-info-box">{{ session('status') }}</div>
            @endif

            <div class="ce-info-box" id="ceInfoBox">
                A confirmation link has been sent to <strong id="cePendingEmail">{{ $pendingEmail ?? $user->pending_email }}</strong>. Your current email stays active until you verify the new one. After you confirm, this page will take you to the dashboard. The email link itself only shows a success page.
            </div>

            <div class="ce-status-table">
                <div class="ce-status-row">
                    <span class="ce-status-key">Current email</span>
                    <span class="ce-status-val" id="ceCurrentEmailVal">{{ $currentEmail ?? $user->email }}</span>
                </div>
                <div class="ce-status-row">
                    <span class="ce-status-key">Pending email</span>
                    <span class="ce-status-val" id="cePendingEmailVal">{{ $pendingEmail ?? $user->pending_email }}</span>
                </div>
                <div class="ce-status-row">
                    <span class="ce-status-key">Status</span>
                    <span class="ce-status-val"><span class="ce-badge-awaiting" id="ceStatusBadge">Awaiting verification</span></span>
                </div>
            </div>

            <div class="ce-resend-timer" id="ceTimer" @if($resendCooldown <= 0) style="display:none;" @endif>
                Resend available in <strong id="ceTimerCount">{{ $resendCooldown > 0 ? sprintf('%d:%02d', intdiv($resendCooldown, 60), $resendCooldown % 60) : '1:00' }}</strong>
            </div>

            <div class="ce-actions">
                <form action="{{ route('change-email.resend') }}" method="POST" id="ceResendForm">
                    @csrf
                    <button type="submit" class="ce-btn-resend" id="ceResendBtn" @if($resendCooldown > 0) disabled @endif>Resend Verification</button>
                </form>
                <form action="{{ route('change-email.cancel') }}" method="POST" id="ceCancelForm">
                    @csrf
                    <button type="submit" class="ce-btn-cancel" id="ceCancelBtn">Cancel Request</button>
                </form>
            </div>

            <div class="youth-register-section ce-back-section">
                <form action="{{ route('change-email.cancel') }}" method="POST" id="ceBackToProfileForm" class="ce-back-form">
                    @csrf
                    <input type="hidden" name="redirect" value="profile">
                    <button type="submit" class="back-link ce-back-btn">Back to Profile</button>
                </form>
            </div>
        </div>
    </div>
@endsection
