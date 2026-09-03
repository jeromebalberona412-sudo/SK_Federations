@extends('layout::app')

@section('title', 'Messages — SK OnePortal')

@push('styles')
    @vite(['app/Modules/Communications/assets/css/communication.css'])
@endpush

@section('content')
@php
    $commsRoutes = [
        'conversations' => route('api.communications.conversations.index'),
        'storeConversation' => route('api.communications.conversations.store'),
        'messages' => url('/api/communications/conversations/__ID__/messages'),
        'react' => url('/api/communications/messages/__ID__/reactions'),
        'updateMessage' => url('/api/communications/messages/__ID__'),
        'deleteMessage' => url('/api/communications/messages/__ID__'),
        'read' => url('/api/communications/conversations/__ID__/read'),
        'showConversation' => url('/api/communications/conversations/__ID__'),
        'searchUsers' => route('api.communications.users.search'),
        'unreadCount' => route('api.communications.unread-count'),
        'startCall' => url('/api/communications/conversations/__ID__/calls'),
        'callStatus' => url('/api/communications/calls/__ID__/status'),
        'calls' => route('api.communications.calls.index'),
        'presence' => route('api.communications.presence'),
        'callHistoryPage' => route('communications.calls'),
        'directory' => route('api.communications.directory'),
    ];
@endphp
<div
    class="comms-app"
    id="commsApp"
    data-current-user-id="{{ $currentUserId }}"
    data-portal-user-type="{{ $portalUserType }}"
    data-initial-conversation="{{ $initialConversationId ?? '' }}"
    data-routes='@json($commsRoutes)'
>
    <div class="comms-shell">
        <aside class="comms-sidebar" id="commsSidebar">
            <div class="comms-sidebar-header">
                <div class="comms-sidebar-title-row">
                    <h1>Chats</h1>
                    <div class="comms-sidebar-actions">
                        <a href="{{ route('communications.calls') }}" class="comms-sidebar-icon-btn" title="Call history" aria-label="Call history">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.79 19.79 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.81.36 1.6.68 2.35a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.75.32 1.54.55 2.35.68A2 2 0 0 1 22 16.92z"/>
                            </svg>
                        </a>
                    </div>
                </div>

                <div class="comms-search-wrap">
                    <span class="comms-search-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                    </span>
                    <label class="visually-hidden" for="commsUserSearch">Search people</label>
                    <input type="search" id="commsUserSearch" class="comms-search-input" placeholder="Search Messenger" autocomplete="off">
                    <div class="comms-search-results" id="commsSearchResults" hidden></div>
                </div>

                @if(($portalUserType ?? '') === 'sk_fed')
                    <div class="comms-filter-bar" id="commsFilterBar" role="tablist" aria-label="Directory filters">
                        <button type="button" class="comms-filter-btn is-active" data-filter="conversations">Chats</button>
                        <button type="button" class="comms-filter-btn" data-filter="chairpersons">Chairpersons</button>
                        <button type="button" class="comms-filter-btn" data-filter="officials">Officials</button>
                        <button type="button" class="comms-filter-btn" data-filter="federation">Federation</button>
                    </div>
                    <div class="comms-barangay-wrap" id="commsBarangayWrap" hidden>
                        <label class="visually-hidden" for="commsBarangayFilter">Filter by barangay</label>
                        <select id="commsBarangayFilter" class="comms-barangay-filter" aria-label="Filter by barangay">
                            <option value="">All barangays</option>
                            @foreach(($barangays ?? []) as $barangay)
                                <option value="{{ $barangay['id'] }}">{{ $barangay['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="comms-search-wrap comms-search-wrap--filter">
                    <span class="comms-search-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                    </span>
                    <label class="visually-hidden" for="commsConvFilter">Filter conversations</label>
                    <input type="search" id="commsConvFilter" class="comms-search-input" placeholder="Filter conversations" autocomplete="off">
                </div>
            </div>
            <div class="comms-conv-list" id="commsConvList" role="list"></div>
            <div class="comms-directory-list" id="commsDirectoryList" role="list" hidden></div>
            <div class="comms-empty" id="commsConvEmpty" hidden>
                <p>No conversations yet. Search for someone to start messaging.</p>
            </div>
        </aside>

        <section class="comms-thread" id="commsThread" aria-live="polite">
            <div class="comms-thread-empty" id="commsThreadEmpty">
                <h2>Your messages</h2>
                <p>Select a conversation or search for a person to start chatting.</p>
            </div>

            <div class="comms-thread-active" id="commsThreadActive" hidden>
                <header class="comms-thread-header">
                    <button type="button" class="comms-back-btn" id="commsBackBtn" aria-label="Back to conversations">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
                    </button>
                    <img src="" alt="" class="comms-avatar" id="commsPeerAvatar" width="40" height="40">
                    <div class="comms-thread-peer">
                        <div class="comms-peer-name" id="commsPeerName"></div>
                        <div class="comms-peer-meta" id="commsPeerMeta"></div>
                    </div>
                    <div class="comms-thread-actions">
                        <button type="button" class="comms-icon-btn" id="commsVoiceBtn" title="Voice call" aria-label="Start voice call">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.79 19.79 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.81.36 1.6.68 2.35a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.75.32 1.54.55 2.35.68A2 2 0 0 1 22 16.92z"/></svg>
                        </button>
                        <button type="button" class="comms-icon-btn" id="commsVideoBtn" title="Video call" aria-label="Start video call">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/></svg>
                        </button>
                    </div>
                </header>

                <div class="comms-messages" id="commsMessages" role="log"></div>
                <div class="comms-typing" id="commsTyping" hidden>Typing...</div>

                <form class="comms-composer" id="commsComposer" autocomplete="off">
                    <div class="comms-composer-tools">
                        <input type="file" id="commsPhotoInput" class="visually-hidden" accept="image/jpeg,image/png,image/webp,image/gif" multiple aria-hidden="true" tabindex="-1">
                        <input type="file" id="commsFileInput" class="visually-hidden" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" multiple aria-hidden="true" tabindex="-1">
                        <div class="comms-attach-menu-wrap">
                            <button type="button" class="comms-attach-btn" id="commsAttachBtn" title="Attach" aria-label="Attach">
                                <span aria-hidden="true">+</span>
                            </button>
                            <div class="comms-attach-menu" id="commsAttachMenu" hidden>
                                <button type="button" class="comms-attach-menu-btn" id="commsPickPhotosBtn">Photos</button>
                                <button type="button" class="comms-attach-menu-btn" id="commsPickFilesBtn">Files</button>
                            </div>
                        </div>
                    </div>
                    <div class="comms-composer-main">
                        <div class="comms-attach-preview" id="commsAttachPreview" hidden>
                            <div class="comms-attach-preview-inner" id="commsAttachPreviewInner"></div>
                            <button type="button" class="comms-attach-clear" id="commsAttachClear" aria-label="Remove attachment">&times;</button>
                        </div>
                        <div class="comms-upload-progress" id="commsUploadProgress" hidden>
                            <div class="comms-upload-progress-bar" id="commsUploadProgressBar"></div>
                        </div>
                        <textarea id="commsMessageInput" rows="1" maxlength="5000" placeholder="Write a message" aria-label="Write a message"></textarea>
                    </div>
                    <button type="submit" class="comms-send-btn" id="commsSendBtn" aria-label="Send message">
                        <span class="comms-send-btn-label">Send</span>
                        <svg class="comms-send-btn-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <line x1="22" y1="2" x2="11" y2="13"/>
                            <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                        </svg>
                    </button>
                </form>
            </div>
        </section>
    </div>

    @include('communications::partials.incoming-call-modal')
    @include('communications::partials.in-call-ui')
</div>
@endsection

@push('scripts')
    @vite([
        'app/Modules/Communications/assets/js/chat.js',
        'app/Modules/Communications/assets/js/webrtc.js',
    ])
@endpush
