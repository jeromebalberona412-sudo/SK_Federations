{{-- Profile page: module styles (layout shell is in layout.css via layout::app) --}}
@php
    $profileCssVersion = @filemtime(app_path('Modules/Profile/assets/css/profile.css')) ?: time();
@endphp
<link rel="stylesheet" href="{{ url('/modules/profile/css/profile.css') }}?v={{ $profileCssVersion }}">
