@extends('layout::app')

@section('title', 'SK Federation Profile - SK OnePortal')

@push('body-attributes')
    class="sk-fed-feed bfp-page sfp-page"
@endpush

@push('styles')
    @vite([
        'app/Modules/CommunityFeed/assets/css/community-feed.css',
        'app/Modules/CommunityFeed/assets/css/community-feed-comment-preview.css',
    ])
    <link rel="preload" href="{{ url('/sounds/reactions_ux.mp3') }}" as="audio" type="audio/mpeg">
@endpush

@section('content')
<div class="bfp-wrap sfp-wrap">

    <a href="{{ route('community-feed') }}" class="bfp-back-link" data-no-loading>
        <i class="fas fa-arrow-left" aria-hidden="true"></i>
        <span>Back to Community Feed</span>
    </a>

    <div class="bfp-grid">
        <div class="bfp-main">
            <div class="bfp-card bfp-feed-card">
                <div class="bfp-card-title"><i class="fas fa-newspaper"></i> Posts from SK Federation</div>

                <div class="feed-filter-bar bfp-filter-bar" id="sfpFilterBar">
                    <button type="button" class="feed-tab feed-tab--icon active" data-filter="all" onclick="setSfpFilter(this,'all')" aria-label="All">
                        <span class="feed-tab-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                        </span>
                        <span class="feed-tab-text">All</span>
                    </button>
                    <button type="button" class="feed-tab feed-tab--icon" data-filter="event" onclick="setSfpFilter(this,'event')" aria-label="Events">
                        <span class="feed-tab-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                        </span>
                        <span class="feed-tab-text">Events</span>
                    </button>
                    <button type="button" class="feed-tab feed-tab--icon" data-filter="announcement" onclick="setSfpFilter(this,'announcement')" aria-label="Announcements">
                        <span class="feed-tab-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 11l18-5v12L3 13v-2z"/><path d="M11 13v8a2 2 0 004 0v-6"/></svg>
                        </span>
                        <span class="feed-tab-text">Announcements</span>
                    </button>
                    <button type="button" class="feed-tab feed-tab--icon" data-filter="activity" onclick="setSfpFilter(this,'activity')" aria-label="Activities">
                        <span class="feed-tab-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2L3 14h8l-1 8 10-12h-8l1-8z"/></svg>
                        </span>
                        <span class="feed-tab-text">Activities</span>
                    </button>
                </div>

                <div id="sfpFeedPosts"></div>
            </div>
        </div>

        <aside class="bfp-sidebar" id="sfpSidebar">
            <button type="button" class="bfp-sidebar-close" id="sfpSidebarClose" aria-label="Close SK Federation profile">&times;</button>
            <div class="bfp-card bfp-profile-card">
                <div class="bfp-profile-head">
                    <div class="bfp-avatar bfp-avatar--logo">
                        <img src="{{ asset('Images/SK_OnePortal_logo.png') }}" alt="SK Federation logo">
                    </div>
                    <div class="bfp-profile-copy">
                        <h1 class="bfp-name">SK Federation</h1>
                        <p class="bfp-loc"><i class="fas fa-map-marker-alt" aria-hidden="true"></i>Santa Cruz, Laguna</p>
                    </div>
                </div>
                <div class="bfp-stats">
                    <div class="bfp-stat"><strong>{{ $post_count ?? 0 }}</strong><span>Posts</span></div>
                    <div class="bfp-stat"><strong>{{ $officer_count ?? 0 }}</strong><span>Officers</span></div>
                    <div class="bfp-stat"><strong>{{ $term_label ?? '—' }}</strong><span>SK Term</span></div>
                </div>
            </div>

            <div class="bfp-card">
                <div class="bfp-card-title"><i class="fas fa-info-circle"></i> About</div>
                <p class="sfp-about-text">Sangguniang Kabataan Federation of Santa Cruz, Laguna. Posts here are federation-wide announcements, events, and activities.</p>
            </div>
        </aside>
    </div>

    <div class="bfp-sidebar-backdrop" id="sfpSidebarBackdrop"></div>
    <button type="button" class="bfp-sidebar-fab" id="sfpSidebarFab" aria-label="View SK Federation profile info">
        <i class="fas fa-id-card" aria-hidden="true"></i>
        <span>Federation Info</span>
    </button>
</div>

<div id="feedToast" class="feed-toast" role="status" aria-live="polite"></div>

<div id="videoLightbox" class="image-lightbox video-lightbox" aria-hidden="true">
    <button type="button" id="videoLightboxClose" class="lightbox-close" aria-label="Close">&times;</button>
    <div class="lightbox-stage">
        <video id="videoLightboxPlayer" controls playsinline></video>
    </div>
</div>

<div id="imageLightbox" class="image-lightbox" aria-hidden="true">
    <button type="button" id="imageLightboxClose" class="lightbox-close" aria-label="Close">&times;</button>
    <button type="button" id="imageLightboxPrev" class="lightbox-nav lightbox-prev" aria-label="Previous">‹</button>
    <div class="lightbox-stage"><img id="imageLightboxImg" src="" alt=""></div>
    <button type="button" id="imageLightboxNext" class="lightbox-nav lightbox-next" aria-label="Next">›</button>
    <div class="lightbox-counter" id="imageLightboxCounter"></div>
</div>

@include('community_feed::comment-preview')
@endsection

@push('scripts')
    <script>
        window.__skProhibitedWords = @json(config('prohibited_words', []));
        window.currentAvatar = @json($avatar ?? asset('Images/SK_OnePortal_logo.png'));
        window.CommunityFeedConfig = {
            userAvatar: @json($avatar ?? asset('Images/SK_OnePortal_logo.png')),
            userDisplayName: @json($user->name ?? 'SK Federation'),
            feedPollMs: 8000,
            commentPollMs: 5000,
            commentsPageUrl: @json(url('/community-feed/comments/__ID__')),
            prohibitedWords: window.__skProhibitedWords,
            cloudinaryCloudName: @json(config('services.cloudinary.cloud_name')),
            communityVideoPreset: @json(config('services.cloudinary.community_video_upload_preset')),
            communityVideoMaxBytes: @json((int) config('services.cloudinary.community_video_max_bytes', 262144000)),
            communityVideoMaxCount: @json((int) config('services.cloudinary.community_video_max_count', 5)),
        };
        window.CommentPreviewConfig = {
            post: @json($commentPreviewPost ?? null),
            userAvatar: @json($avatar ?? asset('Images/SK_OnePortal_logo.png')),
            userDisplayName: @json($user->name ?? 'SK Federation'),
            feedUrl: @json(route('sk-fed-profile')),
            prohibitedWords: window.__skProhibitedWords,
        };
        window.SkFedProfileConfig = {
            posts: @json($formattedPosts ?? []),
            feedPollMs: 8000,
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
    @vite([
        'app/Modules/CommunityFeed/assets/js/prohibited-words.js',
        'app/Modules/CommunityFeed/assets/js/community-feed-media.js',
        'app/Modules/CommunityFeed/assets/js/community-feed.js',
        'app/Modules/CommunityFeed/assets/js/community-feed-comment-preview.js',
        'app/Modules/CommunityFeed/assets/js/sk-fed-profile.js',
    ])
@endpush
