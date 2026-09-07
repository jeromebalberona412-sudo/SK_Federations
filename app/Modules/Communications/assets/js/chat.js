/**
 * Messenger UI: conversation list, thread, send/read, user search.
 */
(function () {
    'use strict';

    var root = document.getElementById('commsApp');
    if (!root || !window.Comms) return;

    var routes = {};
    try { routes = JSON.parse(root.dataset.routes || '{}'); } catch (e) { routes = {}; }

    var state = {
        currentUserId: Number(root.dataset.currentUserId || 0),
        portalUserType: root.dataset.portalUserType || '',
        conversations: [],
        activeId: null,
        activeConversation: null,
        messages: [],
        sendInFlight: false,
        searchTimer: null,
        filterTimer: null,
        loadingOlder: false,
        listMode: 'conversations',
        directoryFilter: 'officials',
        barangayId: '',
        reactionEmojis: ['👍', '❤️', '😆', '😮', '😢', '🙏'],
        openReactionPickerId: null,
        reactInFlight: {},
        reactSkipRealtime: {},
        reactSeq: {},
        pendingAttachments: [],
        pendingPreviewUrls: [],
        editingId: null,
        msgChangeSkipToast: {},
        maxTotalBytes: 25 * 1024 * 1024,
        maxImageCount: 100,
        maxFileCount: 10,
        // Local message cache: conversationId (string) → { messages, reactionEmojis, ts }
        messageCache: {},
        peerProfileRefreshTimer: null
    };

    var els = {
        sidebar: document.getElementById('commsSidebar'),
        convList: document.getElementById('commsConvList'),
        directoryList: document.getElementById('commsDirectoryList'),
        convEmpty: document.getElementById('commsConvEmpty'),
        threadEmpty: document.getElementById('commsThreadEmpty'),
        threadActive: document.getElementById('commsThreadActive'),
        messages: document.getElementById('commsMessages'),
        composer: document.getElementById('commsComposer'),
        input: document.getElementById('commsMessageInput'),
        sendBtn: document.getElementById('commsSendBtn'),
        userSearch: document.getElementById('commsConvFilter'),
        searchResults: document.getElementById('commsSearchResults'),
        convFilter: document.getElementById('commsConvFilter'),
        peerName: document.getElementById('commsPeerName'),
        peerMeta: document.getElementById('commsPeerMeta'),
        peerAvatar: document.getElementById('commsPeerAvatar'),
        peerStatusDot: document.getElementById('commsPeerStatusDot'),
        backBtn: document.getElementById('commsBackBtn'),
        typing: document.getElementById('commsTyping'),
        voiceBtn: document.getElementById('commsVoiceBtn'),
        videoBtn: document.getElementById('commsVideoBtn'),
        filterBar: document.getElementById('commsFilterBar'),
        barangayFilter: document.getElementById('commsBarangayFilter'),
        attachBtn: document.getElementById('commsAttachBtn'),
        photoInput: document.getElementById('commsPhotoInput'),
        fileInput: document.getElementById('commsFileInput'),
        attachPreview: document.getElementById('commsAttachPreview'),
        attachPreviewInner: document.getElementById('commsAttachPreviewInner'),
        attachClear: document.getElementById('commsAttachClear'),
        attachMenu: document.getElementById('commsAttachMenu'),
        pickPhotosBtn: document.getElementById('commsPickPhotosBtn'),
        pickFilesBtn: document.getElementById('commsPickFilesBtn'),
        uploadProgress: document.getElementById('commsUploadProgress'),
        uploadProgressBar: document.getElementById('commsUploadProgressBar')
    };

    function setThreadOpen(open) {
        root.classList.toggle('is-thread-open', !!open);
    }

    function renderConversations(filter) {
        var q = String(filter || '').trim().toLowerCase();
        var items = state.conversations;
        if (q) {
            items = items.filter(function (c) {
                return String((c.other_user && c.other_user.name) || '').toLowerCase().indexOf(q) !== -1;
            });
        }

        if (!items.length) {
            els.convList.innerHTML = '';
            els.convEmpty.hidden = false;
            return;
        }
        els.convEmpty.hidden = true;
        els.convList.innerHTML = items.map(function (c) {
            var peer = c.other_user || {};
            var lm = c.last_message;
            var preview = 'No messages yet';
            if (lm) {
                if (lm.message_type === 'call' || lm.message_type === 'system') {
                    preview = lm.body || 'Call update';
                } else if (lm.body) {
                    preview = lm.body;
                    // Append attachment indicator if message also has attachments
                    if (lm.message_type === 'image') preview = '📷 ' + lm.body;
                    else if (lm.message_type === 'file') preview = '📎 ' + lm.body;
                } else if (lm.message_type === 'image') {
                    preview = '📷 Photo';
                } else if (lm.message_type === 'file') {
                    preview = '📎 File';
                } else {
                    preview = lm.body || 'No messages yet';
                }
            }
            var unread = Number(c.unread_count || 0);
            var active = Number(c.id) === Number(state.activeId) ? ' is-active' : '';
            var avatar = resolvePeerAvatar(peer);
            var online = String(peer.online_status || '').toLowerCase() === 'online' ? 'online' : 'offline';
            return '<button type="button" class="comms-conv-item' + active + '" data-id="' + c.id + '"' +
                ' data-peer-name="' + Comms.escapeHtml(peer.name || 'User') + '"' +
                ' data-peer-avatar="' + Comms.escapeHtml(avatar) + '"' +
                ' data-peer-status="' + online + '"' +
                ' role="listitem">' +
                '<img class="comms-avatar" src="' + Comms.escapeHtml(avatar) + '" alt="" onerror="this.onerror=null;this.src=\'' + Comms.escapeHtml(Comms.defaultAvatar(peer.name)) + '\'">' +
                '<div class="comms-conv-main">' +
                '<div class="comms-conv-name">' + Comms.escapeHtml(peer.name || 'User') + '</div>' +
                '<div class="comms-conv-preview">' + Comms.escapeHtml(preview) + '</div>' +
                '</div>' +
                '<div class="comms-conv-meta">' +
                '<span class="comms-conv-time">' + Comms.escapeHtml(Comms.formatTime(c.updated_at)) + '</span>' +
                (unread > 0 ? '<span class="comms-unread">' + (unread > 99 ? '99+' : unread) + '</span>' : '') +
                '</div></button>';
        }).join('');
    }

    function callBubbleClass(body) {
        var text = String(body || '').toLowerCase();
        if (text.indexOf('ended') !== -1) return 'comms-bubble--call comms-bubble--call-ended';
        if (text.indexOf('missed') !== -1) return 'comms-bubble--call comms-bubble--call-missed';
        if (text.indexOf('declined') !== -1) return 'comms-bubble--call comms-bubble--call-declined';
        if (text.indexOf('cancelled') !== -1 || text.indexOf('canceled') !== -1) return 'comms-bubble--call comms-bubble--call-cancelled';
        if (text.indexOf('answered') !== -1) return 'comms-bubble--call comms-bubble--call-answered';
        if (text.indexOf('started') !== -1 || text.indexOf('ringing') !== -1) return 'comms-bubble--call comms-bubble--call-started';
        return 'comms-bubble--call';
    }

    var callPhoneIcon = '<svg class="comms-call-icon" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.79 19.79 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.81.36 1.6.68 2.35a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.75.32 1.54.55 2.35.68A2 2 0 0 1 22 16.92z"/></svg>';

    function renderCallBubble(m) {
        return '<div class="comms-bubble-wrap ' + (m.mine ? 'is-mine' : 'is-theirs') + ' is-call" data-id="' + m.id + '">' +
            '<div class="comms-bubble-row">' +
            '<div class="' + callBubbleClass(m.body) + '" role="status">' +
            callPhoneIcon +
            '<span class="comms-call-text">' + Comms.escapeHtml(m.body) + '</span>' +
            '<span class="comms-bubble-time">' + Comms.escapeHtml(Comms.formatTime(m.created_at)) + '</span>' +
            '</div>' +
            renderMessageActions(m, false) +
            '</div></div>';
    }

    function renderReactionChips(reactions, messageId) {
        var items = Array.isArray(reactions)
            ? reactions.filter(function (r) { return Number(r.count || 0) > 0; })
            : [];
        if (!items.length) return '';

        var total = 0;
        var anyMine = false;
        items.forEach(function (r) {
            total += Number(r.count || 0);
            if (r.mine) anyMine = true;
        });

        // Messenger-style: stacked emojis in one pill + single total when 2+.
        return '<div class="comms-reaction-chips" role="group" aria-label="Reactions">' +
            '<button type="button" class="comms-reaction-summary' + (anyMine ? ' is-mine' : '') + '"' +
            ' data-react-people="' + Number(messageId || 0) + '"' +
            ' aria-haspopup="true" aria-label="See who reacted">' +
            '<span class="comms-reaction-stack">' +
            items.map(function (r) {
                return '<span class="comms-reaction-stack-btn' + (r.mine ? ' is-mine' : '') + '"' +
                    ' data-react-people-emoji="' + Comms.escapeHtml(r.emoji) + '">' +
                    Comms.escapeHtml(r.emoji) +
                    '</span>';
            }).join('') +
            '</span>' +
            (total > 1 ? '<span class="comms-reaction-count" aria-hidden="true">' + total + '</span>' : '') +
            '</button></div>';
    }

    // Reaction picker is a fixed-position singleton on <body> to escape overflow:hidden clip contexts.
    var pickerEl = (function () {
        var el = document.createElement('div');
        el.className = 'comms-reaction-picker';
        el.setAttribute('role', 'menu');
        el.setAttribute('aria-label', 'Choose reaction');
        el.style.display = 'none';
        el.style.position = 'fixed';
        el.style.zIndex = '99999';
        document.body.appendChild(el);
        return el;
    }());

    function renderReactionPicker(messageId) {
        // Inline picker slot is no longer used; picker lives in pickerEl on body.
        return '';
    }

    function positionPicker(triggerBtn) {
        if (!triggerBtn) return;
        var rect = triggerBtn.getBoundingClientRect();
        var pickerW = pickerEl.offsetWidth || 240;
        var pickerH = pickerEl.offsetHeight || 48;
        var pad = 8;
        var vw = window.innerWidth;
        var vh = window.innerHeight;

        var top = rect.top - pickerH - 8;
        var left = rect.left + (rect.width / 2) - (pickerW / 2);

        if (top < pad) {
            top = rect.bottom + 8;
        }
        left = Math.max(pad, Math.min(left, vw - pickerW - pad));
        top = Math.max(pad, Math.min(top, vh - pickerH - pad));

        pickerEl.style.top = top + 'px';
        pickerEl.style.left = left + 'px';
    }

    function showPickerFor(messageId, triggerBtn) {
        state.openReactionPickerId = Number(messageId) || null;
        if (!state.openReactionPickerId) { hideGlobalPicker(); return; }
        pickerEl.setAttribute('data-react-picker', messageId);
        pickerEl.innerHTML = state.reactionEmojis.map(function (emoji) {
            return '<button type="button" class="comms-reaction-pick" data-react-emoji="' +
                Comms.escapeHtml(emoji) + '" role="menuitem">' +
                Comms.escapeHtml(emoji) + '</button>';
        }).join('');
        pickerEl.style.display = 'inline-flex';
        positionPicker(triggerBtn);
        requestAnimationFrame(function () {
            positionPicker(triggerBtn);
        });
    }

    function hideGlobalPicker() {
        state.openReactionPickerId = null;
        pickerEl.style.display = 'none';
        pickerEl.innerHTML = '';
    }

    var reactBtnIcon = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M8.5 14.5s1.5 2 3.5 2 3.5-2 3.5-2"/><circle cx="9" cy="10" r="1" fill="currentColor" stroke="none"/><circle cx="15" cy="10" r="1" fill="currentColor" stroke="none"/></svg>';

    function formatBytes(bytes) {
        var n = Number(bytes || 0);
        if (!n) return '';
        if (n < 1024) return n + ' B';
        if (n < 1024 * 1024) return (n / 1024).toFixed(1) + ' KB';
        return (n / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function fileExtLabel(name) {
        var parts = String(name || '').split('.');
        return parts.length > 1 ? parts.pop().toUpperCase() : 'FILE';
    }

    function fileKindClass(att) {
        var name = String((att && att.file_name) || '');
        var mime = String((att && att.mime_type) || '').toLowerCase();
        var ext = fileExtLabel(name);
        if (ext === 'PDF' || mime === 'application/pdf') return ' is-pdf';
        if (ext === 'DOC' || ext === 'DOCX' || mime.indexOf('msword') !== -1 || mime.indexOf('wordprocessingml') !== -1) {
            return ' is-word';
        }
        return '';
    }

    function renderAttachments(attachments) {
        var items = Array.isArray(attachments) ? attachments : [];
        if (!items.length) return '';
        return items.map(function (att) {
            if (att.file_type === 'image' || att.storage_provider === 'cloudinary') {
                var src = att.url || att.download_url;
                return '<button type="button" class="comms-bubble-image" data-lightbox-src="' + Comms.escapeHtml(src) + '">' +
                    '<img src="' + Comms.escapeHtml(src) + '" alt="' + Comms.escapeHtml(att.file_name || 'Image') + '" loading="lazy">' +
                    '</button>';
            }
            var ext = fileExtLabel(att.file_name);
            var kindClass = fileKindClass(att);
            var name = Comms.escapeHtml(att.file_name || 'Document');
            var size = Comms.escapeHtml(formatBytes(att.file_size));
            var dl = Comms.escapeHtml(att.download_url || '');
            var downloadName = Comms.escapeHtml(att.file_name || 'document');

            return '<a class="comms-bubble-file' + kindClass + '" href="' + dl + '" download="' + downloadName + '">' +
                '<span class="comms-bubble-file-ext">' + Comms.escapeHtml(ext) + '</span>' +
                '<span class="comms-bubble-file-meta">' +
                '<span class="comms-bubble-file-name">' + name + '</span>' +
                (size ? '<span class="comms-bubble-file-size">' + size + '</span>' : '') +
                '</span></a>';
        }).join('');
    }

    function clearPendingAttachment() {
        state.pendingPreviewUrls.forEach(function (url) {
            try { URL.revokeObjectURL(url); } catch (e) {}
        });
        state.pendingAttachments = [];
        state.pendingPreviewUrls = [];
        if (els.photoInput) els.photoInput.value = '';
        if (els.fileInput) els.fileInput.value = '';
        if (els.attachPreview) els.attachPreview.hidden = true;
        if (els.attachPreviewInner) els.attachPreviewInner.innerHTML = '';
        setUploadProgress(0, true);
    }

    function setUploadProgress(percent, hidden) {
        if (!els.uploadProgress || !els.uploadProgressBar) return;
        if (hidden) {
            els.uploadProgress.hidden = true;
            els.uploadProgressBar.style.width = '0%';
            return;
        }
        els.uploadProgress.hidden = false;
        els.uploadProgressBar.style.width = Math.max(0, Math.min(100, percent)) + '%';
    }

    function isImageFile(file) {
        return String((file && file.type) || '').indexOf('image/') === 0;
    }

    function renderAttachmentPreview() {
        if (!els.attachPreview || !els.attachPreviewInner) return;
        var files = state.pendingAttachments || [];
        if (!files.length) {
            els.attachPreview.hidden = true;
            els.attachPreviewInner.innerHTML = '';
            return;
        }
        els.attachPreview.hidden = false;

        els.attachPreviewInner.innerHTML = files.map(function (file, idx) {
            var isImg = isImageFile(file);
            var preview = '';
            if (isImg && state.pendingPreviewUrls[idx]) {
                preview = '<img src="' + state.pendingPreviewUrls[idx] + '" alt="Image preview">';
            } else {
                preview = '<span class="comms-attach-preview-file"><strong>' + Comms.escapeHtml(fileExtLabel(file.name)) + '</strong></span>';
            }
            return '<div class="comms-attach-preview-item">' +
                preview +
                '<span class="comms-attach-preview-name">' + Comms.escapeHtml(file.name) + '</span>' +
                '</div>';
        }).join('');
    }

    function pushFiles(files, expectedKind) {
        var incoming = Array.isArray(files) ? files : Array.from(files || []);
        if (!incoming.length) return;

        var current = state.pendingAttachments.slice();
        var combined = current.concat(incoming);
        var imageCount = combined.filter(isImageFile).length;
        var fileCount = combined.length - imageCount;
        var totalBytes = combined.reduce(function (sum, f) { return sum + Number(f.size || 0); }, 0);

        if (expectedKind === 'image' && imageCount > state.maxImageCount) {
            Comms.showToast('Maximum 100 images per upload.', 'error');
            return;
        }
        if (expectedKind === 'file' && fileCount > state.maxFileCount) {
            Comms.showToast('Maximum 10 files per upload.', 'error');
            return;
        }
        if (totalBytes > state.maxTotalBytes) {
            Comms.showToast('Total upload size must not exceed 25 MB.', 'error');
            return;
        }

        state.pendingAttachments = combined;
        state.pendingPreviewUrls = state.pendingAttachments.map(function (file, idx) {
            var existing = state.pendingPreviewUrls[idx];
            if (existing) return existing;
            return isImageFile(file) ? URL.createObjectURL(file) : '';
        });
        renderAttachmentPreview();
    }

    var msgMoreIcon = '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="1.85"/><circle cx="12" cy="12" r="1.85"/><circle cx="12" cy="19" r="1.85"/></svg>';

    function renderMessageActions(m, deleted) {
        var items = '';
        if (!deleted && m.mine && m.message_type === 'text') {
            items += '<button type="button" role="menuitem" data-msg-edit="' + m.id + '">Edit</button>';
        }
        items += '<button type="button" role="menuitem" data-msg-delete="' + m.id + '">Delete</button>';
        return '<div class="comms-msg-actions">' +
            '<button type="button" class="comms-msg-more" data-msg-more="' + m.id + '" aria-label="Message actions" aria-haspopup="menu" aria-expanded="false" title="More">' + msgMoreIcon + '</button>' +
            '<div class="comms-msg-menu" data-msg-menu="' + m.id + '" role="menu" hidden>' + items + '</div>' +
            '</div>';
    }

    function closeMessageMenus() {
        if (!els.messages) return;
        els.messages.querySelectorAll('[data-msg-menu]').forEach(function (menu) {
            menu.hidden = true;
        });
        els.messages.querySelectorAll('[data-msg-more]').forEach(function (btn) {
            btn.setAttribute('aria-expanded', 'false');
        });
        els.messages.querySelectorAll('.comms-bubble-wrap.is-menu-open').forEach(function (wrap) {
            wrap.classList.remove('is-menu-open');
        });
    }

    function replaceMessage(updated) {
        if (!updated) return;
        var idx = state.messages.findIndex(function (m) { return Number(m.id) === Number(updated.id); });
        if (idx === -1) return;
        state.messages[idx] = updated;
        saveMsgCache(state.activeId, state.messages, state.reactionEmojis);
        renderMessages({ preserveScroll: true });
    }

    function removeMessageLocal(messageId) {
        state.messages = state.messages.filter(function (m) { return Number(m.id) !== Number(messageId); });
        saveMsgCache(state.activeId, state.messages, state.reactionEmojis);
        renderMessages({ preserveScroll: true });
        loadConversations();
    }

    function saveEditedMessage(messageId) {
        var input = els.messages.querySelector('[data-edit-input="' + messageId + '"]');
        if (!input || !routes.updateMessage) return;
        var raw = String(input.value || '');
        var body = raw.trim();
        if (!body) {
            Comms.showToast('Message cannot be empty or only spaces.', 'error');
            return;
        }
        if (raw.length > 1000 || body.length > 1000) {
            Comms.showToast('Message must be 1000 characters or less.', 'error');
            return;
        }
        state.msgChangeSkipToast[String(messageId)] = true;
        Comms.api(Comms.route(routes.updateMessage, messageId), {
            method: 'PATCH',
            body: JSON.stringify({ body: body }),
            dedupe: false
        }).then(function (data) {
            state.editingId = null;
            replaceMessage(data.message);
            Comms.showToast('Message edited.', 'success');
            window.setTimeout(function () { delete state.msgChangeSkipToast[String(messageId)]; }, 800);
        }).catch(function (err) {
            delete state.msgChangeSkipToast[String(messageId)];
            Comms.showToast(err.message || 'Unable to edit message.', 'error');
        });
    }

    function deleteMessage(messageId, scope) {
        if (!routes.deleteMessage) return;
        state.msgChangeSkipToast[String(messageId)] = true;
        Comms.api(Comms.route(routes.deleteMessage, messageId) + '?scope=' + encodeURIComponent(scope), {
            method: 'DELETE',
            body: JSON.stringify({ scope: scope }),
            dedupe: false
        }).then(function (data) {
            state.editingId = null;
            if (data && data.scope === 'me') {
                removeMessageLocal(messageId);
                Comms.showToast('Message deleted for you.', 'success');
                return;
            }
            if (data && data.message) {
                replaceMessage(data.message);
                loadConversations();
                Comms.showToast('Message deleted for everyone.', 'success');
            }
            window.setTimeout(function () { delete state.msgChangeSkipToast[String(messageId)]; }, 800);
        }).catch(function (err) {
            delete state.msgChangeSkipToast[String(messageId)];
            Comms.showToast(err.message || 'Unable to delete message.', 'error');
        });
    }

    function openDeletePrompt(messageId) {
        var msg = state.messages.find(function (m) { return Number(m.id) === Number(messageId); });
        if (!msg) return;
        var deleted = msg.deleted_for_all || msg.message_type === 'deleted';
        var canDeleteAll = !!msg.mine && !deleted;
        if (typeof Comms.openDeleteMessageDialog !== 'function') {
            deleteMessage(messageId, canDeleteAll ? 'all' : 'me');
            return;
        }
        Comms.openDeleteMessageDialog({ canDeleteAll: canDeleteAll }).then(function (scope) {
            if (!scope) return;
            deleteMessage(messageId, scope);
        });
    }

    function renderMessages(opts) {
        opts = opts || {};
        var prevScroll = els.messages ? els.messages.scrollTop : 0;
        var stickBottom = false;
        if (opts.stickBottom === true) {
            stickBottom = true;
        } else if (els.messages && opts.stickBottom !== false && !opts.preserveScroll) {
            stickBottom = els.messages.scrollTop + els.messages.clientHeight >= els.messages.scrollHeight - 48;
        }
        els.messages.innerHTML = state.messages.map(function (m) {
            if (m.message_type === 'call' || m.message_type === 'system') {
                return renderCallBubble(m);
            }
            if (m.deleted_for_all || m.message_type === 'deleted') {
                return '<div class="comms-bubble-wrap ' + (m.mine ? 'is-mine' : 'is-theirs') + '" data-id="' + m.id + '">' +
                    '<div class="comms-bubble-row">' +
                    '<div class="comms-bubble comms-bubble--deleted">' +
                    '<div class="comms-bubble-text">This message was deleted</div>' +
                    '<span class="comms-bubble-time">' + Comms.escapeHtml(Comms.formatTime(m.created_at)) + '</span>' +
                    '</div>' +
                    renderMessageActions(m, true) +
                    '</div></div>';
            }
            var mine = !!m.mine;
            var pickerOpen = Number(state.openReactionPickerId) === Number(m.id);
            var editing = Number(state.editingId) === Number(m.id);
            var bodyHtml = '';
            if (editing) {
                bodyHtml = '<textarea class="comms-edit-input" data-edit-input="' + m.id + '" maxlength="1000" rows="2">' + Comms.escapeHtml(m.body || '') + '</textarea>' +
                    '<div class="comms-edit-actions">' +
                    '<button type="button" class="comms-edit-cancel" data-edit-cancel="' + m.id + '">Cancel</button>' +
                    '<button type="button" class="comms-edit-save" data-edit-save="' + m.id + '">Save</button>' +
                    '</div>';
            } else if (m.body) {
                bodyHtml = '<div class="comms-bubble-text">' + Comms.escapeHtml(m.body) +
                    (m.edited ? ' <span class="comms-edited">edited</span>' : '') +
                    '</div>';
            }
            return '<div class="comms-bubble-wrap ' + (mine ? 'is-mine' : 'is-theirs') + (pickerOpen ? ' is-picker-open' : '') + '" data-id="' + m.id + '">' +
                '<div class="comms-bubble-row">' +
                '<div class="comms-bubble-tools" aria-hidden="false">' +
                renderMessageActions(m, false) +
                '<button type="button" class="comms-react-btn" data-react-toggle="' + m.id + '" aria-label="Add reaction" title="React" aria-expanded="' + (pickerOpen ? 'true' : 'false') + '">' + reactBtnIcon + '</button>' +
                '</div>' +
                '<div class="comms-bubble ' + (mine ? 'comms-bubble--mine' : 'comms-bubble--theirs') + (m.message_type === 'image' || m.message_type === 'file' ? ' has-attachment' : '') + '">' +
                renderAttachments(m.attachments) +
                bodyHtml +
                '<span class="comms-bubble-time">' + Comms.escapeHtml(Comms.formatTime(m.created_at)) + '</span>' +
                '</div>' +
                '</div>' +
                renderReactionPicker(m.id) +
                renderReactionChips(m.reactions, m.id) +
                '</div>';
        }).join('');
        if (!els.messages) return;
        if (opts.preserveScroll) {
            els.messages.scrollTop = prevScroll;
        } else if (stickBottom || opts.stickBottom === true) {
            els.messages.scrollTop = els.messages.scrollHeight;
        } else {
            els.messages.scrollTop = prevScroll;
        }
    }

    function closeReactionPickersDom() {
        hideGlobalPicker();
        if (!els.messages) return;
        els.messages.querySelectorAll('.comms-bubble-wrap.is-picker-open').forEach(function (el) {
            el.classList.remove('is-picker-open');
            var btn = el.querySelector('[data-react-toggle]');
            if (btn) btn.setAttribute('aria-expanded', 'false');
        });
    }

    function openReactionPickerInPlace(messageId, triggerBtn) {
        if (!els.messages) return;
        closeReactionPickersDom();
        var wrap = els.messages.querySelector('.comms-bubble-wrap[data-id="' + messageId + '"]');
        if (wrap) {
            wrap.classList.add('is-picker-open');
            if (triggerBtn) triggerBtn.setAttribute('aria-expanded', 'true');
        }
        showPickerFor(messageId, triggerBtn || document.body);
    }

    function patchReactionChipsDom(messageId, reactions) {
        if (!els.messages) return false;
        var wrap = els.messages.querySelector('.comms-bubble-wrap[data-id="' + messageId + '"]');
        if (!wrap) return false;
        var chipsHtml = renderReactionChips(reactions, messageId);
        var existing = wrap.querySelector('.comms-reaction-chips');
        if (existing) {
            if (chipsHtml) existing.outerHTML = chipsHtml;
            else existing.remove();
        } else if (chipsHtml) {
            wrap.insertAdjacentHTML('beforeend', chipsHtml);
        }
        return true;
    }

    function cloneReactions(reactions) {
        return Comms.cloneReactions ? Comms.cloneReactions(reactions) : (Array.isArray(reactions) ? reactions.slice() : []);
    }

    function optimisticToggleReactions(reactions, emoji) {
        if (Comms.optimisticToggleReactions) {
            return Comms.optimisticToggleReactions(reactions, emoji, {
                currentUserId: state.currentUserId,
                portalUserType: state.portalUserType
            });
        }
        return reactions;
    }

    function applyMessageReactions(messageId, reactions, opts) {
        opts = opts || {};
        var msg = state.messages.find(function (m) { return Number(m.id) === Number(messageId); });
        if (msg) {
            msg.reactions = Array.isArray(reactions) ? reactions : [];
        }
        if (opts.closePicker !== false) {
            state.openReactionPickerId = null;
            closeReactionPickersDom();
        }
        if (!patchReactionChipsDom(messageId, msg ? msg.reactions : reactions)) {
            renderMessages({ preserveScroll: true });
        }
        if (state.activeId) {
            saveMsgCache(state.activeId, state.messages, state.reactionEmojis);
        }
    }

    function patchRemoteReaction(messageId, eventType, row) {
        var msg = state.messages.find(function (m) { return Number(m.id) === Number(messageId); });
        if (!msg || !row) return false;
        var list = cloneReactions(msg.reactions);
        var emoji = String(row.emoji || '');
        if (!emoji) return false;
        var isMine = Number(row.user_id) === Number(state.currentUserId)
            && String(row.user_type || '') === String(state.portalUserType || '');
        // Ignore our own realtime echoes while a local toggle is in flight.
        if (isMine && state.reactSkipRealtime[String(messageId)]) return true;
        var entry = null;
        list.forEach(function (r) {
            if (r.emoji === emoji) entry = r;
        });

        if (eventType === 'DELETE') {
            if (!entry) return true;
            entry.count = Math.max(0, entry.count - 1);
            if (isMine) entry.mine = false;
            entry.users = (entry.users || []).filter(function (u) {
                return !(Number(u.id) === Number(row.user_id) && String(u.user_type || '') === String(row.user_type || ''));
            });
        } else if (eventType === 'INSERT') {
            var newUser = {
                id: Number(row.user_id || 0),
                user_type: String(row.user_type || ''),
                name: isMine ? 'You' : String((state.activeConversation && state.activeConversation.other_user && state.activeConversation.other_user.name) || 'Someone'),
                mine: isMine
            };
            if (entry) {
                entry.count += 1;
                if (isMine) entry.mine = true;
                entry.users = (entry.users || []).filter(function (u) {
                    return !(Number(u.id) === newUser.id && String(u.user_type || '') === newUser.user_type);
                }).concat([newUser]);
            } else {
                list.push({ emoji: emoji, count: 1, mine: isMine, users: [newUser] });
            }
        } else if (eventType === 'UPDATE') {
            var oldEmoji = String((row._old_emoji) || '');
            if (!oldEmoji || oldEmoji === emoji) return false;
            var oldEntry = null;
            list.forEach(function (r) {
                if (r.emoji === oldEmoji) oldEntry = r;
            });
            if (oldEntry) {
                oldEntry.count = Math.max(0, oldEntry.count - 1);
                if (isMine) oldEntry.mine = false;
            }
            if (entry) {
                entry.count += 1;
                if (isMine) entry.mine = true;
            } else {
                list.push({ emoji: emoji, count: 1, mine: isMine });
            }
        } else {
            return false;
        }

        msg.reactions = list.filter(function (r) { return Number(r.count) > 0; });
        if (!patchReactionChipsDom(messageId, msg.reactions)) {
            renderMessages({ preserveScroll: true });
        }
        if (state.activeId) {
            saveMsgCache(state.activeId, state.messages, state.reactionEmojis);
        }
        return true;
    }

    function onReactionChange(messageId, meta) {
        if (!state.activeId || !messageId) return;
        if (!state.messages.some(function (m) { return Number(m.id) === Number(messageId); })) return;
        if (state.reactSkipRealtime[String(messageId)]) return;

        if (meta && meta.eventType && patchRemoteReaction(messageId, meta.eventType, meta.row)) {
            return;
        }

        var watchId = state.activeId;
        var watchSeq = state.reactSeq[String(messageId)] || 0;
        Comms.api(Comms.route(routes.messages, watchId), { dedupe: true }).then(function (data) {
            if (Number(state.activeId) !== Number(watchId)) return;
            if ((state.reactSeq[String(messageId)] || 0) !== watchSeq) return;
            if (state.reactSkipRealtime[String(messageId)]) return;
            var fresh = (data.messages || []).find(function (m) {
                return Number(m.id) === Number(messageId);
            });
            if (!fresh) return;
            applyMessageReactions(messageId, fresh.reactions || [], { closePicker: false });
        }).catch(function () { /* ignore */ });
    }

    function toggleReaction(messageId, emoji) {
        if (!routes.react || !messageId || !emoji) return;

        var msg = state.messages.find(function (m) { return Number(m.id) === Number(messageId); });
        if (!msg) return;

        var previous = cloneReactions(msg.reactions);
        var next = optimisticToggleReactions(previous, emoji);
        var added = next.some(function (r) { return r.emoji === emoji && r.mine; })
            && !previous.some(function (r) { return r.emoji === emoji && r.mine; });

        var key = String(messageId);
        var seq = (state.reactSeq[key] || 0) + 1;
        state.reactSeq[key] = seq;
        state.reactSkipRealtime[key] = true;

        if (added && typeof Comms.playReactionSound === 'function') {
            Comms.playReactionSound();
        }
        applyMessageReactions(messageId, next);

        Comms.api(Comms.route(routes.react, messageId), {
            method: 'POST',
            body: JSON.stringify({ emoji: emoji }),
            dedupe: false
        }).then(function (data) {
            // Stale response from an older click — keep the latest optimistic UI.
            if (state.reactSeq[key] !== seq) return;
            applyMessageReactions(data.message_id || messageId, data.reactions || next, { closePicker: false });
        }).catch(function (err) {
            if (state.reactSeq[key] !== seq) return;
            applyMessageReactions(messageId, previous, { closePicker: false });
            Comms.showToast(err.message || 'Unable to react.', 'error');
        }).finally(function () {
            if (state.reactSeq[key] !== seq) return;
            window.setTimeout(function () {
                if (state.reactSeq[key] !== seq) return;
                delete state.reactSkipRealtime[key];
            }, 500);
        });
    }

    function resolvePeerAvatar(peer) {
        var name = (peer && peer.name) || 'User';
        var url = peer && peer.profile_image_url ? String(peer.profile_image_url).trim() : '';
        if (!url || url === 'null' || url === 'undefined') {
            return Comms.defaultAvatar(name);
        }
        return url;
    }

    function formatPresenceLabel(peer) {
        var status = String((peer && peer.online_status) || '').toLowerCase();
        if (status === 'online') return 'Online';
        if (status === 'offline') {
            if (peer && peer.last_seen) return 'Offline · Last seen ' + Comms.formatTime(peer.last_seen);
            return 'Offline';
        }
        if (peer && peer.last_seen) return 'Last seen ' + Comms.formatTime(peer.last_seen);
        return 'Offline';
    }

    function updatePeerHeader(conversation) {
        var peer = (conversation && conversation.other_user) || {};
        var name = peer.name || 'User';
        if (els.peerName) els.peerName.textContent = name;

        var statusBits = [];
        if (peer.user_type_label) statusBits.push(String(peer.user_type_label));
        if (peer.position) statusBits.push(String(peer.position));
        if (peer.barangay_name) statusBits.push(String(peer.barangay_name));
        statusBits.push(formatPresenceLabel(peer));
        if (els.peerMeta) els.peerMeta.textContent = statusBits.filter(Boolean).join(' · ');

        var isOnline = String(peer.online_status || '').toLowerCase() === 'online';
        if (els.peerStatusDot) {
            els.peerStatusDot.setAttribute('data-status', isOnline ? 'online' : 'offline');
            els.peerStatusDot.title = isOnline ? 'Online' : 'Offline';
        }

        if (els.peerAvatar) {
            var avatarUrl = resolvePeerAvatar(peer);
            els.peerAvatar.alt = name;
            els.peerAvatar.onerror = function () {
                els.peerAvatar.onerror = null;
                els.peerAvatar.src = Comms.defaultAvatar(name);
            };
            if (els.peerAvatar.getAttribute('src') !== avatarUrl) {
                els.peerAvatar.src = avatarUrl;
            }
        }

        if (conversation && conversation.id) {
            savePeerProfileCache(conversation.id, peer);
        }
    }

    function peerProfileCacheKey(conversationId) {
        return 'comms:peer-profile:' + String(conversationId);
    }

    function loadPeerProfileCache(conversationId) {
        if (!window.SkLocalCache || typeof window.SkLocalCache.get !== 'function') return null;
        var entry = window.SkLocalCache.get(peerProfileCacheKey(conversationId));
        if (!entry || !entry.payload) return null;
        return entry.payload;
    }

    function savePeerProfileCache(conversationId, peer) {
        if (!conversationId || !peer || !window.SkLocalCache || typeof window.SkLocalCache.set !== 'function') return;
        window.SkLocalCache.set(peerProfileCacheKey(conversationId), {
            id: peer.id || null,
            name: peer.name || '',
            user_type_label: peer.user_type_label || '',
            position: peer.position || '',
            barangay_name: peer.barangay_name || '',
            profile_image_url: peer.profile_image_url || '',
            online_status: peer.online_status || '',
            last_seen: peer.last_seen || null,
            user_type: peer.user_type || ''
        });
    }

    function applyCachedPeerHeader(conversationId) {
        var cached = loadPeerProfileCache(conversationId);
        if (!cached) return;
        updatePeerHeader({ id: conversationId, other_user: cached });
    }

    function refreshActivePeerProfile() {
        if (!state.activeId || !routes.showConversation) return Promise.resolve();
        var cid = state.activeId;
        return Comms.api(Comms.route(routes.showConversation, cid), { dedupe: true }).then(function (data) {
            if (Number(state.activeId) !== Number(cid) || !data || !data.conversation) return;
            state.activeConversation = data.conversation;
            updatePeerHeader(data.conversation);
        }).catch(function () { /* ignore */ });
    }

    function startPeerProfileRealtime() {
        if (state.peerProfileRefreshTimer) {
            clearInterval(state.peerProfileRefreshTimer);
        }
        state.peerProfileRefreshTimer = setInterval(function () {
            if (!state.activeId) return;
            refreshActivePeerProfile();
        }, 20000);
    }

    function updatePeerOnlineFromPresence(presenceMap) {
        if (!presenceMap) return;

        var onlineIds = {};
        Object.keys(presenceMap).forEach(function (key) {
            var metas = presenceMap[key] || [];
            metas.forEach(function (meta) {
                var uid = Number(meta.user_id || key || 0);
                if (uid) onlineIds[uid] = true;
            });
        });

        state.conversations.forEach(function (c) {
            var peer = c.other_user;
            if (!peer || !peer.id) return;
            var next = onlineIds[Number(peer.id)] ? 'online' : 'offline';
            if (String(peer.online_status || '') === next) return;
            peer.online_status = next;
            if (next === 'offline') peer.last_seen = new Date().toISOString();
            if (c.id) savePeerProfileCache(c.id, peer);
        });

        if (!state.activeConversation || !state.activeConversation.other_user) return;
        var activePeer = state.activeConversation.other_user;
        var peerId = Number(activePeer.id || 0);
        if (!peerId) return;

        var nextStatus = onlineIds[peerId] ? 'online' : 'offline';
        if (String(activePeer.online_status || '') === nextStatus) return;
        activePeer.online_status = nextStatus;
        if (nextStatus === 'offline') activePeer.last_seen = new Date().toISOString();
        state.activeConversation.other_user = activePeer;
        updatePeerHeader(state.activeConversation);
    }

    function setListMode(mode) {
        state.listMode = mode;
        var isChats = mode === 'conversations';
        if (els.convList) els.convList.hidden = !isChats;
        if (els.directoryList) els.directoryList.hidden = isChats;
        // Keep Search always visible (filters chats or people).
        if (els.convFilter) els.convFilter.hidden = false;
        var convFilterWrap = document.querySelector('.comms-search-wrap--filter');
        if (convFilterWrap) convFilterWrap.hidden = false;
        if (els.barangayFilter) {
            var showBarangay = mode === 'officials' || mode === 'chairpersons';
            els.barangayFilter.hidden = !showBarangay;
            var barangayWrap = document.getElementById('commsBarangayWrap');
            if (barangayWrap) barangayWrap.hidden = !showBarangay;
        }
        if (els.convEmpty && !isChats) {
            els.convEmpty.hidden = true;
        }
        if (els.searchResults) {
            els.searchResults.hidden = true;
            els.searchResults.innerHTML = '';
        }
    }

    function loadDirectory() {
        if (!routes.directory) return Promise.resolve();
        var params = new URLSearchParams();
        params.set('filter', state.directoryFilter || 'officials');
        if (state.barangayId) params.set('barangay_id', state.barangayId);
        return Comms.api(routes.directory + '?' + params.toString()).then(function (data) {
            renderDirectory(data.groups || []);
        }).catch(function (err) {
            Comms.showToast(err.message || 'Failed to load directory.', 'error');
        });
    }

    function renderDirectory(groups) {
        if (!els.directoryList) return;
        if (!groups.length || groups.every(function (g) { return !(g.users || []).length; })) {
            els.directoryList.innerHTML = '<div class="comms-empty"><p>No people found for this filter.</p></div>';
            return;
        }
        els.directoryList.innerHTML = groups.map(function (group) {
            var users = group.users || [];
            if (!users.length) return '';
            return '<div class="comms-dir-group-title">' + Comms.escapeHtml(group.title || 'People') + '</div>' +
                users.map(function (u) {
                    var avatar = u.profile_image_url || Comms.defaultAvatar(u.name);
                    var meta = [u.position || u.user_type_label || '', u.barangay_name || ''].filter(Boolean).join(' · ');
                    return '<div class="comms-dir-item" role="listitem" data-user-id="' + u.id + '">' +
                        '<img class="comms-avatar" src="' + Comms.escapeHtml(avatar) + '" alt="" onerror="this.onerror=null;this.src=\'' + Comms.escapeHtml(Comms.defaultAvatar(u.name)) + '\'">' +
                        '<button type="button" class="comms-dir-open" data-user-id="' + u.id + '" style="border:0;background:transparent;text-align:left;padding:0;cursor:pointer;min-width:0;">' +
                        '<div class="comms-conv-name">' + Comms.escapeHtml(u.name || 'User') + '</div>' +
                        '<div class="comms-dir-meta">' + Comms.escapeHtml(meta) + '</div>' +
                        '</button>' +
                        '<div class="comms-dir-actions">' +
                        '<button type="button" class="comms-icon-btn comms-call-action-btn" data-dir-call="voice" data-user-id="' + u.id + '" aria-label="Voice call">' +
                        '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.79 19.79 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.81.36 1.6.68 2.35a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.75.32 1.54.55 2.35.68A2 2 0 0 1 22 16.92z"/></svg>' +
                        '<span class="comms-call-action-label">Voice call</span>' +
                        '</button>' +
                        '<button type="button" class="comms-icon-btn comms-call-action-btn" data-dir-call="video" data-user-id="' + u.id + '" aria-label="Video call">' +
                        '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/></svg>' +
                        '<span class="comms-call-action-label">Video call</span>' +
                        '</button>' +
                        '</div></div>';
                }).join('');
        }).join('');
    }

    function openOrCallUser(userId, callType) {
        return Comms.api(routes.storeConversation, {
            method: 'POST',
            body: JSON.stringify({ user_id: Number(userId) }),
            dedupe: false
        }).then(function (data) {
            var conversation = data.conversation;
            return openConversation(conversation.id).then(function () {
                if (callType && window.CommsWebRTC) {
                    window.CommsWebRTC.startCall(conversation.id, callType, conversation);
                }
            });
        }).catch(function (err) {
            Comms.showToast(err.message || 'Unable to open conversation.', 'error');
        });
    }

    function loadConversations() {
        return Comms.api(routes.conversations).then(function (data) {
            state.conversations = data.conversations || [];
            renderConversations(els.convFilter.value);
        }).catch(function (err) {
            Comms.showToast(err.message || 'Failed to load conversations.', 'error');
        });
    }

    function saveMsgCache(id, messages, emojis) {
        var payload = {
            messages: messages,
            reactionEmojis: emojis,
            ts: Date.now()
        };
        state.messageCache[String(id)] = payload;
        if (Comms.writeThreadCache) Comms.writeThreadCache(id, payload);
    }

    function loadMsgCache(id) {
        if (state.messageCache[String(id)]) return state.messageCache[String(id)];
        var stored = Comms.readThreadCache ? Comms.readThreadCache(id) : null;
        if (stored) {
            state.messageCache[String(id)] = stored;
            return stored;
        }
        return null;
    }

    function applyMessages(messages, emojis, opts) {
        state.messages = messages || [];
        if (Array.isArray(emojis) && emojis.length) {
            state.reactionEmojis = emojis;
        }
        renderMessages(opts);
    }

    function openConversation(id, opts) {
        opts = opts || {};
        state.activeId = Number(id);
        setThreadOpen(true);
        els.threadEmpty.hidden = true;
        els.threadActive.hidden = false;
        renderConversations(els.convFilter ? els.convFilter.value : '');

        if (window.CommsRealtime && typeof window.CommsRealtime.subscribeConversation === 'function') {
            window.CommsRealtime.subscribeConversation(state.activeId);
        }

        // Instant header: list row → SkLocalCache → then API refresh.
        var listConv = state.conversations.find(function (c) {
            return Number(c.id) === Number(id);
        });
        if (listConv && listConv.other_user) {
            updatePeerHeader(listConv);
        } else {
            applyCachedPeerHeader(id);
        }
        if (state.activeConversation && Number(state.activeConversation.id) === Number(id)) {
            updatePeerHeader(state.activeConversation);
        }

        // Show cached messages immediately so the thread appears instantly
        var cached = loadMsgCache(id);
        if (cached && Array.isArray(cached.messages) && cached.messages.length) {
            state.messages = cached.messages;
            if (Array.isArray(cached.reactionEmojis) && cached.reactionEmojis.length) {
                state.reactionEmojis = cached.reactionEmojis;
            }
            renderMessages({ stickBottom: true });
        }

        // Load conversation header and fresh messages in parallel
        var convPromise = Comms.api(Comms.route(routes.showConversation, id));
        var msgPromise = Comms.api(Comms.route(routes.messages, id));

        convPromise.then(function (data) {
            if (!data || !data.conversation) return;
            if (Number(state.activeId) !== Number(id)) return;
            var conv = data.conversation;
            var peer = conv.other_user || {};
            // Keep a working list/cache avatar if API returns empty/broken placeholder.
            if (listConv && listConv.other_user && listConv.other_user.profile_image_url) {
                var apiAvatar = String(peer.profile_image_url || '').trim();
                if (!apiAvatar) {
                    peer.profile_image_url = listConv.other_user.profile_image_url;
                    conv.other_user = peer;
                }
            }
            state.activeConversation = conv;
            updatePeerHeader(conv);
        }).catch(function () { /* non-fatal */ });

        return msgPromise.then(function (data) {
            var freshMessages = data.messages || [];
            var freshEmojis = Array.isArray(data.reaction_emojis) ? data.reaction_emojis : [];

            // Only re-render if conversation is still active
            if (Number(state.activeId) === Number(id)) {
                applyMessages(freshMessages, freshEmojis, { stickBottom: !cached });
            }

            saveMsgCache(id, freshMessages, freshEmojis);

            return Comms.api(Comms.route(routes.read, id), { method: 'POST', body: '{}' });
        }).then(function () {
            if (!opts.skipReloadList) {
                return loadConversations();
            }
        }).catch(function (err) {
            Comms.showToast(err.message || 'Unable to open conversation.', 'error');
        });
    }

    function appendMessage(message) {
        if (!message || Number(message.conversation_id) !== Number(state.activeId)) return;
        if (state.messages.some(function (m) { return Number(m.id) === Number(message.id); })) return;
        state.messages.push(message);
        saveMsgCache(state.activeId, state.messages, state.reactionEmojis);
        renderMessages({ stickBottom: true });
    }

    function reloadActiveMessages() {
        if (!state.activeId) return Promise.resolve();
        var cid = state.activeId;
        return Comms.api(Comms.route(routes.messages, cid)).then(function (data) {
            var msgs = data.messages || [];
            var emojis = Array.isArray(data.reaction_emojis) ? data.reaction_emojis : [];
            saveMsgCache(cid, msgs, emojis);
            if (Number(state.activeId) === Number(cid)) {
                applyMessages(msgs, emojis, { stickBottom: true });
            }
            return loadConversations();
        }).catch(function () { /* ignore */ });
    }

    function resizeComposer() {
        if (!els.input) return;
        els.input.style.height = 'auto';
        var next = Math.min(Math.max(els.input.scrollHeight, 44), 180);
        els.input.style.height = next + 'px';
        if (els.messages) {
            els.messages.scrollTop = els.messages.scrollHeight;
        }
    }

    function sendMessage(body) {
        if (!state.activeId) return;
        var raw = String(body || '');
        var text = raw.trim();
        var files = (state.pendingAttachments || []).slice();
        if (!text && !files.length) {
            if (raw.length > 0) {
                Comms.showToast('Message cannot be only spaces.', 'error');
            }
            return;
        }
        if (text && (raw.length > 1000 || text.length > 1000)) {
            Comms.showToast('Message must be 1000 characters or less.', 'error');
            return;
        }
        if (state.sendInFlight && files.length) return;

        els.input.value = '';
        resizeComposer();
        clearPendingAttachment();

        var tempId = 'local-' + Date.now();
        if (text) {
            appendMessage({
                id: tempId,
                conversation_id: state.activeId,
                body: text,
                message_type: 'text',
                mine: true,
                created_at: new Date().toISOString(),
                reactions: [],
                attachments: []
            });
        }

        var jobs = [];
        if (text) {
            jobs.push(Comms.api(Comms.route(routes.messages, state.activeId), {
                method: 'POST',
                body: JSON.stringify({ body: text }),
                dedupe: false
            }).then(function (data) {
                if (!data || !data.message) return;
                var realIdx = state.messages.findIndex(function (m) { return Number(m.id) === Number(data.message.id); });
                var tempIdx = state.messages.findIndex(function (m) { return String(m.id) === tempId; });
                if (realIdx !== -1 && tempIdx !== -1) {
                    state.messages.splice(tempIdx, 1);
                } else if (tempIdx !== -1) {
                    state.messages[tempIdx] = data.message;
                } else {
                    appendMessage(data.message);
                    return;
                }
                saveMsgCache(state.activeId, state.messages, state.reactionEmojis);
                renderMessages({ stickBottom: true });
            }));
        }

        if (files.length) {
            state.sendInFlight = true;
            if (els.sendBtn) els.sendBtn.disabled = true;
            if (els.attachBtn) els.attachBtn.disabled = true;
            setUploadProgress(20, false);
            var form = new FormData();
            files.forEach(function (file) {
                form.append('attachments[]', file, file.name);
            });
            jobs.push(Comms.api(Comms.route(routes.messages, state.activeId), {
                method: 'POST',
                body: form,
                dedupe: false
            }).then(function (data) {
                setUploadProgress(100, false);
                if (data && data.message) appendMessage(data.message);
            }));
        }

        Promise.all(jobs).then(function () {
            return loadConversations();
        }).catch(function (err) {
            Comms.showToast(err.message || 'Failed to send message.', 'error');
        }).finally(function () {
            state.sendInFlight = false;
            if (els.sendBtn) els.sendBtn.disabled = false;
            if (els.attachBtn) els.attachBtn.disabled = false;
            window.setTimeout(function () { setUploadProgress(0, true); }, 400);
            els.input.focus();
        });
    }

    function startConversationWithUser(userId) {
        return Comms.api(routes.storeConversation, {
            method: 'POST',
            body: JSON.stringify({ user_id: Number(userId) }),
            dedupe: false
        }).then(function (data) {
            if (els.searchResults) els.searchResults.hidden = true;
            if (els.convFilter) els.convFilter.value = '';
            return loadConversations().then(function () {
                return openConversation(data.conversation.id);
            });
        }).catch(function (err) {
            Comms.showToast(err.message || 'Unable to start conversation.', 'error');
        });
    }

    function searchUsers(q) {
        if (!els.searchResults) return;
        if (!q || q.length < 1) {
            els.searchResults.hidden = true;
            els.searchResults.innerHTML = '';
            return;
        }
        Comms.api(routes.searchUsers + '?q=' + encodeURIComponent(q)).then(function (data) {
            var users = data.users || [];
            if (!users.length) {
                els.searchResults.innerHTML = '<div class="comms-search-item"><span>No users found</span></div>';
                els.searchResults.hidden = false;
                return;
            }
            els.searchResults.innerHTML = users.map(function (u) {
                return '<button type="button" class="comms-search-item" data-user-id="' + u.id + '">' +
                    '<strong>' + Comms.escapeHtml(u.name) + '</strong>' +
                    '<span>' + Comms.escapeHtml(u.user_type_label || u.role) + '</span></button>';
            }).join('');
            els.searchResults.hidden = false;
        }).catch(function (err) {
            Comms.showToast(err.message || 'Search failed.', 'error');
        });
    }

    els.convList.addEventListener('click', function (e) {
        var btn = e.target.closest('.comms-conv-item');
        if (!btn) return;
        // Instant header paint from list row while API loads.
        if (btn.dataset.peerName) {
            updatePeerHeader({
                id: btn.dataset.id,
                other_user: {
                    name: btn.dataset.peerName,
                    profile_image_url: btn.dataset.peerAvatar || '',
                    online_status: btn.dataset.peerStatus || 'offline'
                }
            });
        }
        openConversation(btn.dataset.id);
    });

    if (els.messages) {
        els.messages.addEventListener('click', function (e) {
            var peopleBtn = e.target.closest('[data-react-people]');
            if (peopleBtn) {
                e.preventDefault();
                e.stopPropagation();
                var pid = Number(peopleBtn.getAttribute('data-react-people'));
                var pmsg = state.messages.find(function (m) { return Number(m.id) === pid; });
                var emojiEl = e.target.closest('[data-react-people-emoji]');
                if (Comms.toggleReactionPeople) {
                    Comms.toggleReactionPeople(peopleBtn, pmsg ? pmsg.reactions : [], {
                        pinned: true,
                        emoji: emojiEl ? (emojiEl.getAttribute('data-react-people-emoji') || '') : ''
                    });
                }
                return;
            }
            var toggle = e.target.closest('[data-react-toggle]');
            if (toggle) {
                e.preventDefault();
                e.stopPropagation();
                var tid = Number(toggle.getAttribute('data-react-toggle'));
                if (Number(state.openReactionPickerId) === tid) {
                    closeReactionPickersDom();
                } else {
                    openReactionPickerInPlace(tid, toggle);
                }
                return;
            }
            var emojiBtn = e.target.closest('[data-react-emoji]');
            if (emojiBtn && !emojiBtn.closest('.comms-reaction-picker')) {
                e.preventDefault();
                e.stopPropagation();
                var wrap = emojiBtn.closest('.comms-bubble-wrap');
                var mid = wrap ? Number(wrap.getAttribute('data-id')) : 0;
                var emoji = emojiBtn.getAttribute('data-react-emoji');
                if (mid && emoji) toggleReaction(mid, emoji);
                return;
            }
            var editBtn = e.target.closest('[data-msg-edit]');
            if (editBtn) {
                e.preventDefault();
                closeMessageMenus();
                state.editingId = Number(editBtn.getAttribute('data-msg-edit'));
                renderMessages({ preserveScroll: true });
                var editor = els.messages.querySelector('[data-edit-input="' + state.editingId + '"]');
                if (editor) editor.focus();
                return;
            }
            var saveBtn = e.target.closest('[data-edit-save]');
            if (saveBtn) {
                e.preventDefault();
                saveEditedMessage(Number(saveBtn.getAttribute('data-edit-save')));
                return;
            }
            var cancelBtn = e.target.closest('[data-edit-cancel]');
            if (cancelBtn) {
                e.preventDefault();
                state.editingId = null;
                renderMessages({ preserveScroll: true });
                return;
            }
            var deleteChoice = e.target.closest('[data-msg-delete]');
            if (deleteChoice) {
                e.preventDefault();
                closeMessageMenus();
                openDeletePrompt(Number(deleteChoice.getAttribute('data-msg-delete')));
                return;
            }
            var moreBtn = e.target.closest('[data-msg-more]');
            if (moreBtn) {
                e.preventDefault();
                e.stopPropagation();
                var wrapMore = moreBtn.closest('.comms-bubble-wrap');
                var menu = wrapMore ? wrapMore.querySelector('[data-msg-menu]') : null;
                var opening = menu && menu.hidden;
                closeMessageMenus();
                if (opening && menu) {
                    menu.hidden = false;
                    moreBtn.setAttribute('aria-expanded', 'true');
                    if (wrapMore) wrapMore.classList.add('is-menu-open');
                }
            }
        });
        els.messages.addEventListener('pointerover', function (e) {
            var peopleBtn = e.target.closest('[data-react-people]');
            if (!peopleBtn || !els.messages.contains(peopleBtn)) return;
            var pid = Number(peopleBtn.getAttribute('data-react-people'));
            var pmsg = state.messages.find(function (m) { return Number(m.id) === pid; });
            var emojiEl = e.target.closest('[data-react-people-emoji]');
            if (Comms.showReactionPeople) {
                Comms.showReactionPeople(peopleBtn, pmsg ? pmsg.reactions : [], {
                    pinned: false,
                    emoji: emojiEl ? (emojiEl.getAttribute('data-react-people-emoji') || '') : ''
                });
            }
        });
        els.messages.addEventListener('pointerout', function (e) {
            var peopleBtn = e.target.closest('[data-react-people]');
            if (!peopleBtn) return;
            var related = e.relatedTarget;
            if (related && (peopleBtn.contains(related) || (related.closest && related.closest('#commsReactionPeople')))) {
                return;
            }
            if (Comms.scheduleHideReactionPeople) Comms.scheduleHideReactionPeople();
        });
    }
    pickerEl.addEventListener('click', function (e) {
        var emojiBtn = e.target.closest('[data-react-emoji]');
        if (!emojiBtn) return;
        e.preventDefault();
        e.stopPropagation();
        var mid = Number(state.openReactionPickerId);
        var emoji = emojiBtn.getAttribute('data-react-emoji');
        if (mid && emoji) {
            toggleReaction(mid, emoji);
        }
        closeReactionPickersDom();
    });

    document.addEventListener('click', function (e) {
        if (els.messages && !e.target.closest('[data-msg-more]') && !e.target.closest('[data-msg-menu]')) {
            closeMessageMenus();
        }
        if (!state.openReactionPickerId) return;
        if (e.target.closest('.comms-reaction-picker') || e.target.closest('[data-react-toggle]')) return;
        closeReactionPickersDom();
    });

    // Reposition picker on scroll/resize so it tracks the button
    if (els.messages) {
        els.messages.addEventListener('scroll', function () {
            if (state.openReactionPickerId) { hideGlobalPicker(); }
            closeMessageMenus();
        }, { passive: true });
    }
    window.addEventListener('resize', function () {
        if (state.openReactionPickerId) { hideGlobalPicker(); }
    }, { passive: true });

    // Image lightbox
    function openLightbox(src) {
        var existing = document.querySelector('.comms-lightbox');
        if (existing) existing.remove();
        var overlay = document.createElement('div');
        overlay.className = 'comms-lightbox';
        overlay.innerHTML = '<button type="button" class="comms-lightbox-close" aria-label="Close">&times;</button>' +
            '<img src="' + Comms.escapeHtml(src) + '" alt="Image preview">';
        document.body.appendChild(overlay);
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay || e.target.classList.contains('comms-lightbox-close')) {
                overlay.remove();
            }
        });
        document.addEventListener('keydown', function handler(e) {
            if (e.key === 'Escape') { overlay.remove(); document.removeEventListener('keydown', handler); }
        });
    }

    if (els.messages) {
        els.messages.addEventListener('click', function (e) {
            var imgBtn = e.target.closest('[data-lightbox-src]');
            if (imgBtn) {
                e.preventDefault();
                e.stopPropagation();
                openLightbox(imgBtn.getAttribute('data-lightbox-src'));
            }
        });
    }

    if (els.directoryList) {
        els.directoryList.addEventListener('click', function (e) {
            var callBtn = e.target.closest('[data-dir-call]');
            if (callBtn) {
                openOrCallUser(callBtn.dataset.userId, callBtn.dataset.dirCall);
                return;
            }
            var openBtn = e.target.closest('[data-user-id]');
            if (openBtn) {
                openOrCallUser(openBtn.dataset.userId, null);
            }
        });
    }

    if (els.filterBar) {
        els.filterBar.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-filter]');
            if (!btn) return;
            els.filterBar.querySelectorAll('.comms-filter-btn').forEach(function (el) {
                el.classList.toggle('is-active', el === btn);
            });
            var filter = btn.dataset.filter;
            if (filter === 'conversations') {
                setListMode('conversations');
                loadConversations();
                return;
            }
            state.directoryFilter = filter;
            setListMode(filter);
            loadDirectory();
        });
    }

    if (els.barangayFilter) {
        els.barangayFilter.addEventListener('change', function () {
            state.barangayId = els.barangayFilter.value || '';
            if (state.listMode !== 'conversations') {
                loadDirectory();
            }
        });
    }

    if (els.searchResults) {
        els.searchResults.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-user-id]');
            if (!btn) return;
            startConversationWithUser(btn.dataset.userId);
        });
    }

    if (els.convFilter) {
        els.convFilter.addEventListener('input', function () {
            clearTimeout(state.filterTimer);
            clearTimeout(state.searchTimer);
            var q = els.convFilter.value.trim();
            state.filterTimer = setTimeout(function () {
                if (state.listMode === 'conversations') {
                    renderConversations(q);
                }
            }, 120);
            state.searchTimer = setTimeout(function () {
                searchUsers(q);
            }, 250);
        });
    }

    els.composer.addEventListener('submit', function (e) {
        e.preventDefault();
        sendMessage(els.input.value);
    });

    if (els.attachBtn && els.attachMenu && els.photoInput && els.fileInput) {
        els.attachMenu.hidden = true;
        els.attachBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            els.attachMenu.hidden = !els.attachMenu.hidden;
        });
        if (els.pickPhotosBtn) {
            els.pickPhotosBtn.addEventListener('click', function () {
                els.attachMenu.hidden = true;
                els.photoInput.click();
            });
        }
        if (els.pickFilesBtn) {
            els.pickFilesBtn.addEventListener('click', function () {
                els.attachMenu.hidden = true;
                els.fileInput.click();
            });
        }
        els.photoInput.addEventListener('change', function () {
            pushFiles(els.photoInput.files, 'image');
            els.photoInput.value = '';
        });
        els.fileInput.addEventListener('change', function () {
            pushFiles(els.fileInput.files, 'file');
            els.fileInput.value = '';
        });
    }
    if (els.attachClear) {
        els.attachClear.addEventListener('click', function () {
            clearPendingAttachment();
        });
    }

    els.input.addEventListener('input', resizeComposer);

    els.input.addEventListener('keydown', function (e) {
        // Enter = new line only. Ctrl/Cmd+Enter sends.
        if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
            e.preventDefault();
            sendMessage(els.input.value);
            return;
        }
        if (window.CommsRealtime && typeof window.CommsRealtime.broadcastTyping === 'function' && state.activeId) {
            window.CommsRealtime.broadcastTyping(state.activeId);
        }
    });

    els.backBtn.addEventListener('click', function () {
        setThreadOpen(false);
        state.activeId = null;
        els.threadActive.hidden = true;
        els.threadEmpty.hidden = false;
        renderConversations(els.convFilter.value);
    });

    els.voiceBtn.addEventListener('click', function () {
        if (window.CommsWebRTC && state.activeId) {
            window.CommsWebRTC.startCall(state.activeId, 'voice', state.activeConversation);
        }
    });

    els.videoBtn.addEventListener('click', function () {
        if (window.CommsWebRTC && state.activeId) {
            window.CommsWebRTC.startCall(state.activeId, 'video', state.activeConversation);
        }
    });

    document.addEventListener('click', function (e) {
        if (els.searchResults && !els.searchResults.contains(e.target) && e.target !== els.convFilter) {
            els.searchResults.hidden = true;
        }
        if (els.attachMenu && !els.attachMenu.hidden && !e.target.closest('.comms-attach-menu-wrap')) {
            els.attachMenu.hidden = true;
        }
    });

    window.CommsChat = {
        appendMessage: appendMessage,
        reloadConversations: loadConversations,
        reloadActiveMessages: reloadActiveMessages,
        onReactionChange: onReactionChange,
        onMessageChange: function (row) {
            if (!row || !state.activeId || Number(row.conversation_id) !== Number(state.activeId)) return;
            var idx = state.messages.findIndex(function (m) { return Number(m.id) === Number(row.id); });
            if (idx === -1) return;
            var skipToast = !!state.msgChangeSkipToast[String(row.id)];
            if (row.deleted_for_all_at) {
                state.messages[idx].deleted_for_all = true;
                state.messages[idx].message_type = 'deleted';
                state.messages[idx].body = null;
                state.messages[idx].attachments = [];
                state.messages[idx].reactions = [];
                if (!skipToast) Comms.showToast('Message deleted.', 'info');
            } else if (row.body != null) {
                var prevBody = state.messages[idx].body;
                state.messages[idx].body = row.body;
                state.messages[idx].edited = true;
                if (!skipToast && String(prevBody || '') !== String(row.body || '')) {
                    Comms.showToast('Message edited.', 'info');
                }
            }
            saveMsgCache(state.activeId, state.messages, state.reactionEmojis);
            renderMessages({ preserveScroll: true });
        },
        getActiveId: function () { return state.activeId; },
        setTyping: function (visible) {
            els.typing.hidden = !visible;
        },
        updatePeerOnlineFromPresence: updatePeerOnlineFromPresence,
        refreshActivePeerProfile: refreshActivePeerProfile,
        routes: routes,
        state: state
    };

    if (els.attachMenu) els.attachMenu.hidden = true;
    if (els.attachPreview) els.attachPreview.hidden = true;

    startPeerProfileRealtime();

    loadConversations().then(function () {
        var initial = root.dataset.initialConversation;
        if (initial) openConversation(initial);
    });
})();

