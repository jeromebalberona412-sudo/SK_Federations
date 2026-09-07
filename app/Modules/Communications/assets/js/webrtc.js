/**
 * WebRTC voice/video with Supabase broadcast signaling + Laravel call records.
 * Voice: mic on, camera off (can turn on). Video: camera on (can turn off).
 * Shows visible ringing UI on both sides and a Call ended screen when not picked up.
 */
(function () {
    'use strict';

    var RING_TIMEOUT_MS = 45000;
    var CALL_START_DELAY_MS = 3000;

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
        freqs.forEach(function (freq) {
            var osc = ctx.createOscillator();
            var gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.value = freq;
            gain.gain.setValueAtTime(0.0001, when);
            gain.gain.exponentialRampToValueAtTime(volume, when + 0.02);
            gain.gain.setValueAtTime(volume, when + Math.max(0.05, duration - 0.05));
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
                playToneBurst(ringCtx, [440, 480], now, 0.4, 0.14);
                playToneBurst(ringCtx, [440, 480], now + 0.55, 0.4, 0.14);
            } else {
                playToneBurst(ringCtx, [440, 480], now, 1.4, 0.09);
            }
        }

        scheduleCycle();
        ringTimer = setInterval(scheduleCycle, incoming ? 2200 : 4000);
    }

    function armRingTimeout() {
        clearRingTimeout();
        ringTimeout = setTimeout(function () {
            if (!currentCall || callConnected) return;
            finishUnanswered('missed');
        }, RING_TIMEOUT_MS);
    }

    function endedCopy(reason) {
        switch (reason) {
            case 'missed':
                return {
                    title: 'Call ended',
                    reason: role === 'caller' ? 'No answer. The call was not picked up.' : 'Missed call. You did not answer in time.'
                };
            case 'rejected':
                return {
                    title: 'Call declined',
                    reason: role === 'caller' ? 'The other person declined the call.' : 'You declined the call.'
                };
            case 'cancelled':
                return {
                    title: 'Call cancelled',
                    reason: role === 'caller' ? 'You cancelled the call.' : 'The caller cancelled the call.'
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

    function showCallEnded(reason, peerName, callId) {
        if (callId != null && Number(callId) === Number(lastEndedCallId)) {
            return;
        }
        if (callId != null) lastEndedCallId = callId;

        hideIncoming();
        if (els.inCall) els.inCall.hidden = true;
        clearRingTimeout();
        stopRingtone();

        var copy = endedCopy(reason || 'ended');
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
        var root = document.getElementById('commsApp');
        var myId = root ? Number(root.dataset.currentUserId) : null;
        var myType = root ? root.dataset.portalUserType : null;
        var peerId = null;
        var peerType = null;

        if (currentCall.receiver_id != null && currentCall.caller_id != null) {
            var iAmCaller = Number(currentCall.caller_id) === myId && currentCall.caller_type === myType;
            if (iAmCaller) {
                peerId = currentCall.receiver_id;
                peerType = currentCall.receiver_type;
            } else {
                peerId = currentCall.caller_id;
                peerType = currentCall.caller_type;
            }
        }

        return window.CommsRealtime.broadcastSignal(currentCall.conversation_id, payload, {
            peerId: peerId,
            peerType: peerType
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

    function updateCameraUi() {
        if (els.cameraBtn) {
            els.cameraBtn.hidden = false;
            setCameraUi(!!cameraEnabled);
        }
        if (els.localVideo) {
            els.localVideo.hidden = !cameraEnabled;
        }
        if (els.callCenter) {
            var showCenter = !cameraEnabled || !callConnected;
            els.callCenter.hidden = !showCenter && !!(els.remoteVideo && els.remoteVideo.srcObject);
        }
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
            return await devices.getUserMedia({
                audio: {
                    echoCancellation: true,
                    noiseSuppression: true
                },
                video: videoEnabled ? {
                    facingMode: 'user',
                    width: { ideal: 1280 },
                    height: { ideal: 720 }
                } : false
            });
        } catch (err) {
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

    function showIncoming(call) {
        if (!els.incoming) return;
        role = 'callee';
        callConnected = false;
        currentCall = Object.assign({}, currentCall || {}, call);
        rememberCallContext(currentCall);
        hideEnded();
        if (els.incomingPeer) {
            els.incomingPeer.textContent = (call.peer && call.peer.name) || 'Incoming caller';
        }
        if (els.incomingType) {
            els.incomingType.textContent = (call.call_type === 'video' ? 'Video call' : 'Voice call');
        }
        els.incoming.hidden = false;
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
        callStartedAt = Date.now();
        clearInterval(callTimer);
        callTimer = setInterval(function () {
            if (els.timer) els.timer.textContent = formatTimer(Date.now() - callStartedAt);
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
            var stream = ev.streams && ev.streams[0]
                ? ev.streams[0]
                : (ev.track ? new MediaStream([ev.track]) : null);
            if (!stream) return;
            if (els.remoteVideo) els.remoteVideo.srcObject = stream;
            if (els.remoteAudio) els.remoteAudio.srcObject = stream;
        };

        localStream.getTracks().forEach(function (track) {
            pc.addTrack(track, localStream);
        });
        if (els.localVideo) {
            els.localVideo.srcObject = localStream;
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
                var camStream = await devices.getUserMedia({
                    video: {
                        facingMode: 'user',
                        width: { ideal: 1280 },
                        height: { ideal: 720 }
                    },
                    audio: false
                });
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
    }

    async function cleanupMediaOnly() {
        clearPendingStart();
        clearRingTimeout();
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
        if (els.inCall) els.inCall.hidden = true;
        if (els.incoming) els.incoming.hidden = true;
    }

    async function updateCallStatus(callId, status) {
        var r = routes();
        if (!r.callStatus || !window.Comms) return null;
        return window.Comms.api(window.Comms.route(r.callStatus, callId), {
            method: 'POST',
            body: JSON.stringify({ status: status }),
            dedupe: false
        }).then(function (data) { return data.call; });
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

        try {
            if (callSnapshot.id) {
                await updateCallStatus(callSnapshot.id, status);
            }
        } catch (e) { /* ignore */ }

        try {
            await signalPeer({
                type: 'hangup',
                reason: status,
                call_id: callSnapshot.id,
                conversation_id: callSnapshot.conversation_id,
                caller_id: callSnapshot.caller_id,
                caller_type: callSnapshot.caller_type,
                receiver_id: callSnapshot.receiver_id,
                receiver_type: callSnapshot.receiver_type
            });
        } catch (e) { /* ignore */ }

        currentCall = null;
        await cleanupMediaOnly();
        showCallEnded(status, peerName, callSnapshot.id);
        refreshCallChat(callSnapshot.conversation_id);
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

    function beginCallCountdown(conversationId, callType, conversation) {
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

        role = 'caller';
        callConnected = false;
        lastEndedCallId = null;
        pendingStart = {
            conversationId: conversationId,
            callType: callType,
            conversation: conversation
        };

        var peer = (conversation && conversation.other_user) || { name: 'Contact' };
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
            clearPendingStart();
            if (els.muteBtn) els.muteBtn.hidden = false;
            if (els.cameraBtn) els.cameraBtn.hidden = false;
            setCtrlLabel(els.endBtn, 'End');
            placeCall(conversationId, callType, conversation);
        }, CALL_START_DELAY_MS);
    }

    async function placeCall(conversationId, callType, conversation) {
        if (!window.Comms || startingCall) return;
        startingCall = true;
        var r = routes();
        try {
            assertMediaReady();
            if (!window.CommsRealtime || !window.CommsRealtime.enabled) {
                throw new Error('Realtime is not configured. Rebuild after setting VITE_SUPABASE keys.');
            }

            var data = await window.Comms.api(window.Comms.route(r.startCall, conversationId), {
                method: 'POST',
                body: JSON.stringify({ call_type: callType }),
                dedupe: false
            });
            role = 'caller';
            callConnected = false;
            lastEndedCallId = null;
            currentCall = data.call;
            wantsVideo = callType === 'video';
            if (conversation && conversation.other_user) {
                currentCall.peer = conversation.other_user;
            }
            rememberCallContext(currentCall);
            if (els.muteBtn) els.muteBtn.hidden = false;
            if (els.cameraBtn) els.cameraBtn.hidden = false;
            setCtrlLabel(els.endBtn, 'End');
            showInCall(Object.assign({}, currentCall, {
                peer: (conversation && conversation.other_user) || currentCall.peer
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

    async function startCall(conversationId, callType, conversation) {
        beginCallCountdown(conversationId, callType, conversation);
    }

    async function acceptIncoming() {
        if (!currentCall || startingCall) return;
        startingCall = true;
        clearRingTimeout();
        stopRingtone();
        hideIncoming();
        try {
            assertMediaReady();
            var accepted = await updateCallStatus(currentCall.id, 'accepted');
            if (accepted) {
                currentCall = Object.assign({}, currentCall, accepted);
            }
            wantsVideo = currentCall.call_type === 'video';
            callConnected = true;
            showInCall(currentCall, 'Connecting...');
            await ensurePeerConnection(wantsVideo);
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
        if (!currentCall) return;
        await finishUnanswered('rejected');
    }

    async function endCall() {
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
        try { await updateCallStatus(callSnapshot.id, 'ended'); } catch (e) { /* ignore */ }
        try {
            await signalPeer({
                type: 'hangup',
                reason: 'ended',
                call_id: callSnapshot.id,
                conversation_id: callSnapshot.conversation_id,
                caller_id: callSnapshot.caller_id,
                caller_type: callSnapshot.caller_type,
                receiver_id: callSnapshot.receiver_id,
                receiver_type: callSnapshot.receiver_type
            });
        } catch (e) { /* ignore */ }

        currentCall = null;
        await cleanupMediaOnly();
        showCallEnded('ended', peerName, callSnapshot.id);
        refreshCallChat(callSnapshot.conversation_id);
    }

    async function handleSignal(payload) {
        if (!payload || !payload.type) return;

        if (payload.type === 'offer') {
            lastEndedCallId = null;
            currentCall = Object.assign({}, currentCall || {}, {
                id: payload.call_id,
                conversation_id: payload.conversation_id || (window.CommsChat && window.CommsChat.getActiveId()),
                call_type: payload.call_type || 'voice',
                peer: { name: payload.peer_name || 'Incoming caller' },
                caller_id: payload.caller_id,
                caller_type: payload.caller_type,
                receiver_id: payload.receiver_id,
                receiver_type: payload.receiver_type,
                _remoteOffer: payload.sdp
            });
            showIncoming(currentCall);
            return;
        }

        if (payload.type === 'hangup') {
            var reason = payload.reason || 'ended';
            var peerName = (currentCall && currentCall.peer && currentCall.peer.name) || '';
            var hangupId = (currentCall && currentCall.id) || payload.call_id;
            var hangupConv = (currentCall && currentCall.conversation_id) || payload.conversation_id;
            currentCall = null;
            await cleanupMediaOnly();
            showCallEnded(reason, peerName, hangupId);
            refreshCallChat(hangupConv);
            return;
        }

        if (payload.type === 'camera') {
            return;
        }

        if (payload.type === 'answer') {
            stopRingtone();
            clearRingTimeout();
            callConnected = true;
            setCallStatus('Connected');
            if (els.timer && !callStartedAt) {
                startTimer();
            }
            if (pc) {
                await pc.setRemoteDescription(payload.sdp);
                while (iceQueue.length) {
                    await pc.addIceCandidate(iceQueue.shift());
                }
            }
            refreshCallChat(currentCall && currentCall.conversation_id);
            return;
        }

        if (payload.type === 'ice' && payload.candidate) {
            if (!pc || !pc.remoteDescription) {
                iceQueue.push(payload.candidate);
            } else {
                await pc.addIceCandidate(payload.candidate);
            }
        }
    }

    function handleCallRow(payload) {
        var row = (payload && payload.new) || null;
        if (!row) return;
        var root = document.getElementById('commsApp');
        if (!root) return;
        var myId = Number(root.dataset.currentUserId);
        var myType = root.dataset.portalUserType;
        var isReceiver = Number(row.receiver_id) === myId && row.receiver_type === myType;
        var isCaller = Number(row.caller_id) === myId && row.caller_type === myType;
        if (!isReceiver && !isCaller) return;

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
                stopRingtone();
                clearRingTimeout();
                callConnected = true;
                setCallStatus('Connecting...');
            }
            return;
        }

        if (['rejected', 'cancelled', 'ended', 'missed'].indexOf(row.status) !== -1) {
            if (currentCall && Number(currentCall.id) === Number(row.id)) {
                var peerName = (currentCall.peer && currentCall.peer.name) || '';
                var rowId = row.id;
                var rowConv = row.conversation_id;
                currentCall = null;
                cleanupMediaOnly().then(function () {
                    showCallEnded(row.status, peerName, rowId);
                    refreshCallChat(rowConv);
                });
            } else {
                refreshCallChat(row.conversation_id);
            }
        }
    }

    function showSecureContextBanner() {
        var root = document.getElementById('commsApp');
        if (!root || resolveMediaDevices()) return;
        if (root.querySelector('.comms-secure-banner')) return;

        var banner = document.createElement('div');
        banner.className = 'comms-secure-banner';
        banner.setAttribute('role', 'alert');
        banner.innerHTML = '<strong>Calls need HTTPS.</strong> '
            + 'Open <a href="' + suggestedHttpsUrl() + '">' + suggestedHttpsUrl() + '</a> '
            + '(run <code>npm run serve:lan</code>), accept the certificate once, then voice/video will work.';
        root.insertBefore(banner, root.firstChild);
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
            if (!localStream || !pc) return;
            setCameraEnabled(!cameraEnabled).catch(function (err) {
                toast((err && err.message) || 'Unable to toggle camera.', 'error');
            });
        });
    }

    showSecureContextBanner();

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
        suggestedHttpsUrl: suggestedHttpsUrl
    };
})();
