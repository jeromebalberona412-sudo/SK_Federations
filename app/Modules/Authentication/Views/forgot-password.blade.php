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
    <title>Reset Your Password - SK OnePortal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    @vite([
        'app/Modules/Authentication/assets/css/auth-base.css',
        'app/Modules/Authentication/assets/css/forgot-password.css',
        'app/Modules/Authentication/assets/css/turnstile-gate.css',
        'app/Modules/Authentication/assets/js/turnstile-gate.js',
        'app/Modules/Authentication/assets/js/forgot-password.js',
    ])
</head>
<body>
    @include('authentication::partials.turnstile-gate', [
        'turnstileSubtitle' => 'Complete the security check to send a reset link.',
    ])
    @auth
        <script>window.location.replace("{{ route('dashboard') }}");</script>
    @endauth
    <script>
        (function() {
            window.history.pushState(null, "", window.location.href);
            window.onpopstate = function() {
                window.history.pushState(null, "", window.location.href);
            };
        })();
    </script>

    @php
        $resetEmail = old('email', '');
    @endphp

    <div class="login-page">

        {{-- Background wrapper kept for HTML compatibility � bg-image is hidden via CSS --}}
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

            {{-- RIGHT: Forgot Password Card --}}
            <div class="login-form-container">
                <div class="login-card-inner">

                    {{-- Card header: centered bold title --}}
                    <div class="form-header" style="text-align:center;margin-bottom:1.75rem;">
                        <p style="font-size:1.35rem;font-weight:800;color:#0f172a;letter-spacing:-0.01em;margin:0 0 0.5rem;">
                            Forgot Password
                        </p>
                        <p style="font-size:0.875rem;color:#64748b;font-weight:400;margin:0;line-height:1.55;">
                            Enter your email address and we'll send you a link to reset your password.
                        </p>
                    </div>

                    @if ($errors->any())
                        <div class="alert alert-danger" role="alert" style="margin-bottom:20px;border-radius:12px;">
                            @if ($errors->has('cf-turnstile-response'))
                                {{ $errors->first('cf-turnstile-response') }}
                            @else
                                {{ $errors->first() }}
                            @endif
                        </div>
                    @endif

                    <form id="forgotPasswordForm"
                          class="login-form"
                          method="POST"
                          action="{{ route('password.email', [], false) }}"
                          data-email-sent="0"
                          data-cooldown-key=""
                          novalidate>
                        @csrf

                        <div class="form-group">
                            <label for="email">
                                <svg class="label-icon" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/>
                                    <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/>
                                </svg>
                                Email Address
                            </label>
                            <input
                                type="email"
                                id="email"
                                name="email"
                                class="form-control @error('email') is-invalid @enderror"
                                required
                                placeholder="Enter your email"
                                maxlength="100"
                                autocomplete="email"
                                value="{{ old('email') }}"
                                autofocus
                            >
                            <div class="invalid-feedback" id="email-error"
                                 @if(!$errors->has('email')) hidden @endif>
                                {{ $errors->first('email') }}
                            </div>
                        </div>

                        <button type="submit" class="login-btn btn btn-primary w-100" id="submitBtn">
                            <span id="fpBtnText">Send Reset Link</span>
                        </button>

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
