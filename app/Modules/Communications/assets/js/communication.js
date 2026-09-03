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
            '.comms-delete-modal{position:fixed;inset:0;z-index:100050;display:flex;align-items:center;justify-content:center;padding:1rem;}' +
            '.comms-delete-modal[hidden]{display:none!important;}' +
            '.comms-delete-modal-backdrop{position:absolute;inset:0;background:rgba(15,23,42,.45);}' +
            '.comms-delete-modal-panel{position:relative;z-index:1;width:min(100%,380px);background:#fff;border-radius:16px;box-shadow:0 20px 50px rgba(15,23,42,.28);padding:1.25rem 1.2rem 1rem;}' +
            '.comms-delete-modal-title{margin:0 0 .4rem;font-size:1.15rem;font-weight:700;color:#0f172a;letter-spacing:-.01em;}' +
            '.comms-delete-modal-text{margin:0 0 1.1rem;font-size:.9rem;color:#64748b;line-height:1.45;}' +
            '.comms-delete-modal-actions{display:flex;flex-direction:column;gap:8px;}' +
            '.comms-delete-modal-actions button{border:0;border-radius:12px;padding:.75rem .9rem;font-size:.92rem;font-weight:650;cursor:pointer;}' +
            '.comms-delete-modal-btn-all{background:#fee2e2;color:#b42318;text-align:left;}' +
            '.comms-delete-modal-btn-me{background:#f1f5f9;color:#0f172a;text-align:left;}' +
            '.comms-delete-modal-btn-cancel{background:transparent;color:#64748b;text-align:center;font-weight:560;}' +
            '.comms-delete-modal-actions button:hover,.comms-delete-modal-actions button:focus-visible{filter:brightness(.97);outline:2px solid rgba(11,92,171,.28);outline-offset:1px;}' +
            '.comms-delete-modal-btn-cancel:hover,.comms-delete-modal-btn-cancel:focus-visible{background:#f8fafc;outline:none;}';
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

    window.Comms = {
        csrfToken: csrfToken,
        escapeHtml: escapeHtml,
        defaultAvatar: defaultAvatar,
        formatTime: formatTime,
        route: route,
        api: api,
        showToast: showToast,
        playReactionSound: playReactionSound,
        ensureReactionAudio: ensureReactionAudio,
        openDeleteMessageDialog: openDeleteMessageDialog
    };
})(window);
