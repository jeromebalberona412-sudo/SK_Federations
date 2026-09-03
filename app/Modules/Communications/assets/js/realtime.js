/**
 * Supabase Realtime: messages, typing, presence, unread badge, call signaling.
 * Requires VITE_SUPABASE_URL + VITE_SUPABASE_PUBLISHABLE_KEY.
 */
import { createClient } from '@supabase/supabase-js';

(function () {
    'use strict';

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

    function getRoot() {
        return document.getElementById('commsApp') || document.getElementById('commsRealtimeBoot');
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
                var row = payload.new || {};
                var root = getRoot();
                var mine = root && Number(row.sender_id) === Number(root.dataset.currentUserId)
                    && row.sender_type === root.dataset.portalUserType;
                if (window.CommsChat) {
                    var isAttachment = row.message_type === 'image' || row.message_type === 'file';
                    if (isAttachment && typeof window.CommsChat.reloadActiveMessages === 'function') {
                        window.CommsChat.reloadActiveMessages();
                    } else {
                        window.CommsChat.appendMessage({
                            id: row.id,
                            conversation_id: row.conversation_id,
                            sender_id: row.sender_id,
                            sender_type: row.sender_type,
                            body: row.body,
                            message_type: row.message_type,
                            mine: !!mine,
                            created_at: row.created_at,
                            reactions: [],
                            attachments: []
                        });
                        window.CommsChat.reloadConversations();
                    }
                }
                if (typeof window.onCommsHeaderMessageInsert === 'function') {
                    window.onCommsHeaderMessageInsert({
                        id: row.id,
                        conversation_id: row.conversation_id,
                        sender_id: row.sender_id,
                        sender_type: row.sender_type,
                        body: row.body,
                        message_type: row.message_type,
                        created_at: row.created_at,
                        reactions: []
                    });
                }
                refreshUnread();
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
            .on('broadcast', { event: 'typing' }, function () {
                if (!window.CommsChat || Number(window.CommsChat.getActiveId()) !== Number(conversationId)) return;
                window.CommsChat.setTyping(true);
                clearTimeout(typingTimers[conversationId]);
                typingTimers[conversationId] = setTimeout(function () {
                    window.CommsChat.setTyping(false);
                }, 1600);
            })
            .on('broadcast', { event: 'webrtc' }, function (payload) {
                handleIncomingSignal(payload);
            })
            .subscribe();
    }

    function broadcastTyping(conversationId) {
        if (!signalChannel || !conversationId) return;
        var now = Date.now();
        if (now - lastTypingSent < 800) return;
        lastTypingSent = now;
        signalChannel.send({
            type: 'broadcast',
            event: 'typing',
            payload: { conversation_id: conversationId }
        });
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
        if (!sb || !root) return;

        var userId = root.dataset.currentUserId;
        presenceChannel = sb.channel('comms-presence', {
            config: { presence: { key: String(userId) } }
        });

        presenceChannel
            .on('presence', { event: 'sync' }, function () { /* peers available via presenceState */ })
            .subscribe(async function (status) {
                if (status === 'SUBSCRIBED') {
                    await presenceChannel.track({
                        user_id: Number(userId),
                        portal: root.dataset.portalUserType,
                        online_at: new Date().toISOString()
                    });
                }
            });

        var routes = (window.CommsChat && window.CommsChat.routes) || {};
        if (routes.presence && window.Comms) {
            var beat = function () {
                window.Comms.api(routes.presence, {
                    method: 'POST',
                    body: JSON.stringify({ online: true }),
                    dedupe: false
                }).catch(function () { /* ignore */ });
            };
            beat();
            setInterval(beat, 60000);
        }
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
                if (window.CommsChat) window.CommsChat.reloadConversations();
                refreshUnread();
            })
            .on('postgres_changes', {
                event: 'UPDATE',
                schema: 'public',
                table: 'messages'
            }, function () {
                if (window.CommsChat) window.CommsChat.reloadConversations();
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
        subscribeConversation: subscribeConversation,
        broadcastTyping: broadcastTyping,
        broadcastSignal: broadcastSignal,
        refreshUnread: refreshUnread
    };

    if (enabled) {
        startPresence();
        startPersonalInbox();
        initGlobalMessageWatch();
    }

    refreshUnread();
    setInterval(refreshUnread, enabled ? 45000 : 20000);
})();
