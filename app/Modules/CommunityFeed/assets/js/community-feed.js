/* ── SK Community Feed JS ── */
'use strict';

let currentFilter = 'all';
let feedSearch = '';
let editingPostId = null;
let pendingFiles = [];
let existingImages = [];
let removedImageIds = [];
const MAX_IMAGES = 20;
let lightboxImages = [];
let lightboxIndex = 0;
let lightboxZoom = 1;
const LIGHTBOX_ZOOM_MIN = 0.5;
const LIGHTBOX_ZOOM_MAX = 4;
const LIGHTBOX_ZOOM_STEP = 0.25;
const commentSections = new Set();
const expandedComments = new Set();
const postCache = new Map();
const knownPostIds = new Set();
let feedPollTimer = null;

const FED_AVATAR = window.currentAvatar || 'https://ui-avatars.com/api/?name=SK+Federation&background=213F99&color=fff&size=80';

/* ── API STATE ── */
let posts      = [];
let isLoading  = false;

function csrfToken() {
    // Try meta tag first, then XSRF-TOKEN cookie (always fresh)
    var meta = document.querySelector('meta[name="csrf-token"]')?.content;
    if (meta) return meta;
    var match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

function apiFetch(url, options) {
    options = options || {};
    var extraHeaders = options.headers || {};
    var rest = Object.assign({}, options);
    delete rest.headers;
    return fetch(url, Object.assign({
        headers: Object.assign({
            'X-CSRF-TOKEN': csrfToken(),
            'Accept': 'application/json',
            'Content-Type': 'application/json',
        }, extraHeaders),
        credentials: 'same-origin',
    }, rest));
}

/* ── LOAD POSTS FROM API ── */
function loadPosts(reset) {
    if (isLoading) return Promise.resolve();
    isLoading = true;

    var container = document.getElementById('feed-posts');
    if (reset) { posts = []; knownPostIds.clear(); postCache.clear(); container.innerHTML = '<div class="post-card" style="text-align:center;color:#999;padding:32px;">Loading...</div>'; }

    var url = '/api/community-feed?per_page=100&page=1'
        + (currentFilter !== 'all' ? '&filter=' + encodeURIComponent(currentFilter) : '')
        + (feedSearch ? '&search=' + encodeURIComponent(feedSearch) : '');

    return apiFetch(url, { method: 'GET' })
        .then(function(r) {
            return r.json().catch(function () { return {}; }).then(function(data) {
                if (!r.ok) {
                    var message = data.message || data.error || ('Failed to load posts (HTTP ' + r.status + ').');
                    throw new Error(message);
                }
                return data;
            });
        })
        .then(function(data) {
            if (reset) container.innerHTML = '';

            posts = (data.data || []).filter(function(p) { return p && p.id; });

            if (!posts.length) {
                container.innerHTML = '<div class="post-card" style="text-align:center;color:#999;padding:32px;">No posts found.</div>';
            } else {
                posts.forEach(function(p) {
                    knownPostIds.add(Number(p.id));
                    postCache.set(Number(p.id), p);
                    var el = document.createElement('div');
                    el.className = 'post-card';
                    el.dataset.postId = p.id;
                    el.innerHTML = buildPost(p);
                    bindPostImageClicks(el, p);
                    bindReactionControls(el);
                    container.appendChild(el);
                });
            }
        })
        .catch(function(err) {
            if (reset) {
                container.innerHTML = '<div class="post-card" style="text-align:center;color:#999;padding:32px;">'
                    + escapeHtml(err && err.message ? err.message : 'Failed to load posts.')
                    + '</div>';
            }
        })
        .finally(function() {
            isLoading = false;
            setFilterTabsDisabled(false);
            startFeedPolling();
        });
}

function postExistsInDom(id) {
    return Boolean(document.querySelector('.post-card[data-post-id="' + id + '"]'));
}

function pollFeedUpdates() {
    if (document.hidden || isLoading) return;
    if (document.getElementById('composeModal')?.classList.contains('active')) return;
    if (document.getElementById('commentPreviewShell')?.classList.contains('is-open')) return;

    var params = '/api/community-feed?per_page=100&page=1'
        + (currentFilter !== 'all' ? '&filter=' + encodeURIComponent(currentFilter) : '')
        + (feedSearch ? '&search=' + encodeURIComponent(feedSearch) : '');

    apiFetch(params, { method: 'GET' })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (data) {
            if (!data) return;
            var fresh = (data.data || []).filter(function (p) {
                var id = Number(p.id);
                return id && !knownPostIds.has(id) && !postExistsInDom(id);
            });
            if (!fresh.length) return;
            var container = document.getElementById('feed-posts');
            fresh.reverse().forEach(function (p) {
                knownPostIds.add(Number(p.id));
                postCache.set(Number(p.id), p);
                posts.unshift(p);
                var el = document.createElement('div');
                el.className = 'post-card post-card-new';
                el.dataset.postId = p.id;
                el.innerHTML = buildPost(p);
                bindPostImageClicks(el, p);
                bindReactionControls(el);
                if (container.firstChild) container.insertBefore(el, container.firstChild);
                else container.appendChild(el);
            });
            setTimeout(function () {
                document.querySelectorAll('.post-card-new').forEach(function (el) { el.classList.remove('post-card-new'); });
            }, 1200);
        })
        .catch(function () { /* silent poll failure */ });
}

function startFeedPolling() {
    var interval = window.CommunityFeedConfig?.feedPollMs || 30000;
    if (feedPollTimer) clearInterval(feedPollTimer);
    feedPollTimer = setInterval(pollFeedUpdates, interval);
}

/* ── RENDER (alias kept for filter/search callers) ── */
function renderPosts(reset) {
    loadPosts(reset);
}

function escapeHtml(v) {
    return String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

const LIKE_THUMB_SVG = '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M2 10.5a1.5 1.5 0 113 0v6a1.5 1.5 0 01-3 0v-6zM6 10.333v5.43a2 2 0 001.106 1.79l.05.025A4 4 0 008.943 18h5.416a2 2 0 001.962-1.608l1.2-6A2 2 0 0015.56 8H12V4a2 2 0 00-2-2 1 1 0 00-1 1v.667a4 4 0 01-.8 2.4L6.8 7.933a4 4 0 00-.8 2.4z"/></svg>';
const REACTION_EMOJI = { like: '👍', love: '❤️', haha: '😂', wow: '😮', sad: '😢', angry: '😡' };
const REACTION_LABEL = { like: 'Like', love: 'Love', haha: 'Haha', wow: 'Wow', sad: 'Sad', angry: 'Angry' };

function isTouchDevice() {
    return window.matchMedia('(hover: none), (pointer: coarse)').matches;
}

function reactionPickerHtml(activeType) {
    return '<div class="reaction-picker"><div class="reaction-picker-inner">'
        + Object.keys(REACTION_EMOJI).map(function (type) {
            return '<button type="button" class="reaction-option' + (activeType === type ? ' is-active' : '') + '" data-type="' + type + '" title="' + type + '">' + REACTION_EMOJI[type] + '</button>';
        }).join('')
        + '</div></div>';
}

function reactionLabel(type) {
    return type ? (REACTION_LABEL[type] || 'Like') : 'Like';
}

function commentLikeInner(type) {
    var label = reactionLabel(type);
    if (type && REACTION_EMOJI[type]) {
        return '<span class="comment-react-emoji">' + REACTION_EMOJI[type] + '</span><span>' + escapeHtml(label) + '</span>';
    }
    return escapeHtml(label);
}

var FEED_REACTION_SOUND_URL = '/sounds/reactions_ux.mp3';
var feedReactionAudio = null;

function ensureFeedReactionAudio() {
    if (!feedReactionAudio) {
        feedReactionAudio = new Audio(FEED_REACTION_SOUND_URL);
        feedReactionAudio.preload = 'auto';
        feedReactionAudio.volume = 0.75;
        try { feedReactionAudio.load(); } catch (e) {}
    }
    return feedReactionAudio;
}

function playFeedReactionSound() {
    var audio = ensureFeedReactionAudio();
    audio.muted = false;
    audio.volume = 0.75;
    try {
        if (audio.readyState >= 2) {
            try { audio.currentTime = 0; } catch (e) {}
        }
        var playPromise = audio.play();
        if (playPromise && playPromise.catch) {
            playPromise.catch(function () {
                var oneShot = new Audio(FEED_REACTION_SOUND_URL);
                oneShot.volume = 0.75;
                oneShot.play().catch(function () {});
            });
        }
    } catch (e) {
        try {
            var fallback = new Audio(FEED_REACTION_SOUND_URL);
            fallback.volume = 0.75;
            fallback.play().catch(function () {});
        } catch (err) {}
    }
}

window.playFeedReactionSound = playFeedReactionSound;
ensureFeedReactionAudio();

function resolveNextReaction(currentType, requestedType) {
    var requested = requestedType || 'like';
    var current = currentType || '';
    if (current === requested) return { liked: false, type: '' };
    return { liked: true, type: requested };
}

function bumpReactionCounts(counts, fromType, toType) {
    var next = Object.assign({}, counts || {});
    if (fromType && fromType !== toType) {
        next[fromType] = Math.max(0, Number(next[fromType] || 0) - 1);
    }
    if (toType && fromType !== toType) {
        next[toType] = Number(next[toType] || 0) + 1;
    }
    return next;
}

var reactionRequestSeq = new Map();
var reactionAbort = new Map();

function beginReactionRequest(key) {
    var seq = (reactionRequestSeq.get(key) || 0) + 1;
    reactionRequestSeq.set(key, seq);
    var prev = reactionAbort.get(key);
    if (prev) prev.abort();
    var controller = new AbortController();
    reactionAbort.set(key, controller);
    return { seq: seq, signal: controller.signal };
}

function isLatestReactionRequest(key, seq) {
    return reactionRequestSeq.get(key) === seq;
}

function notifyFeed(message, type) {
    type = type || 'success';
    if (typeof window.showFeedToast === 'function') {
        window.showFeedToast(message, type);
        return;
    }
    if (typeof showFeedToast === 'function') {
        showFeedToast(message, type);
    }
}

function isReplyComment(commentId) {
    return Boolean(
        document.querySelector('.comment-item.is-reply[data-comment-id="' + commentId + '"]')
        || document.querySelector('.cp-comment.is-reply[data-comment-id="' + commentId + '"]')
        || document.querySelector('.comment-item--reply[data-comment-id="' + commentId + '"]')
    );
}

function countAllComments(comments) {
    return (comments || []).reduce(function (sum, c) {
        return sum + 1 + countAllComments(c.replies || []);
    }, 0);
}

function postAvatarUrl(p) {
    if (p.author_avatar_url) return p.author_avatar_url;
    if (p.is_federation_wide) return FED_AVATAR;
    return 'https://ui-avatars.com/api/?name=' + encodeURIComponent(p.author_name || 'SK') + '&background=213F99&color=fff&size=80';
}

function commentAvatarUrl(c) {
    if (c.avatar_url) return c.avatar_url;
    if (c.author_avatar_url) return c.author_avatar_url;
    return 'https://ui-avatars.com/api/?name=' + encodeURIComponent(c.author_name || 'Member') + '&background=213F99&color=fff&size=80';
}

function avatarImg(src, className, alt) {
    return '<img src="' + escapeHtml(src) + '" alt="' + escapeHtml(alt || '') + '" class="' + className + '" onerror="this.onerror=null;this.src=\'' + escapeHtml(FED_AVATAR) + '\'">';
}

function buildImageGrid(images, postId) {
    if (!images || !images.length) return '';

    var unique = [];
    images.forEach(function (url) {
        if (url && unique.indexOf(url) === -1) unique.push(url);
    });
    var count = unique.length;
    var gridClass = 'post-media-grid';
    var slots = [];

    if (count === 1) {
        gridClass += ' grid-1';
        slots = [{ index: 0 }];
    } else if (count === 2) {
        gridClass += ' grid-2 fit-contain';
        slots = [{ index: 0 }, { index: 1 }];
    } else if (count === 3) {
        gridClass += ' grid-3 fit-contain';
        slots = [{ index: 0 }, { index: 1 }, { index: 2 }];
    } else if (count === 4) {
        gridClass += ' grid-4 fit-contain';
        slots = [{ index: 0 }, { index: 1 }, { index: 2 }, { index: 3 }];
    } else {
        gridClass += ' grid-4-plus fit-contain';
        slots = [
            { index: 0 },
            { index: 1 },
            { index: 2 },
            { index: 3, overlay: '+' + (count - 3), moreOnly: true },
        ];
    }

    var tiles = slots.map(function (slot) {
        var overlayHtml = slot.overlay
            ? '<span class="post-media-more">' + escapeHtml(slot.overlay) + '</span>'
            : '';
        var tileClass = slot.moreOnly ? 'post-media-tile post-media-more-tile' : 'post-media-tile';
        var imgHtml = slot.moreOnly
            ? '<img src="' + escapeHtml(unique[slot.index]) + '" alt="" loading="lazy" aria-hidden="true">'
            : '<img src="' + escapeHtml(unique[slot.index]) + '" alt="Post image ' + (slot.index + 1) + '" loading="lazy">';
        return '<button type="button" class="' + tileClass + '" data-post-id="' + postId + '" data-index="' + slot.index + '" aria-label="View photo ' + (slot.index + 1) + '">'
            + imgHtml + overlayHtml + '</button>';
    }).join('');

    return '<div class="' + gridClass + '" data-all-images=\'' + JSON.stringify(unique).replace(/'/g, '&#39;') + '\'>' + tiles + '</div>';
}

function bindPostImageClicks(postEl, post) {
    var grid = postEl.querySelector('.post-media-grid');
    if (!grid) return;

    var images = [];
    try {
        images = JSON.parse(grid.getAttribute('data-all-images') || '[]');
    } catch (_) {
        images = [];
    }
    if (!images.length) {
        images = (post.images && post.images.length) ? post.images : (post.image_url ? [post.image_url] : []);
    }

    grid.querySelectorAll('.post-media-tile').forEach(function (tile) {
        tile.addEventListener('click', function () {
            openLightbox(images, parseInt(tile.getAttribute('data-index'), 10) || 0);
        });
    });
}

function openLightbox(images, startIndex) {
    lightboxImages = images || [];
    lightboxIndex = startIndex || 0;
    lightboxZoom = 1;
    var lb = document.getElementById('imageLightbox');
    if (!lb) return;
    lb.classList.add('active');
    document.body.style.overflow = 'hidden';
    renderLightboxImage();
    applyLightboxZoom();
}

function closeLightbox() {
    var lb = document.getElementById('imageLightbox');
    if (lb) lb.classList.remove('active');
    document.body.style.overflow = '';
    lightboxZoom = 1;
    applyLightboxZoom();
}

function applyLightboxZoom() {
    var img = document.getElementById('lightboxImage');
    var label = document.getElementById('lightboxZoomLevel');
    if (img) img.style.transform = 'scale(' + lightboxZoom + ')';
    if (label) label.textContent = Math.round(lightboxZoom * 100) + '%';
}

function lightboxZoomIn() {
    lightboxZoom = Math.min(LIGHTBOX_ZOOM_MAX, +(lightboxZoom + LIGHTBOX_ZOOM_STEP).toFixed(2));
    applyLightboxZoom();
}

function lightboxZoomOut() {
    lightboxZoom = Math.max(LIGHTBOX_ZOOM_MIN, +(lightboxZoom - LIGHTBOX_ZOOM_STEP).toFixed(2));
    applyLightboxZoom();
}

function lightboxZoomReset() {
    lightboxZoom = 1;
    applyLightboxZoom();
}

function renderLightboxImage() {
    var img = document.getElementById('lightboxImage');
    var counter = document.getElementById('lightboxCounter');
    if (!img || !lightboxImages.length) return;
    img.src = lightboxImages[lightboxIndex] || '';
    if (counter) counter.textContent = (lightboxIndex + 1) + ' / ' + lightboxImages.length;
}

function lightboxPrev() {
    if (!lightboxImages.length) return;
    lightboxIndex = (lightboxIndex - 1 + lightboxImages.length) % lightboxImages.length;
    lightboxZoom = 1;
    renderLightboxImage();
    applyLightboxZoom();
}

function lightboxNext() {
    if (!lightboxImages.length) return;
    lightboxIndex = (lightboxIndex + 1) % lightboxImages.length;
    lightboxZoom = 1;
    renderLightboxImage();
    applyLightboxZoom();
}

window.openLightbox = openLightbox;
window.closeLightbox = closeLightbox;
window.lightboxPrev = lightboxPrev;
window.lightboxNext = lightboxNext;
window.lightboxZoomIn = lightboxZoomIn;
window.lightboxZoomOut = lightboxZoomOut;
window.lightboxZoomReset = lightboxZoomReset;

function buildReactionAvatarsHtml(summary) {
    if (!summary || !summary.reactors || !summary.reactors.length) return '';
    return summary.reactors.slice(0, 3).map(function (r) {
        return '<img src="' + escapeHtml(r.avatar_url) + '" alt="" class="post-like-avatar-mini">';
    }).join('');
}

function formatCount(n) {
    var num = Number(n || 0);
    if (num >= 1000000) {
        var millions = num / 1000000;
        return (millions % 1 === 0 ? millions.toFixed(0) : millions.toFixed(1).replace(/\.0$/, '')) + 'M';
    }
    if (num >= 1000) {
        var thousands = num / 1000;
        return (thousands % 1 === 0 ? thousands.toFixed(0) : thousands.toFixed(1).replace(/\.0$/, '')) + 'K';
    }
    return String(num);
}

function topReactionTypes(counts) {
    return Object.keys(REACTION_EMOJI)
        .filter(function (type) { return Number((counts || {})[type] || 0) > 0; })
        .sort(function (a, b) { return Number(counts[b] || 0) - Number(counts[a] || 0); })
        .slice(0, 3);
}

function reactionFacesHtml(counts) {
    return topReactionTypes(counts).map(function (type) {
        return '<span class="reaction-face reaction-face--' + type + '" title="' + escapeHtml(REACTION_LABEL[type] || type) + '">' + (REACTION_EMOJI[type] || '') + '</span>';
    }).join('');
}

function buildStatsBar(p) {
    var likeCount = p.likes || 0;
    var commentCount = countAllComments(p.comments || []);
    if (likeCount <= 0 && commentCount <= 0) return '';

    var likesHtml = '';
    if (likeCount > 0) {
        likesHtml = '<button type="button" class="post-stats-likes" onclick="event.stopPropagation();openReactionViewer(\'post\',' + p.id + ')">'
            + '<span class="reaction-faces">' + reactionFacesHtml(p.reaction_counts || {}) + '</span>'
            + '<span class="reaction-total">' + formatCount(likeCount) + '</span>'
            + '</button>';
    }

    var commentsHtml = '';
    if (commentCount > 0) {
        commentsHtml = '<button type="button" class="post-stats-comments" onclick="event.stopPropagation();openComments(' + p.id + ')">'
            + formatCount(commentCount) + ' comment' + (commentCount === 1 ? '' : 's')
            + '</button>';
    }

    return '<div class="post-stats-bar">' + likesHtml + commentsHtml + '</div>';
}

function buildCommentItem(c, postId, isReply) {
    var replies = c.replies || [];
    var type = c.reaction_type || (c.liked ? 'like' : '');
    var likeLabel = reactionLabel(type);
    var repliesHtml = replies.map(function (r) { return buildCommentItem(r, postId, true); }).join('');
    var viewReplies = replies.length && !isReply
        ? '<button type="button" class="fb-view-replies" onclick="toggleFedReplies(' + c.id + ')">View ' + replies.length + (replies.length === 1 ? ' reply' : ' replies') + '</button>'
        : '';

    return '<div class="comment-item' + (isReply ? ' is-reply' : '') + '" data-comment-id="' + c.id + '">'
        + avatarImg(commentAvatarUrl(c), 'comment-avatar', c.author_name)
        + '<div class="comment-body">'
        + '<div class="comment-bubble">'
        + '<p class="comment-author">' + escapeHtml(c.author_name) + '</p>'
        + '<p class="comment-text">' + escapeHtml(c.body) + '</p>'
        + '</div>'
        + '<div class="comment-meta">'
        + '<div class="reaction-wrap comment-like-wrap" data-target="comment" data-post-id="' + postId + '" data-comment-id="' + c.id + '">'
        + '<button type="button" class="comment-like-btn' + (c.liked ? ' liked' : '') + '" data-type="' + escapeHtml(type) + '">' + escapeHtml(likeLabel) + '</button>'
        + reactionPickerHtml(type)
        + '</div>'
        + (Number(c.likes || 0) > 0
            ? '<button type="button" class="comment-react-badge" onclick="openReactionViewer(\'comment\',' + postId + ',' + c.id + ')">'
                + '<span class="reaction-faces">' + reactionFacesHtml(c.reaction_counts || {}) + '</span>'
                + '<span class="reaction-total">' + formatCount(c.likes) + '</span>'
                + '</button>'
            : '')
        + '<button type="button" class="comment-action-btn" onclick="showFedReply(' + postId + ',' + c.id + ')">Reply</button>'
        + '<span class="comment-time">' + escapeHtml(c.time || '') + '</span>'
        + '</div>'
        + viewReplies
        + '<div class="comment-reply-box" id="fed-reply-' + c.id + '" style="display:none;gap:8px;margin-top:8px;">'
        + '<input type="text" class="comment-input" placeholder="Write a reply..." maxlength="500" onkeydown="if(event.key===\'Enter\'){event.preventDefault();addComment(' + postId + ',this,' + c.id + ');}">'
        + '</div>'
        + (repliesHtml ? '<div class="comment-replies" id="fed-replies-' + c.id + '" hidden>' + repliesHtml + '</div>' : '')
        + '</div></div>';
}

function buildCommentsList(p) {
    var comments = p.comments || [];
    var expanded = expandedComments.has(p.id);
    var html = '';
    var total = countAllComments(comments);

    if (comments.length > 2 && !expanded) {
        html += '<button type="button" class="view-more-comments" onclick="expandAllComments(' + p.id + ')">'
            + 'View all ' + total + ' comments</button>';
    }

    var visible = expanded ? comments : comments.slice(-2);
    html += visible.map(function (c) { return buildCommentItem(c, p.id, false); }).join('');
    return html;
}

function toggleFedReplies(commentId) {
    var el = document.getElementById('fed-replies-' + commentId);
    if (el) el.hidden = !el.hidden;
}

function showFedReply(postId, commentId) {
    var box = document.getElementById('fed-reply-' + commentId);
    if (!box) return;
    box.style.display = box.style.display === 'none' ? 'flex' : 'none';
    var input = box.querySelector('input');
    if (input) input.focus();
}

window.toggleFedReplies = toggleFedReplies;
window.showFedReply = showFedReply;

function buildCommentInput(p) {
    var userAvatar = window.currentAvatar || FED_AVATAR;
    return '<div class="comment-input-wrapper">'
        + avatarImg(userAvatar, 'comment-avatar', 'You')
        + '<input type="text" class="comment-input" placeholder="Write a comment..." maxlength="500" onkeydown="submitComment(event,' + p.id + ',this)">'
        + '<button type="button" class="send-comment-btn" onclick="submitCommentBtn(' + p.id + ',this)">'
        + '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z"/></svg>'
        + '</button></div>';
}

function refreshCommentsSection(p) {
    var section = document.getElementById('comments-' + p.id);
    if (!section) return;
    var list = section.querySelector('.comments-list');
    if (list) {
        list.innerHTML = buildCommentsList(p);
        bindReactionControls(list);
    }
}

function expandAllComments(id) {
    expandedComments.add(id);
    var p = posts.find(function (x) { return x.id === id; });
    if (p) refreshCommentsSection(p);
}

window.expandAllComments = expandAllComments;

function formatLikeCountLabel(postId, count) {
    var total = count || 0;
    if (total > 0) {
        return 'Like (<span class="post-like-count-link" onclick="event.stopPropagation();openReactionViewer(\'post\',' + postId + ')">' + total + '</span>)';
    }
    return 'Like (0)';
}

function updateLikeCountLabel(postId, count) {
    var el = document.getElementById('like-count-' + postId);
    if (el) el.innerHTML = formatLikeCountLabel(postId, count);
}

var reactionViewerState = { target: 'post', postId: null, commentId: null, data: null, filter: 'all' };

function openReactionViewer(target, postId, commentId) {
    var modal = document.getElementById('reactionViewerModal');
    var list = document.getElementById('reactionViewerList');
    var tabs = document.getElementById('reactionViewerTabs');
    if (!modal || !list) return;

    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    if (tabs) tabs.innerHTML = '';
    list.innerHTML = '<p class="reaction-viewer-empty">Loading...</p>';

    var url = target === 'comment'
        ? '/api/community-feed/' + postId + '/comments/' + commentId + '/reactions'
        : '/api/community-feed/' + postId + '/likes';

    apiFetch(url, { method: 'GET' })
        .then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
        .then(function (result) {
            if (!result.ok) throw new Error(result.data.message || 'Unable to load reactions.');
            reactionViewerState = { target: target, postId: postId, commentId: commentId || null, data: result.data, filter: 'all' };
            renderReactionViewer('all');
        })
        .catch(function (err) {
            list.innerHTML = '<p class="reaction-viewer-empty">' + escapeHtml(err.message || 'Unable to load reactions.') + '</p>';
        });
}

function closeReactionViewer() {
    var modal = document.getElementById('reactionViewerModal');
    if (!modal || !modal.classList.contains('open')) return;
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
    if (!document.getElementById('imageLightbox')?.classList.contains('active')) {
        document.body.style.overflow = '';
    }
}

function renderReactionViewer(filter) {
    reactionViewerState.filter = filter || 'all';
    var data = reactionViewerState.data || { reactors: [], reaction_counts: {}, count: 0 };
    var counts = data.reaction_counts || {};
    var tabs = document.getElementById('reactionViewerTabs');
    if (tabs) {
        var items = [['all', 'All ' + formatCount(data.count || 0)]].concat(
            Object.keys(REACTION_EMOJI)
                .filter(function (type) { return Number(counts[type] || 0) > 0; })
                .map(function (type) { return [type, REACTION_EMOJI[type] + ' ' + formatCount(counts[type])]; })
        );
        tabs.innerHTML = items.map(function (item) {
            return '<button type="button" class="reaction-viewer-tab' + (reactionViewerState.filter === item[0] ? ' is-active' : '') + '" data-type="' + item[0] + '">' + item[1] + '</button>';
        }).join('');
        tabs.querySelectorAll('.reaction-viewer-tab').forEach(function (tab) {
            tab.addEventListener('click', function () { renderReactionViewer(tab.dataset.type); });
        });
    }

    var reactors = (data.reactors || []).filter(function (row) {
        return reactionViewerState.filter === 'all' || row.reaction_type === reactionViewerState.filter;
    });
    var list = document.getElementById('reactionViewerList');
    if (!list) return;
    if (!reactors.length) {
        list.innerHTML = '<p class="reaction-viewer-empty">No reactions yet.</p>';
        return;
    }
    list.innerHTML = reactors.map(function (row) {
        return '<div class="reaction-viewer-row">'
            + '<div class="reaction-viewer-avatar-wrap">'
            + '<img src="' + escapeHtml(row.avatar_url) + '" alt="">'
            + '<span class="reaction-viewer-emoji">' + (REACTION_EMOJI[row.reaction_type] || '') + '</span>'
            + '</div>'
            + '<span class="reaction-viewer-name">' + escapeHtml(row.name) + '</span>'
            + '</div>';
    }).join('');
}

window.openReactionViewer = openReactionViewer;
window.closeReactionViewer = closeReactionViewer;
window.openLikesModal = function (postId) { openReactionViewer('post', postId); };
window.closeLikesModal = closeReactionViewer;

function buildPost(p) {
    var liked = p.liked || false;
    var avatar = postAvatarUrl(p);

    var images = [];
    if (p.images && p.images.length) {
        images = p.images.slice();
    } else if (p.image_url) {
        images = [p.image_url];
    }
    var mediaHtml = buildImageGrid(images, p.id);
    if (p.link_url) {
        mediaHtml += '<a href="' + escapeHtml(p.link_url) + '" target="_blank" rel="noopener" class="post-link-preview">'
            + '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.586 4.586a2 2 0 112.828 2.828l-3 3a2 2 0 01-2.828 0 1 1 0 00-1.414 1.414 4 4 0 005.656 0l3-3a4 4 0 00-5.656-5.656l-1.5 1.5a1 1 0 101.414 1.414l1.5-1.5zm-5 5a2 2 0 012.828 0 1 1 0 101.414-1.414 4 4 0 00-5.656 0l-3 3a4 4 0 105.656 5.656l1.5-1.5a1 1 0 10-1.414-1.414l-1.5 1.5a2 2 0 11-2.828-2.828l3-3z" clip-rule="evenodd"/></svg>'
            + escapeHtml(p.link_url) + '</a>';
    }

    var detailsHtml = '';
    if (p.details) {
        detailsHtml = '<div class="post-details">'
            + '<div class="detail-item"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/></svg><span>' + p.details.date + '</span></div>'
            + '<div class="detail-item"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/></svg><span>' + p.details.location + '</span></div>'
            + '</div>';
    }

    var optionsHtml = '';
    if (p.owned) {
        optionsHtml = '<div style="position:relative;">'
            + '<button class="post-options-btn" onclick="togglePostOptions(' + p.id + ', event)" aria-label="Post options">'
            + '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/></svg>'
            + '</button>'
            + '<div class="post-options-menu" id="options-menu-' + p.id + '">'
            + '<button onclick="editPost(' + p.id + ')"><svg viewBox="0 0 20 20" fill="currentColor"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"/></svg>Edit</button>'
            + '<button class="danger" onclick="deletePost(' + p.id + ')"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>Delete</button>'
            + '</div></div>';
    }

    var statsHtml = buildStatsBar(p);
    var reactionType = p.reaction_type || (liked ? 'like' : '');
    var likeIcon = reactionType && REACTION_EMOJI[reactionType] ? REACTION_EMOJI[reactionType] : LIKE_THUMB_SVG;

    return '<div class="post-header">'
        + avatarImg(avatar, 'post-avatar', p.author_name)
        + '<div class="post-info">'
        + '<h3 class="post-author">' + escapeHtml(p.author_name || 'SK Federation') + (p.barangay_name && !p.is_federation_wide ? ' <small style="font-weight:400;color:#888;">· ' + escapeHtml(p.barangay_name) + '</small>' : '') + '</h3>'
        + '<p class="post-meta"><span class="post-type ' + escapeHtml(p.type || 'update') + '">' + escapeHtml(p.type || 'update') + '</span><span class="post-time">' + escapeHtml(p.time || '') + '</span></p>'
        + '</div>' + optionsHtml + '</div>'
        + '<div class="post-content">'
        + (p.title ? '<h2 class="post-title">' + escapeHtml(p.title) + '</h2>' : '')
        + '<p class="post-text">' + escapeHtml(p.body || '') + '</p>'
        + mediaHtml
        + '</div>'
        + statsHtml
        + '<div class="post-actions">'
        + '<div class="reaction-wrap" data-target="post" data-post-id="' + p.id + '">'
        + '<button type="button" class="action-btn reaction-btn' + (liked ? ' liked' : '') + '" data-type="' + escapeHtml(reactionType) + '" id="like-btn-' + p.id + '">'
        + '<span class="reaction-icon">' + likeIcon + '</span>'
        + '<span class="reaction-label" id="like-count-' + p.id + '">' + escapeHtml(reactionLabel(reactionType)) + '</span></button>'
        + reactionPickerHtml(reactionType)
        + '</div>'
        + '<button type="button" class="action-btn comment-btn" onclick="openComments(' + p.id + ')">'
        + '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z" clip-rule="evenodd"/></svg>'
        + '<span id="comment-count-' + p.id + '">Comment</span></button>'
        + '</div>'
        + buildCommentPreviewHtml(p);
}

/* ── INTERACTIONS ── */
function closeAllReactionPickers(exceptWrap) {
    document.querySelectorAll('.reaction-wrap.is-open').forEach(function (wrap) {
        if (wrap !== exceptWrap) wrap.classList.remove('is-open');
    });
}

function bindReactionControls(root) {
    if (!root) return;
    root.querySelectorAll('.reaction-wrap').forEach(bindReactionWrap);
}

function bindReactionWrap(wrap) {
    if (!wrap || wrap.dataset.bound === '1') return;
    wrap.dataset.bound = '1';
    var btn = wrap.querySelector('.reaction-btn, .comment-like-btn');
    var picker = wrap.querySelector('.reaction-picker');
    var hideTimer = null;
    var pressTimer = null;
    var didLongPress = false;
    var postId = Number(wrap.dataset.postId);
    var commentId = Number(wrap.dataset.commentId || 0);
    var isComment = wrap.dataset.target === 'comment';

    function apply(type) {
        if (isComment) setCommentReaction(postId, commentId, type);
        else setReaction(postId, type);
    }

    function openPicker() {
        closeAllReactionPickers(wrap);
        document.querySelectorAll('.post-options-menu.open, .comment-options-menu.open').forEach(function (menu) {
            menu.classList.remove('open');
        });
        clearTimeout(hideTimer);
        wrap.classList.add('is-open');
    }

    wrap.addEventListener('mouseenter', function () {
        if (isTouchDevice()) return;
        clearTimeout(hideTimer);
        openPicker();
    });
    wrap.addEventListener('mouseleave', function () {
        if (isTouchDevice()) return;
        hideTimer = setTimeout(function () { wrap.classList.remove('is-open'); }, 80);
    });
    picker && picker.addEventListener('mouseenter', function () {
        if (isTouchDevice()) return;
        clearTimeout(hideTimer);
        wrap.classList.add('is-open');
    });
    picker && picker.addEventListener('mouseleave', function () {
        if (isTouchDevice()) return;
        hideTimer = setTimeout(function () { wrap.classList.remove('is-open'); }, 80);
    });
    btn && btn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        if (didLongPress) {
            didLongPress = false;
            return;
        }
        if (isTouchDevice()) {
            if (!wrap.classList.contains('is-open')) {
                openPicker();
                return;
            }
            wrap.classList.remove('is-open');
            apply(btn.dataset.type || 'like');
            return;
        }
        wrap.classList.remove('is-open');
        apply(btn.dataset.type || 'like');
    });
    btn && btn.addEventListener('touchstart', function () {
        didLongPress = false;
        clearTimeout(pressTimer);
        pressTimer = setTimeout(function () {
            didLongPress = true;
            openPicker();
        }, 180);
    }, { passive: true });
    function cancelPress() { clearTimeout(pressTimer); }
    btn && btn.addEventListener('touchend', cancelPress);
    btn && btn.addEventListener('touchcancel', cancelPress);
    btn && btn.addEventListener('touchmove', cancelPress);
    btn && btn.addEventListener('contextmenu', function (e) { e.preventDefault(); });
    picker && picker.querySelectorAll('.reaction-option').forEach(function (opt) {
        opt.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            wrap.classList.remove('is-open');
            apply(opt.dataset.type);
        });
    });
}

function paintPostReaction(id, liked, nextType, count) {
    var btn = document.getElementById('like-btn-' + id);
    if (!btn) return;
    btn.classList.toggle('liked', Boolean(liked));
    btn.dataset.type = nextType || '';
    var icon = btn.querySelector('.reaction-icon');
    if (icon) icon.innerHTML = (nextType && REACTION_EMOJI[nextType]) ? REACTION_EMOJI[nextType] : LIKE_THUMB_SVG;
    var label = document.getElementById('like-count-' + id);
    if (label) label.textContent = reactionLabel(nextType);
    var wrap = btn.closest('.reaction-wrap');
    wrap && wrap.querySelectorAll('.reaction-option').forEach(function (opt) {
        opt.classList.toggle('is-active', Boolean(nextType) && opt.dataset.type === nextType);
    });
}

function refreshPostStats(id) {
    var p = posts.find(function (x) { return Number(x.id) === Number(id); }) || postCache.get(Number(id));
    var card = document.querySelector('[data-post-id="' + id + '"]');
    if (!p || !card) return;
    var statsBar = card.querySelector('.post-stats-bar');
    var newStats = buildStatsBar(p);
    if (statsBar) {
        if (newStats) statsBar.outerHTML = newStats;
        else statsBar.remove();
    } else if (newStats) {
        var actions = card.querySelector('.post-actions');
        if (actions) actions.insertAdjacentHTML('beforebegin', newStats);
    }
}

function setReaction(id, type) {
    var btn = document.getElementById('like-btn-' + id);
    var current = btn ? (btn.dataset.type || '') : '';
    var next = resolveNextReaction(current, type);
    var p = posts.find(function (x) { return Number(x.id) === Number(id); }) || postCache.get(Number(id));
    var count = Number((p && p.likes) || 0);
    if (current && !next.liked) count = Math.max(0, count - 1);
    else if (!current && next.liked) count += 1;
    if (next.liked) playFeedReactionSound();
    paintPostReaction(id, next.liked, next.type, count);
    if (p) {
        p.likes = count;
        p.liked = next.liked;
        p.reaction_type = next.type || '';
        p.reaction_counts = bumpReactionCounts(p.reaction_counts || {}, current, next.type);
    }
    var cached = postCache.get(Number(id));
    if (cached && cached !== p) {
        cached.likes = count;
        cached.liked = next.liked;
        cached.reaction_type = next.type || '';
        cached.reaction_counts = bumpReactionCounts(cached.reaction_counts || {}, current, next.type);
    }
    refreshPostStats(id);

    var key = 'post:' + id;
    var req = beginReactionRequest(key);
    apiFetch('/api/community-feed/' + id + '/react', {
        method: 'POST',
        body: JSON.stringify({ reaction_type: type || 'like', client_seq: req.seq }),
        signal: req.signal,
    })
        .then(function (r) {
            return r.json().then(function (data) { return { ok: r.ok, data: data }; });
        })
        .then(function (result) {
            if (!isLatestReactionRequest(key, req.seq)) return;
            if (!result.ok || typeof result.data.liked !== 'boolean') {
                if (!result.ok) notifyFeed('Unable to update your reaction. Please try again.', 'error');
                return;
            }
            var data = result.data;
            var serverType = data.liked ? (data.reaction_type || 'like') : '';
            paintPostReaction(id, data.liked, serverType, data.count);
            var post = posts.find(function (x) { return Number(x.id) === Number(id); }) || postCache.get(Number(id));
            if (post) {
                post.likes = data.count;
                post.liked = data.liked;
                post.reaction_type = serverType;
                post.reaction_counts = data.reaction_counts || post.reaction_counts;
                if (data.reactions_summary) post.reactions_summary = data.reactions_summary;
            }
            refreshPostStats(id);
        })
        .catch(function (err) {
            if (err && err.name === 'AbortError') return;
            if (!isLatestReactionRequest(key, req.seq)) return;
            notifyFeed('Unable to update your reaction. Please try again.', 'error');
        });
}

function setCommentReaction(postId, commentId, type) {
    var wrap = document.querySelector('.reaction-wrap[data-comment-id="' + commentId + '"]');
    var btn = wrap && wrap.querySelector('.comment-like-btn');
    var current = btn ? (btn.dataset.type || '') : '';
    var next = resolveNextReaction(current, type);
    if (next.liked) playFeedReactionSound();
    if (btn) {
        btn.classList.toggle('liked', Boolean(next.liked));
        btn.dataset.type = next.type || '';
        btn.innerHTML = commentLikeInner(next.type);
        wrap.querySelectorAll('.reaction-option').forEach(function (opt) {
            opt.classList.toggle('is-active', Boolean(next.type) && opt.dataset.type === next.type);
        });
    }

    var key = 'comment:' + commentId;
    var req = beginReactionRequest(key);
    apiFetch('/api/community-feed/' + postId + '/comments/' + commentId + '/reactions', {
        method: 'POST',
        body: JSON.stringify({ reaction_type: type || 'like', client_seq: req.seq }),
        signal: req.signal,
    })
        .then(function (r) {
            return r.json().then(function (data) { return { ok: r.ok, data: data }; });
        })
        .then(function (result) {
            if (!isLatestReactionRequest(key, req.seq)) return;
            if (!result.ok || typeof result.data.liked !== 'boolean') {
                if (!result.ok) notifyFeed('Unable to update your reaction. Please try again.', 'error');
                return;
            }
            var data = result.data;
            var wrapEl = document.querySelector('.reaction-wrap[data-comment-id="' + commentId + '"]');
            var btnEl = wrapEl && wrapEl.querySelector('.comment-like-btn');
            var nextType = data.liked ? (data.reaction_type || 'like') : '';
            if (btnEl) {
                btnEl.classList.toggle('liked', Boolean(data.liked));
                btnEl.dataset.type = nextType;
                btnEl.innerHTML = commentLikeInner(nextType);
                wrapEl.querySelectorAll('.reaction-option').forEach(function (opt) {
                    opt.classList.toggle('is-active', Boolean(nextType) && opt.dataset.type === nextType);
                });
            }

            var count = Number(data.count || 0);
            var badgeHtml = count > 0
                ? '<button type="button" class="comment-react-badge" onclick="openReactionViewer(\'comment\',' + postId + ',' + commentId + ')">'
                    + '<span class="reaction-faces">' + reactionFacesHtml(data.reaction_counts || {}) + '</span>'
                    + '<span class="reaction-total">' + formatCount(count) + '</span>'
                    + '</button>'
                : '';
            var item = wrapEl && wrapEl.closest('.comment-item');
            var badge = item && item.querySelector('.comment-react-badge');
            if (badge) {
                if (badgeHtml) badge.outerHTML = badgeHtml;
                else badge.remove();
            } else if (badgeHtml && wrapEl) {
                wrapEl.insertAdjacentHTML('afterend', badgeHtml);
            }
        })
        .catch(function (err) {
            if (err && err.name === 'AbortError') return;
            if (!isLatestReactionRequest(key, req.seq)) return;
            notifyFeed('Unable to update your reaction. Please try again.', 'error');
        });
}

function toggleLike(id) {
    setReaction(id, 'like');
}

function commentsPageUrl(id) {
    var template = window.CommunityFeedConfig?.commentsPageUrl;
    if (template && String(template).indexOf('__ID__') !== -1) {
        return String(template).replace('__ID__', String(id));
    }
    return '/community-feed/comments/' + id;
}

function openComments(id) {
    var postId = Number(id);
    var cached = postCache.get(postId) || posts.find(function (x) { return Number(x.id) === postId; });

    if (typeof window.openCommentPreview === 'function') {
        if (cached) {
            window.openCommentPreview(cached);
            return;
        }
        apiFetch('/api/community-feed/' + postId, { method: 'GET' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (data && typeof window.openCommentPreview === 'function') {
                    postCache.set(postId, data);
                    window.openCommentPreview(data);
                    return;
                }
                notifyFeed('Unable to open comments for this post.', 'error');
            })
            .catch(function () {
                notifyFeed('Unable to open comments for this post.', 'error');
            });
        return;
    }

    if (cached) {
        postCache.set(postId, cached);
    }
}

function toggleComments(id) {
    openComments(id);
}

function buildCommentPreviewHtml(p) {
    return '<div class="comment-preview" id="comment-preview-' + p.id + '" hidden></div>';
}

function submitComment(e, id, input) {
    if (e.key === 'Enter') addComment(id, input);
}

function submitCommentBtn(id, btn) {
    var input = btn.previousElementSibling;
    if (input) addComment(id, input);
}

function findCachedComment(comments, commentId) {
    var id = String(commentId);
    for (var i = 0; i < (comments || []).length; i++) {
        if (String(comments[i].id) === id) return comments[i];
        var nested = findCachedComment(comments[i].replies || [], commentId);
        if (nested) return nested;
    }
    return null;
}

function removeCachedComment(comments, commentId) {
    var id = String(commentId);
    var list = comments || [];
    for (var i = 0; i < list.length; i++) {
        if (String(list[i].id) === id) {
            list.splice(i, 1);
            return true;
        }
    }
    return list.some(function (c) { return removeCachedComment(c.replies || [], commentId); });
}

function insertCachedComment(p, comment, parentId) {
    if (!p.comments) p.comments = [];
    if (parentId) {
        var parent = findCachedComment(p.comments, parentId);
        if (parent) {
            parent.replies = parent.replies || [];
            parent.replies.push(comment);
            parent.reply_count = parent.replies.length;
            return;
        }
    }
    p.comments.push(comment);
}

function refreshFeedCommentUi(id, p) {
    commentSections.add(id);
    expandedComments.add(id);
    var section = document.getElementById('comments-' + id);
    if (section) {
        section.style.display = 'block';
        refreshCommentsSection(p);
    }
    var countEl = document.getElementById('comment-count-' + id);
    if (countEl) countEl.textContent = 'Comment';
    var preview = document.getElementById('comment-preview-' + id);
    if (preview) preview.outerHTML = buildCommentPreviewHtml(p);
    refreshPostStats(id);
}

function addComment(id, input, parentId) {
    if (!input || input.dataset.sending === '1') return;
    var text = input.value.trim();
    if (!text) return;
    if (text.length > 500) {
        notifyFeed('Comments and replies are limited to 500 characters.', 'error');
        return;
    }
    input.dataset.sending = '1';
    input.value = '';
    var payload = { body: text };
    if (parentId) payload.parent_id = parentId;

    var p = posts.find(function (x) { return Number(x.id) === Number(id); });
    if (!p) p = postCache.get(Number(id));
    var tempId = 'tmp-' + Date.now();
    var tempComment = {
        id: tempId,
        body: text,
        author_name: (window.CommentPreviewConfig && window.CommentPreviewConfig.userDisplayName) || 'You',
        author_avatar_url: window.currentAvatar || FED_AVATAR,
        time: 'Just now',
        liked: false,
        likes: 0,
        owned: true,
        replies: [],
        parent_id: parentId || null,
    };
    if (p) {
        insertCachedComment(p, tempComment, parentId);
        postCache.set(Number(id), p);
        refreshFeedCommentUi(id, p);
    }

    apiFetch('/api/community-feed/' + id + '/comment', { method: 'POST', body: JSON.stringify(payload) })
        .then(function(r) {
            if (!r.ok) {
                return r.json().then(function(data) {
                    throw new Error(data.message || 'Unable to post comment.');
                });
            }
            return r.json();
        })
        .then(function(c) {
            var post = posts.find(function (x) { return Number(x.id) === Number(id); }) || postCache.get(Number(id));
            if (post) {
                removeCachedComment(post.comments || [], tempId);
                insertCachedComment(post, c, c.parent_id || parentId);
                postCache.set(Number(id), post);
                refreshFeedCommentUi(id, post);
            }
            notifyFeed(parentId ? 'Reply added successfully.' : 'Comment added successfully.');
        })
        .catch(function(err) {
            var post = posts.find(function (x) { return Number(x.id) === Number(id); }) || postCache.get(Number(id));
            if (post) {
                removeCachedComment(post.comments || [], tempId);
                refreshFeedCommentUi(id, post);
            }
            notifyFeed(parentId ? 'Unable to add your reply. Please try again.' : 'Unable to add your comment. Please try again.', 'error');
            if (input) input.value = text;
        })
        .finally(function () {
            if (input) input.dataset.sending = '0';
        });
}

let pendingCommentAction = null;

function commentBodyText(commentId) {
    return document.getElementById('comment-text-' + commentId)?.textContent
        || document.getElementById('cp-text-' + commentId)?.textContent
        || '';
}

function editComment(postId, commentId) {
    pendingCommentAction = { postId: Number(postId), commentId: Number(commentId), isReply: isReplyComment(commentId) };
    document.querySelectorAll('.comment-options-menu.open, .cp-options-menu.open, .post-options-menu.open').forEach(function (m) {
        m.classList.remove('open');
    });
    var modal = document.getElementById('editCommentModal');
    var field = document.getElementById('editCommentBody');
    if (!modal || !field) return;
    field.value = commentBodyText(commentId);
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
    setTimeout(function () { field.focus(); }, 50);
}

function closeEditCommentModal() {
    document.getElementById('editCommentModal')?.classList.remove('active');
    pendingCommentAction = null;
    if (!document.getElementById('commentPreviewShell')?.classList.contains('is-open')) {
        document.body.style.overflow = '';
    }
}

function confirmEditComment() {
    if (!pendingCommentAction) return;
    var postId = pendingCommentAction.postId;
    var commentId = pendingCommentAction.commentId;
    var isReply = pendingCommentAction.isReply;
    var body = document.getElementById('editCommentBody')?.value.trim();
    if (!body) return;
    if (body.length > 500) {
        notifyFeed('Comments and replies are limited to 500 characters.', 'error');
        return;
    }
    var btn = document.getElementById('confirmEditCommentBtn');
    if (btn) btn.disabled = true;
    apiFetch('/api/community-feed/' + postId + '/comments/' + commentId, {
        method: 'PUT',
        body: JSON.stringify({ body: body }),
    })
        .then(function (r) {
            return r.json().then(function (data) {
                if (!r.ok) throw new Error(data.message || 'Unable to edit comment.');
                return data;
            });
        })
        .then(function (updated) {
            var feedEl = document.getElementById('comment-text-' + commentId);
            if (feedEl) feedEl.textContent = updated.body;
            var previewEl = document.getElementById('cp-text-' + commentId);
            if (previewEl) previewEl.textContent = updated.body;
            document.dispatchEvent(new CustomEvent('community-feed:comment-updated', { detail: updated }));
            closeEditCommentModal();
            notifyFeed(isReply ? 'Reply updated successfully.' : 'Comment updated successfully.');
        })
        .catch(function () {
            notifyFeed(isReply ? 'Unable to update the reply. Please try again.' : 'Unable to update the comment. Please try again.', 'error');
        })
        .finally(function () {
            if (btn) btn.disabled = false;
        });
}

function deleteComment(postId, commentId) {
    pendingCommentAction = { postId: Number(postId), commentId: Number(commentId), isReply: isReplyComment(commentId) };
    document.querySelectorAll('.comment-options-menu.open, .cp-options-menu.open, .post-options-menu.open').forEach(function (m) {
        m.classList.remove('open');
    });
    var modal = document.getElementById('deleteCommentModal');
    if (!modal) return;
    var title = modal.querySelector('h2');
    var body = modal.querySelector('.modal-body p');
    if (title) title.textContent = pendingCommentAction.isReply ? 'Delete Reply' : 'Delete Comment';
    if (body) body.textContent = pendingCommentAction.isReply
        ? 'Are you sure you want to delete this reply?'
        : 'Are you sure you want to delete this comment?';
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeDeleteCommentModal() {
    document.getElementById('deleteCommentModal')?.classList.remove('active');
    pendingCommentAction = null;
    if (!document.getElementById('commentPreviewShell')?.classList.contains('is-open')) {
        document.body.style.overflow = '';
    }
}

function confirmDeleteComment() {
    if (!pendingCommentAction) return;
    var postId = pendingCommentAction.postId;
    var commentId = pendingCommentAction.commentId;
    var isReply = pendingCommentAction.isReply;
    var btn = document.getElementById('confirmDeleteCommentBtn');
    if (btn) btn.disabled = true;
    apiFetch('/api/community-feed/' + postId + '/comments/' + commentId, { method: 'DELETE' })
        .then(function (r) {
            return r.json().then(function (data) {
                if (!r.ok) throw new Error(data.message || 'Unable to delete comment.');
                return data;
            });
        })
        .then(function () {
            document.querySelectorAll('[data-comment-id="' + commentId + '"]').forEach(function (el) { el.remove(); });
            document.dispatchEvent(new CustomEvent('community-feed:comment-deleted', { detail: { postId: postId, commentId: commentId } }));
            closeDeleteCommentModal();
            notifyFeed(isReply ? 'Reply deleted successfully.' : 'Comment deleted successfully.');
        })
        .catch(function () {
            notifyFeed(isReply ? 'Unable to delete the reply. Please try again.' : 'Unable to delete the comment. Please try again.', 'error');
        })
        .finally(function () {
            if (btn) btn.disabled = false;
        });
}

window.editComment = editComment;
window.deleteComment = deleteComment;
window.closeEditCommentModal = closeEditCommentModal;
window.confirmEditComment = confirmEditComment;
window.closeDeleteCommentModal = closeDeleteCommentModal;
window.confirmDeleteComment = confirmDeleteComment;
window.openComments = openComments;
window.toggleComments = toggleComments;
window.setReaction = setReaction;
window.setCommentReaction = setCommentReaction;
window.toggleLike = toggleLike;

function mountFeedPostsList(list, containerId, filterType) {
    var container = document.getElementById(containerId);
    if (!container || typeof buildPost !== 'function') return;

    var filter = filterType || 'all';
    var filtered = (list || []).filter(function (p) {
        if (!p || !p.id) return false;
        return filter === 'all' || String(p.type || '') === filter;
    });

    container.innerHTML = '';

    if (!filtered.length) {
        container.innerHTML = '<div class="post-card" style="text-align:center;color:#999;padding:32px;">No posts found.</div>';
        return;
    }

    filtered.forEach(function (p) {
        knownPostIds.add(Number(p.id));
        postCache.set(Number(p.id), p);
        var el = document.createElement('div');
        el.className = 'post-card';
        el.dataset.postId = p.id;
        el.dataset.postType = p.type || '';
        el.innerHTML = buildPost(p);
        bindPostImageClicks(el, p);
        bindReactionControls(el);
        container.appendChild(el);
    });
}

window.mountFeedPostsList = mountFeedPostsList;

function bindFilterBarScrollHide() {
    var toolbar = document.querySelector('.feed-sticky-toolbar');
    var bar = document.querySelector('.feed-filter-bar:not(.bfp-filter-bar)');
    var anchor = document.querySelector('.feed-filter-anchor');
    var feedSection = document.querySelector('.feed-section');
    if (!toolbar || !bar) return;

    var DOWN_THRESHOLD = 8;
    var UP_THRESHOLD = 4;
    var TOP_SHOW = 12;
    var overlayQuery = window.matchMedia('(max-width: 1024px)');
    var lastY = 0;
    var ticking = false;
    var barHeight = 64;

    function headerHeight() {
        var header = document.querySelector('.navbar.sk-fed-navbar, .navbar');
        return Math.round((header && header.getBoundingClientRect().height) || 64);
    }

    function scrollRoot() {
        if (!feedSection) return window;
        var overflowY = getComputedStyle(feedSection).overflowY;
        if (overflowY === 'auto' || overflowY === 'scroll') {
            return feedSection;
        }
        return window;
    }

    function currentY() {
        var root = scrollRoot();
        return root === window
            ? (window.scrollY || document.documentElement.scrollTop || 0)
            : root.scrollTop;
    }

    function measureBar() {
        var height = toolbar.scrollHeight || bar.offsetHeight;
        if (height > 8) barHeight = height;
        return barHeight;
    }

    function updateAnchor() {
        if (!anchor) return;
        if (!overlayQuery.matches) {
            anchor.style.height = '0px';
            return;
        }
        anchor.style.height = toolbar.classList.contains('is-hidden') ? '0px' : (measureBar() + 'px');
    }

    function syncStickyTop() {
        toolbar.style.top = headerHeight() + 'px';
        updateAnchor();
    }

    function showBar() {
        toolbar.classList.remove('is-hidden');
        bar.classList.remove('is-hidden');
        updateAnchor();
    }

    function hideBar() {
        measureBar();
        toolbar.classList.add('is-hidden');
        updateAnchor();
    }

    lastY = currentY();
    showBar();
    syncStickyTop();

    function apply() {
        ticking = false;
        var y = Math.max(0, currentY());
        var delta = y - lastY;
        lastY = y;

        if (y <= TOP_SHOW) {
            showBar();
            return;
        }
        if (delta >= DOWN_THRESHOLD) {
            hideBar();
            return;
        }
        if (delta <= -UP_THRESHOLD) {
            showBar();
        }
    }

    function onScroll() {
        if (ticking) return;
        ticking = true;
        requestAnimationFrame(apply);
    }

    window.addEventListener('scroll', onScroll, { passive: true });
    document.addEventListener('scroll', onScroll, { passive: true });
    if (feedSection) feedSection.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', function () {
        lastY = currentY();
        syncStickyTop();
        if (currentY() <= TOP_SHOW) showBar();
    });
    if (overlayQuery.addEventListener) {
        overlayQuery.addEventListener('change', function () {
            lastY = currentY();
            syncStickyTop();
            if (currentY() <= TOP_SHOW) showBar();
        });
    }
    bar.addEventListener('focusin', showBar);
}

function setFeedFilter(btn, filter) {
    if (isLoading) return;
    var toolbar = document.querySelector('.feed-sticky-toolbar');
    if (toolbar) toolbar.classList.remove('is-hidden');
    var bar = document.querySelector('.feed-filter-bar');
    if (bar) bar.classList.remove('is-hidden');
    var goHome = filter === 'all';
    currentFilter = filter;
    document.querySelectorAll('.feed-tab').forEach(function(t) { t.classList.remove('active'); });
    btn.classList.add('active');
    if (goHome) {
        feedSearch = '';
        var search = document.getElementById('feedSearchInput');
        if (search) search.value = '';
    }
    setFilterTabsDisabled(true);
    loadPosts(true).then(function () {
        if (goHome) window.scrollTo({ top: 0, behavior: 'smooth' });
    });
}

function setFilterTabsDisabled(disabled) {
    document.querySelectorAll('.feed-tab').forEach(function (tab) {
        tab.disabled = disabled;
        tab.classList.toggle('is-loading', disabled);
    });
}

function togglePostOptions(id, e) {
    e.stopPropagation();
    var menu = document.getElementById('options-menu-' + id);
    if (!menu) return;
    var isOpen = menu.classList.contains('open');
    document.querySelectorAll('.post-options-menu.open').forEach(function(m) { m.classList.remove('open'); });
    if (!isOpen) menu.classList.add('open');
}


function deletePost(id) {
    if (!confirm('Are you sure you want to delete this post?')) return;
    apiFetch('/api/community-feed/' + id, { method: 'DELETE' })
        .then(function(r) {
            return r.json().then(function (data) {
                if (!r.ok) throw new Error(data.message || 'Unable to delete the post.');
                return data;
            });
        })
        .then(function() {
            posts = posts.filter(function(x) { return x.id !== id; });
            var el = document.querySelector('[data-post-id="' + id + '"]');
            if (el) el.remove();
            document.querySelectorAll('.post-options-menu.open').forEach(function(m) { m.classList.remove('open'); });
            notifyFeed('Post deleted successfully.');
        })
        .catch(function () {
            notifyFeed('Unable to delete the post. Please try again.', 'error');
        });
}

function editPost(id) {
    var p = posts.find(function(x) { return x.id === id; });
    if (!p) return;
    editingPostId = id;
    pendingFiles = [];
    removedImageIds = [];
    existingImages = [];
    if (p.image_items && p.image_items.length) {
        existingImages = p.image_items.map(function(item) {
            return { id: item.id, url: item.url };
        });
    } else if (p.images && p.images.length) {
        existingImages = p.images.map(function(url, idx) {
            return { id: null, url: url, legacyIndex: idx };
        });
    }
    document.getElementById('compose-modal-title').textContent = 'Edit Post';
    document.getElementById('edit-post-id').value = id;
    document.getElementById('compose-content').value = p.body || '';
    document.getElementById('compose-type').value = p.type || 'update';
    document.getElementById('compose-link-input-wrap').style.display = 'none';
    document.getElementById('compose-link-input').value = '';
    if (p.link_url) {
        document.getElementById('compose-link-input').value = p.link_url;
        document.getElementById('compose-link-input-wrap').style.display = 'block';
    }
    refreshPreviewGrid();
    updateCharCount();
    document.querySelectorAll('.post-options-menu.open').forEach(function(m) { m.classList.remove('open'); });
    document.getElementById('composeModal').classList.add('active');
}

function totalSelectedImages() {
    return existingImages.length + pendingFiles.length;
}

function submitPost() {
    var content = document.getElementById('compose-content').value.trim();
    if (!content) { notifyFeed('Please write something.', 'error'); return; }
    if (content.length > 2000) { notifyFeed('Posts are limited to 2000 characters.', 'error'); return; }

    var type = document.getElementById('compose-type').value;
    var linkVal = document.getElementById('compose-link-input').value.trim();
    var titleVal = document.getElementById('compose-title') ? document.getElementById('compose-title').value.trim() : '';

    var fd = new FormData();
    fd.append('type', type);
    fd.append('body', content);
    if (titleVal) fd.append('title', titleVal);
    if (linkVal) fd.append('link_url', linkVal);
    pendingFiles.forEach(function (file) { fd.append('images[]', file); });
    removedImageIds.forEach(function (imageId) { fd.append('removed_image_ids[]', imageId); });

    savePostForm(fd);
}

function savePostForm(formData) {
    var isEdit = editingPostId !== null;
    var url = isEdit ? '/api/community-feed/' + editingPostId : '/api/community-feed';
    var method = isEdit ? 'POST' : 'POST';
    if (isEdit) formData.append('_method', 'PUT');

    if (typeof window.showLoading === 'function') {
        window.showLoading(isEdit ? 'Saving post' : 'Publishing post', 'Please wait');
    }

    fetch(url, {
        method: method,
        headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
        credentials: 'same-origin',
        body: formData,
    })
        .then(function(r) {
            return r.json().then(function(body) {
                if (!r.ok) {
                    var msg = body.message || (body.errors ? Object.values(body.errors).flat().join(' ') : 'Unable to save post.');
                    throw new Error(msg);
                }
                return body;
            });
        })
        .then(function(saved) {
            if (!saved || !saved.id) throw new Error('Invalid response from server.');
            closeComposeModal();
            notifyFeed(isEdit ? 'Post updated successfully.' : 'Post created successfully.');
            renderPosts(true);
        })
        .catch(function() {
            notifyFeed(isEdit ? 'Unable to update the post. Please try again.' : 'Unable to create the post. Please try again.', 'error');
        })
        .finally(function () {
            if (typeof window.hideLoading === 'function') window.hideLoading();
        });
}

function openComposeModal(type) {
    editingPostId = null;
    pendingFiles = [];
    existingImages = [];
    removedImageIds = [];
    document.getElementById('compose-modal-title').textContent = 'Create Post';
    document.getElementById('edit-post-id').value = '';
    document.getElementById('compose-content').value = '';
    var previewWrap = document.getElementById('compose-images-preview');
    if (previewWrap) previewWrap.innerHTML = '';
    var meta = document.getElementById('compose-images-meta');
    if (meta) meta.textContent = '';
    document.getElementById('compose-link-input-wrap').style.display = 'none';
    document.getElementById('compose-link-input').value = '';
    var fileInput = document.getElementById('compose-image-input');
    if (fileInput) fileInput.value = '';
    if (type && document.getElementById('compose-type')) {
        var sel = document.getElementById('compose-type');
        var map = { announcement:'announcement', event:'event', photo:'activity' };
        sel.value = map[type] || 'update';
    }
    var modal = document.getElementById('composeModal');
    modal.classList.add('active');
    modal.classList.remove('compose-maximized');
    var btn = document.getElementById('composeFullscreenBtn');
    if (btn) {
        btn.title = 'Full screen';
        btn.setAttribute('aria-label', 'Full screen');
        btn.innerHTML = '<svg viewBox="0 0 20 20" fill="currentColor" width="16" height="16"><path d="M3 3h5v2H5v3H3V3zm9 0h5v5h-2V5h-3V3zM3 12h2v3h3v2H3v-5zm12 0h2v5h-5v-2h3v-3z"/></svg>';
    }
    updateCharCount();
}

function closeComposeModal() {
    var modal = document.getElementById('composeModal');
    if (modal) modal.classList.remove('active', 'compose-maximized');
    editingPostId = null;
    pendingFiles = [];
    existingImages = [];
    removedImageIds = [];
}

function toggleComposeFullscreen() {
    var modal = document.getElementById('composeModal');
    var btn = document.getElementById('composeFullscreenBtn');
    if (!modal) return;
    modal.classList.toggle('compose-maximized');
    var isMax = modal.classList.contains('compose-maximized');
    if (btn) {
        btn.title = isMax ? 'Restore down (⧉)' : 'Fullscreen (□)';
        btn.setAttribute('aria-label', isMax ? 'Restore down' : 'Fullscreen');
    }
}

function updateCharCount() {
    var el = document.getElementById('compose-content');
    var counter = document.getElementById('compose-char-count');
    if (!el || !counter) return;
    var len = el.value.length;
    counter.textContent = len + ' / 2000';
    counter.classList.toggle('over-limit', len > 2000);
}

function previewImages(input) {
    var files = Array.from(input.files || []);
    if (!files.length) return;

    var remaining = MAX_IMAGES - totalSelectedImages();
    if (remaining <= 0) {
        alert('You can upload up to ' + MAX_IMAGES + ' images per post.');
        input.value = '';
        return;
    }

    var toAdd = files.slice(0, remaining);
    if (files.length > remaining) {
        alert('Only ' + remaining + ' more image(s) can be added (max ' + MAX_IMAGES + ').');
    }

    toAdd.forEach(function (file) {
        pendingFiles.push(file);
    });

    input.value = '';
    refreshPreviewGrid();
}

function appendExistingThumb(image) {
    var wrap = document.getElementById('compose-images-preview');
    if (!wrap || !image || !image.url) return;
    var item = document.createElement('div');
    item.className = 'compose-preview-item compose-preview-item--existing';
    item.innerHTML = '<img src="' + escapeHtml(image.url) + '" alt="Existing photo"><button type="button" class="compose-preview-remove" aria-label="Remove image">&times;</button>';
    item.querySelector('.compose-preview-remove').addEventListener('click', function () {
        if (image.id) {
            removedImageIds.push(image.id);
        }
        existingImages = existingImages.filter(function (img) {
            if (image.id) return img.id !== image.id;
            return img.url !== image.url;
        });
        refreshPreviewGrid();
    });
    wrap.appendChild(item);
}

function appendPreviewThumb(src, index) {
    var wrap = document.getElementById('compose-images-preview');
    if (!wrap) return;
    var item = document.createElement('div');
    item.className = 'compose-preview-item compose-preview-item--new';
    item.innerHTML = '<img src="' + src + '" alt="Preview"><button type="button" class="compose-preview-remove" data-index="' + index + '" aria-label="Remove image">&times;</button>';
    item.querySelector('.compose-preview-remove').addEventListener('click', function () {
        pendingFiles.splice(parseInt(this.getAttribute('data-index'), 10), 1);
        refreshPreviewGrid();
    });
    wrap.appendChild(item);
}

function refreshPreviewGrid() {
    var wrap = document.getElementById('compose-images-preview');
    if (!wrap) return;
    wrap.innerHTML = '';
    existingImages.forEach(function (image) {
        appendExistingThumb(image);
    });
    pendingFiles.forEach(function (file, idx) {
        var reader = new FileReader();
        reader.onload = function (e) { appendPreviewThumb(e.target.result, idx); };
        reader.readAsDataURL(file);
    });
    updateImagesMeta();
}

function updateImagesMeta() {
    var meta = document.getElementById('compose-images-meta');
    if (!meta) return;
    var total = totalSelectedImages();
    meta.textContent = total
        ? total + ' image' + (total === 1 ? '' : 's') + ' selected (max ' + MAX_IMAGES + ')'
        : '';
}

function toggleLinkInput() {
    var wrap = document.getElementById('compose-link-input-wrap');
    wrap.style.display = wrap.style.display === 'none' ? 'block' : 'none';
}

/* ── PROGRAM MODALS ── (defined in blade inline script for success modal support) ── */

/* ── Layout shell (sidebar, notifications, profile) provided by layout.js ── */

/* ── INIT ── */
document.addEventListener('DOMContentLoaded', function() {
    ensureFeedReactionAudio();
    bindFilterBarScrollHide();
    document.querySelector('.feed-sticky-toolbar')?.classList.remove('is-hidden');
    document.querySelector('.feed-filter-bar')?.classList.remove('is-hidden');
    document.addEventListener('pointerdown', ensureFeedReactionAudio, { once: true, capture: true });
    if (document.getElementById('feed-posts')) {
        renderPosts(true);
    }
    document.getElementById('compose-content')?.addEventListener('input', updateCharCount);
    updateCharCount();
    var searchInput = document.getElementById('feedSearchInput');
    if (searchInput) {
        var searchTimer = null;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimer);
            var input = this;
            searchTimer = setTimeout(function() {
                feedSearch = input.value.trim();
                renderPosts(true);
            }, 300);
        });
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(searchTimer);
                feedSearch = searchInput.value.trim();
                renderPosts(true);
            }
        });
    }

    // Close post option menus on outside click
    document.addEventListener('click', function(e) {
        document.querySelectorAll('.post-options-menu.open').forEach(function(m) { m.classList.remove('open'); });
        if (!e.target.closest || !e.target.closest('.reaction-wrap')) {
            closeAllReactionPickers();
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (document.getElementById('editCommentModal')?.classList.contains('active')) {
                closeEditCommentModal();
                return;
            }
            if (document.getElementById('deleteCommentModal')?.classList.contains('active')) {
                closeDeleteCommentModal();
                return;
            }
            closeReactionViewer();
            closeLightbox();
        }
    });

    document.getElementById('editCommentBody')?.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            confirmEditComment();
        }
    });

    document.getElementById('lightboxClose')?.addEventListener('click', closeLightbox);
    document.getElementById('lightboxPrev')?.addEventListener('click', lightboxPrev);
    document.getElementById('lightboxNext')?.addEventListener('click', lightboxNext);
    document.getElementById('lightboxZoomIn')?.addEventListener('click', lightboxZoomIn);
    document.getElementById('lightboxZoomOut')?.addEventListener('click', lightboxZoomOut);
    document.getElementById('lightboxZoomReset')?.addEventListener('click', lightboxZoomReset);
    document.getElementById('imageLightbox')?.addEventListener('click', function (e) {
        if (e.target && e.target.id === 'imageLightbox') closeLightbox();
    });

    var brgyList = document.getElementById('brgyLinkList') || document.getElementById('cfBrgyLinkList');
    var feedSidebar = document.getElementById('programsSidebar') || document.getElementById('feedSidebar');
    feedSidebar?.addEventListener('wheel', function (event) {
        event.preventDefault();
        event.stopPropagation();
        if (brgyList) brgyList.scrollTop += event.deltaY;
    }, { passive: false });

    var fab = document.getElementById('programsFab');
    var backdrop = document.getElementById('programsDrawerBackdrop');
    var closeDrawer = function () {
        feedSidebar?.classList.remove('drawer-open');
        backdrop?.classList.remove('active');
        document.body.style.overflow = '';
    };
    fab?.addEventListener('click', function (e) {
        e.stopPropagation();
        feedSidebar?.classList.add('drawer-open');
        backdrop?.classList.add('active');
        document.body.style.overflow = 'hidden';
    });
    backdrop?.addEventListener('click', closeDrawer);
    window.addEventListener('resize', function () {
        if (window.innerWidth > 1100) closeDrawer();
    });

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) pollFeedUpdates();
    });
});
