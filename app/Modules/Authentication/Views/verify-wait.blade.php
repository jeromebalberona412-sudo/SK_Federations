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
    <title>Verify Your Email - SK OnePortal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    @vite([
        'app/Modules/Authentication/assets/css/auth-base.css',
        'app/Modules/Authentication/assets/css/verify-wait.css',
        'app/Modules/Authentication/assets/js/verify-wait.js',
    ])
    <link rel="stylesheet" href="{{ url('/shared/css/loading.css') }}">
</head>
<body>
    <div class="login-page">
        <div class="bg-wrapper">
            <div class="bg-image"></div>
            <div class="gradient-overlay"></div>
            <div class="floating-shapes">
                <div class="shape shape-1"></div>
                <div class="shape shape-2"></div>
                <div class="shape shape-3"></div>
            </div>
        </div>

        <div class="login-container">

            {{-- LEFT: same dual-logo layout as login page --}}
            <div class="logo-container">
                <div class="collab-logo-wrapper">
                    <div class="logo-glow-wrapper logo-left">
                        <img src="{{ asset('Images/SK_OnePortal_logo.png') }}"
                             alt="SK OnePortal Logo"
                             class="collab-logo">
                    </div>
                    <div class="logo-glow-wrapper logo-right">
                        <img src="{{ asset('Images/SK_Federations_logo.jpg') }}"
                             alt="SK Federations Logo"
                             class="collab-logo">
                    </div>
                </div>
                <h1 class="brand-title" style="white-space:nowrap;">SK OnePortal</h1>
                <p class="brand-subtitle" style="white-space:nowrap;">SK Federation Portal &ndash; Santa Cruz, Laguna</p>
            </div>

            {{-- RIGHT: Verify card --}}
            <div class="login-form-container">
                <div class="login-card-inner">

                    <div class="form-header" style="text-align:center;margin-bottom:1.5rem;">
                        <p style="font-size:1.35rem;font-weight:800;color:#0f172a;letter-spacing:-0.01em;margin:0 0 0.4rem;">
                            Verify Your Email
                        </p>
                        @php
                            $headerIntroSent = $emailSent ?? true;
                            $headerDeliveryFailed = $deliveryFailed ?? false;
                        @endphp
                        @if($headerDeliveryFailed && !$headerIntroSent)
                            <p style="font-size:0.875rem;color:#b91c1c;font-weight:500;margin:0;line-height:1.55;">
                                We couldn&rsquo;t reach your email provider right now. Your session is still valid;
                                use <strong>Resend Verification Email</strong> below to try again, or check spam if
                                a previous attempt delivered.
                            </p>
                        @else
                            <p style="font-size:0.875rem;color:#64748b;font-weight:400;margin:0;line-height:1.55;">
                                We sent a verification link to
                                <strong style="color:#213F99;">{{ $email }}</strong>.
                                Click the link in your email to continue.
                            </p>
                        @endif
                    </div>

                    <div class="verify-content"
                         data-status-url="{{ route('skfed.verification.wait.status', [], false) }}"
                         data-resend-url="{{ route('skfed.verification.resend', [], false) }}"
                         data-dashboard-url="{{ route('dashboard', [], false) }}"
                         data-email="{{ $email }}"
                         data-user-id="{{ (int) ($userId ?? 0) }}"
                         data-session-key="{{ $sessionKey ?? '' }}"
                         data-fresh-session="0"
                         data-resend-cooldown="{{ ($resendStarted ?? false) ? (int) $resendCooldown : 0 }}"
                         data-resend-just-sent="{{ ($resendStarted ?? false) ? '1' : '0' }}"
                         data-delivery-failed="{{ ($deliveryFailed ?? false) ? '1' : '0' }}"
                         data-email-sent="{{ ($emailSent ?? false) ? '1' : '0' }}">

                        @if($headerDeliveryFailed && !($emailSent ?? false))
                            <div id="verification-state" class="alert alert-warning" role="alert"
                                 style="border-radius:12px;font-size:0.9rem;text-align:center;">
                                Email delivery is temporarily unavailable. Use Resend to try again shortly.
                            </div>
                        @else
                            <div id="verification-state" class="alert alert-info" role="alert"
                                 style="border-radius:12px;font-size:0.9rem;text-align:center;">
                                Waiting for email verification...
                            </div>
                        @endif

                        @if ($errors->any())
                            <div class="alert alert-danger" role="alert" style="border-radius:12px;font-size:0.9rem;">
                                @foreach ($errors->all() as $error)
                                    <div>{{ $error }}</div>
                                @endforeach
                            </div>
                        @endif

                        @if (session('status'))
                            <div class="alert alert-success" role="alert" style="border-radius:12px;font-size:0.9rem;">
                                {{ session('status') }}
                            </div>
                        @endif

                        <div class="resend-section">
                            <div class="resend-status" id="resend-status" hidden></div>
                            <div class="resend-cooldown" id="resend-cooldown" style="display:none;">
                                Resend available in <strong id="resend-cooldown-count">1:00</strong>
                            </div>
                            <button type="button" class="login-btn btn-resend" id="resend-btn">
                                <span class="btn-resend-spinner" id="resend-btn-spinner" hidden></span>
                                <span class="btn-resend-label" id="resend-btn-label">Resend Verification Email</span>
                            </button>
                        </div>

                        <div class="form-footer">
                            <a href="{{ route('skfed.verification.cancel', [], false) }}" class="back-link">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="2" style="vertical-align:middle;margin-right:4px;">
                                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                                </svg>
                                Back to Login
                            </a>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        {{-- Success overlay shown briefly before dashboard redirect --}}
        <div class="success-modal-overlay" id="success-modal">
            <div class="success-modal">
                <div class="check-wrap" aria-hidden="true">
                    <span class="checkmark"></span>
                </div>
                <h2>Verified Successfully!</h2>
                <p>Redirecting to dashboard...</p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ url('/shared/js/loading.js') }}"></script>
</body>
</html>
