<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.favicon')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Check Your Email - SK OnePortal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    @vite([
        'app/Modules/Authentication/assets/css/auth-base.css',
        'app/Modules/Authentication/assets/css/fp-verify-email.css',
        'app/Modules/Authentication/assets/css/turnstile-gate.css',
        'app/Modules/Authentication/assets/js/turnstile-gate.js',
        'app/Modules/Authentication/assets/js/fp-verify-email.js',
    ])
</head>
<body>
    @include('authentication::partials.turnstile-gate', [
        'turnstileSubtitle' => 'Complete the security check to resend your reset link.',
    ])
    @auth
        <script>window.location.replace("{{ route('dashboard') }}");</script>
    @endauth
    <script>
        (function () {
            /* Prevent back-button from navigating away from this page */
            window.history.pushState(null, '', window.location.href);
            window.onpopstate = function () {
                window.history.pushState(null, '', window.location.href);
            };
        })();
    </script>

    {{-- ── Data block: backend passes the source-of-truth timestamp to the JS ── --}}
    <div id="fp-verify-data"
         data-email="{{ $email }}"
         data-resend-url="{{ route('password.verify-email.resend', [], false) }}"
         data-login-url="{{ route('login', [], false) }}"
         data-resend-available-at="{{ $resendAvailableAt }}"
         data-cooldown-secs="{{ $resendCooldownSecs }}"
         hidden
         aria-hidden="true"></div>

    <div class="login-page">
        <div class="bg-wrapper">
            <div class="bg-image"></div>
            <div class="gradient-overlay"></div>
        </div>

        <div class="login-container">

            {{-- LEFT: same dual-logo as login --}}
            <div class="logo-container">
                <div class="collab-logo-wrapper">
                    <div class="logo-glow-wrapper logo-left">
                        <img src="{{ asset('Images/SK_OnePortal_logo.png') }}"
                             alt="SK OnePortal Logo" class="collab-logo">
                    </div>
                    <div class="logo-glow-wrapper logo-right">
                        <img src="{{ asset('Images/SK_Federations_logo.jpg') }}"
                             alt="SK Federations Logo" class="collab-logo">
                    </div>
                </div>
                <h1 class="brand-title" style="white-space:nowrap;">SK OnePortal</h1>
                <p class="brand-subtitle" style="white-space:nowrap;">SK Federation Portal &ndash; Santa Cruz, Laguna</p>
            </div>

            {{-- RIGHT: verify-email card --}}
            <div class="login-form-container">
                <div class="login-card-inner">

                    {{-- Header --}}
                    <div class="fpve-header">
                        {{-- Envelope icon --}}
                        <div class="fpve-icon-wrap" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"
                                 stroke-linecap="round" stroke-linejoin="round">
                                <rect x="2" y="4" width="20" height="16" rx="2"/>
                                <path d="M22 7l-10 7L2 7"/>
                            </svg>
                        </div>
                        <p class="fpve-title">Check Your Email</p>
                        <p class="fpve-subtitle">
                            We sent a password reset link to<br>
                            <strong id="fpve-email">{{ $email }}</strong>
                        </p>
                    </div>

                    {{-- Status messages --}}
                    <div id="fpve-status" class="fpve-status" hidden role="alert"></div>

                    {{-- Resend section --}}
                    <div class="fpve-resend-section">

                        {{-- Countdown display --}}
                        <div id="fpve-cooldown" class="fpve-cooldown" hidden>
                            Resend available in&nbsp;<strong id="fpve-countdown">1:00</strong>
                        </div>

                        {{-- Resend button --}}
                        <button type="button" id="fpve-resend-btn" class="login-btn fpve-resend-btn" disabled>
                            <span id="fpve-resend-spinner" class="fpve-spinner" hidden aria-hidden="true"></span>
                            <span id="fpve-resend-label">Resend Reset Link</span>
                        </button>

                        {{-- Cancel button — redirects back to /forgot-password --}}
                        <a href="{{ route('password.request', [], false) }}"
                           class="fpve-cancel-btn">
                            Cancel
                        </a>

                    </div>

                    {{-- Footer --}}
                    <div class="form-footer">
                        <a href="{{ route('login', [], false) }}" class="back-link">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="2"
                                 style="vertical-align:middle;margin-right:4px;">
                                <path d="M19 12H5M12 19l-7-7 7-7"/>
                            </svg>
                            Back to Login
                        </a>
                    </div>

                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
