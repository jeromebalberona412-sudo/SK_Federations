<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.favicon')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Email Verified - SK OnePortal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style id="verify-success-critical-css">
        .collab-logo{width:120px;height:120px;max-width:42vw;max-height:42vw;object-fit:contain;border-radius:50%;background:#fff;display:block;}
    </style>
    @vite([
        'app/Modules/Authentication/assets/css/auth-base.css',
        'app/Modules/Authentication/assets/css/verify-success.css',
    ])
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
            {{-- LEFT: same dual-logo layout as login --}}
            <div class="logo-container">
                <div class="collab-logo-wrapper">
                    <div class="logo-glow-wrapper logo-left">
                        <img src="{{ asset('Images/SK_OnePortal_logo.png') }}"
                             alt="SK OnePortal Logo"
                             class="collab-logo"
                             width="210"
                             height="210">
                    </div>
                    <div class="logo-glow-wrapper logo-right">
                        <img src="{{ asset('Images/SK_Federations_logo.jpg') }}"
                             alt="SK Federations Logo"
                             class="collab-logo"
                             width="210"
                             height="210">
                    </div>
                </div>
                <h1 class="brand-title">SK OnePortal</h1>
                <p class="brand-subtitle">SK Federation Portal &ndash; Santa Cruz, Laguna</p>
            </div>

            {{-- RIGHT: Success card --}}
            <div class="login-form-container">
                <div class="login-card-inner">
                    <div class="success-container"
                         id="verifySuccessPanel"
                         data-redirect-url="{{ $redirectUrl }}"
                         data-auto-redirect-seconds="{{ (int) ($autoRedirectSeconds ?? 2) }}">
                        <div class="check-wrap" aria-hidden="true">
                            <span class="checkmark"></span>
                        </div>
                        <h2>Email verified successfully</h2>
                        <p>Your SK Federation account is ready. You will be taken to the dashboard shortly.</p>
                        <p class="success-redirect-hint" id="verifySuccessHint" role="status">
                            Redirecting in <span id="verifySuccessCountdown">{{ (int) ($autoRedirectSeconds ?? 2) }}</span>s&hellip;
                        </p>
                        <div class="success-actions">
                            <a href="{{ $redirectUrl }}" class="return-login-btn" id="verifySuccessOkBtn">Okay</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var panel = document.getElementById('verifySuccessPanel');
            if (!panel) return;

            var url = panel.getAttribute('data-redirect-url') || '/dashboard';
            var seconds = parseInt(panel.getAttribute('data-auto-redirect-seconds') || '2', 10);
            if (!isFinite(seconds) || seconds < 0) seconds = 2;

            var countdownEl = document.getElementById('verifySuccessCountdown');
            var redirected = false;

            function goDashboard() {
                if (redirected) return;
                redirected = true;
                window.location.replace(url);
            }

            var remaining = seconds;
            if (countdownEl) countdownEl.textContent = String(remaining);

            var tick = window.setInterval(function () {
                remaining -= 1;
                if (countdownEl) countdownEl.textContent = String(Math.max(0, remaining));
                if (remaining <= 0) {
                    window.clearInterval(tick);
                    goDashboard();
                }
            }, 1000);

            var okBtn = document.getElementById('verifySuccessOkBtn');
            if (okBtn) {
                okBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    window.clearInterval(tick);
                    goDashboard();
                });
            }
        })();
    </script>
</body>
</html>
