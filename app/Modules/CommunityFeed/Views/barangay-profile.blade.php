@extends('layout::app')

@section('title', 'SK Barangay ' . ($name ?? '') . ' - SK OnePortal')

@push('body-attributes')
    class="sk-fed-feed bfp-page"
@endpush

@push('styles')
    @php
        $communityFeedCssPath = base_path('app/Modules/CommunityFeed/assets/css/community-feed.css');
        $communityFeedCssVersion = file_exists($communityFeedCssPath) ? filemtime($communityFeedCssPath) : time();
        $commentPreviewCssPath = base_path('app/Modules/CommunityFeed/assets/css/community-feed-comment-preview.css');
        $commentPreviewCssVersion = file_exists($commentPreviewCssPath) ? filemtime($commentPreviewCssPath) : time();
    @endphp
    <link rel="stylesheet" href="{{ url('/modules/community-feed/css/community-feed.css') }}?v={{ $communityFeedCssVersion }}">
    <link rel="stylesheet" href="{{ url('/modules/community-feed/css/community-feed-comment-preview.css') }}?v={{ $commentPreviewCssVersion }}">
    <link rel="preload" href="{{ url('/sounds/reactions_ux.mp3') }}" as="audio" type="audio/mpeg">
@endpush

@section('content')
<div class="bfp-wrap">

    <a href="{{ route('community-feed') }}" class="bfp-back-link" data-no-loading>
        <i class="fas fa-arrow-left" aria-hidden="true"></i>
        <span>Back to Community Feed</span>
    </a>

    <div class="bfp-grid">
        <div class="bfp-main">
            <div class="bfp-card bfp-feed-card">
                <div class="bfp-card-title"><i class="fas fa-newspaper"></i> Posts from Barangay {{ $name }}</div>

                <div class="feed-filter-bar bfp-filter-bar" id="bfpFilterBar">
                    <button type="button" class="feed-tab feed-tab--icon active" data-filter="all" onclick="setBfpFilter(this,'all')" aria-label="All">
                        <span class="feed-tab-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                        </span>
                        <span class="feed-tab-text">All</span>
                    </button>
                    <button type="button" class="feed-tab feed-tab--icon" data-filter="event" onclick="setBfpFilter(this,'event')" aria-label="Events">
                        <span class="feed-tab-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                        </span>
                        <span class="feed-tab-text">Events</span>
                    </button>
                    <button type="button" class="feed-tab feed-tab--icon" data-filter="announcement" onclick="setBfpFilter(this,'announcement')" aria-label="Announcements">
                        <span class="feed-tab-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 11l18-5v12L3 13v-2z"/><path d="M11 13v8a2 2 0 004 0v-6"/></svg>
                        </span>
                        <span class="feed-tab-text">Announcements</span>
                    </button>
                    <button type="button" class="feed-tab feed-tab--icon" data-filter="activity" onclick="setBfpFilter(this,'activity')" aria-label="Activities">
                        <span class="feed-tab-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2L3 14h8l-1 8 10-12h-8l1-8z"/></svg>
                        </span>
                        <span class="feed-tab-text">Activities</span>
                    </button>
                </div>

                <div id="bfpFeedPosts"></div>
            </div>
        </div>

        <aside class="bfp-sidebar" id="bfpSidebar">
            <button type="button" class="bfp-sidebar-close" id="bfpSidebarClose" aria-label="Close barangay profile">&times;</button>
            <div class="bfp-card bfp-profile-card">
                <div class="bfp-profile-head">
                    <div class="bfp-avatar bfp-avatar--logo">
                        @if(!empty($profile['logo_url']))
                            <img src="{{ $profile['logo_url'] }}" alt="SK Barangay {{ $name }} logo">
                        @else
                            {{ $profile['initials'] ?? strtoupper(substr($name, 0, 2)) }}
                        @endif
                    </div>
                    <div class="bfp-profile-copy">
                        <h1 class="bfp-name">SK Barangay {{ $name }}</h1>
                        <p class="bfp-loc"><i class="fas fa-map-marker-alt" aria-hidden="true"></i>Barangay {{ $name }}, Santa Cruz, Laguna</p>
                    </div>
                </div>
                <div class="bfp-stats">
                    <div class="bfp-stat"><strong>{{ $post_count ?? 0 }}</strong><span>Posts</span></div>
                    <div class="bfp-stat"><strong>{{ $officer_count ?? count($officials) }}</strong><span>Officers</span></div>
                    <div class="bfp-stat"><strong>{{ $term_label ?? '—' }}</strong><span>SK Term</span></div>
                </div>
            </div>

            <div class="bfp-card">
                <div class="bfp-card-title"><i class="fas fa-users"></i> SK Officers</div>
                @forelse($officials as $o)
                <div class="bfp-officer-item">
                    <div class="bfp-officer-dot">
                        @if(!empty($o['logo_url']))
                            <img src="{{ $o['logo_url'] }}" alt="Barangay {{ $name }} logo">
                        @else
                            {{ $o['initials'] }}
                        @endif
                    </div>
                    <div>
                        <p class="bfp-officer-name">{{ $o['name'] }}</p>
                        <p class="bfp-officer-role">{{ $o['role'] }}</p>
                    </div>
                </div>
                @empty
                <div class="bfp-empty">
                    <i class="fas fa-user-friends"></i>
                    No SK officials found for this barangay.
                </div>
                @endforelse
            </div>
        </aside>
    </div>

    <div class="bfp-sidebar-backdrop" id="bfpSidebarBackdrop"></div>
    <button type="button" class="bfp-sidebar-fab" id="bfpSidebarFab" aria-label="View barangay profile and officers">
        <i class="fas fa-id-card" aria-hidden="true"></i>
        <span>Barangay Info</span>
    </button>
</div>

<div id="feedToast" class="feed-toast" role="status" aria-live="polite"></div>

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
@endsection

@push('scripts')
    @php
        $communityFeedJsPath = app_path('Modules/CommunityFeed/assets/js/community-feed.js');
        $communityFeedJsVersion = file_exists($communityFeedJsPath) ? filemtime($communityFeedJsPath) : time();
        $commentPreviewJsPath = app_path('Modules/CommunityFeed/assets/js/community-feed-comment-preview.js');
        $commentPreviewJsVersion = file_exists($commentPreviewJsPath) ? filemtime($commentPreviewJsPath) : time();
        $barangayProfileJsPath = app_path('Modules/CommunityFeed/assets/js/barangay-profile.js');
        $barangayProfileJsVersion = file_exists($barangayProfileJsPath) ? filemtime($barangayProfileJsPath) : time();
    @endphp
    <script>
        window.currentAvatar = @json($avatar ?? '');
        window.CommunityFeedConfig = {
            userAvatar: @json($avatar ?? ''),
            userDisplayName: @json($user->name ?? 'SK Federation'),
            feedPollMs: 30000,
            commentsPageUrl: @json(url('/barangay-profile/' . ($slug ?? '') . '/comments/__ID__')),
        };
        window.CommentPreviewConfig = {
            post: @json($commentPreviewPost ?? null),
            userAvatar: @json($avatar ?? ''),
            userDisplayName: @json($user->name ?? 'SK Federation'),
            feedUrl: @json(route('skfed.barangay-profile', ['slug' => $slug ?? ''])),
        };
        window.BarangayProfileConfig = {
            slug: @json($slug ?? ''),
            barangayId: @json($profile['id'] ?? null),
            posts: @json($formattedPosts ?? []),
            feedPollMs: 30000,
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
    <script src="{{ url('/modules/community-feed/js/community-feed.js') }}?v={{ $communityFeedJsVersion }}"></script>
    <script src="{{ url('/modules/community-feed/js/community-feed-comment-preview.js') }}?v={{ $commentPreviewJsVersion }}"></script>
    <script src="{{ url('/modules/community-feed/js/barangay-profile.js') }}?v={{ $barangayProfileJsVersion }}"></script>
@endpush
