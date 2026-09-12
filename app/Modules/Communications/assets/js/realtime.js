/**
 * Supabase Realtime: messages, typing, presence, unread badge, call signaling.
 * Requires VITE_SUPABASE_URL + VITE_SUPABASE_PUBLISHABLE_KEY.
 */
import { createClient } from '@supabase/supabase-js';

(function () {
    'use strict';

    if (window.CommsRealtime && window.CommsRealtime.__booted) {
        return;
    }

    var url = import.meta.env.VITE_SUPABASE_URL || '';
    var key = import.meta.env.VITE_SUPABASE_PUBLISHABLE_KEY || '';
    var enabled = !!(url && key);
    var client = null;
    var messageChannel = null;
    var signalChannel = null;
    var presenceChannel = null;
    var inboxChannel = null;
    var typingTimers = Object.create(null);
    var lastTypingSent = 0;
    var activeConversationId = null;
    var localTyping = {
        active: false,
        conversationId: null,
        idleTimer: null
    };

    function getRoot() {
        return document.getElementById('commsApp') || document.getElementById('commsRealtimeBoot');
    }

    function myUserId() {
        var root = getRoot();
        return root ? Number(root.dataset.currentUserId || 0) : 0;
    }

    function myTypingIdentity() {
        var root = getRoot();
        var portal = root ? String(root.dataset.portalUserType || '').trim() : '';
        var name = root ? String(root.dataset.currentUserName || '').trim() : '';
        if (!name) {
            if (portal === 'kabataan') name = 'Kabataan';
            else if (portal === 'sk_official') name = 'SK Official';
            else if (portal === 'sk_fed') name = 'SK Federation';
            else name = 'Someone';
        }
        return {
            user_id: myUserId(),
            display_name: name,
            portal: portal
        };
    }

    function applyTypingUi(conversationId, visible, meta) {
        var pageCid = window.__COMMS_PAGE_ACTIVE_ID__;
        if (pageCid == null && window.CommsChat && typeof window.CommsChat.getActiveId === 'function') {
            // Fallback when page has not set the global yet.
            var maybe = window.CommsChat.getActiveId();
            if (maybe != null && window.__COMMS_HEADER_ACTIVE_ID__ == null) pageCid = maybe;
        }
        var headerCid = window.__COMMS_HEADER_ACTIVE_ID__;
        var pageEl = document.getElementById('commsTyping');
        var modalEl = document.getElementById('commsChatModalTyping');
        var paint = (window.Comms && typeof window.Comms.paintTypingEl === 'function')
            ? window.Comms.paintTypingEl
            : null;

        if (pageEl) {
            if (visible && Number(pageCid) === Number(conversationId)) {
                if (paint) paint(pageEl, true, meta || null);
                else {
                    var who = (meta && meta.name) ? String(meta.name).trim() : '';
                    pageEl.textContent = who ? (who + ' is typing...') : 'Typing...';
                    pageEl.hidden = false;
                }
            } else if (!visible && (pageCid == null || Number(pageCid) === Number(conversationId))) {
                if (paint) paint(pageEl, false);
                else {
                    pageEl.hidden = true;
                    pageEl.textContent = '';
                }
            }
        }
        if (modalEl) {
            if (visible && Number(headerCid) === Number(conversationId)) {
                if (paint) paint(modalEl, true, meta || null);
                else {
                    var whoM = (meta && meta.name) ? String(meta.name).trim() : '';
                    modalEl.textContent = whoM ? (whoM + ' is typing...') : 'Typing...';
                    modalEl.hidden = false;
                }
            } else if (!visible && (headerCid == null || Number(headerCid) === Number(conversationId))) {
                if (paint) paint(modalEl, false);
                else {
                    modalEl.hidden = true;
                    modalEl.textContent = '';
                }
            }
        }

        if (window.CommsChat && typeof window.CommsChat.setTyping === 'function') {
            var active = typeof window.CommsChat.getActiveId === 'function' ? window.CommsChat.getActiveId() : null;
            if (!visible || Number(active) === Number(conversationId)) {
                try { window.CommsChat.setTyping(!!visible, meta || null); } catch (e) { /* ignore */ }
            }
        }
    }

    function sendTypingBroadcast(conversationId, state) {
        if (!signalChannel || !conversationId) return;
        if (Number(activeConversationId) !== Number(conversationId)) return;
        var identity = myTypingIdentity();
        signalChannel.send({
            type: 'broadcast',
            event: 'typing',
            payload: {
                conversation_id: Number(conversationId),
                user_id: identity.user_id,
                display_name: identity.display_name,
                portal: identity.portal,
                state: state
            }
        });
    }

    function stopTyping(conversationId) {
        var cid = conversationId != null ? conversationId : localTyping.conversationId;
        clearTimeout(localTyping.idleTimer);
        localTyping.idleTimer = null;
        if (!cid) {
            localTyping.active = false;
            localTyping.conversationId = null;
            return;
        }
        if (localTyping.active && Number(localTyping.conversationId) === Number(cid)) {
            localTyping.active = false;
            localTyping.conversationId = null;
            sendTypingBroadcast(cid, 'stopped');
        } else {
            localTyping.active = false;
            localTyping.conversationId = null;
        }
    }

    function broadcastTyping(conversationId, opts) {
        opts = opts || {};
        if (!conversationId) return;
        if (opts.stop || opts.forceStop) {
            stopTyping(conversationId);
            return;
        }
        if (!signalChannel || Number(activeConversationId) !== Number(conversationId)) return;

        var now = Date.now();
        var switched = Number(localTyping.conversationId) !== Number(conversationId);
        if (localTyping.active && switched && localTyping.conversationId) {
            sendTypingBroadcast(localTyping.conversationId, 'stopped');
            localTyping.active = false;
        }

        if (!localTyping.active || switched) {
            localTyping.active = true;
            localTyping.conversationId = conversationId;
            sendTypingBroadcast(conversationId, 'started');
            lastTypingSent = now;
        } else if (now - lastTypingSent >= 1200) {
            // Keepalive so peer safety timeout does not clear mid-typing.
            sendTypingBroadcast(conversationId, 'started');
            lastTypingSent = now;
        }

        clearTimeout(localTyping.idleTimer);
        localTyping.idleTimer = setTimeout(function () {
            stopTyping(conversationId);
        }, 1800);
    }

    function myInboxName() {
        var root = getRoot();
        if (!root) return null;
        return 'comms-inbox-' + root.dataset.portalUserType + '-' + root.dataset.currentUserId;
    }

    function peerInboxName(userType, userId) {
        if (!userType || userId == null || userId === '') return null;
        return 'comms-inbox-' + userType + '-' + userId;
    }

    var inboxReloadTimer = null;

    function scheduleInboxReload() {
        window.clearTimeout(inboxReloadTimer);
        inboxReloadTimer = window.setTimeout(function () {
            if (window.CommsChat && typeof window.CommsChat.reloadConversations === 'function') {
                window.CommsChat.reloadConversations();
            }
            if (typeof window.refreshMessagesPopover === 'function') {
                window.refreshMessagesPopover();
            }
            refreshUnread();
        }, 350);
    }

    function normalizeRealtimeMessage(row) {
        var root = getRoot();
        var mine = !!(root && Number(row.sender_id) === Number(root.dataset.currentUserId)
            && row.sender_type === root.dataset.portalUserType);
        return {
            id: row.id,
            conversation_id: row.conversation_id,
            sender_id: row.sender_id,
            sender_type: row.sender_type,
            body: row.body,
            message_type: row.message_type,
            mine: mine,
            created_at: row.created_at,
            edited: !!row.edited_at,
            deleted_for_all: !!row.deleted_for_all_at,
            reactions: [],
            attachments: []
        };
    }

    function deliverRealtimeInsert(row) {
        var message = normalizeRealtimeMessage(row);
        if (window.CommsChat && typeof window.CommsChat.appendMessage === 'function') {
            var isAttachment = row.message_type === 'image' || row.message_type === 'file';
            if (isAttachment && typeof window.CommsChat.reloadActiveMessages === 'function') {
                window.CommsChat.reloadActiveMessages();
            } else {
                window.CommsChat.appendMessage(message);
            }
            if (typeof window.CommsChat.noteInboxActivity === 'function') {
                window.CommsChat.noteInboxActivity(row);
            }
        }
        if (typeof window.onCommsHeaderMessageInsert === 'function') {
            window.onCommsHeaderMessageInsert(message);
        }
        scheduleInboxReload();
    }

    function updateHeaderBadge(count) {
        var badge = document.getElementById('commsMsgBadge');
        if (!badge) return;
        var n = Number(count || 0);
        badge.dataset.unreadTotal = String(n);
        if (n <= 0) {
            badge.style.display = 'none';
            badge.textContent = '0';
            return;
        }
        badge.style.display = '';
        badge.textContent = n > 99 ? '99+' : String(n);
    }

    function refreshUnread() {
        var routes = (window.CommsChat && window.CommsChat.routes) || {};
        var url = routes.unreadCount || '';
        var root = getRoot();
        if (!url && root && root.dataset.unreadCountUrl) {
            url = root.dataset.unreadCountUrl;
        }
        if (!url || !window.Comms) return;
        window.Comms.api(url, { dedupe: true }).then(function (data) {
            updateHeaderBadge(data.unread_count || 0);
        }).catch(function () { /* ignore */ });
    }

    function notifyReactionChange(messageId, meta) {
        if (!messageId) return;
        if (window.CommsChat && typeof window.CommsChat.onReactionChange === 'function') {
            window.CommsChat.onReactionChange(messageId, meta || null);
        }
        if (typeof window.onCommsHeaderReactionChange === 'function') {
            window.onCommsHeaderReactionChange(messageId, meta || null);
        }
    }

    function ensureClient() {
        if (!enabled) return null;
        if (!client) {
            client = createClient(url, key, {
                realtime: { params: { eventsPerSecond: 20 } }
            });
        }
        return client;
    }

    function handleIncomingSignal(raw) {
        var payload = (raw && raw.payload) ? raw.payload : raw;
        if (window.CommsWebRTC && typeof window.CommsWebRTC.handleSignal === 'function') {
            window.CommsWebRTC.handleSignal(payload);
        }
    }

    function subscribeConversation(conversationId) {
        var sb = ensureClient();
        if (!sb || !conversationId) return;

        if (localTyping.active && localTyping.conversationId
            && Number(localTyping.conversationId) !== Number(conversationId)) {
            stopTyping(localTyping.conversationId);
        }

        activeConversationId = conversationId;

        if (messageChannel) {
            sb.removeChannel(messageChannel);
            messageChannel = null;
        }

        messageChannel = sb.channel('comms-messages-' + conversationId)
            .on('postgres_changes', {
                event: 'INSERT',
                schema: 'public',
                table: 'messages',
                filter: 'conversation_id=eq.' + conversationId
            }, function (payload) {
                deliverRealtimeInsert(payload.new || {});
            })
            .on('postgres_changes', {
                event: 'UPDATE',
                schema: 'public',
                table: 'messages',
                filter: 'conversation_id=eq.' + conversationId
            }, function (payload) {
                var updated = payload.new || {};
                if (window.CommsChat && typeof window.CommsChat.onMessageChange === 'function') {
                    window.CommsChat.onMessageChange(updated);
                }
                if (typeof window.onCommsHeaderMessageChange === 'function') {
                    window.onCommsHeaderMessageChange(updated);
                }
            })
            .on('postgres_changes', {
                event: '*',
                schema: 'public',
                table: 'message_reactions'
            }, function (payload) {
                var eventType = String(payload.eventType || '').toUpperCase();
                var row = Object.assign({}, payload.new || payload.old || {});
                if (payload.old && payload.old.emoji && payload.new && payload.new.emoji
                    && payload.old.emoji !== payload.new.emoji) {
                    row._old_emoji = payload.old.emoji;
                }
                if (eventType === 'DELETE' && payload.old) {
                    row = Object.assign({}, payload.old);
                }
                notifyReactionChange(row.message_id, { eventType: eventType, row: row });
            })
            .subscribe();

        if (signalChannel) {
            sb.removeChannel(signalChannel);
            signalChannel = null;
        }

        signalChannel = sb.channel('comms-signal-' + conversationId, {
            config: { broadcast: { self: false } }
        })
            .on('broadcast', { event: 'typing' }, function (raw) {
                var data = (raw && raw.payload) ? raw.payload : (raw || {});
                var cid = Number(data.conversation_id || conversationId);
                if (!cid) return;
                if (data.user_id && Number(data.user_id) === myUserId()) return;

                var pageMatch = Number(window.__COMMS_PAGE_ACTIVE_ID__) === cid;
                var headerMatch = Number(window.__COMMS_HEADER_ACTIVE_ID__) === cid;
                var chatMatch = window.CommsChat && typeof window.CommsChat.getActiveId === 'function'
                    && Number(window.CommsChat.getActiveId()) === cid;
                if (!pageMatch && !headerMatch && !chatMatch) return;

                clearTimeout(typingTimers[cid]);
                if (data.state === 'stopped') {
                    applyTypingUi(cid, false);
                    return;
                }
                applyTypingUi(cid, true, { name: data.display_name || 'Someone', userId: data.user_id });
                typingTimers[cid] = setTimeout(function () {
                    applyTypingUi(cid, false);
                }, 4000);
            })
            .on('broadcast', { event: 'webrtc' }, function (payload) {
                handleIncomingSignal(payload);
            })
            .subscribe();
    }

    function sendOnChannel(channelName, payload) {
        var sb = ensureClient();
        if (!sb || !channelName) return Promise.resolve();

        return new Promise(function (resolve) {
            var ch = sb.channel(channelName, {
                config: { broadcast: { self: false } }
            });
            var settled = false;
            var finish = function () {
                if (settled) return;
                settled = true;
                try { sb.removeChannel(ch); } catch (e) { /* ignore */ }
                resolve();
            };
            var timer = setTimeout(finish, 4000);
            ch.subscribe(function (status) {
                if (status !== 'SUBSCRIBED') return;
                ch.send({ type: 'broadcast', event: 'webrtc', payload: payload })
                    .then(function () {
                        clearTimeout(timer);
                        finish();
                    })
                    .catch(function () {
                        clearTimeout(timer);
                        finish();
                    });
            });
        });
    }

    /**
     * Broadcast WebRTC signal on conversation channel + optional peer inbox.
     * options.peerType / options.peerId → personal inbox so callee receives even if chat not open.
     */
    function broadcastSignal(conversationId, payload, options) {
        options = options || {};
        if (!ensureClient()) return Promise.resolve();

        var jobs = [];

        if (conversationId && signalChannel
            && String(conversationId) === String(activeConversationId || '')) {
            jobs.push(signalChannel.send({
                type: 'broadcast',
                event: 'webrtc',
                payload: payload
            }).catch(function () { /* ignore */ }));
        } else if (conversationId) {
            jobs.push(sendOnChannel('comms-signal-' + conversationId, payload));
        }

        var inbox = peerInboxName(options.peerType, options.peerId);
        if (inbox) {
            jobs.push(sendOnChannel(inbox, payload));
        }

        return Promise.all(jobs);
    }

    function startPresence() {
        var sb = ensureClient();
        var root = getRoot();
        if (!root) return;

        var userId = root.dataset.currentUserId;
        var routes = (window.CommsChat && window.CommsChat.routes) || {};

        function postPresence(online) {
            if (!routes.presence || !window.Comms || typeof window.Comms.api !== 'function') return;
            window.Comms.api(routes.presence, {
                method: 'POST',
                body: JSON.stringify({ online: !!online }),
                dedupe: false
            }).catch(function () { /* ignore */ });
        }

        if (sb) {
            presenceChannel = sb.channel('comms-presence', {
                config: { presence: { key: String(userId) } }
            });

            presenceChannel
                .on('presence', { event: 'sync' }, function () {
                    var stateMap = presenceChannel.presenceState ? presenceChannel.presenceState() : {};
                    if (window.CommsChat && typeof window.CommsChat.updatePeerOnlineFromPresence === 'function') {
                        window.CommsChat.updatePeerOnlineFromPresence(stateMap);
                    }
                    if (typeof window.onCommsHeaderPresence === 'function') {
                        window.onCommsHeaderPresence(stateMap);
                    }
                })
                .on('presence', { event: 'join' }, function () {
                    var stateMap = presenceChannel.presenceState ? presenceChannel.presenceState() : {};
                    if (window.CommsChat && typeof window.CommsChat.updatePeerOnlineFromPresence === 'function') {
                        window.CommsChat.updatePeerOnlineFromPresence(stateMap);
                    }
                    if (typeof window.onCommsHeaderPresence === 'function') {
                        window.onCommsHeaderPresence(stateMap);
                    }
                })
                .on('presence', { event: 'leave' }, function () {
                    var stateMap = presenceChannel.presenceState ? presenceChannel.presenceState() : {};
                    if (window.CommsChat && typeof window.CommsChat.updatePeerOnlineFromPresence === 'function') {
                        window.CommsChat.updatePeerOnlineFromPresence(stateMap);
                    }
                    if (typeof window.onCommsHeaderPresence === 'function') {
                        window.onCommsHeaderPresence(stateMap);
                    }
                })
                .subscribe(async function (status) {
                    if (status === 'SUBSCRIBED') {
                        await presenceChannel.track({
                            user_id: Number(userId),
                            portal: root.dataset.portalUserType,
                            online_at: new Date().toISOString()
                        });
                    }
                });
        }

        postPresence(true);
        setInterval(function () {
            if (document.visibilityState === 'visible') postPresence(true);
        }, 45000);

        document.addEventListener('visibilitychange', function () {
            postPresence(document.visibilityState === 'visible');
        });

        window.addEventListener('pagehide', function () {
            try {
                var token = document.querySelector('meta[name="csrf-token"]');
                fetch(routes.presence, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': token ? token.getAttribute('content') : ''
                    },
                    credentials: 'same-origin',
                    keepalive: true,
                    body: JSON.stringify({ online: false })
                });
            } catch (e) { /* ignore */ }
        });
    }

    function startPersonalInbox() {
        var sb = ensureClient();
        var name = myInboxName();
        if (!sb || !name) return;

        if (inboxChannel) {
            sb.removeChannel(inboxChannel);
            inboxChannel = null;
        }

        inboxChannel = sb.channel(name, {
            config: { broadcast: { self: false } }
        })
            .on('broadcast', { event: 'webrtc' }, function (payload) {
                handleIncomingSignal(payload);
            })
            .subscribe();
    }

    function initGlobalMessageWatch() {
        var sb = ensureClient();
        if (!sb) return;
        sb.channel('comms-messages-global')
            .on('postgres_changes', {
                event: 'INSERT',
                schema: 'public',
                table: 'messages'
            }, function () {
                scheduleInboxReload();
            })
            .on('postgres_changes', {
                event: 'UPDATE',
                schema: 'public',
                table: 'messages'
            }, function () {
                scheduleInboxReload();
            })
            .on('postgres_changes', {
                event: '*',
                schema: 'public',
                table: 'message_reactions'
            }, function (payload) {
                var eventType = String(payload.eventType || '').toUpperCase();
                var row = Object.assign({}, payload.new || payload.old || {});
                if (payload.old && payload.old.emoji && payload.new && payload.new.emoji
                    && payload.old.emoji !== payload.new.emoji) {
                    row._old_emoji = payload.old.emoji;
                }
                if (eventType === 'DELETE' && payload.old) {
                    row = Object.assign({}, payload.old);
                }
                notifyReactionChange(row.message_id, { eventType: eventType, row: row });
            })
            .on('postgres_changes', {
                event: '*',
                schema: 'public',
                table: 'calls'
            }, function (payload) {
                if (window.CommsWebRTC && typeof window.CommsWebRTC.handleCallRow === 'function') {
                    window.CommsWebRTC.handleCallRow(payload);
                }
            })
            .subscribe();
    }

    window.CommsRealtime = {
        enabled: enabled,
        __booted: true,
        subscribeConversation: subscribeConversation,
        broadcastTyping: broadcastTyping,
        stopTyping: stopTyping,
        broadcastSignal: broadcastSignal,
        refreshUnread: refreshUnread
    };

    window.addEventListener('pagehide', function () {
        if (localTyping.active && localTyping.conversationId) {
            stopTyping(localTyping.conversationId);
        }
    });
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'hidden' && localTyping.active && localTyping.conversationId) {
            stopTyping(localTyping.conversationId);
        }
    });

    if (enabled) {
        startPresence();
        startPersonalInbox();
        initGlobalMessageWatch();
    } else {
        // Still heartbeat so peers see accurate online/offline without realtime.
        startPresence();
    }

    refreshUnread();
    setInterval(refreshUnread, enabled ? 45000 : 20000);
})();
