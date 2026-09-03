@php
    $msgItems = $headerConversations ?? [];
    $hasMsgItems = count($msgItems) > 0;
    $unreadMsgCount = (int) ($unreadMessagesCount ?? 0);
    $unreadMsgLabel = $unreadMsgCount > 99 ? '99+' : (string) $unreadMsgCount;
@endphp

<div class="comms-msg-popover" id="commsMsgPopover" role="menu" aria-label="Messages">
    <div class="comms-msg-popover-header">
        <div class="comms-msg-popover-title">
            <i class="fas fa-comments" aria-hidden="true"></i>
            <h4>Messages</h4>
            <span
                class="comms-msg-count-pill"
                id="commsMsgCountPill"
                style="{{ $unreadMsgCount > 0 ? '' : 'display: none;' }}"
            >{{ $unreadMsgLabel }}</span>
        </div>
        <a
            href="{{ Route::has('communications.index') ? route('communications.index') : '#' }}"
            class="comms-msg-new-link"
            id="commsMsgNewLink"
            data-no-loading
            title="Open Messages"
        >Open</a>
    </div>

    <div class="comms-msg-list" id="commsMsgList" @if(! $hasMsgItems) style="display: none;" @endif>
        @foreach($msgItems as $conversation)
            @php
                $peer = $conversation['other_user'] ?? [];
                $preview = $conversation['last_message']['body'] ?? 'No messages yet';
                $unread = (int) ($conversation['unread_count'] ?? 0);
                $name = $peer['name'] ?? 'User';
                $avatar = $peer['profile_image_url'] ?? ('https://ui-avatars.com/api/?name='.urlencode((string) $name).'&background=213F99&color=fff');
                $convId = (int) ($conversation['id'] ?? 0);
                $href = Route::has('communications.index')
                    ? route('communications.index', ['conversation' => $convId])
                    : '#';
            @endphp
            <a
                href="{{ $href }}"
                class="comms-msg-item {{ $unread > 0 ? 'comms-msg-unread' : '' }}"
                data-id="{{ $convId }}"
                data-no-loading
                role="menuitem"
            >
                <img class="comms-msg-avatar" src="{{ $avatar }}" alt="">
                <div class="comms-msg-content">
                    <div class="comms-msg-item-top">
                        <span class="comms-msg-item-name">{{ $name }}</span>
                        <span class="comms-msg-item-time">{{ !empty($conversation['updated_at']) ? \Illuminate\Support\Carbon::parse($conversation['updated_at'])->diffForHumans(null, true) : '' }}</span>
                    </div>
                    <div class="comms-msg-item-preview">{{ \Illuminate\Support\Str::limit($preview, 56) }}</div>
                </div>
                @if($unread > 0)
                    <span class="comms-msg-item-badge">{{ $unread > 99 ? '99+' : $unread }}</span>
                @endif
            </a>
        @endforeach
    </div>

    <div class="comms-msg-empty" id="commsMsgEmpty" @if($hasMsgItems) style="display: none;" @endif>
        <p>No messages yet</p>
        <p class="comms-msg-empty-sub">Start a conversation from Messages.</p>
    </div>

    <div class="comms-msg-popover-footer">
        <a
            href="{{ Route::has('communications.index') ? route('communications.index') : '#' }}"
            class="comms-msg-see-all-btn"
            data-no-loading
        >
            See All Messages
        </a>
    </div>
</div>
