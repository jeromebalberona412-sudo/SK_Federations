/**
 * WebRTC voice/video with Supabase broadcast signaling + Laravel call records.
 * Voice: mic on, camera off (can turn on). Video: camera on (can turn off).
 * Shows visible ringing UI on both sides and a Call ended screen when not picked up.
 */
import {
    mediaConstraints,
    videoOnlyConstraints,
    prepareLocalStream
} from './media-constraints.js';

(function () {
    'use strict';

    if (window.CommsWebRTC) {
        return;
    }

    var RING_TIMEOUT_MS = 30000;
    var CALL_START_DELAY_MS = 3000;
    var CONNECTION_TIMEOUT_MS = 30000;

    var pc = null;
    var localStream = null;
    var currentCall = null;
    var lastCallContext = null;
    var callTimer = null;
    var callStartedAt = null;
    var iceQueue = [];
    var startingCall = false;
    var cameraEnabled = false;
    var wantsVideo = false;
    var callConnected = false;
    var ringTimeout = null;
    var connectionTimeout = null;
    var endedAutoClose = null;
    var role = null; // 'caller' | 'callee'
    var lastEndedCallId = null;
    var pendingStartTimer = null;
    var pendingStartTick = null;
    var pendingStart = null;

    var ringCtx = null;
    var ringTimer = null;
    var ringOscillators = [];

    var HTTPS_PORT_BY_HTTP = {
        '8000': '8440',
        '8080': '8441',
        '8002': '8443'
    };

    var els = {
        incoming: document.getElementById('commsIncomingCall'),
        incomingPeer: document.getElementById('commsIncomingPeer'),
        incomingType: document.getElementById('commsIncomingType'),
        accept: document.getElementById('commsAcceptCall'),
        reject: document.getElementById('commsRejectCall'),
        inCall: document.getElementById('commsInCall'),
        remoteVideo: document.getElementById('commsRemoteVideo'),
        localVideo: document.getElementById('commsLocalVideo'),
        remoteAudio: document.getElementById('commsRemoteAudio'),
        peerName: document.getElementById('commsCallPeerName'),
        callStatus: document.getElementById('commsCallStatus'),
        timer: document.getElementById('commsCallTimer'),
        muteBtn: document.getElementById('commsMuteBtn'),
        cameraBtn: document.getElementById('commsCameraBtn'),
        endBtn: document.getElementById('commsEndCall'),
        ended: document.getElementById('commsCallEnded'),
        endedPeer: document.getElementById('commsCallEndedPeer'),
        endedReason: document.getElementById('commsCallEndedReason'),
        endedTitle: document.getElementById('commsCallEndedTitle'),
        endedClose: document.getElementById('commsCallEndedClose'),
        endedRedial: document.getElementById('commsCallEndedRedial'),
        callAvatar: document.getElementById('commsCallPeerAvatar'),
        callCenter: document.getElementById('commsCallCenter')
    };

    function mountCallUiToBody() {
        ['incoming', 'inCall', 'ended'].forEach(function (key) {
            var node = els[key];
            if (node && node.parentNode !== document.body) {
                document.body.appendChild(node);
            }
        });
        // Re-bind element refs after move (keeps Accept/Decline clicks reliable).
        els.accept = document.getElementById('commsAcceptCall');
        els.reject = document.getElementById('commsRejectCall');
        els.endBtn = document.getElementById('commsEndCall');
        els.endedClose = document.getElementById('commsCallEndedClose');
        els.endedRedial = document.getElementById('commsCallEndedRedial');
        els.muteBtn = document.getElementById('commsMuteBtn');
        els.cameraBtn = document.getElementById('commsCameraBtn');
        els.incoming = document.getElementById('commsIncomingCall');
        els.inCall = document.getElementById('commsInCall');
        els.ended = document.getElementById('commsCallEnded');
        els.callAvatar = document.getElementById('commsCallPeerAvatar');
        els.callCenter = document.getElementById('commsCallCenter');
        els.peerName = document.getElementById('commsCallPeerName');
        els.callStatus = document.getElementById('commsCallStatus');
        els.timer = document.getElementById('commsCallTimer');
    }

    function refreshCallChat(conversationId) {
        if (!window.CommsChat) return;
        if (conversationId && Number(window.CommsChat.getActiveId()) === Number(conversationId)) {
            if (typeof window.CommsChat.reloadActiveMessages === 'function') {
                window.CommsChat.reloadActiveMessages();
                return;
            }
        }
        if (typeof window.CommsChat.reloadConversations === 'function') {
            window.CommsChat.reloadConversations();
        }
    }

    function routes() {
        return (window.CommsChat && window.CommsChat.routes) || {};
    }

    function getCallRoot() {
        return document.getElementById('commsApp') || document.getElementById('commsRealtimeBoot');
    }

    function myIdentity() {
        var root = getCallRoot();
        return {
            id: root ? Number(root.dataset.currentUserId || 0) : 0,
            type: root ? String(root.dataset.portalUserType || '') : '',
            name: root ? String(root.dataset.currentUserName || '') : ''
        };
    }

    function subscribeCallChannel(conversationId) {
        if (!conversationId || !window.CommsRealtime || typeof window.CommsRealtime.ensureCallChannel !== 'function') {
            return Promise.resolve();
        }
        return window.CommsRealtime.ensureCallChannel(conversationId);
    }

    function signalTargets() {
        var me = myIdentity();
        var peerId = null;
        var peerType = null;
        var peers = [];
        if (!currentCall) return { peerId: peerId, peerType: peerType, peers: peers };
        var isGroup = Number(currentCall.caller_id) === Number(currentCall.receiver_id)
            && String(currentCall.caller_type || '') === String(currentCall.receiver_type || '');
        if (!isGroup && currentCall.receiver_id != null && currentCall.caller_id != null) {
            var iAmCaller = Number(currentCall.caller_id) === me.id && String(currentCall.caller_type || '') === me.type;
            if (iAmCaller) {
                peerId = currentCall.receiver_id;
                peerType = currentCall.receiver_type;
            } else {
                peerId = currentCall.caller_id;
                peerType = currentCall.caller_type;
            }
        }
        if (Array.isArray(currentCall.invitees)) {
            peers = currentCall.invitees;
        }
        return { peerId: peerId, peerType: peerType, peers: peers };
    }

    function toast(message, type) {
        if (window.Comms && typeof window.Comms.showToast === 'function') {
            window.Comms.showToast(message, type || 'error');
            return;
        }
        window.alert(message);
    }

    function isSecureMediaContext() {
        if (window.isSecureContext) return true;
        var host = (window.location && window.location.hostname) || '';
        return host === 'localhost' || host === '127.0.0.1' || host === '[::1]';
    }

    function suggestedHttpsUrl() {
        var host = (window.location && window.location.hostname) || 'localhost';
        var httpPort = String((window.location && window.location.port) || '');
        var httpsPort = HTTPS_PORT_BY_HTTP[httpPort] || '8440';
        return 'https://' + host + ':' + httpsPort;
    }

    function resolveMediaDevices() {
        if (navigator.mediaDevices && typeof navigator.mediaDevices.getUserMedia === 'function') {
            return navigator.mediaDevices;
        }

        var legacy = navigator.getUserMedia
            || navigator.webkitGetUserMedia
            || navigator.mozGetUserMedia
            || navigator.msGetUserMedia;

        if (!legacy) {
            return null;
        }

        return {
            getUserMedia: function (constraints) {
                return new Promise(function (resolve, reject) {
                    try {
                        legacy.call(navigator, constraints, resolve, reject);
                    } catch (err) {
                        reject(err);
                    }
                });
            }
        };
    }

    function mediaUnavailableMessage() {
        if (!isSecureMediaContext()) {
            return 'Voice/video needs HTTPS. Open ' + suggestedHttpsUrl()
                + ' (run npm run serve:lan), accept the certificate warning, then try again.';
        }
        return 'Camera/microphone is not available in this browser. Check site permissions and try again.';
    }

    function assertMediaReady() {
        var devices = resolveMediaDevices();
        if (!devices) {
            var err = new Error(mediaUnavailableMessage());
            err.code = 'MEDIA_UNAVAILABLE';
            throw err;
        }
        return devices;
    }

    function clearRingTimeout() {
        if (ringTimeout) {
            clearTimeout(ringTimeout);
            ringTimeout = null;
        }
    }

    function stopRingtone() {
        if (ringTimer) {
            clearInterval(ringTimer);
            ringTimer = null;
        }
        ringOscillators.forEach(function (osc) {
            try { osc.stop(); } catch (e) { /* ignore */ }
        });
        ringOscillators = [];
        if (ringCtx) {
            try { ringCtx.close(); } catch (e) { /* ignore */ }
            ringCtx = null;
        }
    }

    function playToneBurst(ctx, freqs, when, duration, volume) {
        var level = Math.max(0.01, Math.min(Number(volume) || 0.14, 0.2));
        freqs.forEach(function (freq) {
            var osc = ctx.createOscillator();
            var gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.value = freq;
            gain.gain.setValueAtTime(0.0001, when);
            gain.gain.exponentialRampToValueAtTime(level, when + 0.03);
            gain.gain.setValueAtTime(level, when + Math.max(0.06, duration - 0.08));
            gain.gain.exponentialRampToValueAtTime(0.0001, when + duration);
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start(when);
            osc.stop(when + duration + 0.02);
            ringOscillators.push(osc);
        });
    }

    function startRingtone(mode) {
        stopRingtone();
        var AudioCtx = window.AudioContext || window.webkitAudioContext;
        if (!AudioCtx) return;

        try {
            ringCtx = new AudioCtx();
        } catch (e) {
            return;
        }

        if (ringCtx.state === 'suspended') {
            ringCtx.resume().catch(function () { /* ignore */ });
        }

        var incoming = mode === 'incoming';

        function scheduleCycle() {
            if (!ringCtx) return;
            var now = ringCtx.currentTime;
            if (incoming) {
                playToneBurst(ringCtx, [440, 480], now, 0.42, 0.14);
                playToneBurst(ringCtx, [440, 480], now + 0.52, 0.42, 0.14);
            } else {
                playToneBurst(ringCtx, [440, 480], now, 1.15, 0.11);
            }
        }

        scheduleCycle();
        ringTimer = setInterval(scheduleCycle, incoming ? 2800 : 3600);
    }

    function armRingTimeout() {
        clearRingTimeout();
        ringTimeout = setTimeout(function () {
            if (!currentCall || callConnected) return;
            finishUnanswered('missed');
        }, RING_TIMEOUT_MS);
    }

    function clearConnectionTimeout() {
        if (connectionTimeout) {
            clearTimeout(connectionTimeout);
            connectionTimeout = null;
        }
    }

    function armConnectionTimeout() {
        clearConnectionTimeout();
        connectionTimeout = setTimeout(function () {
            if (!currentCall) return;
            var state = pc && (pc.connectionState || pc.iceConnectionState);
            if (state === 'connected' || state === 'completed') return;
            toast('Call could not connect in time.', 'error');
            endCall();
        }, CONNECTION_TIMEOUT_MS);
    }

    function endedCopy(reason, endedByRole) {
        var actor = endedByRole || role;
        switch (reason) {
            case 'missed':
                return {
                    title: 'Call ended',
                    reason: 'Call ended. No pickup.'
                };
            case 'rejected':
                return {
                    title: 'Call declined',
                    reason: role === 'caller' ? 'The other person declined the call.' : 'You declined the call.'
                };
            case 'cancelled':
                return {
                    title: 'Call cancelled',
                    reason: actor === 'caller'
                        ? (role === 'caller' ? 'You cancelled the call.' : 'The caller cancelled the call.')
                        : (role === 'callee' ? 'You cancelled the call.' : 'The other person cancelled the call.')
                };
            case 'offline':
                return {
                    title: 'Call ended',
                    reason: 'The person you are calling is offline.'
                };
            default:
                return {
                    title: 'Call ended',
                    reason: 'The call has ended.'
                };
        }
    }

    function rememberCallContext(call) {
        if (!call) return;
        lastCallContext = {
            conversationId: call.conversation_id,
            callType: call.call_type || 'voice',
            conversation: {
                id: call.conversation_id,
                other_user: call.peer || null
            },
            peerName: (call.peer && call.peer.name) || ''
        };
    }

    function setMuteUi(muted) {
        if (!els.muteBtn) return;
        setCtrlLabel(els.muteBtn, muted ? 'Unmute' : 'Mute');
        els.muteBtn.setAttribute('aria-pressed', muted ? 'true' : 'false');
        els.muteBtn.classList.toggle('is-off', !!muted);
        var iconWrap = els.muteBtn.querySelector('[data-mute-icon]');
        if (!iconWrap) return;
        if (muted) {
            iconWrap.innerHTML = '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">' +
                '<path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/>' +
                '<path d="M19 10v2a7 7 0 0 1-14 0v-2"/>' +
                '<line x1="12" y1="19" x2="12" y2="23"/>' +
                '<line x1="8" y1="23" x2="16" y2="23"/>' +
                '<line x1="4" y1="4" x2="20" y2="20"/>' +
                '</svg>';
        } else {
            iconWrap.innerHTML = '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">' +
                '<path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/>' +
                '<path d="M19 10v2a7 7 0 0 1-14 0v-2"/>' +
                '<line x1="12" y1="19" x2="12" y2="23"/>' +
                '<line x1="8" y1="23" x2="16" y2="23"/>' +
                '</svg>';
        }
    }

    function setCtrlLabel(btn, label) {
        if (!btn) return;
        var labelEl = btn.querySelector('.comms-call-ctrl-label');
        if (labelEl) labelEl.textContent = label;
        btn.setAttribute('aria-label', label);
        btn.removeAttribute('title');
    }

    function setCallPeerAvatar(call) {
        if (!els.callAvatar) return;
        var peer = (call && call.peer) || {};
        var name = peer.name || 'U';
        var src = peer.profile_image_url || '';
        if (src) {
            els.callAvatar.innerHTML = '<img src="' + String(src).replace(/"/g, '&quot;') + '" alt="">';
            return;
        }
        var initial = String(name).trim().charAt(0).toUpperCase() || 'U';
        els.callAvatar.textContent = initial;
    }

    function hideEnded() {
        if (endedAutoClose) {
            clearTimeout(endedAutoClose);
            endedAutoClose = null;
        }
        if (els.ended) els.ended.hidden = true;
        if (els.endedRedial) els.endedRedial.hidden = true;
    }

    function showCallEnded(reason, peerName, callId, endedByRole) {
        if (callId != null && Number(callId) === Number(lastEndedCallId)) {
            return;
        }
        if (callId != null) lastEndedCallId = callId;

        hideIncoming();
        if (els.inCall) els.inCall.hidden = true;
        clearRingTimeout();
        stopRingtone();

        var copy = endedCopy(reason || 'ended', endedByRole);
        if (els.endedTitle) els.endedTitle.textContent = copy.title;
        if (els.endedReason) els.endedReason.textContent = copy.reason;
        if (els.endedPeer) {
            els.endedPeer.textContent = peerName
                || (lastCallContext && lastCallContext.peerName)
                || (currentCall && currentCall.peer && currentCall.peer.name)
                || '';
        }

        var canRedial = !!(lastCallContext && lastCallContext.conversationId)
            && (reason === 'missed' || reason === 'rejected' || reason === 'cancelled' || reason === 'ended');
        if (els.endedRedial) {
            els.endedRedial.hidden = !canRedial;
            els.endedRedial.textContent = role === 'callee' ? 'Call back' : 'Redial';
        }

        if (els.ended) els.ended.hidden = false;

        if (endedAutoClose) clearTimeout(endedAutoClose);
        endedAutoClose = setTimeout(function () {
            hideEnded();
        }, 12000);
    }

    function redialLastCall() {
        if (!lastCallContext || !lastCallContext.conversationId) return;
        var ctx = lastCallContext;
        hideEnded();
        lastEndedCallId = null;
        startCall(ctx.conversationId, ctx.callType || 'voice', ctx.conversation || null);
    }

    function signalPeer(payload) {
        if (!window.CommsRealtime || !currentCall) return Promise.resolve();
        var me = myIdentity();
        payload.from_user_id = me.id;
        payload.from_user_type = me.type;
        payload.from_name = me.name || payload.from_name;
        payload.call_id = payload.call_id || currentCall.id;
        payload.conversation_id = payload.conversation_id || currentCall.conversation_id;
        var targets = signalTargets();
        subscribeCallChannel(currentCall.conversation_id);
        return window.CommsRealtime.broadcastSignal(currentCall.conversation_id, payload, {
            peerId: targets.peerId,
            peerType: targets.peerType,
            peers: targets.peers
        });
    }

    function setCameraUi(enabled) {
        if (!els.cameraBtn) return;
        setCtrlLabel(els.cameraBtn, enabled ? 'Camera on' : 'Camera off');
        els.cameraBtn.setAttribute('aria-pressed', enabled ? 'true' : 'false');
        els.cameraBtn.classList.toggle('is-off', !enabled);
        var iconWrap = els.cameraBtn.querySelector('[data-camera-icon]');
        if (!iconWrap) return;
        if (enabled) {
            iconWrap.innerHTML = '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">' +
                '<polygon points="23 7 16 12 23 17 23 7"/>' +
                '<rect x="1" y="5" width="15" height="14" rx="2" ry="2"/>' +
                '</svg>';
        } else {
            iconWrap.innerHTML = '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">' +
                '<polygon points="23 7 16 12 23 17 23 7"/>' +
                '<rect x="1" y="5" width="15" height="14" rx="2" ry="2"/>' +
                '<line x1="4" y1="4" x2="20" y2="20"/>' +
                '</svg>';
        }
    }

    function hasLiveRemoteVideo() {
        var stream = els.remoteVideo && els.remoteVideo.srcObject;
        if (!stream || typeof stream.getVideoTracks !== 'function') return false;
        return stream.getVideoTracks().some(function (track) {
            return !!(track && track.readyState === 'live' && track.enabled !== false);
        });
    }

    function updateCameraUi() {
        if (els.cameraBtn) {
            els.cameraBtn.hidden = false;
            setCameraUi(!!cameraEnabled);
        }
        if (els.localVideo) {
            els.localVideo.hidden = !cameraEnabled;
        }
        var remoteVideoLive = hasLiveRemoteVideo();
        if (els.inCall) {
            els.inCall.classList.toggle('is-connected', !!callConnected);
            els.inCall.classList.toggle('has-remote-video', remoteVideoLive);
            els.inCall.classList.toggle('is-video-call', !!cameraEnabled || remoteVideoLive);
        }
        if (els.callCenter) {
            els.callCenter.hidden = false;
            els.callCenter.removeAttribute('hidden');
            els.callCenter.classList.toggle('is-compact', !!callConnected && remoteVideoLive);
        }
        syncLocalPipLayout();
    }

    function syncLocalPipLayout() {
        if (!els.inCall || !els.localVideo) return;
        var landscape = false;
        try {
            landscape = window.matchMedia('(orientation: landscape)').matches;
        } catch (e) {
            landscape = window.innerWidth > window.innerHeight;
        }
        var desktop = window.innerWidth >= 1024;
        els.inCall.classList.toggle('is-landscape', landscape);
        els.inCall.classList.toggle('is-desktop-call', desktop);

        var camLandscape = landscape;
        try {
            var track = localStream && localStream.getVideoTracks && localStream.getVideoTracks()[0];
            var settings = track && typeof track.getSettings === 'function' ? track.getSettings() : null;
            if (settings && settings.width && settings.height) {
                camLandscape = (Number(settings.width) / Number(settings.height)) >= 1.15;
            }
        } catch (e2) { /* ignore */ }
        els.localVideo.classList.toggle('is-cam-landscape', camLandscape);
        els.localVideo.classList.toggle('is-cam-portrait', !camLandscape);
    }

    function ensureRemoteAudioPlaying() {
        if (!els.remoteAudio) return;
        try {
            els.remoteAudio.muted = false;
            els.remoteAudio.volume = 1;
            var playPromise = els.remoteAudio.play();
            if (playPromise && typeof playPromise.catch === 'function') {
                playPromise.catch(function () {});
            }
        } catch (e) { /* ignore */ }
    }

    function applyRemoteVideoMetrics() {
        if (!els.remoteVideo || !els.inCall) return;
        var w = Number(els.remoteVideo.videoWidth || 0);
        var h = Number(els.remoteVideo.videoHeight || 0);
        if (!w || !h) return;
        var portrait = (w / h) < 0.92;
        els.remoteVideo.classList.toggle('is-remote-portrait', portrait);
        els.remoteVideo.classList.toggle('is-remote-landscape', !portrait);
        els.inCall.classList.toggle('is-remote-portrait', portrait);
        els.inCall.classList.toggle('is-remote-landscape', !portrait);
    }

    var remoteStream = null;

    function mergeRemoteTrack(track, inboundStream) {
        if (!remoteStream) remoteStream = new MediaStream();
        function add(next) {
            if (!next || remoteStream.getTracks().some(function (existing) { return existing.id === next.id; })) return;
            remoteStream.addTrack(next);
        }
        add(track);
        if (inboundStream && typeof inboundStream.getTracks === 'function') {
            inboundStream.getTracks().forEach(add);
        }
        return remoteStream;
    }

    function bindRemoteMedia(stream) {
        if (!stream) return;
        if (els.remoteVideo) {
            els.remoteVideo.muted = true;
            els.remoteVideo.setAttribute('muted', '');
            els.remoteVideo.playsInline = true;
            els.remoteVideo.autoplay = true;
            if (els.remoteVideo.srcObject !== stream) {
                els.remoteVideo.srcObject = stream;
            }
            try {
                var vp = els.remoteVideo.play();
                if (vp && typeof vp.catch === 'function') vp.catch(function () {});
            } catch (e) { /* ignore */ }
            if (!els.remoteVideo._commsMetricsBound) {
                els.remoteVideo._commsMetricsBound = true;
                els.remoteVideo.addEventListener('loadedmetadata', applyRemoteVideoMetrics);
                els.remoteVideo.addEventListener('resize', applyRemoteVideoMetrics);
            }
            applyRemoteVideoMetrics();
        }
        if (els.remoteAudio) {
            var audioTracks = typeof stream.getAudioTracks === 'function' ? stream.getAudioTracks() : [];
            els.remoteAudio.muted = false;
            els.remoteAudio.autoplay = true;
            els.remoteAudio.srcObject = new MediaStream(audioTracks);
            ensureRemoteAudioPlaying();
        }
        updateCameraUi();
    }

    function setCallStatus(text) {
        if (els.callStatus) els.callStatus.textContent = text || '';
        if (els.timer) {
            els.timer.hidden = !callConnected;
        }
    }

    async function getLocalStream(videoEnabled) {
        var devices = assertMediaReady();
        try {
            var stream = await devices.getUserMedia(mediaConstraints(!!videoEnabled));
            if (videoEnabled) {
                await prepareLocalStream(stream);
            }
            return stream;
        } catch (err) {
            // Fallback: simpler constraints if the device rejects aspect/ideal combo.
            if (videoEnabled) {
                try {
                    var fallback = await devices.getUserMedia({
                        audio: mediaConstraints(false).audio,
                        video: { facingMode: 'user' }
                    });
                    await prepareLocalStream(fallback);
                    return fallback;
                } catch (e2) {
                    err = e2;
                }
            }
            var name = (err && err.name) || '';
            if (name === 'NotAllowedError' || name === 'PermissionDeniedError') {
                throw new Error('Microphone/camera permission was denied. Allow access and try again.');
            }
            if (name === 'NotFoundError' || name === 'DevicesNotFoundError') {
                throw new Error(videoEnabled
                    ? 'No camera or microphone was found on this device.'
                    : 'No microphone was found on this device.');
            }
            if (name === 'NotReadableError' || name === 'TrackStartError') {
                throw new Error('Camera/microphone is already in use by another app.');
            }
            throw new Error((err && err.message) || mediaUnavailableMessage());
        }
    }

    function stunConfig() {
        return {
            iceServers: [
                { urls: 'stun:stun.l.google.com:19302' },
                { urls: 'stun:stun1.l.google.com:19302' }
            ]
        };
    }

    var dismissedRingIds = Object.create(null);

    function showIncoming(call) {
        if (call && call.id != null && dismissedRingIds[Number(call.id)]) return;
        els.incoming = document.getElementById('commsIncomingCall') || els.incoming;
        els.incomingPeer = document.getElementById('commsIncomingPeer') || els.incomingPeer;
        els.incomingType = document.getElementById('commsIncomingType') || els.incomingType;
        if (!els.incoming || !call) return;
        role = 'callee';
        callConnected = false;
        hideEnded();
        if (els.incomingPeer) {
            els.incomingPeer.textContent = (call.peer && call.peer.name) || 'Incoming caller';
        }
        if (els.incomingType) {
            var groupLabel = !!(call.is_group_call || call._groupInvite);
            els.incomingType.textContent = groupLabel
                ? (call.call_type === 'video' ? 'Group video call' : 'Group voice call')
                : (call.call_type === 'video' ? 'Video call' : 'Voice call');
        }
        if (call.is_group_call || call._groupInvite) {
            window.__commsPendingGroupInvite = call._groupInvite || {
                call_id: call.id,
                conversation_id: call.conversation_id,
                call_type: call.call_type || 'video',
                caller_id: call.caller_id,
                caller_type: call.caller_type,
                peer_name: (call.peer && call.peer.name) || call.peer_name || 'Group call',
                invitees: call.invitees || [],
                call_max_participants: call.call_max_participants
            };
            // Keep currentCall null so 1:1 cleanup does not steal the group room.
            currentCall = null;
            els.incoming.hidden = false;
            els.incoming.removeAttribute('hidden');
            els.incoming.style.display = 'flex';
            startRingtone('incoming');
            clearRingTimeout();
            ringTimeout = setTimeout(function () {
                if (call.id != null) dismissedRingIds[Number(call.id)] = true;
                hideIncoming();
                stopRingtone();
                window.__commsPendingGroupInvite = null;
            }, RING_TIMEOUT_MS);
            return;
        }
        currentCall = Object.assign({}, currentCall || {}, call);
        rememberCallContext(currentCall);
        els.incoming.hidden = false;
        els.incoming.removeAttribute('hidden');
        els.incoming.style.display = 'flex';
        startRingtone('incoming');
        armRingTimeout();
    }

    function hideIncoming() {
        if (els.incoming) els.incoming.hidden = true;
        // Ringtone may continue for outgoing ring UI; stop only if not showing in-call ringing.
    }

    function formatTimer(ms) {
        var total = Math.floor(ms / 1000);
        var m = Math.floor(total / 60);
        var s = total % 60;
        return String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
    }

    function startTimer() {
        if (callTimer) {
            if (els.timer && callStartedAt) {
                els.timer.textContent = formatTimer(Date.now() - callStartedAt);
            }
            return;
        }
        if (!callStartedAt) {
            callStartedAt = Date.now();
        }
        if (els.timer) {
            els.timer.textContent = formatTimer(Date.now() - callStartedAt);
        }
        callTimer = setInterval(function () {
            if (els.timer && callStartedAt) {
                els.timer.textContent = formatTimer(Date.now() - callStartedAt);
            }
        }, 1000);
    }

    async function ensurePeerConnection(videoEnabled) {
        if (pc) return pc;

        if (typeof RTCPeerConnection === 'undefined') {
            throw new Error('WebRTC is not supported in this browser.');
        }

        wantsVideo = !!videoEnabled;
        cameraEnabled = !!videoEnabled;
        localStream = await getLocalStream(!!videoEnabled);

        pc = new RTCPeerConnection(stunConfig());
        pc.onicecandidate = function (ev) {
            if (!ev.candidate || !currentCall) return;
            signalPeer({
                type: 'ice',
                call_id: currentCall.id,
                conversation_id: currentCall.conversation_id,
                candidate: ev.candidate
            });
        };
        pc.ontrack = function (ev) {
            var merged = mergeRemoteTrack(
                ev.track,
                ev.streams && ev.streams[0] ? ev.streams[0] : null
            );
            bindRemoteMedia(merged);
            if (ev.track && ev.track.kind === 'audio') {
                ensureRemoteAudioPlaying();
            }
        };
        pc.oniceconnectionstatechange = function () {
            var st = pc && pc.iceConnectionState;
            if (st === 'connected' || st === 'completed') {
                callConnected = true;
                clearConnectionTimeout();
                setCallStatus('Connected');
                updateCameraUi();
            }
            if (st === 'failed') {
                if (!currentCall) return;
                toast('Call connection failed.', 'error');
                endCall();
            }
        };
        pc.onconnectionstatechange = function () {
            if (pc && pc.connectionState === 'failed') {
                if (!currentCall) return;
                toast('Call connection failed.', 'error');
                endCall();
            }
        };

        localStream.getTracks().forEach(function (track) {
            pc.addTrack(track, localStream);
        });
        if (els.localVideo) {
            els.localVideo.srcObject = localStream;
            syncLocalPipLayout();
        }
        if (els.muteBtn) setMuteUi(false);
        updateCameraUi();
        return pc;
    }

    async function setCameraEnabled(enabled) {
        if (!pc || !localStream) return;
        enabled = !!enabled;

        var videoTracks = localStream.getVideoTracks();
        if (enabled) {
            if (!videoTracks.length || videoTracks.every(function (t) { return t.readyState === 'ended'; })) {
                var devices = assertMediaReady();
                var camStream = await devices.getUserMedia(videoOnlyConstraints());
                await prepareLocalStream(camStream);
                var newTrack = camStream.getVideoTracks()[0];
                if (!newTrack) {
                    throw new Error('No camera was found on this device.');
                }
                localStream.addTrack(newTrack);
                var sender = pc.getSenders().find(function (s) {
                    return s.track && s.track.kind === 'video';
                });
                if (sender) {
                    await sender.replaceTrack(newTrack);
                } else {
                    pc.addTrack(newTrack, localStream);
                }
                if (els.localVideo) els.localVideo.srcObject = localStream;
            } else {
                videoTracks.forEach(function (t) { t.enabled = true; });
            }
            cameraEnabled = true;
        } else {
            videoTracks.forEach(function (t) { t.enabled = false; });
            cameraEnabled = false;
        }

        updateCameraUi();

        if (currentCall) {
            signalPeer({
                type: 'camera',
                call_id: currentCall.id,
                conversation_id: currentCall.conversation_id,
                enabled: cameraEnabled
            });
        }
    }

    function showInCall(call, statusText) {
        if (!els.inCall) return;
        hideEnded();
        if (els.incoming) els.incoming.hidden = true;
        els.inCall.hidden = false;
        if (els.peerName) {
            els.peerName.textContent = (call.peer && call.peer.name) || 'Call';
        }
        setCallPeerAvatar(call);
        setCallStatus(statusText || (callConnected ? 'Connected' : 'Calling...'));
        if (els.timer) els.timer.textContent = callConnected ? (els.timer.textContent || '00:00') : '00:00';
        if (callConnected && !callStartedAt) {
            startTimer();
        }
        setMuteUi(false);
        updateCameraUi();
        syncLocalPipLayout();
        ensureRemoteAudioPlaying();
    }

    async function cleanupMediaOnly() {
        clearPendingStart();
        clearRingTimeout();
        clearConnectionTimeout();
        if (ringTimer || ringCtx) stopRingtone();
        clearInterval(callTimer);
        callTimer = null;
        callStartedAt = null;
        cameraEnabled = false;
        wantsVideo = false;
        callConnected = false;
        if (els.endBtn) setCtrlLabel(els.endBtn, 'End');
        if (els.muteBtn) {
            els.muteBtn.hidden = false;
            setMuteUi(false);
        }
        if (els.cameraBtn) els.cameraBtn.hidden = false;
        if (localStream) {
            localStream.getTracks().forEach(function (t) { t.stop(); });
            localStream = null;
        }
        if (pc) {
            try { pc.close(); } catch (e) { /* ignore */ }
            pc = null;
        }
        iceQueue = [];
        if (els.remoteVideo) els.remoteVideo.srcObject = null;
        if (els.localVideo) els.localVideo.srcObject = null;
        if (els.remoteAudio) els.remoteAudio.srcObject = null;
        remoteStream = null;
        if (els.inCall) {
            els.inCall.hidden = true;
            els.inCall.classList.remove('is-connected', 'has-remote-video', 'is-video-call', 'is-remote-portrait', 'is-remote-landscape');
        }
        if (els.remoteVideo) {
            els.remoteVideo.classList.remove('is-remote-portrait', 'is-remote-landscape');
        }
        if (window.CommsRealtime && typeof window.CommsRealtime.teardownCallChannel === 'function') {
            window.CommsRealtime.teardownCallChannel();
        }
        if (els.callCenter) {
            els.callCenter.classList.remove('is-compact');
            els.callCenter.hidden = false;
        }
        if (els.incoming) els.incoming.hidden = true;
    }

    function publishCallNotice(data) {
        var notice = data && data.message;
        if (!notice || !window.CommsChat || typeof window.CommsChat.publishSentMessage !== 'function') return;
        var peers = (data.call && data.call.invitees) || [];
        window.CommsChat.publishSentMessage(notice, peers);
        if (typeof window.CommsChat.appendMessage === 'function') {
            window.CommsChat.appendMessage(notice);
        }
    }

    async function updateCallStatus(callId, status) {
        var r = routes();
        if (!r.callStatus || !window.Comms) return null;
        return window.Comms.api(window.Comms.route(r.callStatus, callId), {
            method: 'POST',
            body: JSON.stringify({ status: status }),
            dedupe: false
        }).then(function (data) {
            publishCallNotice(data);
            return data.call;
        });
    }

    async function finishUnanswered(reason) {
        if (!currentCall || callConnected) return;
        var callSnapshot = currentCall;
        rememberCallContext(callSnapshot);
        var peerName = (callSnapshot.peer && callSnapshot.peer.name) || '';
        var status = reason === 'rejected' ? 'rejected'
            : (reason === 'cancelled' ? 'cancelled' : 'missed');

        clearRingTimeout();
        stopRingtone();

        var signalPromise = signalPeer({
            type: 'hangup',
            reason: status,
            ended_by_role: role || (status === 'cancelled' ? 'caller' : 'callee'),
            call_id: callSnapshot.id,
            conversation_id: callSnapshot.conversation_id,
            caller_id: callSnapshot.caller_id,
            caller_type: callSnapshot.caller_type,
            receiver_id: callSnapshot.receiver_id,
            receiver_type: callSnapshot.receiver_type
        });
        currentCall = null;
        await cleanupMediaOnly();
        showCallEnded(status, peerName, callSnapshot.id);
        refreshCallChat(callSnapshot.conversation_id);
        Promise.all([
            signalPromise.catch(function () {}),
            callSnapshot.id ? updateCallStatus(callSnapshot.id, status).catch(function () {}) : Promise.resolve()
        ]);
    }

    function clearPendingStart() {
        if (pendingStartTimer) {
            clearTimeout(pendingStartTimer);
            pendingStartTimer = null;
        }
        if (pendingStartTick) {
            clearInterval(pendingStartTick);
            pendingStartTick = null;
        }
        pendingStart = null;
    }

    async function cancelPendingStart() {
        if (!pendingStart) return false;
        clearPendingStart();
        role = null;
        currentCall = null;
        await cleanupMediaOnly();
        hideEnded();
        toast('Call cancelled.', 'info');
        return true;
    }

    function beginCallCountdown(conversationId, callType, conversation, receiverId) {
        if (startingCall || pendingStart || currentCall) return;

        var peer = (conversation && conversation.other_user) || {};
        var peerStatus = String(peer.online_status || '').toLowerCase();
        if (peerStatus && peerStatus !== 'online') {
            toast((peer.name || 'This contact') + ' is offline. Call ended.', 'error');
            showCallEnded('offline', peer.name || 'Contact', null);
            setCallStatus('Offline — call ended');
            return;
        }

        try {
            assertMediaReady();
        } catch (err) {
            toast(err.message || 'Unable to start call.', 'error');
            return;
        }
        if (!window.CommsRealtime || !window.CommsRealtime.enabled) {
            toast('Realtime is not configured. Rebuild after setting VITE_SUPABASE keys.', 'error');
            return;
        }

        role = 'caller';
        callConnected = false;
        lastEndedCallId = null;
        pendingStart = {
            conversationId: conversationId,
            callType: callType,
            conversation: conversation,
            receiverId: receiverId || null
        };

        peer = peer.name ? peer : { name: 'Contact' };
        showInCall({ peer: peer }, 'Starting in 5...');
        if (els.muteBtn) els.muteBtn.hidden = true;
        if (els.cameraBtn) els.cameraBtn.hidden = true;
        setCtrlLabel(els.endBtn, 'Cancel');

        var remaining = Math.ceil(CALL_START_DELAY_MS / 1000);
        setCallStatus('Starting in ' + remaining + 's — tap Cancel to stop');
        pendingStartTick = setInterval(function () {
            remaining -= 1;
            if (remaining <= 0) return;
            setCallStatus('Starting in ' + remaining + 's — tap Cancel to stop');
        }, 1000);

        pendingStartTimer = setTimeout(function () {
            var queuedReceiverId = pendingStart ? pendingStart.receiverId : null;
            clearPendingStart();
            if (els.muteBtn) els.muteBtn.hidden = false;
            if (els.cameraBtn) els.cameraBtn.hidden = false;
            setCtrlLabel(els.endBtn, 'End');
            placeCall(conversationId, callType, conversation, queuedReceiverId);
        }, CALL_START_DELAY_MS);
    }

    async function placeCall(conversationId, callType, conversation, receiverId) {
        if (!window.Comms || startingCall) return;
        startingCall = true;
        var r = routes();
        try {
            assertMediaReady();
            if (!window.CommsRealtime || !window.CommsRealtime.enabled) {
                throw new Error('Realtime is not configured. Rebuild after setting VITE_SUPABASE keys.');
            }

            var body = { call_type: callType };
            if (receiverId) {
                body.receiver_id = Number(receiverId);
            }
            var data = await window.Comms.api(window.Comms.route(r.startCall, conversationId), {
                method: 'POST',
                body: JSON.stringify(body),
                dedupe: false
            });
            role = 'caller';
            callConnected = false;
            lastEndedCallId = null;
            currentCall = data.call;
            publishCallNotice(data);
            wantsVideo = callType === 'video';
            if (conversation && conversation.other_user && !conversation.other_user.is_group) {
                currentCall.peer = conversation.other_user;
            }
            rememberCallContext(currentCall);
            await subscribeCallChannel(conversationId);
            if (els.muteBtn) els.muteBtn.hidden = false;
            if (els.cameraBtn) els.cameraBtn.hidden = false;
            setCtrlLabel(els.endBtn, 'End');
            showInCall(Object.assign({}, currentCall, {
                peer: currentCall.peer || (conversation && conversation.other_user) || { name: 'Contact' }
            }), 'Calling...');
            startRingtone('outgoing');
            armRingTimeout();
            await ensurePeerConnection(wantsVideo);
            var offer = await pc.createOffer({
                offerToReceiveAudio: true,
                offerToReceiveVideo: true
            });
            await pc.setLocalDescription(offer);
            await signalPeer({
                type: 'offer',
                call_id: currentCall.id,
                conversation_id: conversationId,
                sdp: offer,
                call_type: callType,
                peer_name: document.getElementById('commsPeerName')?.textContent || 'Caller',
                caller_id: currentCall.caller_id,
                caller_type: currentCall.caller_type,
                receiver_id: currentCall.receiver_id,
                receiver_type: currentCall.receiver_type
            });
            setCallStatus('Ringing...');
            refreshCallChat(conversationId);
        } catch (err) {
            toast(err.message || 'Unable to start call.', 'error');
            if (currentCall && currentCall.id) {
                try { await updateCallStatus(currentCall.id, 'cancelled'); } catch (e) { /* ignore */ }
            }
            currentCall = null;
            await cleanupMediaOnly();
            hideEnded();
        } finally {
            startingCall = false;
        }
    }

    async function startCall(conversationId, callType, conversation, receiverId) {
        // Groups skip offline peer check — recipient is chosen explicitly.
        if (conversation && (conversation.conversation_type === 'group' || conversation.is_default_group || (conversation.other_user && conversation.other_user.is_group))) {
            if (startingCall || pendingStart || currentCall) return;
            try {
                assertMediaReady();
            } catch (err) {
                toast(err.message || 'Unable to start call.', 'error');
                return;
            }
            if (!window.CommsRealtime || !window.CommsRealtime.enabled) {
                toast('Realtime is not configured. Rebuild after setting VITE_SUPABASE keys.', 'error');
                return;
            }
            placeCall(conversationId, callType, conversation, receiverId);
            return;
        }
        beginCallCountdown(conversationId, callType, conversation, receiverId);
    }

    function waitForRemoteOffer(ms) {
        return new Promise(function (resolve) {
            if (currentCall && currentCall._remoteOffer) {
                resolve(true);
                return;
            }
            var started = Date.now();
            var timer = setInterval(function () {
                if (currentCall && currentCall._remoteOffer) {
                    clearInterval(timer);
                    resolve(true);
                    return;
                }
                if (Date.now() - started >= (ms || 8000)) {
                    clearInterval(timer);
                    resolve(false);
                }
            }, 50);
        });
    }

    async function acceptIncoming() {
        if (typeof window.CommsWebRTC !== 'undefined'
            && typeof window.CommsWebRTC.__acceptGroupIfPending === 'function') {
            try {
                var groupHandled = await window.CommsWebRTC.__acceptGroupIfPending();
                if (groupHandled) {
                    hideIncoming();
                    stopRingtone();
                    clearRingTimeout();
                    return;
                }
            } catch (err) {
                toast((err && err.message) || 'Unable to join group call.', 'error');
                return;
            }
        }
        if (!currentCall || startingCall) return;
        startingCall = true;
        clearRingTimeout();
        stopRingtone();
        hideIncoming();
        try {
            assertMediaReady();
            await subscribeCallChannel(currentCall.conversation_id);
            callConnected = true;
            showInCall(currentCall, 'Connecting...');
            armConnectionTimeout();
            signalPeer({
                type: 'call-accepted',
                call_id: currentCall.id,
                conversation_id: currentCall.conversation_id,
                caller_id: currentCall.caller_id,
                caller_type: currentCall.caller_type,
                receiver_id: currentCall.receiver_id,
                receiver_type: currentCall.receiver_type
            });
            updateCallStatus(currentCall.id, 'accepted').then(function (accepted) {
                if (accepted && currentCall && Number(currentCall.id) === Number(accepted.id)) {
                    currentCall = Object.assign({}, currentCall, accepted);
                }
            }).catch(function () {});
            wantsVideo = currentCall.call_type === 'video';
            await ensurePeerConnection(wantsVideo);
            if (!currentCall._remoteOffer) {
                await waitForRemoteOffer(8000);
            }
            if (currentCall._remoteOffer) {
                await pc.setRemoteDescription(currentCall._remoteOffer);
                var answer = await pc.createAnswer();
                await pc.setLocalDescription(answer);
                await signalPeer({
                    type: 'answer',
                    call_id: currentCall.id,
                    conversation_id: currentCall.conversation_id,
                    sdp: answer,
                    caller_id: currentCall.caller_id,
                    caller_type: currentCall.caller_type,
                    receiver_id: currentCall.receiver_id,
                    receiver_type: currentCall.receiver_type
                });
                while (iceQueue.length) {
                    await pc.addIceCandidate(iceQueue.shift());
                }
                setCallStatus('Connected');
                startTimer();
                refreshCallChat(currentCall.conversation_id);
            } else {
                toast('Call signal was incomplete. Ask the caller to try again.', 'error');
                await updateCallStatus(currentCall.id, 'ended');
                var peerName = (currentCall.peer && currentCall.peer.name) || '';
                var incompleteId = currentCall.id;
                var incompleteConv = currentCall.conversation_id;
                currentCall = null;
                await cleanupMediaOnly();
                showCallEnded('ended', peerName, incompleteId);
                refreshCallChat(incompleteConv);
            }
        } catch (err) {
            toast(err.message || 'Unable to accept call.', 'error');
            if (currentCall && currentCall.id) {
                try { await updateCallStatus(currentCall.id, 'rejected'); } catch (e) { /* ignore */ }
            }
            var peer = (currentCall && currentCall.peer && currentCall.peer.name) || '';
            var failId = currentCall && currentCall.id;
            var failConv = currentCall && currentCall.conversation_id;
            currentCall = null;
            await cleanupMediaOnly();
            showCallEnded('rejected', peer, failId);
            refreshCallChat(failConv);
        } finally {
            startingCall = false;
        }
    }

    async function rejectIncoming() {
        if (typeof window.CommsWebRTC !== 'undefined'
            && typeof window.CommsWebRTC.__rejectGroupIfPending === 'function') {
            try {
                var groupRejected = await window.CommsWebRTC.__rejectGroupIfPending();
                if (groupRejected) {
                    hideIncoming();
                    stopRingtone();
                    clearRingTimeout();
                    return;
                }
            } catch (e) { /* fall through */ }
        }
        if (!currentCall) return;
        await finishUnanswered('rejected');
    }

    async function endCall() {
        if (typeof window.CommsWebRTC !== 'undefined'
            && typeof window.CommsWebRTC.__meshActive === 'function'
            && window.CommsWebRTC.__meshActive()
            && typeof window.CommsWebRTC.__meshLeave === 'function') {
            return window.CommsWebRTC.__meshLeave('ended');
        }

        if (pendingStart) {
            await cancelPendingStart();
            setCtrlLabel(els.endBtn, 'End');
            return;
        }

        if (!currentCall) {
            await cleanupMediaOnly();
            return;
        }

        if (!callConnected) {
            await finishUnanswered(role === 'caller' ? 'cancelled' : 'rejected');
            return;
        }

        var peerName = (currentCall.peer && currentCall.peer.name) || '';
        var callSnapshot = currentCall;
        rememberCallContext(callSnapshot);
        stopRingtone();
        var signalPromise = signalPeer({
            type: 'hangup',
            reason: 'ended',
            ended_by_role: role || 'caller',
            call_id: callSnapshot.id,
            conversation_id: callSnapshot.conversation_id,
            caller_id: callSnapshot.caller_id,
            caller_type: callSnapshot.caller_type,
            receiver_id: callSnapshot.receiver_id,
            receiver_type: callSnapshot.receiver_type
        });
        currentCall = null;
        await cleanupMediaOnly();
        showCallEnded('ended', peerName, callSnapshot.id);
        refreshCallChat(callSnapshot.conversation_id);
        Promise.all([
            signalPromise.catch(function () {}),
            updateCallStatus(callSnapshot.id, 'ended').catch(function () {})
        ]);
    }

    function sameCall(payload) {
        if (!payload || payload.call_id == null || !currentCall) return true;
        return Number(currentCall.id) === Number(payload.call_id);
    }

    function applyPeerAnswered() {
        stopRingtone();
        clearRingTimeout();
        callConnected = true;
        setCallStatus('Connecting...');
        armConnectionTimeout();
        updateCameraUi();
    }

    async function handleSignal(payload) {
        if (!payload || !payload.type) return;

        if (payload.type === 'offer') {
            if (role === 'caller' && sameCall(payload)) return;
            if (currentCall && currentCall._remoteOffer && Number(currentCall.id) === Number(payload.call_id)) {
                currentCall._remoteOffer = payload.sdp;
                return;
            }
            lastEndedCallId = null;
            currentCall = Object.assign({}, currentCall || {}, {
                id: payload.call_id,
                conversation_id: payload.conversation_id || (window.CommsChat && window.CommsChat.getActiveId()),
                call_type: payload.call_type || 'voice',
                peer: { name: payload.peer_name || payload.from_name || 'Incoming caller' },
                caller_id: payload.caller_id,
                caller_type: payload.caller_type,
                receiver_id: payload.receiver_id,
                receiver_type: payload.receiver_type,
                _remoteOffer: payload.sdp
            });
            subscribeCallChannel(currentCall.conversation_id);
            showIncoming(currentCall);
            return;
        }

        if (payload.type === 'call-accepted' || payload.type === 'accepted') {
            if (role !== 'caller' || !sameCall(payload)) return;
            applyPeerAnswered();
            return;
        }

        if (payload.type === 'hangup') {
            if (currentCall && payload.call_id != null && Number(currentCall.id) !== Number(payload.call_id)) {
                return;
            }
            var reason = payload.reason || 'ended';
            var peerName = (currentCall && currentCall.peer && currentCall.peer.name) || '';
            var hangupId = (currentCall && currentCall.id) || payload.call_id;
            var hangupConv = (currentCall && currentCall.conversation_id) || payload.conversation_id;
            currentCall = null;
            await cleanupMediaOnly();
            showCallEnded(reason, peerName, hangupId, payload.ended_by_role);
            refreshCallChat(hangupConv);
            return;
        }

        if (payload.type === 'camera') {
            return;
        }

        if (payload.type === 'answer') {
            if (role !== 'caller' || !sameCall(payload)) return;
            applyPeerAnswered();
            setCallStatus('Connected');
            if (els.timer && !callStartedAt) {
                startTimer();
            }
            if (pc && payload.sdp && pc.signalingState === 'have-local-offer') {
                try {
                    await pc.setRemoteDescription(payload.sdp);
                    while (iceQueue.length) {
                        await pc.addIceCandidate(iceQueue.shift());
                    }
                } catch (e) { /* ignore duplicate/stale SDP */ }
            }
            refreshCallChat(currentCall && currentCall.conversation_id);
            return;
        }

        if (payload.type === 'ice' && payload.candidate) {
            if (!sameCall(payload)) return;
            if (!pc || !pc.remoteDescription) {
                iceQueue.push(payload.candidate);
            } else {
                try { await pc.addIceCandidate(payload.candidate); } catch (e) { /* ignore */ }
            }
        }
    }

    function handleCallRow(payload) {
        var row = (payload && payload.new) || null;
        if (!row) return;
        var root = getCallRoot();
        if (!root) return;
        var myId = Number(root.dataset.currentUserId);
        var myType = root.dataset.portalUserType;
        var isReceiver = Number(row.receiver_id) === myId && row.receiver_type === myType;
        var isCaller = Number(row.caller_id) === myId && row.caller_type === myType;
        if (!isReceiver && !isCaller) return;

        if (row.is_group_call && row.status === 'ringing' && !isCaller
            && typeof window.CommsWebRTC.__showIncomingGroup === 'function') {
            window.CommsWebRTC.__showIncomingGroup({
                call_id: row.id,
                conversation_id: row.conversation_id,
                call_type: row.call_type,
                caller_id: row.caller_id,
                caller_type: row.caller_type,
                peer_name: (row.peer && row.peer.name) || 'Group call',
                invitees: row.invitees || [],
                call_max_participants: row.call_max_participants
            });
            return;
        }

        if (row.status === 'ringing' && isReceiver) {
            currentCall = Object.assign({}, currentCall || {}, {
                id: row.id,
                conversation_id: row.conversation_id,
                call_type: row.call_type,
                caller_id: row.caller_id,
                caller_type: row.caller_type,
                receiver_id: row.receiver_id,
                receiver_type: row.receiver_type,
                peer: (currentCall && currentCall.peer) || { name: 'Incoming caller' }
            });
            if (els.incoming && els.incoming.hidden && (!els.inCall || els.inCall.hidden)) {
                showIncoming(currentCall);
            }
            return;
        }

        if (row.status === 'accepted') {
            if (isCaller && currentCall && Number(currentCall.id) === Number(row.id)) {
                applyPeerAnswered();
            }
            return;
        }

        if (['rejected', 'cancelled', 'ended', 'missed'].indexOf(row.status) !== -1) {
            if (currentCall && Number(currentCall.id) === Number(row.id)) {
                var peerName = (currentCall.peer && currentCall.peer.name) || '';
                var rowId = row.id;
                var rowConv = row.conversation_id;
                var endedBy = isCaller
                    ? (row.status === 'cancelled' ? 'caller' : null)
                    : (row.status === 'cancelled' ? 'caller' : (row.status === 'rejected' ? 'callee' : null));
                currentCall = null;
                cleanupMediaOnly().then(function () {
                    showCallEnded(row.status, peerName, rowId, endedBy);
                    refreshCallChat(rowConv);
                });
            } else {
                refreshCallChat(row.conversation_id);
            }
        }
    }

    function showSecureContextBanner() {
        var root = getCallRoot();
        if (!root || resolveMediaDevices()) return;
        if (document.querySelector('.comms-secure-banner')) return;

        var banner = document.createElement('div');
        banner.className = 'comms-secure-banner';
        banner.setAttribute('role', 'alert');
        banner.innerHTML = '<strong>Calls need HTTPS.</strong> '
            + 'Open <a href="' + suggestedHttpsUrl() + '">' + suggestedHttpsUrl() + '</a> '
            + '(run <code>npm run serve:lan</code>), accept the certificate once, then voice/video will work.';
        if (root.id === 'commsApp') {
            root.classList.add('has-secure-banner');
            root.insertBefore(banner, root.firstChild);
        } else {
            document.body.appendChild(banner);
        }
    }

    mountCallUiToBody();

    document.addEventListener('click', function (e) {
        var acceptBtn = e.target && e.target.closest('#commsAcceptCall');
        var rejectBtn = e.target && e.target.closest('#commsRejectCall');
        var endBtn = e.target && e.target.closest('#commsEndCall');
        var endedClose = e.target && e.target.closest('#commsCallEndedClose, #commsCallEndedCloseSide');
        var endedRedial = e.target && e.target.closest('#commsCallEndedRedial');
        if (acceptBtn) {
            e.preventDefault();
            acceptIncoming();
            return;
        }
        if (rejectBtn) {
            e.preventDefault();
            rejectIncoming();
            return;
        }
        if (endBtn) {
            e.preventDefault();
            endCall();
            return;
        }
        if (endedRedial) {
            e.preventDefault();
            redialLastCall();
            return;
        }
        if (endedClose) {
            e.preventDefault();
            hideEnded();
        }
    });

    if (els.muteBtn) {
        els.muteBtn.addEventListener('click', function () {
            if (window.CommsWebRTC && typeof window.CommsWebRTC.__meshActive === 'function' && window.CommsWebRTC.__meshActive()) {
                var pressed = els.muteBtn.getAttribute('aria-pressed') === 'true';
                var nextMuted = !pressed;
                if (typeof window.CommsWebRTC.__meshSetMuted === 'function') {
                    window.CommsWebRTC.__meshSetMuted(nextMuted);
                }
                setMuteUi(nextMuted);
                return;
            }
            if (!localStream) return;
            localStream.getAudioTracks().forEach(function (t) {
                t.enabled = !t.enabled;
                setMuteUi(!t.enabled);
            });
        });
    }
    if (els.cameraBtn) {
        els.cameraBtn.hidden = false;
        els.cameraBtn.addEventListener('click', function () {
            if (window.CommsWebRTC && typeof window.CommsWebRTC.__meshActive === 'function' && window.CommsWebRTC.__meshActive()) {
                var next = !(els.cameraBtn.getAttribute('aria-pressed') === 'true');
                if (typeof window.CommsWebRTC.__meshSetCamera === 'function') {
                    window.CommsWebRTC.__meshSetCamera(next).then(function () {
                        setCameraUi(next);
                        updateCameraUi();
                    }).catch(function (err) {
                        toast((err && err.message) || 'Unable to toggle camera.', 'error');
                    });
                }
                return;
            }
            if (!localStream || !pc) return;
            setCameraEnabled(!cameraEnabled).catch(function (err) {
                toast((err && err.message) || 'Unable to toggle camera.', 'error');
            });
        });
    }

    showSecureContextBanner();

    window.addEventListener('orientationchange', syncLocalPipLayout);
    window.addEventListener('resize', syncLocalPipLayout);
    if (window.screen && screen.orientation && typeof screen.orientation.addEventListener === 'function') {
        screen.orientation.addEventListener('change', syncLocalPipLayout);
    }

    window.CommsWebRTC = {
        startCall: startCall,
        handleSignal: handleSignal,
        handleCallRow: handleCallRow,
        endCall: endCall,
        canUseMedia: function () {
            try {
                return !!resolveMediaDevices();
            } catch (e) {
                return false;
            }
        },
        suggestedHttpsUrl: suggestedHttpsUrl,
        __paintIncoming: showIncoming,
        __showEnded: showCallEnded,
        __startTimer: startTimer
    };

    if (window.CommsRealtime && typeof window.CommsRealtime.flushPendingSignals === 'function') {
        window.CommsRealtime.flushPendingSignals();
    }
})();
