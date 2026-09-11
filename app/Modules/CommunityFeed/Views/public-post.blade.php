<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" href="{{ asset('images/SK_OnePortal_logo.png') }}" type="image/png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>
        @if (!empty($available) && !empty($post))
            Community Post - SK OnePortal
        @else
            Post unavailable - SK OnePortal
        @endif
    </title>
    @vite([
        'app/Modules/CommunityFeed/assets/css/community-feed.css',
        'app/Modules/CommunityFeed/assets/css/community-feed-public.css',
    ])
</head>
<body class="cf-public-page">
<header class="cf-public-header">
    <div class="cf-public-header-inner">
        <img src="{{ asset('images/SK_OnePortal_logo.png') }}" alt="SK OnePortal" class="cf-public-logo" width="40" height="40">
        <div class="cf-public-brand">
            <p class="cf-public-brand-name">SK OnePortal</p>
            <p class="cf-public-brand-sub">Community Post</p>
        </div>
    </div>
</header>

<main class="cf-public-main">
    @if (!empty($available) && !empty($post))
        @php
            $avatar = $post['barangay_logo_url'] ?? asset('images/SK_OnePortal_logo.png');
            $images = array_values(array_filter($post['images'] ?? []));
            $imageCount = count($images);
            $gridClass = $imageCount <= 1 ? 'grid-1' : ($imageCount === 2 ? 'grid-2' : ($imageCount === 3 ? 'grid-3' : ($imageCount === 4 ? 'grid-4' : 'grid-4-plus')));
        @endphp
        <article class="post-card cf-public-post" aria-label="Shared community post">
            <div class="post-header">
                <img src="{{ $avatar }}" alt="" class="post-avatar">
                <div class="post-info">
                    <h1 class="post-author">{{ $post['author_name'] ?? 'SK Federation' }}</h1>
                    <p class="post-meta">
                        @if (!empty($post['type']))
                            <span class="post-type {{ $post['type'] }}">{{ $post['type'] }}</span>
                        @endif
                        <span class="post-time">{{ $post['posted_at'] ?? ($post['time'] ?? '') }}</span>
                    </p>
                </div>
            </div>
            <div class="post-content">
                @if (!empty($post['title']))
                    <h2 class="post-title">{{ $post['title'] }}</h2>
                @endif
                <p class="post-text">{!! nl2br(e($post['body'] ?? '')) !!}</p>
                @if ($imageCount > 0)
                    <div class="post-media-grid {{ $gridClass }}">
                        @foreach (array_slice($images, 0, 4) as $index => $url)
                            <div class="post-media-tile">
                                <img src="{{ $url }}" alt="Post image {{ $index + 1 }}" loading="lazy">
                                @if ($index === 3 && $imageCount > 4)
                                    <span class="post-media-more">+{{ $imageCount - 4 }}</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
                @if (!empty($post['videos']) && is_array($post['videos']))
                    @foreach ($post['videos'] as $video)
                        @if (($video['provider'] ?? '') === 'cloudinary' && !empty($video['video_url']))
                            <div class="cf-cloud-video">
                                <div class="cf-cloud-video-frame">
                                    <video src="{{ $video['video_url'] }}" controls playsinline preload="metadata" controlslist="nodownload"></video>
                                </div>
                            </div>
                        @elseif (!empty($video['preview_url']))
                            <div class="cf-drive-video" data-drive-video data-preview-src="{{ $video['preview_url'] }}">
                                <div class="cf-drive-video-frame cf-drive-video-poster">
                                    <button type="button" class="cf-drive-play-btn" aria-label="Play video">
                                        <span class="cf-drive-play-icon" aria-hidden="true">▶</span>
                                        <span>Play video</span>
                                    </button>
                                </div>
                                <div class="cf-drive-video-fallback" hidden>
                                    <p>Video unavailable. Please check the Google Drive sharing permissions.</p>
                                    @if (!empty($video['open_url']))
                                        <a href="{{ $video['open_url'] }}" target="_blank" rel="noopener noreferrer" class="cf-drive-open-link">Open in Google Drive</a>
                                    @endif
                                </div>
                            </div>
                        @endif
                    @endforeach
                @elseif (!empty($post['google_drive_video']['preview_url']))
                    <div class="cf-drive-video" data-drive-video data-preview-src="{{ $post['google_drive_video']['preview_url'] }}">
                        <div class="cf-drive-video-frame cf-drive-video-poster">
                            <button type="button" class="cf-drive-play-btn" aria-label="Play video">
                                <span class="cf-drive-play-icon" aria-hidden="true">▶</span>
                                <span>Play video</span>
                            </button>
                        </div>
                        <div class="cf-drive-video-fallback" hidden>
                            <p>Video unavailable. Please check the Google Drive sharing permissions.</p>
                            @if (!empty($post['google_drive_video']['open_url']))
                                <a href="{{ $post['google_drive_video']['open_url'] }}" target="_blank" rel="noopener noreferrer" class="cf-drive-open-link">Open in Google Drive</a>
                            @endif
                        </div>
                    </div>
                @elseif (!empty($post['link_url']))
                    <a href="{{ $post['link_url'] }}" target="_blank" rel="noopener noreferrer" class="post-link-preview">{{ $post['link_url'] }}</a>
                @endif
            </div>
        </article>
        <p class="cf-public-note">This is a shared community post. Interactions are available only inside SK OnePortal.</p>
    @else
        <div class="cf-public-unavailable" role="status">
            <h1>Post unavailable</h1>
            <p>This post is no longer available.</p>
        </div>
    @endif
</main>
<script>
(() => {
    document.querySelectorAll('[data-drive-video]').forEach((wrap) => {
        const playBtn = wrap.querySelector('.cf-drive-play-btn');
        const frame = wrap.querySelector('.cf-drive-video-frame');
        const fallback = wrap.querySelector('.cf-drive-video-fallback');
        let previewSrc = wrap.dataset.previewSrc;
        if (!playBtn || !frame || !previewSrc) return;
        if (!previewSrc.includes('autoplay=')) {
            previewSrc += previewSrc.includes('?') ? '&autoplay=1' : '?autoplay=1';
        }
        playBtn.addEventListener('click', () => {
            if (wrap.dataset.playing === '1') return;
            wrap.dataset.playing = '1';
            wrap.classList.add('is-playing');
            frame.classList.remove('cf-drive-video-poster');
            frame.innerHTML = `<iframe src="${previewSrc}" title="Google Drive video" allow="autoplay; fullscreen" allowfullscreen referrerpolicy="no-referrer-when-downgrade"></iframe>`;
            const iframe = frame.querySelector('iframe');
            iframe?.addEventListener('error', () => {
                wrap.classList.add('is-fallback');
                wrap.classList.remove('is-playing');
                if (fallback) fallback.hidden = false;
                frame.hidden = true;
            });
        });
    });
})();
</script>
</body>
</html>
