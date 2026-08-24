@extends('profile::layouts.account-auth')

@section('title', 'Verify Email Change')

@section('card-class', 'ce-verify-card sk-fed-compact-card')

@push('styles')
    <link rel="stylesheet" href="{{ url('/modules/profile/css/change-email.css') }}?v={{ @filemtime(app_path('Modules/Profile/assets/css/change-email.css')) ?: time() }}">
@endpush

@push('scripts-before')
    <script>
        window.ceResendCooldown = {{ (int) $resendCooldown }};
        window.ceFreshVerification = {{ ($freshVerification ?? false) ? 'true' : 'false' }};
    </script>
@endpush

@push('scripts')
    <script src="{{ url('/modules/profile/js/change-email-verify.js') }}"></script>
@endpush

@section('content')
    <div id="ceVerifySection">
        <div class="card-header">
            <h2 class="card-title">Verify Email Change</h2>
            <p class="card-subtitle">We sent a confirmation link to your new email address.</p>
        </div>

        <div class="ce-verify-content">
            @if ($errors->any())
                <div class="sk-alert sk-alert-error">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            @if (session('status'))
                <div class="ce-info-box">{{ session('status') }}</div>
            @endif

            <div class="ce-info-box">
                A confirmation link has been sent to <strong id="cePendingEmail">{{ $user->pending_email }}</strong>. Your current email stays active until you verify the new one.
            </div>

            <div class="ce-status-table">
                <div class="ce-status-row">
                    <span class="ce-status-key">Current email</span>
                    <span class="ce-status-val" id="ceCurrentEmailVal">{{ $user->email }}</span>
                </div>
                <div class="ce-status-row">
                    <span class="ce-status-key">Pending email</span>
                    <span class="ce-status-val" id="cePendingEmailVal">{{ $user->pending_email }}</span>
                </div>
                <div class="ce-status-row">
                    <span class="ce-status-key">Status</span>
                    <span class="ce-status-val"><span class="ce-badge-awaiting">Awaiting verification</span></span>
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
                <p class="register-text">
                    <a href="{{ route('profile') }}" class="register-link">← Back to Profile</a>
                </p>
            </div>
        </div>
    </div>
@endsection
