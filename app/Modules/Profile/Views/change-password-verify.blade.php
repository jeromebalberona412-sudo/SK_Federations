@extends('profile::layouts.account-auth')

@section('title', 'Verify Password Change')

@section('card-class', 'ce-verify-card sk-fed-compact-card')

@push('styles')
    <link rel="stylesheet" href="{{ url('/modules/profile/css/change-password.css') }}?v={{ @filemtime(app_path('Modules/Profile/assets/css/change-password.css')) ?: time() }}">
@endpush

@push('scripts-before')
    <script>
        window.cpResendCooldown = {{ (int) $resendCooldown }};
    </script>
@endpush

@push('scripts')
    <script src="{{ url('/modules/profile/js/change-password-verify.js') }}?v={{ @filemtime(app_path('Modules/Profile/assets/js/change-password-verify.js')) ?: time() }}"></script>
@endpush

@section('content')
    <div id="cpVerifySection" data-status-url="{{ route('change-password.verify.status', [], false) }}" data-resend-url="{{ route('change-password.resend', [], false) }}" data-email="{{ $user->email }}">
        <div class="card-header">
            <h2 class="card-title">Verify Password Change</h2>
            <p class="card-subtitle">Check your email and tap the confirmation link. This page will detect it automatically.</p>
        </div>

        <div class="ce-verify-content">
            <div class="cp-listening-badge" id="cpListeningBadge">
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

            @if (session('status'))
                <div class="ce-info-box">{{ session('status') }}</div>
            @endif

            <div class="ce-info-box" id="cpInfoBox">
                A confirmation link has been sent to <strong>{{ $user->email }}</strong>. Your current password stays active until you verify. After confirming on any device, you will be signed out automatically on this page.
            </div>

            <div class="ce-status-table">
                <div class="ce-status-row">
                    <span class="ce-status-key">Account email</span>
                    <span class="ce-status-val">{{ $user->email }}</span>
                </div>
                <div class="ce-status-row">
                    <span class="ce-status-key">Status</span>
                    <span class="ce-status-val"><span class="ce-badge-awaiting" id="cpStatusBadge">Awaiting verification</span></span>
                </div>
            </div>

            <div class="ce-resend-timer" id="cpTimer" @if($resendCooldown <= 0) style="display:none;" @endif>
                Resend available in <strong id="cpTimerCount">{{ $resendCooldown > 0 ? sprintf('%d:%02d', intdiv($resendCooldown, 60), $resendCooldown % 60) : '1:00' }}</strong>
            </div>

            <div class="ce-actions" id="cpActions">
                <form action="{{ route('change-password.resend') }}" method="POST" id="cpResendForm">
                    @csrf
                    <button type="button" class="ce-btn-resend" id="cpResendBtn" @if($resendCooldown > 0) disabled @endif>Resend Verification</button>
                </form>
                <form action="{{ route('change-password.cancel') }}" method="POST" id="cpCancelForm">
                    @csrf
                    <button type="submit" class="ce-btn-cancel" id="cpCancelBtn">Cancel Request</button>
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
