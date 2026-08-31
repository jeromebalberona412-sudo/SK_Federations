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
    <title>Set New Password - SK OnePortal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    @vite([
        'app/Modules/Authentication/assets/css/auth-base.css',
        'app/Modules/Authentication/assets/css/login.css',
        'app/Modules/Authentication/assets/css/reset-password.css',
        'app/Modules/Authentication/assets/js/reset-password.js',
    ])
    <link rel="stylesheet" href="{{ url('/shared/css/loading.css') }}">
</head>
<body class="auth-page-reset">
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
            <div class="logo-container">
                <div class="collab-logo-wrapper">
                    <div class="logo-glow-wrapper logo-left">
                        <img src="{{ asset('Images/SK_OnePortal_logo.png') }}" alt="SK OnePortal Logo" class="collab-logo">
                    </div>
                    <div class="logo-glow-wrapper logo-right">
                        <img src="{{ asset('Images/SK_Federations_logo.jpg') }}" alt="SK Federations Logo" class="collab-logo">
                    </div>
                </div>
                <h1 class="brand-title">SK OnePortal</h1>
                <p class="brand-subtitle">SK Federation Portal &ndash; Santa Cruz, Laguna</p>
            </div>

            <div class="login-form-container">
                <div class="login-card-inner">
                    <div class="form-header" style="text-align:center;margin-bottom:1.35rem;">
                        <p style="font-size:1.3rem;font-weight:800;color:#0f172a;letter-spacing:-0.01em;margin:0 0 0.4rem;">
                            Set New Password
                        </p>
                        <p style="font-size:0.9rem;color:#64748b;font-weight:400;margin:0;line-height:1.5;">
                            Create a new secure password for your account.
                        </p>
                    </div>

                    @if ($errors->has('email') || $errors->has('reset'))
                        <div class="alert alert-danger" role="alert" style="margin-bottom: 16px; border-radius: 12px;">
                            {{ $errors->first('email') ?: $errors->first('reset') }}
                        </div>
                    @endif

                    <form id="reset-password-form"
                          method="POST"
                          action="{{ route('password.update', [], false) }}"
                          class="login-form"
                          data-password-min-length="{{ (int) config('sk_fed_auth.password_reset.password.min_length', 8) }}"
                          data-password-max-length="{{ (int) config('sk_fed_auth.password_reset.password.max_length', 64) }}"
                          novalidate>
                        @csrf
                        <input type="hidden" name="token" value="{{ old('token', $token) }}">
                        <input type="hidden" name="email" value="{{ old('email', $email) }}">

                        <div class="form-group">
                            <label for="new-password">
                                <svg class="label-icon" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                                </svg>
                                New Password
                            </label>
                            <div class="password-input-container">
                                <input
                                    type="password"
                                    id="new-password"
                                    name="password"
                                    class="form-control @error('password') is-invalid @enderror"
                                    required
                                    placeholder="Enter new password"
                                    minlength="{{ (int) config('sk_fed_auth.password_reset.password.min_length', 8) }}"
                                    maxlength="{{ (int) config('sk_fed_auth.password_reset.password.max_length', 64) }}"
                                    autocomplete="new-password"
                                >
                                <button type="button" class="pw-toggle-btn" id="newPwToggleBtn" aria-label="Show password" tabindex="-1">
                                    <svg class="pw-eye pw-eye-show" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </svg>
                                    <svg class="pw-eye pw-eye-hide" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
                                        <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
                                        <path d="M1 1l22 22"/>
                                    </svg>
                                </button>
                            </div>
                            <ul class="password-rules" id="passwordRules" aria-live="polite" hidden>
                                <li id="rule-length">At least 8 characters</li>
                                <li id="rule-lowercase">At least one lowercase letter</li>
                                <li id="rule-uppercase">At least one uppercase letter</li>
                                <li id="rule-number">At least one number</li>
                                <li id="rule-special">At least one special character</li>
                            </ul>
                            @error('password')
                                <div class="invalid-feedback d-block" data-server-error="true">{{ $message }}</div>
                            @enderror
                            <div class="invalid-feedback d-block" id="new-password-error" style="display: none;"></div>
                        </div>

                        <div class="form-group">
                            <label for="confirm-password">
                                <svg class="label-icon" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                                </svg>
                                Confirm Password
                            </label>
                            <div class="password-input-container">
                                <input
                                    type="password"
                                    id="confirm-password"
                                    name="password_confirmation"
                                    class="form-control @error('password_confirmation') is-invalid @enderror"
                                    required
                                    placeholder="Confirm new password"
                                    minlength="{{ (int) config('sk_fed_auth.password_reset.password.min_length', 8) }}"
                                    maxlength="{{ (int) config('sk_fed_auth.password_reset.password.max_length', 64) }}"
                                    autocomplete="new-password"
                                >
                                <button type="button" class="pw-toggle-btn" id="confirmPwToggleBtn" aria-label="Show password" tabindex="-1">
                                    <svg class="pw-eye pw-eye-show" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </svg>
                                    <svg class="pw-eye pw-eye-hide" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
                                        <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
                                        <path d="M1 1l22 22"/>
                                    </svg>
                                </button>
                            </div>
                            @error('password_confirmation')
                                <div class="invalid-feedback d-block" data-server-error="true">{{ $message }}</div>
                            @enderror
                            <div class="invalid-feedback d-block" id="confirm-password-error" style="display: none;"></div>
                        </div>

                        <button type="submit" class="login-btn btn btn-primary w-100" id="resetSubmitBtn">
                            Set New Password
                        </button>
                    </form>

                    <div class="form-footer">
                        <a href="{{ route('login', [], false) }}" id="resetBackToLogin">Back to login</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ url('/shared/js/loading.js') }}"></script>
</body>
</html>
