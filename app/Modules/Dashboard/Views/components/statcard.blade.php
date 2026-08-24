@php
    $metricValue = $value ?? null;
    $cardContent = function () use ($tone, $metricKey, $icon, $label, $metricValue) {
        ob_start();
@endphp
    <div class="stat-card-top">
        @if($metricValue !== null)
            <span class="stat-card-value">{{ number_format($metricValue) }}</span>
        @else
            <span class="stat-card-value" x-text="dashboardMetrics.{{ $metricKey }}.value">0</span>
        @endif
        <div class="stat-card-icon stat-icon-{{ $tone }}">
            @switch($icon ?? '')
                @case('users')
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                    @break
                @case('federation')
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="4" width="7" height="7" rx="1.4" />
                        <rect x="14" y="4" width="7" height="7" rx="1.4" />
                        <rect x="8.5" y="14" width="7" height="7" rx="1.4" />
                        <path d="M10 7.5h4" />
                        <path d="M12 11v3" />
                    </svg>
                    @break
                @case('officials')
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 4l8 4v2H4V8l8-4z" />
                        <path d="M5 10v7" />
                        <path d="M9 10v7" />
                        <path d="M15 10v7" />
                        <path d="M19 10v7" />
                        <path d="M3 19h18" />
                    </svg>
                    @break
                @case('kabataan')
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                    @break
                @case('location')
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                        <circle cx="12" cy="10" r="3"></circle>
                    </svg>
                    @break
                @case('trash')
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6l-1 14H6L5 6"></path>
                        <path d="M10 11v6"></path>
                        <path d="M14 11v6"></path>
                        <path d="M9 6V4h6v2"></path>
                    </svg>
                    @break
                @case('archive')
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="21 8 21 21 3 21 3 8"></polyline>
                        <rect x="1" y="3" width="22" height="5"></rect>
                        <line x1="10" y1="12" x2="14" y2="12"></line>
                    </svg>
                    @break
                @case('reject')
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="15" y1="9" x2="9" y2="15"></line>
                        <line x1="9" y1="9" x2="15" y2="15"></line>
                    </svg>
                    @break
                @case('activity')
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                    </svg>
                    @break
                @default
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 13a8 8 0 1 1 16 0" />
                        <path d="M6 13h2" />
                        <path d="M16 13h2" />
                        <path d="M12 13l3-3" />
                    </svg>
            @endswitch
        </div>
    </div>
    <span class="stat-card-label">{{ $label }}</span>
@php
        return ob_get_clean();
    };
@endphp

@if(isset($route) && $route)
    <a href="{{ route($route) }}" class="stat-card stat-card-{{ $tone }}" style="text-decoration: none; color: inherit; cursor: pointer;">
        {!! $cardContent() !!}
    </a>
@else
    <div class="stat-card stat-card-{{ $tone }}">
        {!! $cardContent() !!}
    </div>
@endif
