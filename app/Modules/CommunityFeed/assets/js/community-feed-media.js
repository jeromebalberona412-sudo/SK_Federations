/* Community Feed — video album, compose upload, share modal, body validation */
'use strict';

(function (global) {
    var MAX_BODY_CHARS = 25000;
    var MIN_BODY_CHARS = 3;
    var UPDATE_TITLE_MAX = 50;

    var pendingVideoUploads = [];
    var existingVideos = [];
    var removedVideoIds = [];
    var videoUploadSeq = 0;
    var videoUploadUnloadBound = false;
    var attachVideosAfterPostQueue = [];
    var videoAlbumCache = new Map();
    var videoLightboxItems = [];
    var videoLightboxIndex = 0;
    var composeTitleErrorTimer = null;

    function escapeHtml(v) {
        return String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function notifyFeed(message, type) {
        if (typeof global.showFeedToast === 'function') {
            global.showFeedToast(message, type || 'success');
        }
    }

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta && meta.content) return meta.content;
        var match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
        return match ? decodeURIComponent(match[1]) : '';
    }

    function normalizePostBody(text) {
        return String(text || '').replace(/\r\n|\r/g, '\n');
    }

    function estimateComposerCharsPerLine(el) {
        if (!el || !el.clientWidth) return 36;
        var style = window.getComputedStyle(el);
        var fontSize = parseFloat(style.fontSize) || 14;
        var pad = (parseFloat(style.paddingLeft) || 0) + (parseFloat(style.paddingRight) || 0);
        var width = Math.max(0, el.clientWidth - pad);
        return Math.max(16, Math.floor(width / Math.max(fontSize * 0.55, 7)));
    }

    function measurePostBodyLength(text, charsPerLine) {
        var cpl = Math.max(8, Number(charsPerLine) || 36);
        var lines = normalizePostBody(text).split('\n');
        var total = 0;
        for (var i = 0; i < lines.length; i++) {
            var len = Array.from(lines[i]).length;
            if (i < lines.length - 1) total += Math.max(cpl, len);
            else total += len;
        }
        return total;
    }

    function countedBodyLength(text, el) {
        var textarea = el || document.getElementById('compose-content');
        return measurePostBodyLength(text, estimateComposerCharsPerLine(textarea));
    }

    function validatePostBody(rawBody, el, options) {
        options = options || {};
        var body = normalizePostBody(rawBody);
        var allowEmpty = Boolean(options.allowEmpty);
        if (body === '' || !/\S/.test(body)) {
            if (allowEmpty) return null;
            return 'Please write something. Posts cannot be empty or only spaces.';
        }
        if (body.trim().length < MIN_BODY_CHARS) {
            return 'Please write at least 3 characters before posting.';
        }
        if (countedBodyLength(body, el) > MAX_BODY_CHARS) {
            return 'The post content cannot exceed 25,000 characters (new lines count as a full line).';
        }
        var prohibited = global.ProhibitedWords && global.ProhibitedWords.assertClean
            ? global.ProhibitedWords.assertClean(body)
            : null;
        if (prohibited) return prohibited;
        return null;
    }

    function updateCharCount() {
        var el = document.getElementById('compose-content');
        var totalCounter = document.getElementById('compose-char-count');
        if (!el || !totalCounter) return;
        var total = countedBodyLength(el.value, el);
        totalCounter.textContent = total + ' / ' + MAX_BODY_CHARS;
        totalCounter.classList.toggle('over-limit', total > MAX_BODY_CHARS);
    }

    function wouldExceedTotal(textarea, insertText) {
        var value = String(textarea.value || '');
        var start = textarea.selectionStart != null ? textarea.selectionStart : value.length;
        var end = textarea.selectionEnd != null ? textarea.selectionEnd : start;
        var nextValue = value.slice(0, start) + insertText + value.slice(end);
        if (countedBodyLength(nextValue, textarea) > MAX_BODY_CHARS) {
            return 'The post content cannot exceed 25,000 characters (new lines count as a full line).';
        }
        return null;
    }

    function bindComposeBodyValidation() {
        var el = document.getElementById('compose-content');
        if (!el || el.dataset.lineLimitBound === '1') return;
        el.dataset.lineLimitBound = '1';

        el.addEventListener('beforeinput', function (event) {
            if (event.isComposing) return;
            var type = event.inputType || '';
            if (type.indexOf('delete') === 0 || type === 'historyUndo' || type === 'historyRedo') return;
            var insertText = '';
            if (typeof event.data === 'string') insertText = event.data;
            else if (type === 'insertLineBreak' || type === 'insertParagraph') insertText = '\n';
            else if (type === 'insertFromPaste') return;
            else return;
            var error = wouldExceedTotal(el, insertText);
            if (error) {
                event.preventDefault();
                notifyFeed(error, 'error');
                updateCharCount();
            }
        });

        el.addEventListener('paste', function (event) {
            var pasted = event.clipboardData ? event.clipboardData.getData('text') : '';
            if (pasted === '') return;
            event.preventDefault();
            var value = String(el.value || '');
            var start = el.selectionStart != null ? el.selectionStart : value.length;
            var end = el.selectionEnd != null ? el.selectionEnd : start;
            var nextValue = value.slice(0, start) + pasted + value.slice(end);
            if (countedBodyLength(nextValue, el) > MAX_BODY_CHARS) {
                notifyFeed('The post content cannot exceed 25,000 characters (new lines count as a full line).', 'error');
                updateCharCount();
                return;
            }
            el.value = nextValue;
            var caret = start + pasted.length;
            el.setSelectionRange(caret, caret);
            updateCharCount();
        });

        window.addEventListener('resize', updateCharCount);
        ['input', 'keyup', 'click', 'select', 'mouseup'].forEach(function (evt) {
            el.addEventListener(evt, updateCharCount);
        });
    }

    function setComposeBodyError(message) {
        var el = document.getElementById('compose-body-error');
        var input = document.getElementById('compose-content');
        if (el) {
            el.hidden = !message;
            el.textContent = message || '';
        }
        if (input) {
            input.classList.toggle('is-invalid', Boolean(message));
            input.setAttribute('aria-invalid', message ? 'true' : 'false');
        }
    }

    function setComposeTitleError(message, options) {
        options = options || {};
        var el = document.getElementById('compose-title-error');
        var input = document.getElementById('compose-title');
        clearTimeout(composeTitleErrorTimer);
        composeTitleErrorTimer = null;
        if (el) el.textContent = message || '';
        if (input) {
            input.classList.toggle('is-invalid', Boolean(message));
            input.setAttribute('aria-invalid', message ? 'true' : 'false');
        }
        var hideMs = Number(options.autoHideMs) || 0;
        if (message && hideMs > 0) {
            composeTitleErrorTimer = setTimeout(function () {
                var still = document.getElementById('compose-title-error');
                if (still && still.textContent === message) setComposeTitleError('');
            }, hideMs);
        }
    }

    function validateUpdateTitle(type) {
        if (type !== 'update') return '';
        var title = String((document.getElementById('compose-title') || {}).value || '').trim();
        if (!title) return 'Please specify what update.';
        if (title.length > UPDATE_TITLE_MAX) {
            return 'Update details are limited to ' + UPDATE_TITLE_MAX + ' characters.';
        }
        return '';
    }

    function syncComposeMediaByType(type) {
        var isPhoto = type === 'photo';
        var videoBtn = document.getElementById('compose-add-video-btn');
        var videoWrap = document.getElementById('compose-video-method-wrap');
        if (videoBtn) {
            videoBtn.hidden = isPhoto;
            videoBtn.disabled = isPhoto;
            videoBtn.style.display = isPhoto ? 'none' : '';
        }
        if (isPhoto) {
            if (videoWrap) videoWrap.hidden = true;
            clearComposeVideos(false);
        }
    }

    function updateComposeTitleUi(event) {
        var type = (document.getElementById('compose-type') || {}).value || 'update';
        syncComposeMediaByType(type);
        var wrap = document.getElementById('compose-update-title-wrap');
        var input = document.getElementById('compose-title');
        var countEl = document.getElementById('compose-title-count');
        var show = type === 'update';
        if (wrap) wrap.hidden = !show;
        if (!show) {
            setComposeTitleError('');
            return;
        }
        if (!input) return;
        var value = String(input.value || '');
        if (value.length > UPDATE_TITLE_MAX) {
            input.value = value.slice(0, UPDATE_TITLE_MAX);
            value = input.value;
            setComposeTitleError('Maximum ' + UPDATE_TITLE_MAX + ' characters reached.', { autoHideMs: 4000 });
        }
        if (countEl) countEl.textContent = value.length + ' / ' + UPDATE_TITLE_MAX;
    }

    function parseGoogleDriveVideoUrl(raw) {
        var url = String(raw || '').trim();
        if (!url) return null;
        var lower = url.toLowerCase();
        if (lower.indexOf('javascript:') === 0 || lower.indexOf('data:') === 0 || lower.indexOf('blob:') === 0) return null;
        var parsed;
        try { parsed = new URL(url); } catch (_) { return null; }
        if (parsed.protocol !== 'http:' && parsed.protocol !== 'https:') return null;
        var host = parsed.hostname.toLowerCase();
        var trusted = host === 'drive.google.com' || host === 'docs.google.com'
            || host.slice(-17) === '.drive.google.com' || host.slice(-16) === '.docs.google.com';
        if (!trusted) return null;
        var fileId = null;
        var pathMatch = parsed.pathname.match(/\/file\/d\/([a-zA-Z0-9_-]+)/);
        if (pathMatch) fileId = pathMatch[1];
        if (!fileId && (parsed.pathname === '/open' || parsed.pathname === '/uc' || parsed.pathname === '/' || parsed.pathname === '')) {
            fileId = parsed.searchParams.get('id');
        }
        if (!fileId || !/^[a-zA-Z0-9_-]{10,128}$/.test(fileId)) return null;
        return {
            media_type: 'google_drive_video',
            media_url: 'https://drive.google.com/file/d/' + fileId + '/view',
            external_provider: 'google_drive',
            external_id: fileId,
            preview_url: 'https://drive.google.com/file/d/' + fileId + '/preview',
            open_url: 'https://drive.google.com/file/d/' + fileId + '/view',
        };
    }

    function driveThumbnailUrl(fileId) {
        var id = String(fileId || '').trim();
        return id ? 'https://drive.google.com/thumbnail?id=' + encodeURIComponent(id) + '&sz=w1000' : '';
    }

    function videoPreviewSrc(video) {
        if (!video) return '';
        if (video.provider === 'cloudinary' && video.video_url) return String(video.video_url);
        var fileId = video.external_id || video.google_drive_file_id || '';
        if (video.preview_url) {
            var base = String(video.preview_url);
            return base.indexOf('autoplay=') >= 0 ? base : (base.indexOf('?') >= 0 ? base + '&autoplay=1' : base + '?autoplay=1');
        }
        if (fileId) return 'https://drive.google.com/file/d/' + fileId + '/preview?autoplay=1';
        return String(video.video_url || '');
    }

    function buildGoogleDriveVideoHtml(video, postId) {
        if (!video || !video.preview_url) return '';
        if (!(video.external_id || video.google_drive_file_id)) return '';
        var previewBase = String(video.preview_url);
        var previewWithAutoplay = previewBase.indexOf('?') >= 0 ? previewBase + '&autoplay=1' : previewBase + '?autoplay=1';
        var preview = escapeHtml(previewWithAutoplay);
        var openUrl = escapeHtml(video.open_url || video.media_url || video.video_url || '');
        var id = escapeHtml(String(postId || video.external_id || video.google_drive_file_id));
        return '<div class="cf-drive-video" data-drive-video data-post-id="' + id + '" data-preview-src="' + preview + '">'
            + '<div class="cf-drive-video-frame cf-drive-video-poster">'
            + '<button type="button" class="cf-drive-play-btn" aria-label="Play video">'
            + '<span class="cf-drive-play-icon" aria-hidden="true">▶</span><span>Play video</span></button></div>'
            + '<div class="cf-drive-video-fallback" hidden><p>Video unavailable. Please check the Google Drive sharing permissions.</p>'
            + (openUrl ? '<a href="' + openUrl + '" target="_blank" rel="noopener noreferrer" class="cf-drive-open-link">Open in Google Drive</a>' : '')
            + '</div>'
            + (openUrl ? '<a href="' + openUrl + '" target="_blank" rel="noopener noreferrer" class="cf-drive-open-link cf-drive-open-link--below">Open in Google Drive</a>' : '')
            + '</div>';
    }

    function buildCloudinaryVideoHtml(video, key) {
        if (!video || !video.video_url) return '';
        var src = escapeHtml(video.video_url);
        var id = escapeHtml(String(key || video.id || ''));
        return '<div class="cf-cloud-video" data-cloud-video data-video-key="' + id + '"><div class="cf-cloud-video-frame">'
            + '<video src="' + src + '" controls playsinline preload="metadata" controlslist="nodownload"></video></div></div>';
    }

    function buildVideoAlbumGrid(videos, postId) {
        var list = Array.isArray(videos) ? videos.filter(Boolean) : [];
        var count = list.length;
        var cacheKey = String(postId || '');
        if (cacheKey) videoAlbumCache.set(cacheKey, list);
        var visible = Math.min(4, count);
        var slots = [];
        for (var i = 0; i < visible; i++) {
            slots.push({ index: i, overlay: (i === 3 && count > 4) ? '+' + (count - 4) : null });
        }
        var gridClass = 'post-video-grid';
        if (count === 1) gridClass += ' video-grid-1';
        else if (count === 2) gridClass += ' video-grid-2';
        else if (count === 3) gridClass += ' video-grid-3';
        else gridClass += ' video-grid-4';

        var tiles = slots.map(function (slot) {
            var video = list[slot.index];
            var fileId = video.external_id || video.google_drive_file_id || '';
            var thumb = video.provider === 'cloudinary' && video.video_url
                ? escapeHtml(video.video_url)
                : escapeHtml(driveThumbnailUrl(fileId) || '');
            var preview = escapeHtml(videoPreviewSrc(video));
            var isCloud = video.provider === 'cloudinary' && video.video_url;
            var overlayHtml = slot.overlay
                ? '<span class="post-media-more">' + escapeHtml(slot.overlay) + '</span>'
                : '<span class="post-video-play" aria-hidden="true"></span>';
            var mediaHtml = isCloud
                ? '<video src="' + escapeHtml(video.video_url) + '" preload="metadata" muted playsinline></video>'
                : (thumb ? '<img src="' + thumb + '" alt="" loading="lazy">' : '<div class="post-video-tile-fallback"></div>');
            return '<button type="button" class="post-video-tile' + (slot.overlay ? ' post-media-more-tile' : '') + '"'
                + ' data-video-album-tile data-post-id="' + escapeHtml(cacheKey) + '" data-index="' + slot.index + '"'
                + ' data-has-more="' + (slot.overlay ? '1' : '0') + '" data-preview-src="' + preview + '"'
                + ' data-is-cloud="' + (isCloud ? '1' : '0') + '" data-cloud-src="' + (isCloud ? escapeHtml(video.video_url) : '') + '"'
                + ' aria-label="' + (slot.overlay ? 'Play more videos (' + count + ' total)' : 'Play video ' + (slot.index + 1)) + '">'
                + '<span class="post-video-tile-inner">' + mediaHtml + overlayHtml + '</span></button>';
        }).join('');

        return '<div class="' + gridClass + '" data-video-album data-post-id="' + escapeHtml(cacheKey) + '" data-video-count="' + count + '">'
            + '<div class="post-video-grid-tiles">' + tiles + '</div></div>';
    }

    function buildPostVideosHtml(post) {
        var videos = Array.isArray(post && post.videos) ? post.videos.filter(Boolean) : [];
        if (videos.length > 1) return buildVideoAlbumGrid(videos, post.id);
        if (videos.length === 1) {
            var video = videos[0];
            if (video.provider === 'google_drive' || video.preview_url) {
                return buildGoogleDriveVideoHtml(video, String(post.id) + '-0');
            }
            return buildCloudinaryVideoHtml(video, String(post.id) + '-0');
        }
        if (post && post.google_drive_video) return buildGoogleDriveVideoHtml(post.google_drive_video, post.id);
        return '';
    }

    function restoreAlbumTilePoster(tile) {
        if (!tile) return;
        var inner = tile.querySelector('.post-video-tile-inner') || tile;
        if (tile.dataset.posterHtml) inner.innerHTML = tile.dataset.posterHtml;
        tile.dataset.playing = '0';
        tile.classList.remove('is-playing');
        delete tile.dataset.activeVideoIndex;
    }

    function restoreDriveVideoPoster(wrap) {
        if (!wrap || !wrap.dataset.posterHtml) return;
        var frame = wrap.querySelector('.cf-drive-video-frame');
        if (frame) {
            frame.classList.add('cf-drive-video-poster');
            frame.innerHTML = wrap.dataset.posterHtml;
            var playBtn = frame.querySelector('.cf-drive-play-btn');
            if (playBtn) playBtn.addEventListener('click', function () { playDriveVideoWrap(wrap); });
        }
        wrap.dataset.playing = '0';
        wrap.classList.remove('is-playing', 'is-fallback');
    }

    function stopAllFeedMediaExcept(keepEl) {
        document.querySelectorAll('[data-video-album-tile][data-playing="1"]').forEach(function (tile) {
            if (keepEl && (tile === keepEl || tile.contains(keepEl))) return;
            restoreAlbumTilePoster(tile);
        });
        document.querySelectorAll('[data-drive-video][data-playing="1"]').forEach(function (wrap) {
            if (keepEl && (wrap === keepEl || wrap.contains(keepEl))) return;
            restoreDriveVideoPoster(wrap);
        });
        document.querySelectorAll('[data-cloud-video] video, .post-video-tile video, .cf-cloud-video-frame video').forEach(function (video) {
            if (keepEl && keepEl.contains(video)) return;
            try { video.pause(); video.currentTime = 0; } catch (_) {}
        });
    }

    function playMediaInAlbumTile(tile, videoIndex, videos) {
        if (!tile || !videos.length) return;
        var index = Math.max(0, Math.min(videos.length - 1, Number(videoIndex) || 0));
        var video = videos[index];
        var inner = tile.querySelector('.post-video-tile-inner') || tile;
        var isCloud = video.provider === 'cloudinary' && video.video_url;
        var src = videoPreviewSrc(video);
        stopAllFeedMediaExcept(tile);
        if (!tile.dataset.posterHtml) tile.dataset.posterHtml = inner.innerHTML;
        tile.dataset.playing = '1';
        tile.dataset.activeVideoIndex = String(index);
        tile.classList.add('is-playing');
        if (isCloud && src) {
            inner.innerHTML = '<video src="' + escapeHtml(src) + '" controls playsinline preload="metadata" controlslist="nodownload" autoplay></video>';
            var el = inner.querySelector('video');
            if (el) el.addEventListener('play', function () { stopAllFeedMediaExcept(tile); });
        } else if (src) {
            inner.innerHTML = '<iframe src="' + escapeHtml(src) + '" title="Google Drive video ' + (index + 1) + '" allow="autoplay; fullscreen" allowfullscreen referrerpolicy="no-referrer-when-downgrade"></iframe>';
        } else {
            inner.innerHTML = tile.dataset.posterHtml || '';
            tile.dataset.playing = '0';
            tile.classList.remove('is-playing');
        }
    }

    function playDriveVideoWrap(wrap) {
        var frame = wrap.querySelector('.cf-drive-video-frame');
        var previewSrc = wrap.dataset.previewSrc;
        if (!frame || !previewSrc) return;
        if (!wrap.dataset.posterHtml) wrap.dataset.posterHtml = frame.innerHTML;
        stopAllFeedMediaExcept(wrap);
        wrap.dataset.playing = '1';
        wrap.classList.add('is-playing');
        frame.classList.remove('cf-drive-video-poster');
        frame.innerHTML = '<iframe src="' + previewSrc + '" title="Google Drive video" allow="autoplay; fullscreen" allowfullscreen referrerpolicy="no-referrer-when-downgrade"></iframe>';
    }

    function closeVideoLightbox() {
        var lb = document.getElementById('videoLightbox');
        var frame = document.getElementById('videoLightboxFrame');
        if (frame) frame.innerHTML = '';
        if (lb) {
            lb.classList.remove('active');
            lb.setAttribute('aria-hidden', 'true');
        }
        if (!document.body.classList.contains('feed-commenting')) document.body.style.overflow = '';
        videoLightboxItems = [];
        videoLightboxIndex = 0;
    }

    function renderVideoLightboxFrame() {
        var frame = document.getElementById('videoLightboxFrame');
        var counter = document.getElementById('videoLightboxCounter');
        var prevBtn = document.getElementById('videoLightboxPrev');
        var nextBtn = document.getElementById('videoLightboxNext');
        if (!frame || !videoLightboxItems.length) return;
        var index = ((videoLightboxIndex % videoLightboxItems.length) + videoLightboxItems.length) % videoLightboxItems.length;
        videoLightboxIndex = index;
        var video = videoLightboxItems[index];
        var isCloud = video && video.provider === 'cloudinary' && video.video_url;
        var src = videoPreviewSrc(video);
        if (isCloud && src) {
            frame.innerHTML = '<video src="' + escapeHtml(src) + '" controls playsinline autoplay preload="metadata" controlslist="nodownload"></video>';
        } else if (src) {
            frame.innerHTML = '<iframe src="' + escapeHtml(src) + '" title="Video ' + (index + 1) + '" allow="autoplay; fullscreen" allowfullscreen referrerpolicy="no-referrer-when-downgrade"></iframe>';
        } else {
            frame.innerHTML = '<p style="color:#fff;text-align:center;padding:24px;">Video unavailable.</p>';
        }
        if (counter) {
            counter.textContent = (index + 1) + ' / ' + videoLightboxItems.length;
            counter.hidden = videoLightboxItems.length < 2;
        }
        var multi = videoLightboxItems.length > 1;
        if (prevBtn) prevBtn.hidden = !multi;
        if (nextBtn) nextBtn.hidden = !multi;
    }

    function openVideoLightbox(videos, startIndex) {
        var list = Array.isArray(videos) ? videos.filter(Boolean) : [];
        if (!list.length) return;
        stopAllFeedMediaExcept(null);
        videoLightboxItems = list;
        videoLightboxIndex = Math.max(0, Math.min(list.length - 1, Number(startIndex) || 0));
        var lb = document.getElementById('videoLightbox');
        if (!lb) return;
        lb.classList.add('active');
        lb.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        renderVideoLightboxFrame();
    }

    function videoLightboxPrev() {
        if (videoLightboxItems.length < 2) return;
        videoLightboxIndex = (videoLightboxIndex - 1 + videoLightboxItems.length) % videoLightboxItems.length;
        renderVideoLightboxFrame();
    }

    function videoLightboxNext() {
        if (videoLightboxItems.length < 2) return;
        videoLightboxIndex = (videoLightboxIndex + 1) % videoLightboxItems.length;
        renderVideoLightboxFrame();
    }

    function bindVideoAlbumGrids(root) {
        root = root || document;
        root.querySelectorAll('[data-video-album]').forEach(function (album) {
            if (album.dataset.bound === '1') return;
            album.dataset.bound = '1';
            var postId = String(album.dataset.postId || '');
            var videos = videoAlbumCache.get(postId) || [];
            album.querySelectorAll('[data-video-album-tile]').forEach(function (tile) {
                tile.addEventListener('click', function (e) {
                    if (e.target.closest('[data-tile-controls]')) return;
                    var startIndex = parseInt(tile.dataset.index, 10) || 0;
                    if (tile.dataset.hasMore === '1') {
                        openVideoLightbox(videos, startIndex);
                        return;
                    }
                    if (tile.dataset.playing === '1') return;
                    playMediaInAlbumTile(tile, startIndex, videos);
                });
            });
        });
    }

    function bindDriveVideoEmbeds(root) {
        root = root || document;
        root.querySelectorAll('[data-drive-video]').forEach(function (wrap) {
            if (wrap.dataset.bound === '1') return;
            wrap.dataset.bound = '1';
            var playBtn = wrap.querySelector('.cf-drive-play-btn');
            if (playBtn) playBtn.addEventListener('click', function () { playDriveVideoWrap(wrap); });
        });
        root.querySelectorAll('[data-cloud-video] video').forEach(function (video) {
            if (video.dataset.exclusiveBound === '1') return;
            video.dataset.exclusiveBound = '1';
            video.addEventListener('play', function () {
                stopAllFeedMediaExcept(video.closest('[data-cloud-video]') || video);
            });
        });
    }

    function buildShareButtonHtml(p) {
        var canShare = p.can_share !== false && !!p.share_url;
        return '<button type="button" class="action-btn share-btn" onclick="openShareModal(' + p.id + ')" aria-label="Share post"'
            + (canShare ? '' : ' disabled title="Sharing unavailable for this post"') + '>'
            + '<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M15 8a3 3 0 10-2.977-2.63l-4.94 2.47a3 3 0 100 4.319l4.94 2.47a3 3 0 10.895-1.789l-4.94-2.47a3.027 3.027 0 000-.74l4.94-2.47C13.456 7.68 14.19 8 15 8z"/></svg>'
            + '<span>Share</span></button>';
    }

    function openShareModal(postId) {
        var id = Number(postId);
        var cached = (global.postCache && global.postCache.get) ? (global.postCache.get(id) || {}) : {};
        var shareUrl = String(cached.share_url || '').trim();
        if (!shareUrl || cached.can_share === false) {
            notifyFeed('This post cannot be shared.', 'error');
            return;
        }
        var modal = document.getElementById('sharePostModal');
        var input = document.getElementById('sharePostLinkInput');
        if (!modal || !input) return;
        input.value = shareUrl;
        modal.classList.add('active');
        modal.setAttribute('aria-hidden', 'false');
        input.focus();
        input.select();
    }

    function closeShareModal() {
        var modal = document.getElementById('sharePostModal');
        if (!modal) return;
        modal.classList.remove('active');
        modal.setAttribute('aria-hidden', 'true');
    }

    function copySharePostLink() {
        var input = document.getElementById('sharePostLinkInput');
        var url = String((input && input.value) || '').trim();
        if (!url) {
            notifyFeed('Share link is unavailable.', 'error');
            return Promise.resolve();
        }
        var copied = false;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(url).then(function () {
                notifyFeed('Post link copied.');
            }).catch(function () {
                notifyFeed('Could not copy automatically. The link is selected — press Ctrl+C (or Cmd+C).', 'error');
            });
        }
        if (input) {
            input.focus();
            input.select();
            try { copied = document.execCommand('copy'); } catch (_) { copied = false; }
        }
        notifyFeed(copied ? 'Post link copied.' : 'Could not copy automatically. The link is selected — press Ctrl+C (or Cmd+C).', copied ? 'success' : 'error');
        return Promise.resolve();
    }

    function setComposeVideoError(message) {
        var el = document.getElementById('compose-video-error');
        if (!el) return;
        if (message) { el.textContent = message; el.hidden = false; }
        else { el.textContent = ''; el.hidden = true; }
    }

    function setComposeVideoUploadError(message) {
        var el = document.getElementById('compose-video-upload-error');
        if (!el) return;
        if (message) { el.textContent = message; el.hidden = false; }
        else { el.textContent = ''; el.hidden = true; }
    }

    function clearComposeDriveVideo() {
        var input = document.getElementById('compose-video-input');
        if (input) input.value = '';
        setComposeVideoError('');
        updateComposeDriveRemoveVisibility();
    }

    function updateComposeDriveRemoveVisibility() {
        var btn = document.getElementById('compose-remove-video-btn');
        var input = document.getElementById('compose-video-input');
        if (!btn) return;
        var raw = String((input && input.value) || '').trim();
        btn.hidden = !(raw && parseGoogleDriveVideoUrl(raw));
    }

    function clearComposeVideos(hideWrap) {
        pendingVideoUploads.forEach(function (item) {
            try { if (item.xhr) item.xhr.abort(); } catch (_) {}
            if (item.previewUrl && String(item.previewUrl).indexOf('blob:') === 0) {
                try { URL.revokeObjectURL(item.previewUrl); } catch (_) {}
            }
        });
        pendingVideoUploads = [];
        existingVideos = [];
        removedVideoIds = [];
        var fileInput = document.getElementById('compose-video-input-file');
        if (fileInput) fileInput.value = '';
        renderComposeVideosPreview();
        setComposeVideoUploadError('');
        clearComposeDriveVideo();
        if (hideWrap !== false) {
            var wrap = document.getElementById('compose-video-method-wrap');
            if (wrap) wrap.hidden = true;
        }
    }

    function videoMaxBytes() {
        return Number((global.CommunityFeedConfig && global.CommunityFeedConfig.communityVideoMaxBytes) || 262144000);
    }

    function videoMaxCount() {
        return Number((global.CommunityFeedConfig && global.CommunityFeedConfig.communityVideoMaxCount) || 5);
    }

    function isAllowedVideoFile(file) {
        var type = String((file && file.type) || '').toLowerCase();
        var name = String((file && file.name) || '').toLowerCase();
        if (['video/mp4', 'video/webm', 'video/quicktime', 'video/x-m4v'].indexOf(type) >= 0) return true;
        return /\.(mp4|webm|mov)$/i.test(name);
    }

    function readyUploadedVideos() {
        return pendingVideoUploads.filter(function (item) {
            return item.status === 'done' && item.result && item.result.public_id && item.result.video_url;
        }).map(function (item) {
            return {
                public_id: item.result.public_id,
                video_url: item.result.video_url,
                bytes: item.result.bytes != null ? item.result.bytes : null,
                mime_type: item.result.mime_type || null,
            };
        });
    }

    function hasUploadingVideos() {
        return pendingVideoUploads.some(function (item) {
            return item.status === 'uploading' || item.status === 'queued';
        });
    }

    function appendUploadedVideosToFormData(fd, videos) {
        videos.forEach(function (video, index) {
            fd.append('uploaded_videos[' + index + '][public_id]', video.public_id);
            fd.append('uploaded_videos[' + index + '][video_url]', video.video_url);
            if (video.bytes != null) fd.append('uploaded_videos[' + index + '][bytes]', String(video.bytes));
            if (video.mime_type) fd.append('uploaded_videos[' + index + '][mime_type]', video.mime_type);
        });
    }

    function ensureVideoUploadUnloadGuard() {
        if (videoUploadUnloadBound) return;
        videoUploadUnloadBound = true;
        window.addEventListener('beforeunload', function (event) {
            if (!hasUploadingVideos() && !attachVideosAfterPostQueue.length) return;
            event.preventDefault();
            event.returnValue = '';
        });
    }

    function queueAttachVideosToPost(postId, uploadIds) {
        var id = Number(postId);
        if (!id || !uploadIds || !uploadIds.length) return;
        attachVideosAfterPostQueue.push({ postId: id, uploadIds: uploadIds.slice() });
        maybeFlushAttachVideosQueue();
    }

    function maybeFlushAttachVideosQueue() {
        if (!attachVideosAfterPostQueue.length) return;
        var remaining = [];
        attachVideosAfterPostQueue.forEach(function (job) {
            var items = pendingVideoUploads.filter(function (item) { return job.uploadIds.indexOf(item.id) >= 0; });
            var stillPending = items.some(function (item) { return item.status === 'uploading' || item.status === 'queued'; });
            if (stillPending) { remaining.push(job); return; }
            var ready = items.filter(function (item) { return item.status === 'done' && item.result; }).map(function (item) {
                return {
                    public_id: item.result.public_id,
                    video_url: item.result.video_url,
                    bytes: item.result.bytes != null ? item.result.bytes : null,
                    mime_type: item.result.mime_type || null,
                };
            });
            if (!ready.length) return;
            var fd = new FormData();
            fd.append('_method', 'PUT');
            appendUploadedVideosToFormData(fd, ready);
            fetch('/api/community-feed/' + job.postId, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
                body: fd,
            }).then(function (res) { return res.json().then(function (updated) { return { ok: res.ok, updated: updated }; }); })
                .then(function (result) {
                    if (result.ok && global.replacePostCard) global.replacePostCard(result.updated);
                }).catch(function () {});
        });
        attachVideosAfterPostQueue = remaining;
    }

    function renderComposeVideosPreview() {
        var wrap = document.getElementById('compose-videos-preview');
        var meta = document.getElementById('compose-videos-meta');
        if (!wrap) return;
        wrap.innerHTML = '';
        existingVideos.forEach(function (video) {
            var item = document.createElement('div');
            item.className = 'compose-video-card is-done';
            var src = escapeHtml(video.video_url || video.preview_url || '');
            item.innerHTML = '<div class="compose-video-card__media">' + (src ? '<video src="' + src + '" preload="metadata" muted playsinline></video>' : '<div class="compose-video-card__placeholder">Video</div>')
                + '<button type="button" class="compose-video-card__remove" aria-label="Remove video">&times;</button></div>'
                + '<p class="compose-video-card__name">' + escapeHtml(video.name || video.public_id || 'Saved video') + '</p>';
            item.querySelector('.compose-video-card__remove').addEventListener('click', function (e) {
                e.preventDefault(); e.stopPropagation();
                if (video.id) removedVideoIds.push(Number(video.id));
                existingVideos = existingVideos.filter(function (v) { return v !== video; });
                renderComposeVideosPreview();
            });
            wrap.appendChild(item);
        });
        pendingVideoUploads.forEach(function (upload) {
            var item = document.createElement('div');
            item.className = 'compose-video-card is-' + (upload.status === 'done' ? 'done' : (upload.status === 'error' ? 'error' : 'uploading'));
            var previewSrc = upload.previewUrl || (upload.result && upload.result.video_url) || '';
            var statusText = upload.status === 'done' ? 'Ready' : (upload.status === 'error' ? (upload.error || 'Failed') : 'Uploading ' + Math.max(0, Math.min(99, Number(upload.progress) || 0)) + '%');
            item.innerHTML = '<div class="compose-video-card__media">' + (previewSrc ? '<video src="' + escapeHtml(previewSrc) + '" preload="metadata" muted playsinline></video>' : '<div class="compose-video-card__placeholder">Video</div>')
                + '<div class="compose-video-card__overlay" aria-hidden="true">' + escapeHtml(statusText) + '</div>'
                + '<button type="button" class="compose-video-card__remove" aria-label="Remove video">&times;</button></div>'
                + '<p class="compose-video-card__name">' + escapeHtml(upload.name) + '</p>';
            item.querySelector('.compose-video-card__remove').addEventListener('click', function (e) {
                e.preventDefault(); e.stopPropagation();
                try { if (upload.xhr) upload.xhr.abort(); } catch (_) {}
                if (upload.previewUrl) { try { URL.revokeObjectURL(upload.previewUrl); } catch (_) {} }
                pendingVideoUploads = pendingVideoUploads.filter(function (v) { return v.id !== upload.id; });
                renderComposeVideosPreview();
            });
            wrap.appendChild(item);
        });
        var total = existingVideos.length + pendingVideoUploads.length;
        if (meta) meta.textContent = total ? (total + ' / ' + videoMaxCount() + ' videos') : '';
    }

    function cloudinaryVideoUploadUrl() {
        var cloud = String((global.CommunityFeedConfig && global.CommunityFeedConfig.cloudinaryCloudName) || '').trim();
        return cloud ? 'https://api.cloudinary.com/v1_1/' + encodeURIComponent(cloud) + '/video/upload' : '';
    }

    function startPendingVideoUpload(item) {
        ensureVideoUploadUnloadGuard();
        item.status = 'uploading';
        item.progress = 0;
        if (!item.previewUrl && item.file) {
            try { item.previewUrl = URL.createObjectURL(item.file); } catch (_) { item.previewUrl = ''; }
        }
        renderComposeVideosPreview();
        var cloudUrl = cloudinaryVideoUploadUrl();
        var preset = String((global.CommunityFeedConfig && global.CommunityFeedConfig.communityVideoPreset) || 'SK_Federations_community_video').trim();
        if (!cloudUrl || !preset) {
            item.status = 'error';
            item.error = 'Cloudinary is not configured.';
            setComposeVideoUploadError(item.error);
            notifyFeed(item.error, 'error');
            renderComposeVideosPreview();
            return;
        }
        notifyFeed('Uploading ' + item.name + '…', 'success');
        var fd = new FormData();
        fd.append('file', item.file, item.name);
        fd.append('upload_preset', preset);
        var xhr = new XMLHttpRequest();
        item.xhr = xhr;
        xhr.open('POST', cloudUrl);
        xhr.upload.onprogress = function (event) {
            if (!event.lengthComputable) return;
            item.progress = Math.round((event.loaded / event.total) * 100);
            renderComposeVideosPreview();
        };
        xhr.onload = function () {
            var payload = {};
            try { payload = JSON.parse(xhr.responseText || '{}'); } catch (_) { payload = {}; }
            var publicId = String(payload.public_id || '');
            var videoUrl = String(payload.secure_url || payload.url || '');
            if (xhr.status >= 200 && xhr.status < 300 && publicId && videoUrl) {
                item.status = 'done';
                item.progress = 100;
                item.result = {
                    public_id: publicId,
                    video_url: videoUrl,
                    bytes: payload.bytes != null ? payload.bytes : item.file.size,
                    mime_type: payload.format ? 'video/' + String(payload.format).toLowerCase() : (item.file.type || 'video/mp4'),
                };
                if (item.previewUrl) { try { URL.revokeObjectURL(item.previewUrl); } catch (_) {} }
                item.previewUrl = videoUrl;
                setComposeVideoUploadError('');
                notifyFeed(item.name + ' uploaded. You can post anytime.');
                maybeFlushAttachVideosQueue();
            } else {
                item.status = 'error';
                item.error = (payload.error && payload.error.message) || payload.message || 'The video failed to upload.';
                setComposeVideoUploadError(item.error);
                notifyFeed(item.error, 'error');
            }
            renderComposeVideosPreview();
        };
        xhr.onerror = function () {
            item.status = 'error';
            item.error = 'The video failed to upload.';
            setComposeVideoUploadError(item.error);
            notifyFeed(item.error, 'error');
            renderComposeVideosPreview();
        };
        xhr.onabort = function () {
            item.status = 'error';
            item.error = 'Upload cancelled.';
            renderComposeVideosPreview();
        };
        xhr.send(fd);
    }

    function handleComposeVideoFiles(fileList) {
        var incoming = Array.from(fileList || []);
        if (!incoming.length) return;
        setComposeVideoUploadError('');
        var maxBytes = videoMaxBytes();
        var maxCount = videoMaxCount();
        var room = Math.max(0, maxCount - existingVideos.length - pendingVideoUploads.length);
        var accepted = [];
        incoming.forEach(function (file) {
            if (accepted.length >= room) return;
            if (!isAllowedVideoFile(file)) {
                setComposeVideoUploadError('Allowed formats: MP4, WebM, MOV.');
                return;
            }
            if (file.size > maxBytes) {
                setComposeVideoUploadError('Each video must be 250 MB or smaller.');
                return;
            }
            accepted.push(file);
        });
        accepted.forEach(function (file) {
            var previewUrl = '';
            try { previewUrl = URL.createObjectURL(file); } catch (_) { previewUrl = ''; }
            var item = {
                id: 'vu_' + Date.now() + '_' + (++videoUploadSeq),
                file: file,
                name: file.name,
                status: 'queued',
                progress: 0,
                result: null,
                xhr: null,
                error: null,
                previewUrl: previewUrl,
            };
            pendingVideoUploads.push(item);
            startPendingVideoUpload(item);
        });
        renderComposeVideosPreview();
    }

    function getSelectedVideoMethod() {
        var checked = document.querySelector('input[name="compose-video-method"]:checked');
        return (checked && checked.value) || 'upload';
    }

    function syncVideoMethodPanels() {
        var method = getSelectedVideoMethod();
        var uploadPanel = document.getElementById('compose-video-upload-panel');
        var drivePanel = document.getElementById('compose-video-drive-panel');
        if (uploadPanel) uploadPanel.hidden = method !== 'upload';
        if (drivePanel) drivePanel.hidden = method !== 'drive';
        if (method === 'upload') clearComposeDriveVideo();
        if (method === 'drive') {
            pendingVideoUploads.forEach(function (item) { try { if (item.xhr) item.xhr.abort(); } catch (_) {} });
            pendingVideoUploads = [];
            renderComposeVideosPreview();
        }
    }

    function toggleVideoMethodPanel() {
        var type = (document.getElementById('compose-type') || {}).value || 'update';
        if (type === 'photo') {
            notifyFeed('Photo posts are for images only. Videos are not allowed.', 'error');
            return;
        }
        var wrap = document.getElementById('compose-video-method-wrap');
        if (!wrap) return;
        var opening = wrap.hidden;
        wrap.hidden = !opening;
        if (opening) {
            var uploadRadio = document.querySelector('input[name="compose-video-method"][value="upload"]');
            if (uploadRadio) uploadRadio.checked = true;
            syncVideoMethodPanels();
            var fileInput = document.getElementById('compose-video-input-file');
            if (fileInput) fileInput.focus();
        }
    }

    function resolveComposeLinkPayload() {
        var methodWrap = document.getElementById('compose-video-method-wrap');
        var methodWrapOpen = methodWrap && !methodWrap.hidden;
        var method = getSelectedVideoMethod();
        var videoRaw = String(((document.getElementById('compose-video-input') || {}).value) || '').trim();
        if (methodWrapOpen && method === 'drive') {
            if (!videoRaw) return { link: null, linkKind: null, error: null };
            var parsed = parseGoogleDriveVideoUrl(videoRaw);
            if (!parsed) return { link: null, linkKind: 'google_drive_video', error: 'Please enter a valid Google Drive video link.' };
            return { link: parsed.media_url, linkKind: 'google_drive_video', error: null };
        }
        return { link: null, linkKind: null, error: null };
    }

    function fillEditPostVideos(post) {
        pendingVideoUploads.forEach(function (item) { try { if (item.xhr) item.xhr.abort(); } catch (_) {} });
        pendingVideoUploads = [];
        removedVideoIds = [];
        existingVideos = Array.isArray(post.videos) ? post.videos.slice() : [];
        clearComposeDriveVideo();
        var methodWrap = document.getElementById('compose-video-method-wrap');
        if (methodWrap) methodWrap.hidden = true;
        var driveVideo = (post.videos || []).find(function (v) { return v.provider === 'google_drive'; })
            || post.google_drive_video || parseGoogleDriveVideoUrl(post.link_url);
        var cloudVideos = (post.videos || []).filter(function (v) { return v.provider === 'cloudinary'; });
        if (driveVideo) {
            existingVideos = [];
            var driveRadio = document.querySelector('input[name="compose-video-method"][value="drive"]');
            if (driveRadio) driveRadio.checked = true;
            var driveInput = document.getElementById('compose-video-input');
            if (driveInput) driveInput.value = driveVideo.open_url || driveVideo.media_url || driveVideo.video_url || post.link_url || '';
            if (methodWrap) methodWrap.hidden = false;
            syncVideoMethodPanels();
            updateComposeDriveRemoveVisibility();
        } else if (cloudVideos.length) {
            var uploadRadio = document.querySelector('input[name="compose-video-method"][value="upload"]');
            if (uploadRadio) uploadRadio.checked = true;
            existingVideos = cloudVideos.slice();
            if (methodWrap) methodWrap.hidden = false;
            syncVideoMethodPanels();
            renderComposeVideosPreview();
        }
    }

    function resetComposeMediaState() {
        clearComposeVideos(true);
        var titleInput = document.getElementById('compose-title');
        if (titleInput) titleInput.value = '';
        setComposeTitleError('');
        updateComposeTitleUi();
    }

    function bindComposeMediaEvents() {
        document.getElementById('compose-video-input-file') && document.getElementById('compose-video-input-file').addEventListener('change', function (e) {
            handleComposeVideoFiles(e.target.files);
            e.target.value = '';
        });
        document.querySelectorAll('input[name="compose-video-method"]').forEach(function (radio) {
            radio.addEventListener('change', syncVideoMethodPanels);
        });
        var driveInput = document.getElementById('compose-video-input');
        if (driveInput) {
            driveInput.addEventListener('input', function () {
                setComposeVideoError('');
                updateComposeDriveRemoveVisibility();
            });
        }
        var typeSelect = document.getElementById('compose-type');
        if (typeSelect) typeSelect.addEventListener('change', updateComposeTitleUi);
        var titleInput = document.getElementById('compose-title');
        if (titleInput) titleInput.addEventListener('input', updateComposeTitleUi);
        document.getElementById('videoLightboxClose') && document.getElementById('videoLightboxClose').addEventListener('click', closeVideoLightbox);
        document.getElementById('videoLightboxPrev') && document.getElementById('videoLightboxPrev').addEventListener('click', videoLightboxPrev);
        document.getElementById('videoLightboxNext') && document.getElementById('videoLightboxNext').addEventListener('click', videoLightboxNext);
        var vlb = document.getElementById('videoLightbox');
        if (vlb) vlb.addEventListener('click', function (e) { if (e.target && e.target.id === 'videoLightbox') closeVideoLightbox(); });
    }

    function getPendingUploadIds() {
        return pendingVideoUploads.filter(function (item) {
            return item.status === 'uploading' || item.status === 'queued';
        }).map(function (item) { return item.id; });
    }

    function appendRemovedVideoIds(fd) {
        removedVideoIds.forEach(function (videoId) {
            fd.append('removed_video_ids[]', videoId);
        });
    }

    global.SkCfMedia = {
        MAX_BODY_CHARS: MAX_BODY_CHARS,
        buildPostVideosHtml: buildPostVideosHtml,
        buildShareButtonHtml: buildShareButtonHtml,
        bindVideoAlbumGrids: bindVideoAlbumGrids,
        bindDriveVideoEmbeds: bindDriveVideoEmbeds,
        validatePostBody: validatePostBody,
        normalizePostBody: normalizePostBody,
        updateCharCount: updateCharCount,
        bindComposeBodyValidation: bindComposeBodyValidation,
        validateUpdateTitle: validateUpdateTitle,
        updateComposeTitleUi: updateComposeTitleUi,
        resetComposeMediaState: resetComposeMediaState,
        fillEditPostVideos: fillEditPostVideos,
        resolveComposeLinkPayload: resolveComposeLinkPayload,
        readyUploadedVideos: readyUploadedVideos,
        appendUploadedVideosToFormData: appendUploadedVideosToFormData,
        queueAttachVideosToPost: queueAttachVideosToPost,
        hasUploadingVideos: hasUploadingVideos,
        getPendingUploadIds: getPendingUploadIds,
        appendRemovedVideoIds: appendRemovedVideoIds,
        parseGoogleDriveVideoUrl: parseGoogleDriveVideoUrl,
        bindComposeMediaEvents: bindComposeMediaEvents,
        toggleVideoMethodPanel: toggleVideoMethodPanel,
        clearComposeDriveVideo: clearComposeDriveVideo,
        clearComposeVideos: clearComposeVideos,
        setComposeTitleError: setComposeTitleError,
        setComposeBodyError: setComposeBodyError,
        setComposeVideoError: setComposeVideoError,
        stopAllFeedMediaExcept: stopAllFeedMediaExcept,
        driveThumbnailUrl: driveThumbnailUrl,
        videoPreviewSrc: videoPreviewSrc,
        cacheVideoAlbum: function (postId, videos) {
            if (!postId) return;
            videoAlbumCache.set(String(postId), Array.isArray(videos) ? videos.filter(Boolean) : []);
        },
    };

    global.openShareModal = openShareModal;
    global.closeShareModal = closeShareModal;
    global.copySharePostLink = copySharePostLink;
    global.toggleVideoMethodPanel = toggleVideoMethodPanel;
    global.clearComposeDriveVideo = clearComposeDriveVideo;
    global.videoLightboxPrev = videoLightboxPrev;
    global.videoLightboxNext = videoLightboxNext;
    global.closeVideoLightbox = closeVideoLightbox;
})(window);
