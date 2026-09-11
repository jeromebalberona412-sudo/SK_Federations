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
    @php
        $turnoverModal = $turnoverModal ?? ['show' => false];
        $layoutVite = \App\Support\ViteManifest::existing([
            'app/Modules/Dashboard/assets/css/dashboard.css',
            'app/Modules/Layout/assets/css/layout.css',
        ]);
    @endphp
    @if ($layoutVite !== [])
        @vite($layoutVite)
    @endif
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
    @include('layout::chat-modal')

    @auth
        <div
            id="commsRealtimeBoot"
            hidden
            data-current-user-id="{{ auth()->id() }}"
            data-portal-user-type="{{ config('communications.portal_user_type', 'sk_fed') }}"
            data-current-user-name="{{ auth()->user()->name ?? '' }}"
            data-unread-count-url="{{ url('/api/communications/unread-count') }}"
        ></div>
        <script>
            window.CommsChat = window.CommsChat || {
                routes: {
                    conversations: @json(url('/api/communications/conversations')),
                    storeConversation: @json(url('/api/communications/conversations')),
                    messages: @json(url('/api/communications/conversations/__ID__/messages')),
                    react: @json(url('/api/communications/messages/__ID__/reactions')),
                    read: @json(url('/api/communications/conversations/__ID__/read')),
                    showConversation: @json(url('/api/communications/conversations/__ID__')),
                    searchUsers: @json(url('/api/communications/users/search')),
                    unreadCount: @json(url('/api/communications/unread-count')),
                    startCall: @json(url('/api/communications/conversations/__ID__/calls')),
                    callStatus: @json(url('/api/communications/calls/__ID__/status')),
                    calls: @json(url('/api/communications/calls')),
                    presence: @json(url('/api/communications/presence'))
                },
                getActiveId: function () {
                    return (window.__COMMS_HEADER_ACTIVE_ID__ != null) ? window.__COMMS_HEADER_ACTIVE_ID__ : null;
                },
                reloadActiveMessages: function () {},
                reloadConversations: function () {
                    if (typeof window.refreshMessagesPopover === 'function') {
                        window.refreshMessagesPopover();
                    }
                },
                onReactionChange: function () {},
                appendMessage: function () {},
                setTyping: function (visible, meta) {
                    var el = document.getElementById('commsChatModalTyping');
                    if (!el) return;
                    if (window.Comms && typeof window.Comms.paintTypingEl === 'function') {
                        window.Comms.paintTypingEl(el, !!visible, meta || null);
                        return;
                    }
                    if (!visible) {
                        el.hidden = true;
                        el.textContent = '';
                        return;
                    }
                    var who = meta && meta.name ? String(meta.name).trim() : '';
                    el.textContent = who ? (who + ' is typing...') : 'Typing...';
                    el.hidden = false;
                }
            };
        </script>
    @endauth

    @if(!empty($turnoverModal['show']))
        @php
            $turnoverVite = \App\Support\ViteManifest::existing([
                'app/Modules/Turnover/assets/css/turnover.css',
                'app/Modules/Turnover/assets/js/turnover.js',
            ]);
        @endphp
        @if ($turnoverVite !== [])
            @vite($turnoverVite)
        @endif
        @include('turnover::partials.global-modal', ['turnoverModal' => $turnoverModal])
        <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js" defer></script>
    @endif

    <script src="{{ url('/shared/js/local-data-cache.js') }}?v={{ @filemtime(public_path('shared/js/local-data-cache.js')) ?: time() }}"></script>
    @auth
        @php
            $commsVite = \App\Support\ViteManifest::existing([
                'app/Modules/Communications/assets/css/communication.css',
                'app/Modules/Communications/assets/js/communication.js',
                'app/Modules/Communications/assets/js/realtime.js',
            ]);
        @endphp
        @if ($commsVite !== [])
            @vite($commsVite)
        @endif
    @endauth
    @php
        $layoutJsVite = \App\Support\ViteManifest::existing([
            'app/Modules/Layout/assets/js/layout.js',
        ]);
    @endphp
    @if ($layoutJsVite !== [])
        @vite($layoutJsVite)
    @endif
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
