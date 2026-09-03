<div class="comms-call-overlay" id="commsInCall" hidden>
    <div class="comms-call-stage">
        <video id="commsRemoteVideo" class="comms-remote-video" autoplay playsinline></video>
        <video id="commsLocalVideo" class="comms-local-video" autoplay playsinline muted></video>
        <audio id="commsRemoteAudio" autoplay></audio>
        <div class="comms-call-info">
            <div class="comms-call-status" id="commsCallStatus">Calling...</div>
            <div id="commsCallPeerName"></div>
            <div id="commsCallTimer">00:00</div>
        </div>
        <div class="comms-call-controls">
            <button type="button" class="comms-icon-btn" id="commsMuteBtn" aria-label="Mute">Mute</button>
            <button type="button" class="comms-icon-btn" id="commsCameraBtn" aria-label="Toggle camera" aria-pressed="false">Camera off</button>
            <button type="button" class="comms-btn comms-btn--danger" id="commsEndCall">End</button>
        </div>
    </div>
</div>
