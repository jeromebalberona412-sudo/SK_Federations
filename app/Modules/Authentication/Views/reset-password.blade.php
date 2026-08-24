<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.favicon')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Set New Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ url('/modules/authentication/css/style.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ url('/shared/css/loading.css') }}">
</head>
<body>
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
        {{-- Background --}}
        <div class="bg-wrapper">
            <div class="bg-image"></div>
            <div class="gradient-overlay"></div>
            <div class="floating-shapes">
                <div class="shape shape-1"></div>
                <div class="shape shape-2"></div>
                <div class="shape shape-3"></div>
            </div>
        </div>

        {{-- Main Split Layout --}}
        <div class="login-container">

            {{-- LEFT: Logo & Branding --}}
            <div class="logo-container">
                <div class="collab-logo-wrapper">
                    <div class="logo-glow-wrapper logo-left">
                        <img src="{{ url('/modules/authentication/images/skoneportal_logo.webp') }}"
                             alt="SK OnePortal Logo"
                             class="collab-logo">
                    </div>
                    <div class="logo-glow-wrapper logo-right">
                        <img src="{{ url('/modules/authentication/images/Sk_Fed_logo.png') }}"
                             alt="SK Federations Logo"
                             class="collab-logo">
                    </div>
                </div>
                <h1 class="brand-title">SK OnePortal</h1>
                <p class="brand-subtitle">SK Federation Portal – Santa Cruz, Laguna</p>
            </div>

            {{-- RIGHT: Reset Password Card --}}
            <div class="login-form-container">
                <div class="login-card-inner">
                    <div class="form-header" style="text-align:center;margin-bottom:1.75rem;">
                        <p style="font-size:1.35rem;font-weight:800;color:#0f172a;letter-spacing:-0.01em;margin:0 0 0.5rem;">
                            Set New Password
                        </p>
                        <p style="font-size:0.875rem;color:#64748b;font-weight:400;margin:0;line-height:1.55;">
                            Create a new secure password for your account.
                        </p>
                    </div>

                    @if ($errors->has('email') || $errors->has('reset'))
                        <div class="alert alert-danger" role="alert" style="margin-bottom: 20px; border-radius: 12px; border: 1px solid #f87171;">
                            {{ $errors->first('email') ?: $errors->first('reset') }}
                        </div>
                    @endif

                    <form id="reset-password-form" method="POST" action="{{ route('password.update', [], false) }}" class="login-form" data-password-min-length="{{ (int) config('sk_fed_auth.password_reset.password.min_length', 8) }}" data-password-max-length="{{ (int) config('sk_fed_auth.password_reset.password.max_length', 64) }}" novalidate>
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

                        <button type="submit" class="login-btn btn btn-primary w-100">
                            Set New Password
                        </button>
                    </form>

                    <div class="form-footer">
                        <a href="{{ route('login', [], false) }}">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;">
                                <path d="M19 12H5M12 19l-7-7 7-7"/>
                            </svg>
                            Back to login
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ url('/shared/js/loading.js') }}"></script>
    <script>
        const resetPasswordForm = document.getElementById('reset-password-form');
        const newPasswordInput = document.getElementById('new-password');
        const confirmPasswordInput = document.getElementById('confirm-password');
        const newPasswordError = document.getElementById('new-password-error');
        const confirmPasswordError = document.getElementById('confirm-password-error');
        const passwordRules = document.getElementById('passwordRules');

        function clearError(input, errorElement) {
            input.classList.remove('is-invalid');
            errorElement.style.display = 'none';
            errorElement.textContent = '';
        }

        function showError(input, errorElement, message) {
            input.classList.add('is-invalid');
            errorElement.textContent = message;
            errorElement.style.display = 'block';
        }

        function validatePasswordStrength(password) {
            const minLength = Number.parseInt(resetPasswordForm.dataset.passwordMinLength || '8', 10);

            return {
                isValid: password.length >= minLength
                    && /[a-z]/.test(password)
                    && /[A-Z]/.test(password)
                    && /\d/.test(password)
                    && /[^A-Za-z0-9]/.test(password),
                hasMinLength: password.length >= minLength,
                hasLowerCase: /[a-z]/.test(password),
                hasUpperCase: /[A-Z]/.test(password),
                hasNumber: /\d/.test(password),
                hasSpecial: /[^A-Za-z0-9]/.test(password),
            };
        }

        function updatePasswordRules(password) {
            if (!passwordRules) {
                return;
            }

            const state = validatePasswordStrength(password);
            [
                { id: 'rule-length', ok: state.hasMinLength },
                { id: 'rule-lowercase', ok: state.hasLowerCase },
                { id: 'rule-uppercase', ok: state.hasUpperCase },
                { id: 'rule-number', ok: state.hasNumber },
                { id: 'rule-special', ok: state.hasSpecial },
            ].forEach(function (rule) {
                const node = document.getElementById(rule.id);
                if (node) {
                    node.classList.toggle('ok', rule.ok);
                    node.classList.toggle('valid', rule.ok);
                }
            });

            const showRules = password.length > 0 && !state.isValid;
            passwordRules.hidden = !showRules;
            passwordRules.classList.toggle('active', showRules);
        }

        newPasswordInput.addEventListener('input', function() {
            clearError(this, newPasswordError);
            updatePasswordRules(this.value);
        });

        confirmPasswordInput.addEventListener('input', function() {
            clearError(this, confirmPasswordError);
        });

        resetPasswordForm.addEventListener('submit', function(e) {
            const maxLength = Number.parseInt(resetPasswordForm.dataset.passwordMaxLength || '64', 10);
            const strength = validatePasswordStrength(newPasswordInput.value);
            let isValid = true;

            clearError(newPasswordInput, newPasswordError);
            clearError(confirmPasswordInput, confirmPasswordError);

            if (!strength.isValid) {
                e.preventDefault();
                showError(newPasswordInput, newPasswordError, 'Please meet all password requirements.');
                updatePasswordRules(newPasswordInput.value);
                isValid = false;
            } else if (newPasswordInput.value.length > maxLength) {
                e.preventDefault();
                showError(newPasswordInput, newPasswordError, 'Password must not exceed ' + maxLength + ' characters.');
                isValid = false;
            }

            if (confirmPasswordInput.value !== newPasswordInput.value) {
                e.preventDefault();
                showError(confirmPasswordInput, confirmPasswordError, 'Passwords do not match.');
                isValid = false;
            }

            if (isValid) {
                LoadingScreen.show('Resetting Password', 'Please wait...');
            }
        });

        // Password visibility toggle
        document.getElementById('newPwToggleBtn').addEventListener('click', function() {
            const show = newPasswordInput.type === 'password';
            newPasswordInput.type = show ? 'text' : 'password';
            this.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            this.classList.toggle('pw-visible', show);
        });

        document.getElementById('confirmPwToggleBtn').addEventListener('click', function() {
            const show = confirmPasswordInput.type === 'password';
            confirmPasswordInput.type = show ? 'text' : 'password';
            this.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            this.classList.toggle('pw-visible', show);
        });

        // Show loading on back to login
        document.querySelector('.form-footer a').addEventListener('click', function(e) {
            e.preventDefault();
            LoadingScreen.show('Redirecting', 'Taking you to login...');
            setTimeout(() => {
                window.location.href = this.href;
            }, 300);
        });
    </script>
</body>
</html>
