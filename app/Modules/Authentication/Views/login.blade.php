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
    <title>SK Federation Portal - OnePortal Santa Cruz</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    {{-- Critical fallback if Vite build CSS fails to load on production --}}
    <style id="login-critical-css">
        .label-icon{width:18px!important;height:18px!important;max-width:18px!important;max-height:18px!important;display:inline-block;vertical-align:-0.15em;flex-shrink:0;}
        .pw-toggle-btn svg,.pw-eye{width:22px!important;height:22px!important;max-width:22px!important;max-height:22px!important;}
        .collab-logo{width:120px;height:120px;max-width:42vw;max-height:42vw;object-fit:contain;border-radius:50%;background:#fff;display:block;}
        .auth-legal-link{display:inline;padding:0;border:0;background:none;color:#2563eb;text-decoration:underline;font:inherit;cursor:pointer;-webkit-appearance:none;appearance:none;}
        .pw-toggle-btn{position:absolute;right:10px;top:50%;transform:translateY(-50%);border:0;background:transparent;padding:4px;line-height:0;cursor:pointer;}
        .password-input-container{position:relative;}
        .form-group label{display:flex;align-items:center;gap:8px;font-weight:600;margin-bottom:0.4rem;}
    </style>
    @vite([
        'app/Modules/Authentication/assets/css/auth-base.css',
        'app/Modules/Authentication/assets/css/login.css',
        'app/Modules/Authentication/assets/css/turnstile-gate.css',
        'app/Modules/Authentication/assets/css/auth-legal.css',
        'app/Modules/Authentication/assets/js/auth-legal.js',
        'app/Modules/Authentication/assets/js/turnstile-gate.js',
        'app/Modules/Authentication/assets/js/login.js',
    ])
</head>
<body>

    @include('authentication::partials.turnstile-gate', [
        'turnstileSubtitle' => 'Complete the security check to continue logging in.',
    ])

    <script>
        (function() {
            @auth
                window.location.replace("{{ route('dashboard') }}");
            @endauth
            window.history.pushState(null, "", window.location.href);
            window.onpopstate = function() {
                window.history.pushState(null, "", window.location.href);
            };
        })();
    </script>

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

            {{-- LEFT: Logo & Branding --}}
            <div class="logo-container">
                <div class="collab-logo-wrapper">
                    <div class="logo-glow-wrapper logo-left">
                        <img src="{{ asset('Images/SK_OnePortal_logo.png') }}" alt="SK OnePortal Logo" class="collab-logo" width="210" height="210">
                    </div>
                    <div class="logo-glow-wrapper logo-right">
                        <img src="{{ asset('Images/SK_Federations_logo.jpg') }}" alt="SK Federations Logo" class="collab-logo" width="210" height="210">
                    </div>
                </div>
                <h1 class="brand-title">SK OnePortal</h1>
                <p class="brand-subtitle">SK Federation Portal &ndash; Santa Cruz, Laguna</p>
            </div>

            {{-- RIGHT: Login Card --}}
            <div class="login-form-container">
                <div class="login-card-inner">
                    <div class="form-header">
                        <p style="font-size:1.25rem;font-weight:800;color:#0f172a;letter-spacing:-0.01em;margin:0;text-align:center;">
                            Login to your account
                        </p>
                    </div>

                    <form method="POST"
                          action="{{ route('login', [], false) }}"
                          class="login-form"
                          id="loginForm"
                          novalidate
                          @if(config('services.turnstile.enabled') && config('services.turnstile.site_key'))
                              data-turnstile-enabled="true"
                              data-turnstile-sitekey="{{ config('services.turnstile.site_key') }}"
                              data-turnstile-required="{{ (!empty($turnstileRequired) || session('turnstile_required')) ? '1' : '0' }}"
                          @else
                              data-turnstile-required="0"
                          @endif>
                        @csrf

                        @if (session('access_denied'))
                            <div class="alert alert-danger access-denied-alert" role="alert">
                                <strong>{{ session('access_denied.title', 'Access Denied') }}</strong>
                                <p style="margin:0.35rem 0 0;">{{ session('access_denied.message') }}</p>
                            </div>
                        @endif

                        @error('session')
                            <div class="alert alert-warning access-denied-alert" role="alert">
                                <strong>Session Expired</strong>
                                <p style="margin:0.35rem 0 0;">{{ $message }}</p>
                            </div>
                        @enderror

                        @error('verification')
                            <div class="alert alert-warning access-denied-alert" role="alert">
                                <strong>Verification Required</strong>
                                <p style="margin:0.35rem 0 0;">{{ $message }}</p>
                            </div>
                        @enderror

                        @error('auth')
                            <div class="alert alert-danger access-denied-alert" role="alert">
                                <strong>Login Not Available</strong>
                                <p style="margin:0.35rem 0 0;">{{ $message }}</p>
                            </div>
                        @enderror

                        @if ($errors->has('email') || $errors->has('password') || $errors->has('cf-turnstile-response'))
                            <div class="alert alert-danger" role="alert">
                                <svg style="width:18px;height:18px;flex-shrink:0;vertical-align:middle;margin-right:6px;" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                </svg>
                                {{ $errors->first('cf-turnstile-response') ?: ($errors->first('email') ?: $errors->first('password')) }}
                            </div>
                        @endif

                        <div class="form-group">
                            <label for="email">
                                <svg class="label-icon" width="18" height="18" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/>
                                    <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/>
                                </svg>
                                Email Address
                            </label>
                            <input type="email" id="email" name="email" class="form-control"
                                   value="{{ old('email') }}" autofocus autocomplete="email"
                                   placeholder="Enter your email" maxlength="150">
                            <div class="invalid-feedback fed-field-error" id="email-error" style="display:none;"></div>
                        </div>

                        <div class="form-group">
                            <label for="password">
                                <svg class="label-icon" width="18" height="18" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                                </svg>
                                Password
                            </label>
                            <div class="password-input-container">
                                <input type="password" id="password" name="password" class="form-control"
                                       autocomplete="current-password" placeholder="Enter your password" maxlength="64">
                                <button type="button" class="pw-toggle-btn" id="pwToggleBtn" aria-label="Show password" tabindex="-1">
                                    <svg class="pw-eye pw-eye-show" width="22" height="22" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </svg>
                                    <svg class="pw-eye pw-eye-hide" width="22" height="22" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
                                        <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
                                        <path d="M1 1l22 22"/>
                                    </svg>
                                </button>
                            </div>
                            <div class="invalid-feedback fed-field-error" id="password-error" style="display:none;"></div>
                        </div>

                        <div class="form-options">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="remember" name="remember" value="1">
                                <label class="form-check-label" for="remember">Remember me</label>
                            </div>
                            <a href="{{ url('/forgot-password') }}" class="forgot-password" id="forgotBtn">Forgot Password</a>
                        </div>

                        @include('authentication::partials.login-legal-consent')

                        @php
                            $fedLoginErr = session('login_error', '');
                            $isFedTurnstileErr = $fedLoginErr && (
                                str_contains(strtolower($fedLoginErr), 'pagpapatunay') ||
                                str_contains(strtolower($fedLoginErr), 'verification') ||
                                str_contains(strtolower($fedLoginErr), 'turnstile') ||
                                str_contains(strtolower($fedLoginErr), 'security check')
                            );
                        @endphp
                        @if($isFedTurnstileErr && config('services.turnstile.enabled') && config('services.turnstile.site_key'))
                            <div id="turnstile-server-error" style="display:none;" aria-hidden="true">{{ $fedLoginErr }}</div>
                        @endif

                        <button type="submit" class="login-btn btn btn-primary w-100" id="loginBtn">
                            <span id="loginBtnText">Login</span>
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </div>

    @include('authentication::partials.legal-modals')

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
@if (session('verification_wait') && session()->has('sk_fed_email_verification_pending'))
    <script>window.location.replace("{{ route('skfed.verification.wait', [], false) }}");</script>
@endif
</html>
