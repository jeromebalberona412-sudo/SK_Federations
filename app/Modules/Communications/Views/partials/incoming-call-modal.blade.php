<div class="comms-modal" id="commsIncomingCall" hidden role="dialog" aria-modal="true" aria-labelledby="commsIncomingTitle">
    <div class="comms-modal-card comms-modal-card--call">
        <div class="comms-call-pulse" aria-hidden="true"></div>
        <h2 id="commsIncomingTitle">Incoming call</h2>
        <p class="comms-modal-peer" id="commsIncomingPeer"></p>
        <p class="comms-modal-type" id="commsIncomingType"></p>
        <p class="comms-modal-hint">Ringing...</p>
        <div class="comms-modal-actions">
            <button type="button" class="comms-btn comms-btn--danger" id="commsRejectCall">Decline</button>
            <button type="button" class="comms-btn comms-btn--success" id="commsAcceptCall">Accept</button>
        </div>
    </div>
</div>

<div class="comms-modal" id="commsCallEnded" hidden role="dialog" aria-modal="true" aria-labelledby="commsCallEndedTitle">
    <div class="comms-modal-card">
        <h2 id="commsCallEndedTitle">Call ended</h2>
        <p class="comms-modal-peer" id="commsCallEndedPeer"></p>
        <p class="comms-modal-type" id="commsCallEndedReason">The call has ended.</p>
        <div class="comms-modal-actions">
            <button type="button" class="comms-btn" id="commsCallEndedClose">Close</button>
        </div>
    </div>
</div>
