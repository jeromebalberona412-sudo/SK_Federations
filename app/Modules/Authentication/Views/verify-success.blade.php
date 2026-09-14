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
    @vite([
        'app/Modules/Authentication/assets/css/auth-base.css',
        'app/Modules/Authentication/assets/css/verify-wait.css',
    ])
    <style>
        .success-container {
            text-align: center;
            animation: vsFadeIn 0.5s ease-in;
        }

        .check-wrap {
            width: 96px;
            height: 96px;
            border-radius: 50%;
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            display: grid;
            place-items: center;
            margin: 0 auto 1.5rem;
            box-shadow: 0 10px 40px rgba(34, 197, 94, 0.3);
            animation: vsScaleIn 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            position: relative;
        }

        .check-wrap::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 50%;
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            opacity: 0.3;
            animation: vsPulseRing 1.5s ease-out infinite;
        }

        .checkmark {
            width: 28px;
            height: 56px;
            border-right: 7px solid #fff;
            border-bottom: 7px solid #fff;
            transform: rotate(45deg) scale(0);
            animation: vsDrawCheck 0.6s 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55) forwards;
            transform-origin: center center;
            margin-left: 6px;
            margin-bottom: 8px;
        }

        .success-container h2 {
            color: #0f172a;
            margin: 0 0 0.5rem;
            font-size: 1.35rem;
            font-weight: 800;
            letter-spacing: -0.01em;
        }

        .success-container p {
            color: #64748b;
            margin: 0 0 1.5rem;
            font-size: 0.9rem;
            line-height: 1.55;
            font-weight: 400;
        }

        .return-login-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(90deg, #213F99 0%, #1E6ED8 100%);
            color: #fff;
            text-decoration: none;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.95rem;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .return-login-btn:hover,
        .return-login-btn:focus-visible {
            transform: translateY(-1px);
            box-shadow: 0 10px 20px rgba(30, 110, 216, 0.2);
            color: #fff;
            outline: none;
        }

        @keyframes vsScaleIn {
            0% { transform: scale(0) rotate(-180deg); opacity: 0; }
            50% { transform: scale(1.08) rotate(8deg); }
            100% { transform: scale(1) rotate(0deg); opacity: 1; }
        }

        @keyframes vsDrawCheck {
            0% { transform: rotate(45deg) scale(0); opacity: 0; }
            100% { transform: rotate(45deg) scale(1); opacity: 1; }
        }

        @keyframes vsPulseRing {
            0% { transform: scale(1); opacity: 0.3; }
            100% { transform: scale(1.3); opacity: 0; }
        }

        @keyframes vsFadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 640px) {
            .check-wrap { width: 84px; height: 84px; margin-bottom: 1.25rem; }
            .checkmark { width: 24px; height: 48px; border-width: 0 6px 6px 0; }
            .success-container h2 { font-size: 1.2rem; }
            .success-container p { font-size: 0.85rem; }
        }
    </style>
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
            {{-- LEFT: dual logos + branding (same as login / change password) --}}
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
                <h1 class="brand-title">SK Federation</h1>
                <p class="brand-subtitle">Santa Cruz Youth Leadership Portal</p>
            </div>

            {{-- RIGHT: success card --}}
            <div class="login-form-container">
                <div class="login-card-inner">
                    <div class="success-container">
                        <div class="check-wrap" aria-hidden="true">
                            <span class="checkmark"></span>
                        </div>
                        <h2>Email verified successfully</h2>
                        <p>Your SK Federation account email has been verified. You can now sign in on your device.</p>
                        <div class="success-actions">
                            <a href="{{ route('login', [], false) }}" class="return-login-btn">Go to Login</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
