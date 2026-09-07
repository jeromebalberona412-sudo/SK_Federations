/**
 * Shared Communications helpers (Federations / Officials / Kabataan).
 */
(function (window) {
    'use strict';

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, function (ch) {
            return ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            })[ch];
        });
    }

    function defaultAvatar(name) {
        var label = encodeURIComponent(String(name || 'User').slice(0, 40));
        return 'https://ui-avatars.com/api/?name=' + label + '&background=0B5CAB&color=fff';
    }

    function formatTime(iso) {
        if (!iso) return '';
        var d = new Date(iso);
        if (Number.isNaN(d.getTime())) return '';
        var now = new Date();
        var sameDay = d.toDateString() === now.toDateString();
        if (sameDay) {
            return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }
        return d.toLocaleDateString([], { month: 'short', day: 'numeric' });
    }

    function formatHeaderMsgCount(count) {
        var n = Math.max(0, parseInt(count, 10) || 0);
        return n >= 9 ? '9+' : String(n);
    }

    function route(template, id) {
        return String(template || '').replace('__ID__', String(id));
    }

    var inflight = Object.create(null);

    function api(url, options) {
        options = options || {};
        var method = (options.method || 'GET').toUpperCase();
        var isFormData = typeof FormData !== 'undefined' && options.body instanceof FormData;
        var key = method + ':' + url + ':' + (isFormData ? '[formdata]' : (options.body || ''));
        if (options.dedupe !== false && inflight[key]) {
            return inflight[key];
        }

        var headers = Object.assign({
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken()
        }, options.headers || {});

        if (options.body && !isFormData && !headers['Content-Type']) {
            headers['Content-Type'] = 'application/json';
        }
        if (isFormData && headers['Content-Type']) {
            delete headers['Content-Type'];
        }

        var promise = fetch(url, {
            method: method,
            headers: headers,
            credentials: 'same-origin',
            body: options.body || undefined
        }).then(async function (res) {
            var data = null;
            var text = await res.text();
            try { data = text ? JSON.parse(text) : null; } catch (e) { data = { message: text }; }
            if (!res.ok) {
                var msg = (data && (data.message || data.error)) || 'Request failed';
                if (data && data.errors) {
                    var first = Object.keys(data.errors)[0];
                    if (first && data.errors[first] && data.errors[first][0]) {
                        msg = data.errors[first][0];
                    }
                }
                var err = new Error(msg);
                err.status = res.status;
                err.data = data;
                throw err;
            }
            return data;
        }).finally(function () {
            delete inflight[key];
        });

        if (options.dedupe !== false) {
            inflight[key] = promise;
        }
        return promise;
    }

    function showToast(message, type) {
        type = type || 'info';
        if (typeof window.showToast === 'function' && window.showToast !== showToast) {
            window.showToast(message, type);
            return;
        }
        var existing = document.getElementById('commsToast');
        if (existing) existing.remove();
        var el = document.createElement('div');
        el.id = 'commsToast';
        el.setAttribute('role', 'status');
        el.textContent = message;
        el.style.cssText = 'position:fixed;z-index:3000;right:1rem;bottom:1rem;background:' +
            (type === 'error' ? '#b42318' : '#0b5cab') +
            ';color:#fff;padding:0.75rem 1rem;border-radius:8px;max-width:min(90vw,360px);box-shadow:0 8px 24px rgba(0,0,0,.18);';
        document.body.appendChild(el);
        setTimeout(function () { el.remove(); }, 3200);
    }

    var REACTION_SOUND_URL = '/sounds/reactions_ux.mp3';
    var reactionAudio = null;
    var reactionAudioPool = [];

    function makeReactionAudio() {
        var audio = new Audio(REACTION_SOUND_URL);
        audio.preload = 'auto';
        audio.volume = 0.75;
        try { audio.load(); } catch (e) {}
        return audio;
    }

    function ensureReactionAudio() {
        if (!reactionAudio) {
            reactionAudio = makeReactionAudio();
            // Warm a small pool so rapid reacts never wait on load.
            while (reactionAudioPool.length < 3) {
                reactionAudioPool.push(makeReactionAudio());
            }
        }
        return reactionAudio;
    }

    function playReactionSound() {
        try {
            ensureReactionAudio();
            var oneShot = reactionAudioPool.pop() || makeReactionAudio();
            oneShot.volume = 0.75;
            try { oneShot.currentTime = 0; } catch (e) {}
            var playPromise = oneShot.play();
            var recycle = function () {
                try { oneShot.pause(); } catch (e) {}
                try { oneShot.currentTime = 0; } catch (e) {}
                if (reactionAudioPool.length < 4) reactionAudioPool.push(oneShot);
            };
            oneShot.onended = recycle;
            if (playPromise && typeof playPromise.then === 'function') {
                playPromise.catch(function () {
                    // Fallback if autoplay policy blocks the pooled clip.
                    try {
                        var fallback = makeReactionAudio();
                        fallback.play().catch(function () {});
                    } catch (e2) {}
                    recycle();
                });
            }
        } catch (e) {}
    }

    ensureReactionAudio();
    document.addEventListener('pointerdown', ensureReactionAudio, { once: true, capture: true });
    document.addEventListener('touchstart', ensureReactionAudio, { once: true, capture: true, passive: true });

    var deleteModalEl = null;
    var deleteModalResolve = null;

    function ensureDeleteModalStyles() {
        if (document.getElementById('commsDeleteModalStyles')) return;
        var style = document.createElement('style');
        style.id = 'commsDeleteModalStyles';
        style.textContent =
            '.comms-delete-modal{position:fixed;inset:0;z-index:100050;display:flex;align-items:center;justify-content:center;padding:max(1rem,env(safe-area-inset-top)) 1rem max(1rem,env(safe-area-inset-bottom));}' +
            '.comms-delete-modal[hidden]{display:none!important;}' +
            '.comms-delete-modal-backdrop{position:absolute;inset:0;background:rgba(15,23,42,.5);backdrop-filter:blur(3px);-webkit-backdrop-filter:blur(3px);}' +
            '.comms-delete-modal-panel{position:relative;z-index:1;width:min(100%,380px);max-height:min(90dvh,520px);overflow:auto;background:#fff;border-radius:16px;box-shadow:0 20px 50px rgba(15,23,42,.28);padding:1.25rem 1.15rem 1rem;border:1px solid rgba(15,23,42,.08);}' +
            '.comms-delete-modal-x{position:absolute;top:.65rem;right:.65rem;width:36px;height:36px;border:0;border-radius:999px;background:#f1f5f9;color:#334155;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;}' +
            '.comms-delete-modal-x:hover,.comms-delete-modal-x:focus-visible{background:#e2e8f0;outline:2px solid rgba(11,92,171,.25);outline-offset:1px;}' +
            '.comms-delete-modal-title{margin:0 2.25rem .4rem 0;font-size:1.15rem;font-weight:800;color:#0f172a;letter-spacing:-.02em;line-height:1.25;}' +
            '.comms-delete-modal-text{margin:0 0 1rem;font-size:.9rem;color:#64748b;line-height:1.45;}' +
            '.comms-delete-modal-actions{display:flex;flex-direction:column;gap:8px;}' +
            '.comms-delete-modal-actions button{border:0;border-radius:12px;padding:.8rem .9rem;font-size:.93rem;font-weight:650;cursor:pointer;font-family:inherit;width:100%;}' +
            '.comms-delete-modal-btn-all{background:#fee2e2;color:#b42318;text-align:left;}' +
            '.comms-delete-modal-btn-me{background:#f1f5f9;color:#0f172a;text-align:left;}' +
            '.comms-delete-modal-btn-cancel{background:#fff;color:#475569;text-align:center;font-weight:600;border:1px solid #e2e8f0!important;margin-top:2px;}' +
            '.comms-delete-modal-btn-all:hover,.comms-delete-modal-btn-all:focus-visible{background:#fecaca;outline:2px solid rgba(180,35,24,.25);outline-offset:1px;}' +
            '.comms-delete-modal-btn-me:hover,.comms-delete-modal-btn-me:focus-visible{background:#e2e8f0;outline:2px solid rgba(11,92,171,.28);outline-offset:1px;}' +
            '.comms-delete-modal-btn-cancel:hover,.comms-delete-modal-btn-cancel:focus-visible{background:#f8fafc;color:#0f172a;outline:2px solid rgba(100,116,139,.25);outline-offset:1px;}' +
            '@media (max-width:375px){.comms-delete-modal-panel{width:100%;border-radius:14px;padding:1.1rem 1rem .9rem;}}';
        document.head.appendChild(style);
    }

    function ensureDeleteModal() {
        ensureDeleteModalStyles();
        if (deleteModalEl) return deleteModalEl;
        deleteModalEl = document.createElement('div');
        deleteModalEl.id = 'commsDeleteModal';
        deleteModalEl.className = 'comms-delete-modal';
        deleteModalEl.hidden = true;
        deleteModalEl.setAttribute('role', 'dialog');
        deleteModalEl.setAttribute('aria-modal', 'true');
        deleteModalEl.setAttribute('aria-labelledby', 'commsDeleteModalTitle');
        deleteModalEl.innerHTML =
            '<div class="comms-delete-modal-backdrop" data-delete-cancel tabindex="-1"></div>' +
            '<div class="comms-delete-modal-panel">' +
            '<button type="button" class="comms-delete-modal-x" data-delete-cancel aria-label="Close">' +
            '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.25" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>' +
            '</button>' +
            '<h3 class="comms-delete-modal-title" id="commsDeleteModalTitle">Delete message?</h3>' +
            '<p class="comms-delete-modal-text" id="commsDeleteModalText">Choose who this message should be removed for.</p>' +
            '<div class="comms-delete-modal-actions">' +
            '<button type="button" class="comms-delete-modal-btn-all" id="commsDeleteModalAll" hidden>Delete for everyone</button>' +
            '<button type="button" class="comms-delete-modal-btn-me" id="commsDeleteModalMe">Delete for me</button>' +
            '<button type="button" class="comms-delete-modal-btn-cancel" data-delete-cancel>Cancel</button>' +
            '</div></div>';
        document.body.appendChild(deleteModalEl);

        deleteModalEl.addEventListener('click', function (e) {
            if (e.target.closest('[data-delete-cancel]')) {
                closeDeleteMessageDialog(null);
                return;
            }
            if (e.target.closest('#commsDeleteModalMe')) {
                closeDeleteMessageDialog('me');
                return;
            }
            if (e.target.closest('#commsDeleteModalAll')) {
                closeDeleteMessageDialog('all');
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && deleteModalEl && !deleteModalEl.hidden) {
                closeDeleteMessageDialog(null);
            }
        });

        return deleteModalEl;
    }

    function closeDeleteMessageDialog(result) {
        if (!deleteModalEl) return;
        deleteModalEl.hidden = true;
        var resolve = deleteModalResolve;
        deleteModalResolve = null;
        if (typeof resolve === 'function') resolve(result);
    }

    /**
     * @param {{ canDeleteAll?: boolean, title?: string, text?: string }} options
     * @returns {Promise<'me'|'all'|null>}
     */
    function openDeleteMessageDialog(options) {
        options = options || {};
        var modal = ensureDeleteModal();
        var title = document.getElementById('commsDeleteModalTitle');
        var text = document.getElementById('commsDeleteModalText');
        var allBtn = document.getElementById('commsDeleteModalAll');
        var meBtn = document.getElementById('commsDeleteModalMe');

        if (title) title.textContent = options.title || 'Delete message?';
        if (text) {
            text.textContent = options.canDeleteAll
                ? 'Choose who this message should be removed for.'
                : 'This message will only be removed from your chat.';
        }
        if (allBtn) allBtn.hidden = !options.canDeleteAll;
        if (meBtn) meBtn.textContent = 'Delete for me';

        if (deleteModalResolve) {
            deleteModalResolve(null);
            deleteModalResolve = null;
        }

        modal.hidden = false;
        window.setTimeout(function () {
            var focusBtn = options.canDeleteAll ? allBtn : meBtn;
            if (focusBtn) focusBtn.focus();
        }, 0);

        return new Promise(function (resolve) {
            deleteModalResolve = resolve;
        });
    }

    var THREAD_CACHE_PREFIX = 'comms:thread:';
    var reactionPeopleEl = null;
    var reactionPeoplePinned = false;
    var reactionPeopleHideTimer = null;

    function readCacheEntry(key) {
        if (window.SkLocalCache && typeof window.SkLocalCache.get === 'function') {
            var entry = window.SkLocalCache.get(key);
            return entry && Object.prototype.hasOwnProperty.call(entry, 'payload') ? entry.payload : null;
        }
        try {
            var raw = localStorage.getItem(key);
            if (!raw) return null;
            var parsed = JSON.parse(raw);
            if (parsed && typeof parsed === 'object' && Object.prototype.hasOwnProperty.call(parsed, 'payload')) {
                return parsed.payload;
            }
            return parsed;
        } catch (e) {
            return null;
        }
    }

    function writeCacheEntry(key, payload) {
        if (window.SkLocalCache && typeof window.SkLocalCache.set === 'function') {
            window.SkLocalCache.set(key, payload);
            return;
        }
        try {
            localStorage.setItem(key, JSON.stringify({ savedAt: Date.now(), payload: payload }));
        } catch (e) {}
    }

    function readThreadCache(conversationId) {
        if (!conversationId) return null;
        return readCacheEntry(THREAD_CACHE_PREFIX + String(conversationId));
    }

    function writeThreadCache(conversationId, payload) {
        if (!conversationId || payload == null) return;
        writeCacheEntry(THREAD_CACHE_PREFIX + String(conversationId), payload);
    }

    function cloneReactions(reactions) {
        return (Array.isArray(reactions) ? reactions : []).map(function (r) {
            return {
                emoji: r.emoji,
                count: Number(r.count || 0),
                mine: !!r.mine,
                users: Array.isArray(r.users) ? r.users.map(function (u) {
                    return {
                        id: Number(u.id || 0),
                        user_type: String(u.user_type || ''),
                        name: String(u.name || ''),
                        mine: !!u.mine
                    };
                }) : []
            };
        });
    }

    function selfReactor(ctx) {
        ctx = ctx || {};
        return {
            id: Number(ctx.currentUserId || 0),
            user_type: String(ctx.portalUserType || ''),
            name: 'You',
            mine: true
        };
    }

    function usersWithoutSelf(users) {
        return (users || []).filter(function (u) { return !u.mine; });
    }

    function optimisticToggleReactions(reactions, emoji, ctx) {
        var me = selfReactor(ctx);
        var list = cloneReactions(reactions).filter(function (r) { return r.count > 0; });
        var mineEntry = null;
        var target = null;
        list.forEach(function (r) {
            if (r.mine) mineEntry = r;
            if (r.emoji === emoji) target = r;
        });

        if (target && target.mine) {
            target.count -= 1;
            target.mine = false;
            target.users = usersWithoutSelf(target.users);
        } else {
            if (mineEntry && mineEntry !== target) {
                mineEntry.count -= 1;
                mineEntry.mine = false;
                mineEntry.users = usersWithoutSelf(mineEntry.users);
            }
            if (target) {
                target.count += 1;
                target.mine = true;
                target.users = [me].concat(usersWithoutSelf(target.users));
            } else {
                list.push({ emoji: emoji, count: 1, mine: true, users: [me] });
            }
        }

        return list.filter(function (r) { return Number(r.count) > 0; });
    }

    function reactorDisplayName(user) {
        if (!user) return 'Someone';
        if (user.mine) return 'You';
        var name = String(user.name || '').trim();
        return name !== '' ? name : 'Someone';
    }

    function ensureReactionPeopleStyles() {
        if (document.getElementById('commsReactionPeopleStyles')) return;
        var style = document.createElement('style');
        style.id = 'commsReactionPeopleStyles';
        style.textContent =
            '.comms-reaction-people{position:fixed;z-index:100100;min-width:148px;max-width:min(260px,calc(100vw - 16px));background:#1c1e21;color:#fff;border-radius:10px;padding:8px 10px;box-shadow:0 12px 28px rgba(15,23,42,.32);font-size:12px;line-height:1.35;}' +
            '.comms-reaction-people[hidden]{display:none!important;}' +
            '.comms-reaction-people-group{display:flex;gap:8px;align-items:flex-start;}' +
            '.comms-reaction-people-group+.comms-reaction-people-group{margin-top:8px;padding-top:8px;border-top:1px solid rgba(255,255,255,.12);}' +
            '.comms-reaction-people-emoji{font-size:1.05rem;line-height:1.2;flex:0 0 auto;}' +
            '.comms-reaction-people-list{margin:0;padding:0;list-style:none;}' +
            '.comms-reaction-people-list li{font-weight:600;word-break:break-word;}' +
            'button.comms-reaction-summary{cursor:pointer;font:inherit;color:inherit;}' +
            '@media (max-width:768px){.comms-reaction-people{max-width:calc(100vw - 24px);}}';
        document.head.appendChild(style);
    }

    function ensureReactionPeopleEl() {
        ensureReactionPeopleStyles();
        if (reactionPeopleEl) return reactionPeopleEl;
        reactionPeopleEl = document.createElement('div');
        reactionPeopleEl.id = 'commsReactionPeople';
        reactionPeopleEl.className = 'comms-reaction-people';
        reactionPeopleEl.hidden = true;
        reactionPeopleEl.setAttribute('role', 'tooltip');
        document.body.appendChild(reactionPeopleEl);
        reactionPeopleEl.addEventListener('pointerenter', function () {
            if (reactionPeopleHideTimer) {
                window.clearTimeout(reactionPeopleHideTimer);
                reactionPeopleHideTimer = null;
            }
        });
        reactionPeopleEl.addEventListener('pointerleave', function () {
            if (!reactionPeoplePinned) hideReactionPeople();
        });
        document.addEventListener('click', function (e) {
            if (!reactionPeopleEl || reactionPeopleEl.hidden) return;
            if (e.target.closest('#commsReactionPeople') || e.target.closest('[data-react-people]')) return;
            hideReactionPeople();
        });
        return reactionPeopleEl;
    }

    function hideReactionPeople() {
        reactionPeoplePinned = false;
        if (reactionPeopleHideTimer) {
            window.clearTimeout(reactionPeopleHideTimer);
            reactionPeopleHideTimer = null;
        }
        if (reactionPeopleEl) reactionPeopleEl.hidden = true;
    }

    function positionReactionPeople(anchor) {
        var el = ensureReactionPeopleEl();
        if (!anchor) return;
        var rect = anchor.getBoundingClientRect();
        var pad = 8;
        var w = el.offsetWidth || 180;
        var h = el.offsetHeight || 48;
        var top = rect.top - h - 8;
        var left = rect.left + (rect.width / 2) - (w / 2);
        if (top < pad) top = rect.bottom + 8;
        left = Math.max(pad, Math.min(left, window.innerWidth - w - pad));
        top = Math.max(pad, Math.min(top, window.innerHeight - h - pad));
        el.style.top = top + 'px';
        el.style.left = left + 'px';
    }

    function renderReactionPeopleHtml(reactions, filterEmoji) {
        var items = cloneReactions(reactions).filter(function (r) {
            if (Number(r.count || 0) <= 0) return false;
            if (filterEmoji && r.emoji !== filterEmoji) return false;
            return true;
        });
        if (!items.length) {
            return '<div class="comms-reaction-people-group"><ul class="comms-reaction-people-list"><li>No reactions yet</li></ul></div>';
        }
        return items.map(function (r) {
            var names = (r.users && r.users.length)
                ? r.users.map(reactorDisplayName)
                : (r.mine ? ['You'] : []);
            if (!names.length) {
                names = [String(r.count) + (Number(r.count) === 1 ? ' reaction' : ' reactions')];
            }
            return '<div class="comms-reaction-people-group">' +
                '<span class="comms-reaction-people-emoji">' + escapeHtml(r.emoji) + '</span>' +
                '<ul class="comms-reaction-people-list">' +
                names.map(function (n) { return '<li>' + escapeHtml(n) + '</li>'; }).join('') +
                '</ul></div>';
        }).join('');
    }

    function showReactionPeople(anchor, reactions, options) {
        options = options || {};
        if (!anchor) return;
        var el = ensureReactionPeopleEl();
        if (reactionPeopleHideTimer) {
            window.clearTimeout(reactionPeopleHideTimer);
            reactionPeopleHideTimer = null;
        }
        reactionPeoplePinned = !!options.pinned;
        el.innerHTML = renderReactionPeopleHtml(reactions, options.emoji || '');
        el.hidden = false;
        positionReactionPeople(anchor);
        requestAnimationFrame(function () { positionReactionPeople(anchor); });
    }

    function toggleReactionPeople(anchor, reactions, options) {
        options = options || {};
        var el = ensureReactionPeopleEl();
        if (!el.hidden && reactionPeoplePinned && options.pinned) {
            hideReactionPeople();
            return;
        }
        showReactionPeople(anchor, reactions, Object.assign({}, options, { pinned: true }));
    }

    function scheduleHideReactionPeople() {
        if (reactionPeoplePinned) return;
        if (reactionPeopleHideTimer) window.clearTimeout(reactionPeopleHideTimer);
        reactionPeopleHideTimer = window.setTimeout(hideReactionPeople, 160);
    }

    window.Comms = {
        csrfToken: csrfToken,
        escapeHtml: escapeHtml,
        defaultAvatar: defaultAvatar,
        formatTime: formatTime,
        formatHeaderMsgCount: formatHeaderMsgCount,
        route: route,
        api: api,
        showToast: showToast,
        playReactionSound: playReactionSound,
        ensureReactionAudio: ensureReactionAudio,
        openDeleteMessageDialog: openDeleteMessageDialog,
        readCacheEntry: readCacheEntry,
        writeCacheEntry: writeCacheEntry,
        readThreadCache: readThreadCache,
        writeThreadCache: writeThreadCache,
        cloneReactions: cloneReactions,
        optimisticToggleReactions: optimisticToggleReactions,
        showReactionPeople: showReactionPeople,
        toggleReactionPeople: toggleReactionPeople,
        hideReactionPeople: hideReactionPeople,
        scheduleHideReactionPeople: scheduleHideReactionPeople
    };
})(window);
