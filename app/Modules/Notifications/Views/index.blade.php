@extends('layout::app')

@section('title', 'Notifications — SK OnePortal')

@section('content')
    @php
        $totalCount = count($notifications ?? []);
        $unreadTotal = (int) ($unreadCount ?? collect($notifications ?? [])->where('unread', true)->count());
        $readTotal = max(0, $totalCount - $unreadTotal);
    @endphp

    <div class="page-header">
        <div class="notif-page-header-row">
            <div>
                <h1>Notifications</h1>
                <p>Stay updated on community feed activity and barangay ABYIP submissions.</p>
            </div>
            <button type="button" class="notif-page-mark-all" id="pageMarkAllBtn">Mark all as read</button>
        </div>
    </div>

    <div class="notif-page-card">
        <div class="notif-page-stats">
            <span>Total: <strong id="totalCount">{{ $totalCount }}</strong></span>
            <span>Unread: <strong id="unreadCount">{{ $unreadTotal }}</strong></span>
            <span>Read: <strong id="readCount">{{ $readTotal }}</strong></span>
        </div>

        <div class="notif-filter-bar">
            <button type="button" class="notif-filter-btn active" data-filter="all">All</button>
            <button type="button" class="notif-filter-btn" data-filter="unread">Unread</button>
            <button type="button" class="notif-filter-btn" data-filter="read">Read</button>
        </div>

        <div class="notif-page-list" id="notifPageList" @if($totalCount === 0) style="display:none;" @endif>
            @foreach(($notifications ?? []) as $notification)
                <article
                    class="notif-page-item {{ ($notification['unread'] ?? false) ? 'notif-page-unread' : '' }}"
                    data-id="{{ $notification['id'] }}"
                    data-action-url="{{ $notification['action_url'] ?? '' }}"
                    role="button"
                    tabindex="0"
                >
                    <div class="notif-content">
                        <div class="notif-item-title">{{ $notification['title'] }}</div>
                        <p class="notif-item-text">{{ $notification['text'] }}</p>
                        <div class="notif-item-time">{{ $notification['time'] }}</div>
                    </div>
                    @if($notification['unread'] ?? false)
                        <span class="notif-unread-dot notif-page-dot"></span>
                    @endif
                </article>
            @endforeach
        </div>

        <div class="notif-page-empty" id="notifPageEmpty" @if($totalCount > 0) style="display:none;" @endif>
            <p>No notifications yet.</p>
        </div>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ url('/modules/notifications/css/notifications.css') }}?v={{ @filemtime(app_path('Modules/Notifications/assets/css/notifications.css')) ?: time() }}">
@endpush

@push('scripts')
    <script src="{{ url('/modules/notifications/js/notifications.js') }}?v={{ @filemtime(app_path('Modules/Notifications/assets/js/notifications.js')) ?: time() }}"></script>
@endpush
