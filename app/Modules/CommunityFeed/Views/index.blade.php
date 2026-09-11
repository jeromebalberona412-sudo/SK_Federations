@extends('layout::app')

@section('title', 'SK Community Feed - SK OnePortal')

@push('body-attributes')
    class="sk-fed-feed"
@endpush

@push('main-class')
    cf-main
@endpush

@push('styles')
    @vite([
        'app/Modules/CommunityFeed/assets/css/community-feed.css',
        'app/Modules/CommunityFeed/assets/css/community-feed-comment-preview.css',
    ])
    <link rel="preload" href="{{ url('/sounds/reactions_ux.mp3') }}" as="audio" type="audio/mpeg">
@endpush

@section('content')
<div class="cf-container">

            {{-- ── CENTER: Feed ── --}}
            <div class="feed-section">

                {{-- Filter tabs: hide on scroll down, show on scroll up --}}
                <div class="feed-sticky-toolbar">
                <div class="feed-filter-bar">
                    <button type="button" class="feed-tab feed-tab--icon active" data-filter="all" aria-label="All">
                        <span class="feed-tab-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                        </span>
                        <span class="feed-tab-text">All</span>
                    </button>
                    <button type="button" class="feed-tab feed-tab--icon" data-filter="announcement" aria-label="Announcements">
                        <span class="feed-tab-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 11l18-5v12L3 13v-2z"/><path d="M11 13v8a2 2 0 004 0v-6"/></svg>
                        </span>
                        <span class="feed-tab-text">Announcements</span>
                    </button>
                    <button type="button" class="feed-tab feed-tab--icon" data-filter="event" aria-label="Events">
                        <span class="feed-tab-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                        </span>
                        <span class="feed-tab-text">Events</span>
                    </button>
                    <button type="button" class="feed-tab feed-tab--icon" data-filter="activity" aria-label="Activities">
                        <span class="feed-tab-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2L3 14h8l-1 8 10-12h-8l1-8z"/></svg>
                        </span>
                        <span class="feed-tab-text">Activities</span>
                    </button>
                    <button type="button" class="feed-tab feed-tab--icon" data-filter="program" aria-label="Programs">
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
                        <button type="button" class="compose-trigger" data-compose-open>
                            What's happening in your barangay?
                        </button>
                    </div>
                    <div class="compose-actions">
                        <button type="button" class="compose-action-btn" data-compose-open="announcement">
                            <svg viewBox="0 0 20 20" fill="currentColor"><path d="M18 3a1 1 0 00-1.447-.894L8.763 6H5a3 3 0 000 6h.28l1.771 5.316A1 1 0 008 18h1a1 1 0 001-1v-4.382l6.553 3.276A1 1 0 0018 15V3z"/></svg>
                            Announcement
                        </button>
                        <button type="button" class="compose-action-btn" data-compose-open="event">
                            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/></svg>
                            Event
                        </button>
                        <button type="button" class="compose-action-btn" data-compose-open="photo">
                            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"/></svg>
                            Photo
                        </button>
                        <button type="button" class="compose-action-btn" data-compose-open="video">
                            <svg viewBox="0 0 20 20" fill="currentColor"><path d="M2 6a2 2 0 012-2h6a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V6zm12.553 1.106A1 1 0 0014 8v4a1 1 0 00.553.894l2 1A1 1 0 0018 13V7a1 1 0 00-1.447-.894l-2 1z"/></svg>
                            Video
                        </button>
                    </div>
                </div>

                {{-- Feed header --}}
                <div class="feed-header">
                    <div class="feed-header__intro">
                        <h1>SK Community Feed</h1>
                    </div>
                    <div class="feed-header__tools">
                        <div class="feed-header__search">
                            <svg class="feed-header__search-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"/>
                            </svg>
                            <input type="search" id="feedSearchInput" class="feed-header__search-input" placeholder="Search posts, programs, announcements..." autocomplete="off" aria-label="Search community feed">
                        </div>
                        <button type="button" class="feed-filters-btn" id="feedFiltersBtn" aria-haspopup="dialog" aria-controls="feedFiltersModal">
                            <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M3 4a1 1 0 011-1h12a1 1 0 01.8 1.6L12 11.333V15a1 1 0 01-.553.894l-2 1A1 1 0 018 16v-4.667L3.2 4.6A1 1 0 013 4z"/></svg>
                            <span>Filters</span>
                        </button>
                    </div>
                </div>

                {{-- Feed Posts --}}
                <div id="feed-empty" class="post-card feed-empty-card" hidden>No posts found.</div>
                <div id="feed-posts"></div>
            </div>

            {{-- ── RIGHT: Barangay SK Profiles Sidebar ── --}}
            <aside class="programs-sidebar" id="programsSidebar">
                <div class="sidebar-card">
                    <h2 class="sidebar-title">Barangay SK Profiles</h2>
                    <p class="sidebar-subtitle">Browse SK officials from each barangay.</p>
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
                        <div class="compose-audience-wrap" id="compose-audience-wrap">
                            <button type="button" class="compose-visibility-select compose-audience-toggle" id="compose-audience-toggle" aria-haspopup="listbox" aria-expanded="false" aria-label="Notify targets">
                                Select targets
                            </button>
                            <div class="compose-audience-menu" id="compose-audience-menu" hidden role="listbox" aria-multiselectable="true" aria-label="Select who to notify">
                                <p class="compose-audience-hint">Select one or more. These control who gets the notification bell. Email is optional via the checkbox below. Public also makes the post shareable and selects all audiences.</p>
                                <label class="compose-audience-option">
                                    <input type="checkbox" name="compose-audience" value="public">
                                    <span>
                                        <strong>Public</strong>
                                        <small>Shareable post. Selects Federation, SK Officials, and Kabataan. Bell notifications always send.</small>
                                    </span>
                                </label>
                                <label class="compose-audience-option">
                                    <input type="checkbox" name="compose-audience" value="federation">
                                    <span>
                                        <strong>Federation</strong>
                                        <small>Federation portal users only (not publicly shareable unless Public is also selected).</small>
                                    </span>
                                </label>
                                <label class="compose-audience-option">
                                    <input type="checkbox" name="compose-audience" value="officials">
                                    <span>
                                        <strong>SK Officials</strong>
                                        <small>All SK Officials across Santa Cruz barangays.</small>
                                    </span>
                                </label>
                                <label class="compose-audience-option">
                                    <input type="checkbox" name="compose-audience" value="kabataan">
                                    <span>
                                        <strong>Kabataan</strong>
                                        <small>All eligible Kabataan members across Santa Cruz.</small>
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="compose-type-row">
                    <label class="compose-type-label" for="compose-type">Post Type:</label>
                    <select class="compose-type-select" id="compose-type" aria-label="Post type">
                        <option value="update">Update</option>
                        <option value="announcement">Announcement</option>
                        <option value="event">Event</option>
                        <option value="photo">Photo</option>
                        <option value="activity">Activity</option>
                        <option value="program">Youth Program</option>
                    </select>
                </div>
                <div class="compose-update-title-wrap" id="compose-update-title-wrap">
                    <label class="compose-type-label" for="compose-title">Please specify what update</label>
                    <input type="text" id="compose-title" class="compose-title-input" maxlength="50" placeholder="e.g. Clean-up drive schedule" autocomplete="off" aria-describedby="compose-title-count compose-title-error">
                    <div class="compose-title-meta">
                        <span id="compose-title-count" class="compose-char-count">0 / 50</span>
                    </div>
                    <p id="compose-title-error" class="compose-field-error" role="alert"></p>
                </div>
                <textarea class="compose-textarea" id="compose-content" placeholder="Write something..." rows="6"></textarea>
                <div class="compose-char-row" aria-live="polite">
                    <span id="compose-char-count" class="compose-char-count">0 / 25000</span>
                </div>
                <div class="compose-attach-row">
                    <label class="compose-attach-btn" for="compose-image-input" title="Upload Image">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"/></svg>
                        Photo
                    </label>
                    <input type="file" id="compose-image-input" accept="image/*" multiple style="display:none;" onchange="previewImages(this)">
                    <button type="button" class="compose-attach-btn" id="compose-add-video-btn" onclick="toggleVideoMethodPanel()">
                        <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M2 6a2 2 0 012-2h6a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V6zm12.553 1.106A1 1 0 0014 8v4a1 1 0 00.553.894l2 1A1 1 0 0018 13V7a1 1 0 00-1.447-.894l-2 1z"/></svg>
                        Add Video
                    </button>
                </div>
                <p id="compose-images-meta" class="compose-images-meta"></p>
                <div id="compose-images-preview" class="compose-images-preview"></div>
                <div id="compose-video-method-wrap" class="compose-video-method-wrap" hidden>
                    <p class="compose-media-label">Video Upload Method</p>
                    <div class="compose-video-method-list" role="radiogroup" aria-label="Video upload method">
                        <label class="compose-video-method-option">
                            <input type="radio" name="compose-video-method" value="upload" checked>
                            <span><strong>Upload Video</strong><small>Upload the video directly to SK OnePortal.</small></span>
                        </label>
                        <label class="compose-video-method-option">
                            <input type="radio" name="compose-video-method" value="drive">
                            <span><strong>Use Google Drive Link</strong><small>Paste one Google Drive video file link.</small></span>
                        </label>
                    </div>
                    <div id="compose-video-upload-panel" class="compose-video-upload-panel">
                        <label class="compose-attach-btn" for="compose-video-input-file">
                            <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M2 6a2 2 0 012-2h6a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V6zm12.553 1.106A1 1 0 0014 8v4a1 1 0 00.553.894l2 1A1 1 0 0018 13V7a1 1 0 00-1.447-.894l-2 1z"/></svg>
                            Choose videos
                        </label>
                        <input type="file" id="compose-video-input-file" accept="video/mp4,video/webm,video/quicktime,.mp4,.webm,.mov" multiple hidden>
                        <p class="compose-video-help">Maximum video size: 250 MB<br>Allowed: MP4, WebM, MOV<br>Up to 5 videos</p>
                        <p id="compose-videos-meta" class="compose-images-meta"></p>
                        <div id="compose-videos-preview" class="compose-videos-preview"></div>
                        <p id="compose-video-upload-error" class="compose-field-error" role="alert" hidden></p>
                    </div>
                    <div id="compose-video-drive-panel" class="compose-video-input-wrap" hidden>
                        <label class="compose-media-label" for="compose-video-input">Google Drive Video Link</label>
                        <input type="url" id="compose-video-input" class="compose-link-field" placeholder="Paste Google Drive video link here" autocomplete="off" inputmode="url" aria-describedby="compose-video-help compose-video-error">
                        <p id="compose-video-help" class="compose-video-help">For <strong>1 specific video</strong> only (single file link, not a folder).</p>
                        <p id="compose-video-error" class="compose-field-error" role="alert" hidden></p>
                        <button type="button" class="compose-remove-video-btn" id="compose-remove-video-btn" hidden onclick="clearComposeDriveVideo()">Remove Drive link</button>
                    </div>
                </div>
            </div>
            <div id="compose-email-notify-wrap" class="compose-email-notify form-check">
                <input
                    class="form-check-input"
                    type="checkbox"
                    id="compose-email-notify"
                    value="1"
                    checked
                    autocomplete="off"
                >
                <label class="form-check-label" for="compose-email-notify">
                    Send email notify to selected targets
                </label>
                <p id="compose-email-notify-help" class="compose-email-notify-help form-text mb-0">
                    Email selected audiences across Santa Cruz. Uncheck to skip email (bell notifications still send to selected targets).
                </p>
            </div>
            <p id="compose-body-error" class="compose-field-error" role="alert" hidden></p>
            <div class="modal-footer-btns">
                <button class="btn-secondary" onclick="closeComposeModal()">Cancel</button>
                <button type="button" class="btn-primary" id="composePostBtn" onclick="submitPost()">Post</button>
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

    <div id="videoLightbox" class="image-lightbox video-lightbox" aria-hidden="true">
        <button type="button" id="videoLightboxClose" class="lightbox-close" aria-label="Close">&times;</button>
        <button type="button" id="videoLightboxPrev" class="lightbox-nav lightbox-prev" aria-label="Previous video">&#10094;</button>
        <div class="lightbox-viewport video-lightbox-viewport" id="videoLightboxViewport">
            <div id="videoLightboxFrame" class="video-lightbox-frame"></div>
        </div>
        <button type="button" id="videoLightboxNext" class="lightbox-nav lightbox-next" aria-label="Next video">&#10095;</button>
        <div id="videoLightboxCounter" class="lightbox-counter"></div>
    </div>

    <div id="sharePostModal" class="program-modal" aria-hidden="true">
        <div class="modal-overlay" onclick="closeShareModal()"></div>
        <div class="modal-container share-post-modal" role="dialog" aria-modal="true" aria-labelledby="sharePostModalTitle">
            <div class="modal-header">
                <h2 id="sharePostModalTitle">Share post</h2>
                <button type="button" class="modal-close" onclick="closeShareModal()" aria-label="Close">
                    <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                </button>
            </div>
            <form class="share-post-form" id="sharePostForm" onsubmit="event.preventDefault(); copySharePostLink();">
                <div class="modal-body">
                    <p class="share-post-hint">Anyone with this link can view this post.</p>
                    <label class="share-post-label" for="sharePostLinkInput">Public post link</label>
                    <div class="share-post-field">
                        <input type="text" id="sharePostLinkInput" class="share-post-link-input" readonly value="" aria-label="Public post link">
                    </div>
                </div>
                <div class="modal-footer-btns share-post-footer">
                    <button type="button" class="btn-secondary" onclick="closeShareModal()">Close</button>
                    <button type="submit" class="btn-primary" id="copySharePostLinkBtn">Copy link</button>
                </div>
            </form>
        </div>
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

    <div id="archivePostModal" class="program-modal comment-action-modal">
        <div class="modal-overlay" onclick="closeArchiveModal()"></div>
        <div class="modal-container" style="max-width:440px;">
            <div class="modal-header">
                <h2>Delete Post</h2>
                <button type="button" class="modal-close" onclick="closeArchiveModal()" aria-label="Close">
                    <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                </button>
            </div>
            <div class="modal-body">
                <p style="font-size:14px;color:#555;line-height:1.65;margin:0;">
                    This post will be moved to Deleted Posts.<br><br>
                    You can restore it from Archive Management within 30 days.<br><br>
                    After 30 days, the post and uploads may be permanently deleted.
                </p>
            </div>
            <div class="modal-footer-btns">
                <button type="button" class="btn-secondary" onclick="closeArchiveModal()">Cancel</button>
                <button type="button" class="btn-danger" id="confirmArchiveBtn" onclick="confirmArchivePost()">Delete Post</button>
            </div>
        </div>
    </div>

    <div id="feedFiltersModal" class="program-modal" aria-hidden="true">
        <div class="modal-overlay" data-feed-filters-close></div>
        <div class="modal-container feed-filters-modal" role="dialog" aria-modal="true" aria-labelledby="feedFiltersTitle">
            <div class="modal-header">
                <h2 id="feedFiltersTitle">Post filters</h2>
                <button type="button" class="modal-close" data-feed-filters-close aria-label="Close"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg></button>
            </div>
            <div class="modal-body">
                <label class="feed-filters-label" for="feedFilterYear">Go to:</label>
                <select id="feedFilterYear" class="feed-filters-select" aria-label="Filter posts by year"></select>
            </div>
            <div class="modal-footer-btns feed-filters-footer">
                <button type="button" class="feed-filters-clear" id="feedFiltersClear">Clear</button>
                <button type="button" class="btn-primary" id="feedFiltersDone">Done</button>
            </div>
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
                <textarea id="editCommentBody" class="edit-comment-textarea" maxlength="2000" placeholder="Write a comment..."></textarea>
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
        window.__skProhibitedWords = @json(config('prohibited_words', []));
        window.currentAvatar = @json($avatar ?? '');
        window.CommunityFeedConfig = {
            userAvatar: @json($avatar ?? ''),
            userDisplayName: @json($user->name ?? 'SK Federation'),
            feedPollMs: 5000,
            commentPollMs: 3000,
            deletedPostsUrl: @json(route('archived.deleted-posts')),
            feedYearsWithPosts: @json($feedYearsWithPosts ?? []),
            commentsPageUrl: @json(url('/community-feed/comments/__ID__')),
            prohibitedWords: window.__skProhibitedWords,
            cloudinaryCloudName: @json(config('services.cloudinary.cloud_name')),
            communityVideoPreset: @json(config('services.cloudinary.community_video_upload_preset')),
            communityVideoMaxBytes: @json((int) config('services.cloudinary.community_video_max_bytes', 262144000)),
            communityVideoMaxCount: @json((int) config('services.cloudinary.community_video_max_count', 5)),
        };
        window.CommentPreviewConfig = {
            post: @json($commentPreviewPost ?? null),
            userAvatar: @json($avatar ?? ''),
            userDisplayName: @json($user->name ?? 'SK Federation'),
            feedUrl: @json(route('community-feed')),
            prohibitedWords: window.__skProhibitedWords,
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
        'app/Modules/CommunityFeed/assets/js/comment-spam-guard.js',
        'app/Modules/CommunityFeed/assets/js/community-feed-media.js',
        'app/Modules/CommunityFeed/assets/js/community-feed.js',
        'app/Modules/CommunityFeed/assets/js/community-feed-comment-preview.js',
    ])
@endpush
