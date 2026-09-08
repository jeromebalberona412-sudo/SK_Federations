@php
    $msgItems = $headerConversations ?? [];
    $hasMsgItems = is_countable($msgItems) ? count($msgItems) > 0 : false;
    $unreadMsgCount = (int) ($unreadMessagesCount ?? 0);
    $unreadMsgLabel = $unreadMsgCount > 99 ? '99+' : (string) $unreadMsgCount;
    $messagesIndexUrl = \Illuminate\Support\Facades\Route::has('communications.index')
        ? route('communications.index')
        : url('/communications');
@endphp

<div class="comms-msg-popover" id="commsMsgPopover" role="menu" aria-label="Messages">
    <div class="comms-msg-popover-header">
        <div class="comms-msg-popover-title">
            <span class="comms-msg-header-icon-btn comms-msg-header-icon-btn--title" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                </svg>
            </span>
            <h4>Chats</h4>
            <span
                class="comms-msg-count-pill"
                id="commsMsgCountPill"
                style="{{ $unreadMsgCount > 0 ? '' : 'display: none;' }}"
            >{{ $unreadMsgLabel }}</span>
        </div>
        <div class="comms-msg-header-actions">
            <button
                type="button"
                class="comms-msg-header-icon-btn"
                id="commsMsgOpenFull"
                data-no-loading
                title="Open fullscreen Messages"
                aria-label="Open fullscreen Messages"
            >
                <i class="fas fa-expand" aria-hidden="true"></i>
            </button>
            <a
                href="{{ $messagesIndexUrl }}"
                class="comms-msg-header-icon-btn"
                id="commsMsgNewLink"
                data-no-loading
                title="New message"
                aria-label="Compose new message"
            >
                <i class="fas fa-edit" aria-hidden="true"></i>
            </a>
        </div>
    </div>

    <div class="comms-msg-search-wrap">
        <label class="comms-msg-search" for="commsMsgSearch">
            <i class="fas fa-search" aria-hidden="true"></i>
            <input
                type="search"
                id="commsMsgSearch"
                class="comms-msg-search-input"
                placeholder="Search Messages"
                autocomplete="off"
                aria-label="Search messages"
            >
        </label>
    </div>

    <div class="comms-msg-filters" role="tablist" aria-label="Message filters">
        <button type="button" class="comms-msg-filter-chip is-active" data-filter="all" role="tab" aria-selected="true">All</button>
        <button type="button" class="comms-msg-filter-chip" data-filter="unread" role="tab" aria-selected="false">Unread</button>
    </div>

    <div class="comms-msg-list" id="commsMsgList" @if(! $hasMsgItems) style="display: none;" @endif>
        @foreach(($msgItems ?: []) as $conversation)
            @php
                $peer = $conversation['other_user'] ?? [];
                $preview = $conversation['last_message']['body'] ?? 'No messages yet';
                $unread = (int) ($conversation['unread_count'] ?? 0);
                $name = $peer['name'] ?? 'User';
                $avatar = $peer['profile_image_url'] ?? ('https://ui-avatars.com/api/?name='.urlencode((string) $name).'&background=213F99&color=fff');
                $convId = (int) ($conversation['id'] ?? 0);
                $peerUserId = (int) ($peer['id'] ?? 0);
                $searchBlob = mb_strtolower(trim($name.' '.$preview.' '.($peer['barangay_name'] ?? '').' '.($peer['position'] ?? '')));
            @endphp
            <button
                type="button"
                class="comms-msg-item {{ $unread > 0 ? 'comms-msg-unread' : '' }}"
                data-id="{{ $convId > 0 ? $convId : '' }}"
                data-user-id="{{ $peerUserId > 0 ? $peerUserId : '' }}"
                data-name="{{ e($name) }}"
                data-avatar="{{ e((string) ($avatar ?? '')) }}"
                data-unread="{{ $unread > 0 ? '1' : '0' }}"
                data-search="{{ e($searchBlob) }}"
                role="menuitem"
            >
                <img class="comms-msg-avatar" src="{{ $avatar }}" alt="">
                <div class="comms-msg-content">
                    <div class="comms-msg-item-top">
                        <span class="comms-msg-item-name">{{ $name }}</span>
                        <span class="comms-msg-item-time">
                            @if(!empty($conversation['updated_at']))
                                {{ \Illuminate\Support\Carbon::parse($conversation['updated_at'])->diffForHumans(null, true) }}
                            @endif
                        </span>
                    </div>
                    <div class="comms-msg-item-preview">{{ \Illuminate\Support\Str::limit((string) $preview, 56) }}</div>
                </div>
                @if($unread > 0)
                    <span class="comms-msg-unread-dot" aria-hidden="true"></span>
                @endif
            </button>
        @endforeach
    </div>

    <div class="comms-msg-empty" id="commsMsgEmpty" @if($hasMsgItems) style="display: none;" @endif>
        <p>No SK Officials found</p>
        <p class="comms-msg-empty-sub">Accounts will appear here when available.</p>
    </div>

    <div class="comms-msg-popover-footer">
        <a href="{{ $messagesIndexUrl }}" class="comms-msg-see-all-btn" data-no-loading>
            See All in Messages
        </a>
    </div>
</div>
