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
        .success-container{text-align:center;animation:vs-fade-in .45s ease both;}
        .check-wrap{width:104px;height:104px;border-radius:50%;background:linear-gradient(135deg,#22c55e 0%,#16a34a 100%);display:grid;place-items:center;margin:0 auto 1.5rem;box-shadow:0 10px 28px rgba(34,197,94,.28);position:relative;animation:vs-scale-in .5s cubic-bezier(.68,-.55,.265,1.55) both;}
        .check-wrap::before{content:'';position:absolute;inset:0;border-radius:50%;background:linear-gradient(135deg,#22c55e 0%,#16a34a 100%);opacity:.28;animation:vs-pulse-ring 1.5s ease-out infinite;}
        .checkmark{width:30px;height:58px;border-right:7px solid #fff;border-bottom:7px solid #fff;transform:rotate(45deg) scale(0);transform-origin:center;margin:0 0 8px 8px;animation:vs-draw-check .55s .25s cubic-bezier(.68,-.55,.265,1.55) forwards;}
        .success-container h2{color:#0f172a;margin:0 0 .55rem;font-size:1.45rem;font-weight:800;letter-spacing:-.02em;line-height:1.25;}
        .success-container > p{color:#64748b;margin:0 0 .85rem;font-size:.95rem;line-height:1.55;}
        .success-redirect-hint{color:#475569!important;font-size:.85rem!important;font-weight:600;margin-bottom:1.35rem!important;}
        .success-actions{display:flex;justify-content:center;}
        .return-login-btn{display:inline-flex;align-items:center;justify-content:center;min-width:148px;min-height:46px;padding:.7rem 1.5rem;border-radius:10px;background:linear-gradient(90deg,#213F99 0%,#1E6ED8 100%);color:#fff!important;text-decoration:none;font-weight:700;font-size:.95rem;border:none;box-shadow:0 8px 18px rgba(33,63,153,.22);}
        .return-login-btn:hover,.return-login-btn:focus-visible{transform:translateY(-1px);box-shadow:0 12px 22px rgba(30,110,216,.28);color:#fff!important;outline:none;}
        @keyframes vs-fade-in{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
        @keyframes vs-scale-in{0%{transform:scale(0) rotate(-160deg);opacity:0}60%{transform:scale(1.08) rotate(8deg);opacity:1}100%{transform:scale(1) rotate(0);opacity:1}}
        @keyframes vs-draw-check{0%{transform:rotate(45deg) scale(0);opacity:0}100%{transform:rotate(45deg) scale(1);opacity:1}}
        @keyframes vs-pulse-ring{0%{transform:scale(1);opacity:.28}100%{transform:scale(1.32);opacity:0}}
        @media (max-width:640px){.check-wrap{width:92px;height:92px}.success-container h2{font-size:1.25rem}.return-login-btn{width:100%}}
    </style>
    {{-- Use login.css (already in production manifest). Do not @vite verify-success.css — missing entry 500s the page. --}}
    @vite([
        'app/Modules/Authentication/assets/css/auth-base.css',
        'app/Modules/Authentication/assets/css/login.css',
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
