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
    <title>@yield('title', 'Account Settings') - SK OnePortal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    @php
        $accountAuthCssVersion = @filemtime(app_path('Modules/Profile/assets/css/account-auth.css')) ?: time();
    @endphp
    <link rel="stylesheet" href="{{ url('/modules/profile/css/account-auth.css') }}?v={{ $accountAuthCssVersion }}">
    @stack('styles')
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

        {{-- Main Split Layout --}}
        <div class="login-container">

            {{-- LEFT: Logo & Branding --}}
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
                <h1 class="brand-title">SK OnePortal</h1>
                <p class="brand-subtitle">SK Federation Portal – Santa Cruz, Laguna</p>
            </div>

            {{-- RIGHT: Form Card --}}
            <div class="login-form-container">
                <div class="login-card-inner @yield('card-class')">
                    @yield('content')
                </div>
            </div>

        </div>
    </div>

    @stack('scripts-before')
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>
