@extends('layout::app')

@section('title', 'SK Community Feed - SK OnePortal')

@push('body-attributes')
    class="sk-fed-feed"
@endpush

@push('main-class')
    cf-main
@endpush

@push('styles')
    @php
        $communityFeedCssPath = app_path('Modules/CommunityFeed/assets/css/community-feed.css');
        $communityFeedCssVersion = file_exists($communityFeedCssPath) ? filemtime($communityFeedCssPath) : time();
        $commentPreviewCssPath = app_path('Modules/CommunityFeed/assets/css/community-feed-comment-preview.css');
        $commentPreviewCssVersion = file_exists($commentPreviewCssPath) ? filemtime($commentPreviewCssPath) : time();
    @endphp
    <link rel="stylesheet" href="{{ url('/modules/community-feed/css/community-feed.css') }}?v={{ $communityFeedCssVersion }}">
    <link rel="stylesheet" href="{{ url('/modules/community-feed/css/community-feed-comment-preview.css') }}?v={{ $commentPreviewCssVersion }}">
    <link rel="preload" href="{{ url('/sounds/reactions_ux.mp3') }}" as="audio" type="audio/mpeg">
@endpush

@section('content')
<div class="cf-container">

            {{-- ── CENTER: Feed ── --}}
            <div class="feed-section">

                {{-- Filter tabs: hide on scroll down, show on scroll up --}}
                <div class="feed-sticky-toolbar">
                <div class="feed-filter-bar">
                    <button type="button" class="feed-tab feed-tab--icon active" data-filter="all" onclick="setFeedFilter(this,'all')" aria-label="All">
                        <span class="feed-tab-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                        </span>
                        <span class="feed-tab-text">All</span>
                    </button>
                    <button type="button" class="feed-tab feed-tab--icon" data-filter="announcement" onclick="setFeedFilter(this,'announcement')" aria-label="Announcements">
                        <span class="feed-tab-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 11l18-5v12L3 13v-2z"/><path d="M11 13v8a2 2 0 004 0v-6"/></svg>
                        </span>
                        <span class="feed-tab-text">Announcements</span>
                    </button>
                    <button type="button" class="feed-tab feed-tab--icon" data-filter="event" onclick="setFeedFilter(this,'event')" aria-label="Events">
                        <span class="feed-tab-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                        </span>
                        <span class="feed-tab-text">Events</span>
                    </button>
                    <button type="button" class="feed-tab feed-tab--icon" data-filter="activity" onclick="setFeedFilter(this,'activity')" aria-label="Activities">
                        <span class="feed-tab-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2L3 14h8l-1 8 10-12h-8l1-8z"/></svg>
                        </span>
                        <span class="feed-tab-text">Activities</span>
                    </button>
                    <button type="button" class="feed-tab feed-tab--icon" data-filter="program" onclick="setFeedFilter(this,'program')" aria-label="Programs">
                        <span class="feed-tab-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg>
                        </span>
                        <span class="feed-tab-text">Programs</span>
                    </button>
                </div>
                </div>
                <div class="feed-filter-anchor" aria-hidden="true"></div>

                {{-- Compose Post --}}
                <div class="post-card compose-card">
                    <div class="compose-row">
                        <img src="{{ $avatar }}" alt="Avatar" class="post-avatar">
                        <button class="compose-trigger" onclick="openComposeModal()">
                            What's happening in your barangay?
                        </button>
                    </div>
                    <div class="compose-actions">
                        <button class="compose-action-btn" onclick="openComposeModal('announcement')">
                            <svg viewBox="0 0 20 20" fill="currentColor"><path d="M18 3a1 1 0 00-1.447-.894L8.763 6H5a3 3 0 000 6h.28l1.771 5.316A1 1 0 008 18h1a1 1 0 001-1v-4.382l6.553 3.276A1 1 0 0018 15V3z"/></svg>
                            Announcement
                        </button>
                        <button class="compose-action-btn" onclick="openComposeModal('event')">
                            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/></svg>
                            Event
                        </button>
                        <button class="compose-action-btn" onclick="openComposeModal('photo')">
                            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"/></svg>
                            Photo
                        </button>
                    </div>
                </div>

                {{-- Feed header --}}
                <div class="feed-header">
                    <div class="feed-header__intro">
                        <h1>SK Community Feed</h1>
                    </div>
                    <div class="feed-header__search">
                        <svg class="feed-header__search-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"/>
                        </svg>
                        <input type="search" id="feedSearchInput" class="feed-header__search-input" placeholder="Search posts, programs, announcements..." autocomplete="off" aria-label="Search community feed">
                    </div>
                </div>

                {{-- Feed Posts --}}
                <div id="feed-posts"></div>
            </div>

            {{-- ── RIGHT: Barangay SK Profiles Sidebar ── --}}
            <aside class="programs-sidebar" id="programsSidebar">
                <div class="sidebar-card">
                    <h2 class="sidebar-title">Barangay SK Profiles</h2>
                    <p class="sidebar-subtitle">Browse SK officials from each barangay ({{ count($barangayProfiles ?? []) }} barangays).</p>
                    <div class="brgy-link-list" id="brgyLinkList">
                        @forelse($barangayProfiles ?? [] as $brgy)
                        <a href="{{ route('skfed.barangay-profile', ['slug' => $brgy['slug']]) }}" class="brgy-link-item">
                            <div class="brgy-link-dot">
                                @if(!empty($brgy['logo_url']))
                                    <img src="{{ $brgy['logo_url'] }}" alt="Brgy. {{ $brgy['name'] }} logo" class="brgy-link-logo">
                                @else
                                    <span class="brgy-link-initials">{{ $brgy['initials'] }}</span>
                                @endif
                            </div>
                            <div class="brgy-link-info">
                                <p class="brgy-link-name">Brgy. {{ $brgy['name'] }}</p>
                                <p class="brgy-link-sub">SK Officials</p>
                            </div>
                            <svg style="width:14px;height:14px;color:#bbb;flex-shrink:0;" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg>
                        </a>
                        @empty
                        <p class="brgy-link-empty">No barangays found for your municipality.</p>
                        @endforelse
                    </div>
                </div>
            </aside>
        </div>

<div class="programs-drawer-backdrop" id="programsDrawerBackdrop"></div>
<button type="button" class="programs-fab" id="programsFab" aria-label="View Barangay Profiles"><i class="fas fa-users"></i></button>
@endsection

@push('scripts')
    {{-- ── COMPOSE / EDIT POST MODAL ── --}}
    <div id="composeModal" class="program-modal compose-modal">
        <div class="modal-overlay" onclick="closeComposeModal()"></div>
        <div class="modal-container compose-modal-container" id="composeModalContainer">
            <div class="modal-header">
                <h2 id="compose-modal-title">Create Post</h2>
                <div class="compose-modal-window-actions">
                    <button type="button" id="composeFullscreenBtn" class="compose-window-btn" onclick="toggleComposeFullscreen()" title="Fullscreen (□)" aria-label="Fullscreen">
                        <svg class="compose-win-icon-max" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" width="14" height="14" aria-hidden="true"><rect x="2.5" y="2.5" width="11" height="11" rx="0.5"></rect></svg>
                        <svg class="compose-win-icon-restore" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" width="14" height="14" aria-hidden="true"><path d="M5 3.5h7.5V11"></path><rect x="2.5" y="5.5" width="8" height="8" rx="0.5"></rect></svg>
                    </button>
                    <button class="modal-close" onclick="closeComposeModal()" aria-label="Close">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                    </button>
                </div>
            </div>
            <div class="modal-body">
                <input type="hidden" id="edit-post-id" value="">
                <div class="compose-author-row">
                    <img src="{{ $avatar }}" alt="Avatar" class="compose-avatar-sm">
                    <div>
                        <div class="compose-author-name">{{ $user->name ?? 'SK Official' }}</div>
                        <select class="compose-visibility-select" id="compose-visibility" aria-label="Visibility">
                            <option value="public">🌐 Public</option>
                            <option value="federation">🔒 Federation Only</option>
                        </select>
                    </div>
                </div>
                <div class="compose-type-row">
                    <label class="compose-type-label">Post Type:</label>
                    <select class="compose-type-select" id="compose-type" aria-label="Post type">
                        <option value="update">Update</option>
                        <option value="announcement">Announcement</option>
                        <option value="event">Event</option>
                        <option value="activity">Activity</option>
                        <option value="program">Youth Program</option>
                    </select>
                </div>
                <textarea class="compose-textarea" id="compose-content" placeholder="Write something..." rows="6" maxlength="2000"></textarea>
                <div class="compose-char-row">
                    <span id="compose-char-count" class="compose-char-count">0 / 2000</span>
                </div>
                <div class="compose-attach-row">
                    <label class="compose-attach-btn" for="compose-image-input" title="Upload Image">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"/></svg>
                        Photo
                    </label>
                    <input type="file" id="compose-image-input" accept="image/*" multiple style="display:none;" onchange="previewImages(this)">
                    <label class="compose-attach-btn" id="compose-link-btn" onclick="toggleLinkInput()" title="Add Link">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.586 4.586a2 2 0 112.828 2.828l-3 3a2 2 0 01-2.828 0 1 1 0 00-1.414 1.414 4 4 0 005.656 0l3-3a4 4 0 00-5.656-5.656l-1.5 1.5a1 1 0 101.414 1.414l1.5-1.5zm-5 5a2 2 0 012.828 0 1 1 0 101.414-1.414 4 4 0 00-5.656 0l-3 3a4 4 0 105.656 5.656l1.5-1.5a1 1 0 10-1.414-1.414l-1.5 1.5a2 2 0 11-2.828-2.828l3-3z" clip-rule="evenodd"/></svg>
                        Link
                    </label>
                </div>
                <p id="compose-images-meta" class="compose-images-meta"></p>
                <div id="compose-images-preview" class="compose-images-preview"></div>
                <div id="compose-link-input-wrap" style="display:none;margin-top:10px;">
                    <input type="url" id="compose-link-input" class="compose-link-field" placeholder="Paste a link (https://...)">
                </div>
            </div>
            <div class="modal-footer-btns">
                <button class="btn-secondary" onclick="closeComposeModal()">Cancel</button>
                <button class="btn-primary" onclick="submitPost()">
                    <svg viewBox="0 0 20 20" fill="currentColor" style="width:16px;height:16px;"><path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z"/></svg>
                    Post
                </button>
            </div>
        </div>
    </div>

    <div id="feedToast" class="feed-toast" role="status" aria-live="polite"></div>

    {{-- Image Lightbox --}}
    <div id="imageLightbox" class="image-lightbox" aria-hidden="true">
        <button type="button" id="lightboxClose" class="lightbox-close" aria-label="Close">&times;</button>
        <div class="lightbox-toolbar">
            <button type="button" id="lightboxZoomOut" class="lightbox-tool-btn" aria-label="Zoom out">−</button>
            <span id="lightboxZoomLevel" class="lightbox-zoom-level">100%</span>
            <button type="button" id="lightboxZoomIn" class="lightbox-tool-btn" aria-label="Zoom in">+</button>
            <button type="button" id="lightboxZoomReset" class="lightbox-tool-btn lightbox-reset-btn" aria-label="Reset zoom">Reset</button>
        </div>
        <button type="button" id="lightboxPrev" class="lightbox-nav lightbox-prev" aria-label="Previous">&#10094;</button>
        <div class="lightbox-viewport" id="lightboxViewport">
            <img id="lightboxImage" src="" alt="Full size photo" draggable="false">
        </div>
        <button type="button" id="lightboxNext" class="lightbox-nav lightbox-next" aria-label="Next">&#10095;</button>
        <div id="lightboxCounter" class="lightbox-counter"></div>
    </div>

    <div id="reactionViewerModal" class="reaction-viewer" aria-hidden="true">
        <div class="reaction-viewer-overlay" onclick="closeReactionViewer()"></div>
        <div class="reaction-viewer-panel" role="dialog" aria-label="People who reacted">
            <div class="reaction-viewer-header">
                <div class="reaction-viewer-tabs" id="reactionViewerTabs"></div>
                <button type="button" class="reaction-viewer-close" onclick="closeReactionViewer()" aria-label="Close">&times;</button>
            </div>
            <div class="reaction-viewer-list" id="reactionViewerList"></div>
        </div>
    </div>

    <div id="editCommentModal" class="program-modal comment-action-modal">
        <div class="modal-overlay" onclick="closeEditCommentModal()"></div>
        <div class="modal-container" style="max-width:440px;">
            <div class="modal-header">
                <h2>Edit Comment</h2>
                <button type="button" class="modal-close" onclick="closeEditCommentModal()" aria-label="Close">
                    <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                </button>
            </div>
            <div class="modal-body">
                <textarea id="editCommentBody" class="edit-comment-textarea" maxlength="500" placeholder="Write a comment..."></textarea>
            </div>
            <div class="modal-footer-btns">
                <button type="button" class="btn-secondary" onclick="closeEditCommentModal()">Cancel</button>
                <button type="button" class="btn-primary" id="confirmEditCommentBtn" onclick="confirmEditComment()">Save</button>
            </div>
        </div>
    </div>

    <div id="deleteCommentModal" class="program-modal comment-action-modal">
        <div class="modal-overlay" onclick="closeDeleteCommentModal()"></div>
        <div class="modal-container" style="max-width:440px;">
            <div class="modal-header">
                <h2>Delete Comment</h2>
                <button type="button" class="modal-close" onclick="closeDeleteCommentModal()" aria-label="Close">
                    <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                </button>
            </div>
            <div class="modal-body">
                <p style="font-size:14px;color:#555;line-height:1.65;margin:0;">
                    Delete this comment? This cannot be undone.
                </p>
            </div>
            <div class="modal-footer-btns">
                <button type="button" class="btn-secondary" onclick="closeDeleteCommentModal()">Cancel</button>
                <button type="button" class="btn-danger" id="confirmDeleteCommentBtn" onclick="confirmDeleteComment()">Delete</button>
            </div>
        </div>
    </div>

    @include('community_feed::comment-preview')

    <script>
        window.currentAvatar = @json($avatar ?? '');
        window.CommunityFeedConfig = {
            userAvatar: @json($avatar ?? ''),
            userDisplayName: @json($user->name ?? 'SK Federation'),
            feedPollMs: 30000,
            commentsPageUrl: @json(url('/community-feed/comments/__ID__')),
        };
        window.CommentPreviewConfig = {
            post: @json($commentPreviewPost ?? null),
            userAvatar: @json($avatar ?? ''),
            userDisplayName: @json($user->name ?? 'SK Federation'),
            feedUrl: @json(route('community-feed')),
        };
        function showFeedToast(message, type) {
            var el = document.getElementById('feedToast');
            if (!el) return;
            el.textContent = message;
            el.className = 'feed-toast feed-toast--' + (type || 'success') + ' is-visible';
            clearTimeout(window._feedToastTimer);
            window._feedToastTimer = setTimeout(function () {
                el.classList.remove('is-visible');
            }, 3200);
        }
        window.showFeedToast = showFeedToast;
    </script>
    @php
        $communityFeedJsPath = app_path('Modules/CommunityFeed/assets/js/community-feed.js');
        $communityFeedJsVersion = file_exists($communityFeedJsPath) ? filemtime($communityFeedJsPath) : time();
        $commentPreviewJsPath = app_path('Modules/CommunityFeed/assets/js/community-feed-comment-preview.js');
        $commentPreviewJsVersion = file_exists($commentPreviewJsPath) ? filemtime($commentPreviewJsPath) : time();
    @endphp
    <script src="{{ url('/modules/community-feed/js/community-feed.js') }}?v={{ $communityFeedJsVersion }}"></script>
    <script src="{{ url('/modules/community-feed/js/community-feed-comment-preview.js') }}?v={{ $commentPreviewJsVersion }}"></script>
@endpush
