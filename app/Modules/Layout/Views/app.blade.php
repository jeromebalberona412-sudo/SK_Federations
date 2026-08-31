<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.favicon')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @hasSection('meta-cache')
        @yield('meta-cache')
    @else
        <meta http-equiv="Cache-Control" content="private, max-age=0, must-revalidate">
    @endif
    <title>@yield('title', 'SK OnePortal')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"></noscript>
    @vite([
        'app/Modules/Dashboard/assets/css/dashboard.css',
        'app/Modules/Layout/assets/css/layout.css',
    ])
    @stack('styles')
    <link rel="stylesheet" href="{{ url('/shared/css/federations-responsive.css') }}?v={{ @filemtime(public_path('shared/css/federations-responsive.css')) ?: time() }}">
</head>
<body @stack('body-attributes') @if(!empty($turnoverModal['show']) && !empty($turnoverModal['portal_locked'])) class="turnover-portal-locked" @endif>
    @include('layout::anti-back')
    @include('layout::header')
    @include('layout::sidebar-overlay')
    @include('layout::sidebar')

    <main class="main-content @stack('main-class')" @stack('main-attributes')>
        @yield('content')
    </main>

    @include('layout::logout-modal')
    @include('layout::session-timeout-modal')

    @if(!empty($turnoverModal['show']))
        @vite(['app/Modules/Turnover/assets/css/turnover.css'])
        @include('turnover::partials.global-modal', ['turnoverModal' => $turnoverModal])
        <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js" defer></script>
        @vite(['app/Modules/Turnover/assets/js/turnover.js'])
    @endif

    <script src="{{ url('/shared/js/local-data-cache.js') }}?v={{ @filemtime(public_path('shared/js/local-data-cache.js')) ?: time() }}"></script>
    @vite(['app/Modules/Layout/assets/js/layout.js'])
    <script>
        window.logoutRoute = "{{ route('logout') }}";
        window.loginRoute  = "{{ route('login') }}";
        window.sessionContinueRoute = "{{ route('skfed.session.continue') }}";
        window.sessionTimeoutConfig = {
            timeoutMinutes: {{ max(1, (int) config('session.timeout', 120)) }},
            warningMinutes: {{ max(0, (int) config('session.timeout_warning_minutes', 5)) }},
            lastActivityAt: {{ (int) session(\App\Modules\Authentication\Middleware\SessionTimeout::SESSION_KEY, now()->timestamp) }}
        };
    </script>
    @stack('scripts')
</body>
</html>
