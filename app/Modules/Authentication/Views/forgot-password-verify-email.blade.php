<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.favicon')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Check Your Email - SK OnePortal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    @vite([
        'app/Modules/Authentication/assets/css/auth-base.css',
        'app/Modules/Authentication/assets/css/forgot-password.css',
        'app/Modules/Authentication/assets/js/forgot-password.js',
    ])
</head>
<body>
    @auth
        <script>window.location.replace("{{ route('dashboard') }}");</script>
    @endauth
    <script>
        (function() {
            {{-- Prevent browser back button from going back to /forgot-password --}}
            window.history.pushState(null, "", window.location.href);
            window.onpopstate = function() {
                window.history.pushState(null, "", window.location.href);
            };
        })();
    </script>

    @php
        $resetEmail   = session('password_reset_email', '');
        $statusMsg    = session('status', 'A password reset link has been sent to your email address.');
        // Scoped key: each email address gets its own independent cooldown timer
        $cooldownKey  = 'sk_fed_fp_cooldown_' . md5($resetEmail);
    @endphp

    <div class="login-page">
        <div class="bg-wrapper">
            <div class="bg-image"></div>
            <div class="gradient-overlay"></div>
        </div>

        <div class="login-container">

            {{-- LEFT: Logo & Branding --}}
            <div class="logo-container">
                <div class="collab-logo-wrapper">
                    <div class="logo-glow-wrapper logo-left">
                        <img src="{{ asset('Images/SK_OnePortal_logo.png') }}" alt="SK OnePortal Logo" class="collab-logo">
                    </div>
                    <div class="logo-glow-wrapper logo-right">
                        <img src="{{ asset('Images/SK_Federations_logo.jpg') }}" alt="SK Federations Logo" class="collab-logo">
                    </div>
                </div>
                <h1 class="brand-title" style="white-space:nowrap;">SK OnePortal</h1>
                <p class="brand-subtitle" style="white-space:nowrap;">SK Federation Portal &ndash; Santa Cruz, Laguna</p>
            </div>

            {{-- RIGHT: Check Email Card --}}
            <div class="login-form-container">
                <div class="login-card-inner">

                    <div class="form-header" style="text-align:center;margin-bottom:1.75rem;">
                        <p style="font-size:1.35rem;font-weight:800;color:#0f172a;letter-spacing:-0.01em;margin:0 0 0.5rem;">
                            Check Your Email
                        </p>
                        <p style="font-size:0.875rem;color:#64748b;font-weight:400;margin:0;line-height:1.55;">
                            We sent a password reset link to
                            @if($resetEmail)
                                <strong>{{ $resetEmail }}</strong>.
                            @else
                                your email address.
                            @endif
                        </p>
                    </div>

                    <div class="alert alert-info fp-success-alert" role="alert"
                         style="margin-bottom:20px;border-radius:12px;">
                        {{ $statusMsg }}
                    </div>

                    <form id="forgotPasswordForm"
                          class="login-form"
                          method="POST"
                          action="{{ route('password.email', [], false) }}"
                          data-email-sent="1"
                          data-cooldown-key="{{ $cooldownKey }}"
                          novalidate>
                        @csrf
                        <input type="hidden" name="email" id="email" value="{{ $resetEmail }}">

                        <button type="submit" class="login-btn btn btn-primary w-100" id="submitBtn">
                            <span id="fpBtnText">Resend Reset Link</span>
                        </button>

                        <p class="fp-cooldown-notice" id="fpCooldownNotice" hidden
                           style="text-align:center;margin-top:12px;font-size:0.9rem;color:#64748b;">
                            You can resend the link in <strong id="fpCooldownCount">60</strong>s.
                        </p>
                    </form>

                    <div class="form-footer">
                        <a href="{{ route('login', [], false) }}" class="back-link">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
