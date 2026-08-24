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
        <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
        <meta http-equiv="Pragma" content="no-cache">
        <meta http-equiv="Expires" content="0">
    @endif
    <title>@yield('title', 'SK OnePortal')</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @php
        $dashboardCssVersion = @filemtime(app_path('Modules/Dashboard/assets/css/dashboard.css')) ?: time();
        $layoutCssVersion = @filemtime(app_path('Modules/Layout/assets/css/layout.css')) ?: time();
        $layoutJsVersion = @filemtime(app_path('Modules/Layout/assets/js/layout.js')) ?: time();
    @endphp
    <link rel="stylesheet" href="{{ url('/modules/dashboard/css/dashboard.css') }}?v={{ $dashboardCssVersion }}">
    <link rel="stylesheet" href="{{ url('/modules/layout/css/layout.css') }}?v={{ $layoutCssVersion }}">
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

    @if(!empty($turnoverModal['show']))
        <link rel="stylesheet" href="{{ url('/modules/turnover/css/turnover.css') }}?v={{ $turnoverModal['cssVersion'] }}">
        @include('turnover::partials.global-modal', ['turnoverModal' => $turnoverModal])
        <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
        <script src="{{ url('/modules/turnover/js/turnover.js') }}?v={{ $turnoverModal['jsVersion'] }}"></script>
    @endif

    <script src="{{ url('/modules/layout/js/layout.js') }}?v={{ $layoutJsVersion }}"></script>
    <script>
        window.logoutRoute = "{{ route('logout') }}";
        window.loginRoute  = "{{ route('login') }}";
    </script>
    @stack('scripts')
</body>
</html>
