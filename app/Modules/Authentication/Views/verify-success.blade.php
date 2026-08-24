<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.favicon')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Email Verified</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ url('/modules/authentication/css/style.css') }}" rel="stylesheet">
    <style>
        .check-wrap {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            display: grid;
            place-items: center;
            margin: 0 auto 32px;
            box-shadow: 0 10px 40px rgba(34, 197, 94, 0.3);
            animation: scaleIn 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            position: relative;
        }

        .check-wrap::before {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            opacity: 0.3;
            animation: pulse-ring 1.5s ease-out infinite;
        }

        .checkmark {
            width: 35px;
            height: 70px;
            border-right: 8px solid white;
            border-bottom: 8px solid white;
            transform: rotate(45deg) scale(0);
            animation: draw-check 0.6s 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55) forwards;
            transform-origin: center center;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.1));
            margin-left: 8px;
            margin-bottom: 8px;
        }

        @keyframes scaleIn {
            0% {
                transform: scale(0) rotate(-180deg);
                opacity: 0;
            }
            50% {
                transform: scale(1.1) rotate(10deg);
            }
            100% {
                transform: scale(1) rotate(0deg);
                opacity: 1;
            }
        }

        @keyframes draw-check {
            0% {
                transform: rotate(45deg) scale(0);
                opacity: 0;
            }
            50% {
                transform: rotate(45deg) scale(1.1);
                opacity: 1;
            }
            100% {
                transform: rotate(45deg) scale(1);
                opacity: 1;
            }
        }

        @keyframes pulse-ring {
            0% {
                transform: scale(1);
                opacity: 0.3;
            }
            50% {
                transform: scale(1.15);
                opacity: 0.1;
            }
            100% {
                transform: scale(1.3);
                opacity: 0;
            }
        }

        .success-container {
            text-align: center;
            animation: fadeIn 0.5s ease-in;
        }

        .success-container h2 {
            color: #213F99;
            margin-bottom: 12px;
            font-size: 28px;
            font-weight: 700;
            animation: fadeIn 0.6s 0.4s ease-in backwards;
        }

        .success-container p {
            color: #64748b;
            margin-bottom: 32px;
            font-size: 16px;
            animation: fadeIn 0.6s 0.5s ease-in backwards;
        }

        .success-actions {
            animation: fadeIn 0.6s 0.6s ease-in backwards;
        }

        .return-login-btn {
            display: inline-block;
            background: linear-gradient(90deg, #213F99 0%, #1E6ED8 100%);
            color: #ffffff;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .return-login-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 20px rgba(30, 110, 216, 0.2);
            color: #ffffff;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeOut {
            from {
                opacity: 1;
            }
            to {
                opacity: 0;
            }
        }

        .fade-out-animation {
            animation: fadeOut 1s ease-out forwards;
        }

        @media (max-width: 640px) {
            .check-wrap {
                width: 100px;
                height: 100px;
                margin-bottom: 24px;
            }

            .checkmark {
                width: 30px;
                height: 60px;
                border-right: 6px solid white;
                border-bottom: 6px solid white;
                margin-left: 6px;
                margin-bottom: 6px;
            }

            .success-container h2 {
                font-size: 22px;
            }

            .success-container p {
                font-size: 14px;
                margin-bottom: 24px;
            }
        }

        @media (max-width: 480px) {
            .check-wrap {
                width: 90px;
                height: 90px;
                margin-bottom: 20px;
            }

            .checkmark {
                width: 25px;
                height: 50px;
                border-right: 5px solid white;
                border-bottom: 5px solid white;
                margin-left: 5px;
                margin-bottom: 5px;
            }

            .success-container h2 {
                font-size: 20px;
            }

            .success-container p {
                font-size: 13px;
            }
        }
    </style>
</head>
<body>
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

        <div class="login-container">
            {{-- LEFT: Logo --}}
            <div class="logo-container">
                <div class="logo-glow-wrapper">
                    <img src="{{ url('/modules/authentication/images/Sk_Fed_logo.png') }}" alt="SK Federations Logo" class="large-logo">
                </div>
                <h1 class="brand-title">SK Federation</h1>
                <p class="brand-subtitle">Santa Cruz Youth Leadership Portal</p>
            </div>

            {{-- RIGHT: Content Card --}}
            <div class="login-form-container">
                <div class="login-card-inner">
                    <div class="success-container">
                        <div class="check-wrap" aria-hidden="true">
                            <span class="checkmark"></span>
                        </div>
                        <h2>Verified successfully</h2>
                        <p>Your SK Federation account is now verified. Return to sign in on your device.</p>
                        <div class="success-actions">
                            <a href="{{ route('login', [], false) }}" class="return-login-btn">Go to Sign In</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
