(function () {
'use strict';

const REACTION_EMOJI = { like: '👍', love: '❤️', haha: '😂', wow: '😮', sad: '😢', angry: '😡' };
const REACTION_LABEL = { like: 'Like', love: 'Love', haha: 'Haha', wow: 'Wow', sad: 'Sad', angry: 'Angry' };
const THUMBS_SVG = '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M2 10.5a1.5 1.5 0 113 0v6a1.5 1.5 0 01-3 0v-6zM6 10.333v5.43a2 2 0 001.106 1.79l.05.025A4 4 0 008.943 18h5.416a2 2 0 001.962-1.608l1.2-6A2 2 0 0015.56 8H12V4a2 2 0 00-2-2 1 1 0 00-1 1v.667a4 4 0 01-.8 2.4L6.8 7.933a4 4 0 00-.8 2.4z"/></svg>';
const COMMENT_SVG = '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 10 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7z" clip-rule="evenodd"/></svg>';
const CHEVRON_SVG = '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>';
const SEND_SVG = '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z"/></svg>';

let post = window.CommentPreviewConfig?.post || null;
let sortMode = 'relevant';
let sending = false;
let viewerState = { data: null, filter: 'all' };
let pageBound = false;
let syncingUrl = false;
let expandedReplies = new Set();

const COMMENT_MAX_CHARS = 500;
const COMMENT_LIMIT_MSG = 'Comments and replies are limited to 500 characters.';

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';
const cfg = () => window.CommentPreviewConfig || {};
const REACTION_SOUND_URL = '/sounds/reactions_ux.mp3';
let reactionAudio = null;

function playReactionSound() {
    if (typeof window.playFeedReactionSound === 'function') {
        window.playFeedReactionSound();
        return;
    }
    try {
        if (!reactionAudio) {
            reactionAudio = new Audio(REACTION_SOUND_URL);
            reactionAudio.preload = 'auto';
            reactionAudio.volume = 0.75;
            try { reactionAudio.load(); } catch (_) {}
        }
        reactionAudio.muted = false;
        reactionAudio.volume = 0.75;
        if (reactionAudio.readyState >= 2) {
            try { reactionAudio.currentTime = 0; } catch (_) {}
        }
        reactionAudio.play().catch(() => {
            const oneShot = new Audio(REACTION_SOUND_URL);
            oneShot.volume = 0.75;
            oneShot.play().catch(() => {});
        });
    } catch (e) {}
}

function escapeHtml(text) {
    return String(text ?? '')
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function notifyPreview(message, type = 'success') {
    if (typeof window.showFeedToast === 'function') {
        window.showFeedToast(message, type);
    }
}

const reactionRequestSeq = new Map();
const reactionAbort = new Map();

function beginReactionRequest(key) {
    const seq = (reactionRequestSeq.get(key) || 0) + 1;
    reactionRequestSeq.set(key, seq);
    reactionAbort.get(key)?.abort();
    const controller = new AbortController();
    reactionAbort.set(key, controller);
    return { seq, signal: controller.signal };
}

function isLatestReactionRequest(key, seq) {
    return reactionRequestSeq.get(key) === seq;
}

async function apiFetch(url, options = {}) {
    const res = await fetch(url, {
        ...options,
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
            ...(options.headers || {}),
        },
    });
    if (!res.ok) {
        let message = 'Request failed.';
        try { message = (await res.json()).message || message; } catch (_) { /* ignore */ }
        throw new Error(message);
    }
    return res.json();
}

function avatarUrl(item) {
    return item?.author_avatar_url || item?.avatar_url || item?.barangay_logo_url || cfg().userAvatar || cfg().defaultLogo;
}

function postAvatar() {
    return post?.author_avatar_url || post?.barangay_logo_url || cfg().userAvatar || cfg().defaultLogo;
}

function safeImg(src, className, alt) {
    const fallback = cfg().defaultLogo || cfg().userAvatar || '';
    return `<img src="${escapeHtml(src)}" alt="${escapeHtml(alt || '')}" class="${className || ''}" onerror="this.onerror=null;this.src='${escapeHtml(fallback)}'">`;
}

function formatCount(n) {
    const num = Number(n || 0);
    if (num >= 1000) return `${(num / 1000).toFixed(num % 1000 === 0 ? 0 : 1).replace(/\.0$/, '')}K`;
    return String(num);
}

function topTypes(counts) {
    return Object.keys(REACTION_EMOJI)
        .filter((type) => Number((counts || {})[type] || 0) > 0)
        .sort((a, b) => Number(counts[b] || 0) - Number(counts[a] || 0))
        .slice(0, 3);
}

function facesHtml(counts) {
    return topTypes(counts).map((type) =>
        `<span class="cp-face ${type}" title="${escapeHtml(REACTION_LABEL[type] || type)}">${REACTION_EMOJI[type] || ''}</span>`
    ).join('');
}

function countComments(comments) {
    return (comments || []).reduce((sum, c) => sum + 1 + countComments(c.replies || []), 0);
}

function sortComments(comments) {
    const list = [...(comments || [])];
    if (sortMode === 'newest') return list.sort((a, b) => Number(b.id) - Number(a.id));
    if (sortMode === 'oldest') return list.sort((a, b) => Number(a.id) - Number(b.id));
    return list.sort((a, b) => {
        const likes = Number(b.likes || 0) - Number(a.likes || 0);
        return likes !== 0 ? likes : Number(b.id) - Number(a.id);
    });
}

function pickerHtml(active) {
    return `<div class="cp-picker"><div class="cp-picker-inner">${
        Object.entries(REACTION_EMOJI).map(([type, emoji]) =>
            `<button type="button" class="cp-picker-opt${active === type ? ' is-active' : ''}" data-type="${type}" title="${type}">${emoji}</button>`
        ).join('')
    }</div></div>`;
}

function likeInner(type) {
    const label = type ? (REACTION_LABEL[type] || 'Like') : 'Like';
    const icon = type && REACTION_EMOJI[type] ? `<span>${REACTION_EMOJI[type]}</span>` : THUMBS_SVG;
    return `${icon}<span>${escapeHtml(label)}</span>`;
}

function commentLikeInner(type) {
    const label = type ? (REACTION_LABEL[type] || 'Like') : 'Like';
    if (type && REACTION_EMOJI[type]) {
        return `<span class="comment-react-emoji">${REACTION_EMOJI[type]}</span><span>${escapeHtml(label)}</span>`;
    }
    return `<span>${escapeHtml(label)}</span>`;
}

function renderPost() {
    if (!post) return;
    document.getElementById('cpTitle').textContent = `${post.author_name || 'SK'}'s Post`;
    const images = post.images?.length ? post.images : (post.image_url ? [post.image_url] : []);
    const mediaClass = images.length > 1 ? 'two' : 'one';
    const media = images.length
        ? `<div class="cp-media ${mediaClass}">${images.map((src, index) =>
            `<button type="button" class="cp-media-btn" data-index="${index}" aria-label="View photo ${index + 1}"><img src="${escapeHtml(src)}" alt=""></button>`
        ).join('')}</div>`
        : '';

    document.getElementById('cpPost').innerHTML = `
        <div class="cp-post-head">
            ${safeImg(postAvatar(), 'cp-avatar', post.author_name || '')}
            <div>
                <div class="cp-author">${escapeHtml(post.author_name || '')}</div>
                <div class="cp-meta">${escapeHtml(post.type || '')} · ${escapeHtml(post.time || '')}</div>
            </div>
        </div>
        ${post.title ? `<h2 class="cp-title-text">${escapeHtml(post.title)}</h2>` : ''}
        <p class="cp-body">${escapeHtml(post.body || '')}</p>
        ${media}
    `;

    const likes = Number(post.likes || 0);
    const comments = countComments(post.comments);
    const type = post.reaction_type || (post.liked ? 'like' : '');
    document.getElementById('cpEngage').innerHTML = `
        <div class="cp-stats">
            <button type="button" class="cp-stats-left" id="cpViewPostReactions"${likes > 0 ? '' : ' hidden'}>
                <span class="cp-faces">${facesHtml(post.reaction_counts)}</span>
                <span class="cp-react-total">${likes > 0 ? formatCount(likes) : ''}</span>
            </button>
            <button type="button" class="cp-stats-comments" id="cpFocusComments">${comments > 0 ? `${formatCount(comments)} comment${comments === 1 ? '' : 's'}` : ''}</button>
        </div>
        <div class="cp-actions">
            <div class="cp-reaction-wrap" data-target="post">
                <button type="button" class="cp-action${post.liked ? ' liked' : ''}" id="cpLikeBtn" data-type="${escapeHtml(type)}">${likeInner(type)}</button>
                ${pickerHtml(type)}
            </div>
            <button type="button" class="cp-action" id="cpFocusComment">${COMMENT_SVG}<span>Comment</span></button>
        </div>
    `;

    bindReactionWrap(document.querySelector('.cp-reaction-wrap'));
    document.querySelectorAll('.cp-media-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (typeof window.openLightbox === 'function') {
                window.openLightbox(images, Number(btn.dataset.index || 0));
            }
        });
    });
    document.getElementById('cpViewPostReactions')?.addEventListener('click', () => openViewer('post'));
    document.getElementById('cpFocusComments')?.addEventListener('click', () => {
        document.getElementById('cpComments')?.scrollIntoView({ block: 'nearest' });
    });
    document.getElementById('cpFocusComment')?.addEventListener('click', () => {
        document.getElementById('cpCommentInput')?.focus();
        document.getElementById('cpComposer')?.scrollIntoView({ block: 'end' });
    });
}

function renderComments() {
    const root = document.getElementById('cpComments');
    const comments = sortComments(post?.comments || []);
    if (!comments.length) {
        root.innerHTML = '<p class="cp-empty">No comments yet. Be the first to comment.</p>';
        return;
    }
    root.innerHTML = comments.map((c) => commentHtml(c, false)).join('');
    root.querySelectorAll('.cp-reaction-wrap').forEach(bindReactionWrap);
}

function commentHtml(comment, isReply) {
    const replies = comment.replies || [];
    const likes = Number(comment.likes || 0);
    const type = comment.reaction_type || (comment.liked ? 'like' : '');
    const badge = likes > 0
        ? `<button type="button" class="cp-bubble-react" data-comment-id="${comment.id}">${facesHtml(comment.reaction_counts)}<span class="cp-react-total">${formatCount(likes)}</span></button>`
        : `<button type="button" class="cp-bubble-react" data-comment-id="${comment.id}" hidden></button>`;
    const options = comment.owned
        ? `<div class="cp-options">
            <button type="button" class="cp-options-btn" data-opt="${comment.id}">⋯</button>
            <div class="cp-options-menu" id="cp-opt-${comment.id}">
              <button type="button" data-edit="${comment.id}">Edit</button>
              <button type="button" class="danger" data-del="${comment.id}">Delete</button>
            </div>
           </div>`
        : '';
    const repliesOpen = !isReply && replies.length > 0 && expandedReplies.has(Number(comment.id));
    const viewReplies = replies.length && !isReply
        ? `<button type="button" class="cp-view-replies${repliesOpen ? ' is-open' : ''}" data-toggle-replies="${comment.id}" data-count="${replies.length}">${CHEVRON_SVG} ${repliesOpen ? 'Hide replies' : `View ${replies.length} ${replies.length === 1 ? 'reply' : 'replies'}`}</button>`
        : '';

    return `<div class="cp-comment${isReply ? ' is-reply' : ''}" data-comment-id="${comment.id}">
        ${safeImg(avatarUrl(comment), 'cp-comment-avatar', comment.author_name || '')}
        <div class="cp-comment-body">
            <div class="cp-bubble">
                <span class="cp-bubble-name">${escapeHtml(comment.author_name)}</span>
                <span class="cp-bubble-text" id="cp-text-${comment.id}">${escapeHtml(comment.body)}</span>
                ${badge}
            </div>
            <div class="cp-comment-meta">
                <div class="cp-reaction-wrap" data-target="comment" data-comment-id="${comment.id}">
                    <button type="button" class="cp-like-btn${comment.liked ? ' liked' : ''}" data-type="${escapeHtml(type)}">${commentLikeInner(type)}</button>
                    ${pickerHtml(type)}
                </div>
                <button type="button" class="cp-meta-btn" data-reply="${comment.id}">Reply</button>
                <span class="cp-time">${escapeHtml(comment.time || '')}</span>
                ${options}
            </div>
            ${viewReplies}
            <div class="cp-reply-box" id="cp-reply-${comment.id}">
                <input type="text" maxlength="500" placeholder="Write a reply..." data-reply-input="${comment.id}">
                <button type="button" class="cp-send-btn" data-reply-send="${comment.id}">${SEND_SVG}</button>
            </div>
            ${replies.length ? `<div class="cp-replies" id="cp-replies-${comment.id}"${repliesOpen ? '' : ' hidden'}>${replies.map((r) => commentHtml(r, true)).join('')}</div>` : ''}
        </div>
    </div>`;
}

function isTouch() {
    return window.matchMedia('(hover: none), (pointer: coarse)').matches;
}

function closePickers(except = null) {
    document.querySelectorAll('.cp-reaction-wrap.is-open').forEach((el) => {
        if (el !== except) el.classList.remove('is-open');
    });
}

function bindReactionWrap(wrap) {
    if (!wrap || wrap.dataset.bound === '1') return;
    wrap.dataset.bound = '1';
    const btn = wrap.querySelector('.cp-action, .cp-like-btn');
    const picker = wrap.querySelector('.cp-picker');
    let hideTimer = null;
    let pressTimer = null;
    let didLongPress = false;
    const target = wrap.dataset.target;
    const commentId = Number(wrap.dataset.commentId || 0);

    const apply = (type) => {
        if (target === 'comment') setCommentReaction(commentId, type);
        else setPostReaction(type);
    };
    const open = () => {
        closePickers(wrap);
        wrap.classList.add('is-open');
    };

    wrap.addEventListener('mouseenter', () => {
        if (isTouch()) return;
        clearTimeout(hideTimer);
        open();
    });
    wrap.addEventListener('mouseleave', () => {
        if (isTouch()) return;
        hideTimer = setTimeout(() => wrap.classList.remove('is-open'), 80);
    });
    picker?.addEventListener('mouseenter', () => {
        if (isTouch()) return;
        clearTimeout(hideTimer);
        wrap.classList.add('is-open');
    });
    picker?.addEventListener('mouseleave', () => {
        if (isTouch()) return;
        hideTimer = setTimeout(() => wrap.classList.remove('is-open'), 80);
    });
    btn?.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        if (didLongPress) { didLongPress = false; return; }
        if (isTouch()) {
            if (!wrap.classList.contains('is-open')) {
                open();
                return;
            }
            wrap.classList.remove('is-open');
            apply(btn.dataset.type || 'like');
            return;
        }
        wrap.classList.remove('is-open');
        apply(btn.dataset.type || 'like');
    });
    btn?.addEventListener('touchstart', () => {
        didLongPress = false;
        pressTimer = setTimeout(() => { didLongPress = true; open(); }, 180);
    }, { passive: true });
    ['touchend', 'touchcancel', 'touchmove'].forEach((ev) => btn?.addEventListener(ev, () => clearTimeout(pressTimer)));
    picker?.querySelectorAll('.cp-picker-opt').forEach((opt) => {
        opt.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            wrap.classList.remove('is-open');
            apply(opt.dataset.type);
        });
    });
}

function resolveNextReaction(currentType, requestedType) {
    const requested = requestedType || 'like';
    const current = currentType || '';
    if (current === requested) return { liked: false, type: '' };
    return { liked: true, type: requested };
}

function bumpReactionCounts(counts, fromType, toType) {
    const next = { ...(counts || {}) };
    if (fromType && fromType !== toType) {
        next[fromType] = Math.max(0, Number(next[fromType] || 0) - 1);
    }
    if (toType && fromType !== toType) {
        next[toType] = Number(next[toType] || 0) + 1;
    }
    return next;
}

function paintCpPostReaction(liked, type, count, counts) {
    post.liked = liked;
    post.reaction_type = type || null;
    post.likes = count;
    if (counts) post.reaction_counts = counts;
    const btn = document.getElementById('cpLikeBtn');
    if (btn) {
        btn.classList.toggle('liked', Boolean(liked));
        btn.dataset.type = type || '';
        btn.innerHTML = likeInner(type);
        btn.closest('.cp-reaction-wrap')?.querySelectorAll('.cp-picker-opt').forEach((opt) => {
            opt.classList.toggle('is-active', Boolean(type) && opt.dataset.type === type);
        });
    }
    const left = document.getElementById('cpViewPostReactions');
    if (left) {
        left.hidden = count <= 0;
        const faces = left.querySelector('.cp-faces');
        if (faces) faces.innerHTML = facesHtml(post.reaction_counts);
        const countSpan = left.querySelector('.cp-react-total');
        if (countSpan) countSpan.textContent = count > 0 ? formatCount(count) : '';
    }
}

function paintCpCommentReaction(commentId, liked, type, count, counts) {
    updateCommentReaction(post.comments, commentId, {
        reaction_type: type || null,
        liked,
        count,
        reaction_counts: counts,
    });
    const wrap = document.querySelector(`.cp-reaction-wrap[data-comment-id="${commentId}"]`);
    const btn = wrap?.querySelector('.cp-like-btn');
    if (btn) {
        btn.classList.toggle('liked', Boolean(liked));
        btn.dataset.type = type || '';
        btn.innerHTML = commentLikeInner(type);
    }
    wrap?.querySelectorAll('.cp-picker-opt').forEach((opt) => {
        opt.classList.toggle('is-active', Boolean(type) && opt.dataset.type === type);
    });
    const badge = document.querySelector(`.cp-bubble-react[data-comment-id="${commentId}"]`);
    if (badge) {
        if (count > 0) {
            badge.hidden = false;
            badge.innerHTML = `${facesHtml(counts || {})}<span class="cp-react-total">${formatCount(count)}</span>`;
        } else {
            badge.hidden = true;
        }
    }
}

async function setPostReaction(type) {
    const btn = document.getElementById('cpLikeBtn');
    const current = btn?.dataset.type || post.reaction_type || '';
    const next = resolveNextReaction(current, type);
    let count = Number(post.likes || 0);
    if (current && !next.liked) count = Math.max(0, count - 1);
    else if (!current && next.liked) count += 1;
    if (next.liked) playReactionSound();
    paintCpPostReaction(next.liked, next.type, count, bumpReactionCounts(post.reaction_counts || {}, current, next.type));
    const key = `post:${post.id}`;
    const { seq, signal } = beginReactionRequest(key);
    try {
        const data = await apiFetch(`/api/community-feed/${post.id}/react`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ reaction_type: type, client_seq: seq }),
            signal,
        });
        if (!isLatestReactionRequest(key, seq)) return;
        if (typeof data?.liked !== 'boolean') return;
        paintCpPostReaction(data.liked, data.liked ? (data.reaction_type || 'like') : '', data.count, data.reaction_counts);
    } catch (err) {
        if (err?.name === 'AbortError') return;
        if (!isLatestReactionRequest(key, seq)) return;
        notifyPreview('Unable to update your reaction. Please try again.', 'error');
    }
}

async function setCommentReaction(commentId, type) {
    const wrap = document.querySelector(`.cp-reaction-wrap[data-comment-id="${commentId}"]`);
    const btn = wrap?.querySelector('.cp-like-btn');
    const comment = findComment(post.comments, commentId);
    const current = btn?.dataset.type || comment?.reaction_type || '';
    const next = resolveNextReaction(current, type);
    let count = Number(comment?.likes || 0);
    if (current && !next.liked) count = Math.max(0, count - 1);
    else if (!current && next.liked) count += 1;
    const nextCounts = bumpReactionCounts(comment?.reaction_counts || {}, current, next.type);
    if (next.liked) playReactionSound();
    paintCpCommentReaction(commentId, next.liked, next.type, count, nextCounts);
    const key = `comment:${commentId}`;
    const { seq, signal } = beginReactionRequest(key);
    try {
        const data = await apiFetch(`/api/community-feed/${post.id}/comments/${commentId}/reactions`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ reaction_type: type, client_seq: seq }),
            signal,
        });
        if (!isLatestReactionRequest(key, seq)) return;
        if (typeof data?.liked !== 'boolean') return;
        paintCpCommentReaction(
            commentId,
            data.liked,
            data.liked ? (data.reaction_type || 'like') : '',
            data.count,
            data.reaction_counts
        );
    } catch (err) {
        if (err?.name === 'AbortError') return;
        if (!isLatestReactionRequest(key, seq)) return;
        notifyPreview('Unable to update your reaction. Please try again.', 'error');
    }
}

function updateCommentReaction(comments, id, data) {
    (comments || []).forEach((c) => {
        if (Number(c.id) === Number(id)) {
            c.reaction_type = data.reaction_type;
            c.liked = data.liked;
            c.likes = data.count;
            if (data.reaction_counts) c.reaction_counts = data.reaction_counts;
        }
        updateCommentReaction(c.replies || [], id, data);
    });
}

function threadRootId(commentId) {
    for (const c of post.comments || []) {
        if (Number(c.id) === Number(commentId)) return Number(c.id);
        if (findComment(c.replies || [], commentId)) return Number(c.id);
    }
    return Number(commentId);
}

function appendToThread(parentId, comment) {
    const rootId = threadRootId(parentId);
    const parent = findComment(post.comments, rootId);
    if (!parent) return rootId;
    parent.replies = parent.replies || [];
    if (!parent.replies.some((r) => String(r.id) === String(comment.id))) {
        parent.replies.push(comment);
    }
    parent.reply_count = parent.replies.length;
    expandedReplies.add(rootId);
    return rootId;
}

function keepInScroller(el) {
    const scroller = document.getElementById('cpScroll');
    if (!scroller || !el) return;
    const scrollerRect = scroller.getBoundingClientRect();
    const elRect = el.getBoundingClientRect();
    if (elRect.top < scrollerRect.top) {
        scroller.scrollTop -= (scrollerRect.top - elRect.top) + 12;
    } else if (elRect.bottom > scrollerRect.bottom) {
        scroller.scrollTop += (elRect.bottom - scrollerRect.bottom) + 12;
    }
}

function refreshPreview(focusId) {
    const scroller = document.getElementById('cpScroll');
    const top = scroller?.scrollTop || 0;
    renderPost();
    renderComments();
    if (scroller) scroller.scrollTop = top;
    if (!focusId) return;
    requestAnimationFrame(() => {
        keepInScroller(document.querySelector(`#cpComments [data-comment-id="${String(focusId)}"]`));
    });
}

async function submitComment(body, parentId = null) {
    if (sending || !body) return;
    if (body.length > COMMENT_MAX_CHARS) {
        notifyPreview(COMMENT_LIMIT_MSG, 'error');
        return;
    }
    sending = true;
    const tempId = 'tmp-' + Date.now();
    const optimistic = {
        id: tempId,
        body,
        author_name: cfg().userDisplayName || 'You',
        author_avatar_url: cfg().userAvatar || '',
        time: 'Just now',
        liked: false,
        likes: 0,
        owned: true,
        replies: [],
        parent_id: parentId || null,
    };
    if (parentId) {
        appendToThread(parentId, optimistic);
    } else {
        post.comments = post.comments || [];
        post.comments.push(optimistic);
    }
    refreshPreview(tempId);
    try {
        const payload = { body };
        if (parentId) payload.parent_id = parentId;
        const comment = await apiFetch(`/api/community-feed/${post.id}/comment`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const removeTemp = (comments) => {
            const idx = (comments || []).findIndex((c) => String(c.id) === String(tempId));
            if (idx >= 0) comments.splice(idx, 1);
            (comments || []).forEach((c) => removeTemp(c.replies || []));
        };
        removeTemp(post.comments);
        if (!findComment(post.comments, comment.id)) {
            const actualParentId = comment.parent_id || parentId;
            if (actualParentId) {
                appendToThread(actualParentId, comment);
            } else {
                post.comments = post.comments || [];
                post.comments.push(comment);
            }
        }
        refreshPreview(comment.id);
        notifyPreview(parentId ? 'Reply added successfully.' : 'Comment added successfully.');
    } catch (err) {
        const removeTemp = (comments) => {
            const idx = (comments || []).findIndex((c) => String(c.id) === String(tempId));
            if (idx >= 0) comments.splice(idx, 1);
            (comments || []).forEach((c) => removeTemp(c.replies || []));
        };
        removeTemp(post.comments);
        refreshPreview(parentId ? threadRootId(parentId) : null);
        notifyPreview(parentId ? 'Unable to add your reply. Please try again.' : 'Unable to add your comment. Please try again.', 'error');
    } finally {
        sending = false;
    }
}

function findComment(comments, id) {
    for (const c of comments || []) {
        if (Number(c.id) === Number(id)) return c;
        const nested = findComment(c.replies || [], id);
        if (nested) return nested;
    }
    return null;
}

async function openViewer(target, commentId = null) {
    const modal = document.getElementById('cpReactionViewer');
    const list = document.getElementById('cpViewerList');
    modal.hidden = false;
    if (target === 'post' && post) {
        viewerState = {
            data: {
                reactors: [],
                reaction_counts: post.reaction_counts || {},
                count: Number(post.likes || 0),
            },
            filter: 'all',
        };
        renderViewer('all');
    } else if (list) {
        list.innerHTML = '';
    }
    const url = target === 'comment'
        ? `/api/community-feed/${post.id}/comments/${commentId}/reactions`
        : `/api/community-feed/${post.id}/likes`;
    try {
        viewerState = { data: await apiFetch(url), filter: 'all' };
        renderViewer('all');
    } catch (err) {
        list.innerHTML = `<p class="cp-viewer-empty">${escapeHtml(err?.message || 'Unable to load reactions.')}</p>`;
    }
}

function renderViewer(filter) {
    viewerState.filter = filter;
    const data = viewerState.data || { reactors: [], reaction_counts: {}, count: 0 };
    const counts = data.reaction_counts || {};
    const tabs = document.getElementById('cpViewerTabs');
    const items = [['all', `All ${formatCount(data.count || 0)}`]].concat(
        Object.entries(REACTION_EMOJI).filter(([t]) => Number(counts[t] || 0) > 0)
            .map(([t, e]) => [t, `${e} ${formatCount(counts[t])}`])
    );
    tabs.innerHTML = items.map(([t, label]) =>
        `<button type="button" class="cp-viewer-tab${filter === t ? ' is-active' : ''}" data-type="${t}">${label}</button>`
    ).join('');
    tabs.querySelectorAll('.cp-viewer-tab').forEach((tab) => {
        tab.addEventListener('click', () => renderViewer(tab.dataset.type));
    });
    const rows = (data.reactors || []).filter((r) => filter === 'all' || r.reaction_type === filter);
    const list = document.getElementById('cpViewerList');
    list.innerHTML = rows.length
        ? rows.map((r) => `<div class="cp-viewer-row"><div class="cp-viewer-avatar-wrap"><img src="${escapeHtml(r.avatar_url)}" alt=""><span class="cp-viewer-emoji">${REACTION_EMOJI[r.reaction_type] || ''}</span></div><span class="cp-viewer-name">${escapeHtml(r.name)}</span></div>`).join('')
        : '<p class="cp-viewer-empty">No reactions yet.</p>';
}

function bindPage() {
    const input = document.getElementById('cpCommentInput');
    const send = document.getElementById('cpSendBtn');
    const updateSend = () => { send.disabled = !input.value.trim(); };
    input?.addEventListener('input', updateSend);
    input?.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter' || e.repeat || e.isComposing) return;
        e.preventDefault();
        const text = input.value.trim();
        if (!text) return;
        if (text.length > COMMENT_MAX_CHARS) {
            notifyPreview(COMMENT_LIMIT_MSG, 'error');
            return;
        }
        input.value = '';
        updateSend();
        submitComment(text);
    });
    send?.addEventListener('click', () => {
        const text = input.value.trim();
        if (!text) return;
        if (text.length > COMMENT_MAX_CHARS) {
            notifyPreview(COMMENT_LIMIT_MSG, 'error');
            return;
        }
        input.value = '';
        updateSend();
        submitComment(text);
    });

    document.getElementById('cpSortBtn')?.addEventListener('click', (e) => {
        e.stopPropagation();
        document.getElementById('cpSortMenu')?.classList.toggle('open');
    });
    document.querySelectorAll('#cpSortMenu button').forEach((btn) => {
        btn.addEventListener('click', () => {
            sortMode = btn.dataset.sort;
            const labels = { relevant: 'Most relevant', newest: 'Newest', oldest: 'Oldest' };
            document.getElementById('cpSortBtn').textContent = labels[sortMode] || 'Most relevant';
            document.getElementById('cpSortMenu').classList.remove('open');
            renderComments();
        });
    });

    document.getElementById('cpComments')?.addEventListener('click', (e) => {
        const reply = e.target.closest('[data-reply]');
        if (reply) {
            document.querySelectorAll('.cp-reply-box.open').forEach((box) => {
                if (box.id !== `cp-reply-${reply.dataset.reply}`) box.classList.remove('open');
            });
            const box = document.getElementById(`cp-reply-${reply.dataset.reply}`);
            if (box) {
                box.classList.toggle('open');
                box.querySelector('input')?.focus();
                keepInScroller(box);
            }
            return;
        }
        const toggle = e.target.closest('[data-toggle-replies]');
        if (toggle) {
            const id = Number(toggle.dataset.toggleReplies);
            const el = document.getElementById(`cp-replies-${id}`);
            if (!el) return;
            const open = el.hidden;
            el.hidden = !open;
            if (open) expandedReplies.add(id);
            else expandedReplies.delete(id);
            toggle.classList.toggle('is-open', open);
            const n = Number(toggle.dataset.count || 0);
            toggle.innerHTML = `${CHEVRON_SVG} ${open ? 'Hide replies' : `View ${n} ${n === 1 ? 'reply' : 'replies'}`}`;
            return;
        }
        const react = e.target.closest('.cp-bubble-react');
        if (react) {
            openViewer('comment', react.dataset.commentId);
            return;
        }
        const opt = e.target.closest('[data-opt]');
        if (opt) {
            e.stopPropagation();
            document.querySelectorAll('.cp-options-menu.open').forEach((m) => m.classList.remove('open'));
            document.getElementById(`cp-opt-${opt.dataset.opt}`)?.classList.toggle('open');
            return;
        }
        const edit = e.target.closest('[data-edit]');
        if (edit) {
            if (typeof window.editComment === 'function') {
                window.editComment(post.id, edit.dataset.edit);
            }
            return;
        }
        const del = e.target.closest('[data-del]');
        if (del) {
            if (typeof window.deleteComment === 'function') {
                window.deleteComment(post.id, del.dataset.del);
            }
            return;
        }
        const replySend = e.target.closest('[data-reply-send]');
        if (replySend) {
            const id = replySend.dataset.replySend;
            const field = document.querySelector(`[data-reply-input="${id}"]`);
            const text = field?.value.trim();
            if (!text) return;
            if (text.length > COMMENT_MAX_CHARS) {
                notifyPreview(COMMENT_LIMIT_MSG, 'error');
                return;
            }
            field.value = '';
            submitComment(text, Number(id));
        }
    });

    document.getElementById('cpComments')?.addEventListener('keydown', (e) => {
        const field = e.target.closest('[data-reply-input]');
        if (!field || e.key !== 'Enter' || e.repeat) return;
        e.preventDefault();
        const text = field.value.trim();
        if (!text) return;
        if (text.length > COMMENT_MAX_CHARS) {
            notifyPreview(COMMENT_LIMIT_MSG, 'error');
            return;
        }
        field.value = '';
        submitComment(text, Number(field.dataset.replyInput));
    });

    document.getElementById('cpViewerOverlay')?.addEventListener('click', () => {
        document.getElementById('cpReactionViewer').hidden = true;
    });
    document.getElementById('cpViewerClose')?.addEventListener('click', () => {
        document.getElementById('cpReactionViewer').hidden = true;
    });
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.cp-reaction-wrap')) closePickers();
        if (!e.target.closest('.cp-options')) {
            document.querySelectorAll('.cp-options-menu.open').forEach((m) => m.classList.remove('open'));
        }
        if (!e.target.closest('.cp-sort')) {
            document.getElementById('cpSortMenu')?.classList.remove('open');
        }
    });
    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape') return;
        if (document.getElementById('editCommentModal')?.classList.contains('active')) return;
        if (document.getElementById('deleteCommentModal')?.classList.contains('active')) return;
        const viewer = document.getElementById('cpReactionViewer');
        if (viewer && !viewer.hidden) {
            viewer.hidden = true;
            return;
        }
        closeCommentPreview();
    });

    document.getElementById('cpClose')?.addEventListener('click', (e) => {
        e.preventDefault();
        closeCommentPreview();
    });
    document.getElementById('commentPreviewShell')?.addEventListener('click', (e) => {
        if (e.target.id === 'commentPreviewShell') closeCommentPreview();
    });
}

function removeComment(comments, id) {
    return (comments || []).filter((c) => Number(c.id) !== id).map((c) => ({
        ...c,
        replies: removeComment(c.replies || [], id),
    }));
}

function commentsPath(id) {
    const template = window.CommunityFeedConfig?.commentsPageUrl;
    if (template && String(template).includes('__ID__')) {
        return String(template).replace('__ID__', String(id));
    }
    return `/community-feed/comments/${id}`;
}

function feedPath() {
    return cfg().feedUrl || '/community-feed';
}

function pathOf(url) {
    try {
        return new URL(url, window.location.origin).pathname;
    } catch (_) {
        return url;
    }
}

function syncCommentsUrl(id) {
    const next = id ? commentsPath(id) : feedPath();
    if (pathOf(window.location.href) === pathOf(next)) return;
    syncingUrl = true;
    history.pushState({ commentPreview: id || null }, '', next);
    syncingUrl = false;
}

function openCommentPreview(nextPost, { skipUrl } = {}) {
    if (!nextPost) return;
    if (!post || Number(post.id) !== Number(nextPost.id)) {
        expandedReplies = new Set();
    }
    post = nextPost;
    const shell = document.getElementById('commentPreviewShell');
    if (!shell) return;
    shell.hidden = false;
    shell.classList.add('is-open');
    document.body.style.overflow = 'hidden';
    if (!pageBound) {
        bindPage();
        pageBound = true;
    }
    renderPost();
    renderComments();
    document.getElementById('cpScroll')?.scrollTo({ top: 0 });
    if (!skipUrl) syncCommentsUrl(nextPost.id);
}

function closeCommentPreview({ skipUrl } = {}) {
    const shell = document.getElementById('commentPreviewShell');
    if (shell) {
        shell.hidden = true;
        shell.classList.remove('is-open');
    }
    document.getElementById('cpReactionViewer')?.setAttribute('hidden', '');
    document.body.style.overflow = '';
    if (!skipUrl) syncCommentsUrl(null);
}

window.openCommentPreview = openCommentPreview;
window.closeCommentPreview = closeCommentPreview;

window.addEventListener('popstate', () => {
    if (syncingUrl) return;
    const match = window.location.pathname.match(/\/community-feed\/comments\/(\d+)\/?$/)
        || window.location.pathname.match(/\/community-feed\/(\d+)\/comments\/?$/);
    if (match) {
        const id = Number(match[1]);
        if (post && Number(post.id) === id) {
            openCommentPreview(post, { skipUrl: true });
            return;
        }
        fetch(`/api/community-feed/${id}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        }).then((res) => res.ok ? res.json() : null)
            .then((data) => { if (data) openCommentPreview(data, { skipUrl: true }); })
            .catch(() => {});
        return;
    }
    closeCommentPreview({ skipUrl: true });
});

document.addEventListener('community-feed:comment-updated', (event) => {
    const updated = event.detail;
    if (!post || !updated?.id) return;
    const found = findComment(post.comments, updated.id);
    if (found) found.body = updated.body;
});

document.addEventListener('community-feed:comment-deleted', (event) => {
    const commentId = Number(event.detail?.commentId);
    if (!post || !commentId) return;
    post.comments = removeComment(post.comments, commentId);
    renderPost();
    renderComments();
});

document.addEventListener('DOMContentLoaded', () => {
    if (post) openCommentPreview(post, { skipUrl: true });
});
})();
