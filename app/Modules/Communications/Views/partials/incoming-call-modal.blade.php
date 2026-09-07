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
    <div class="comms-modal-card comms-modal-card--ended">
        <button type="button" class="comms-modal-x" id="commsCallEndedClose" aria-label="Close">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.25" aria-hidden="true">
                <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
        </button>
        <div class="comms-ended-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.79 19.79 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.81.36 1.6.68 2.35a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.75.32 1.54.55 2.35.68A2 2 0 0 1 22 16.92z"/>
            </svg>
        </div>
        <h2 id="commsCallEndedTitle">Call ended</h2>
        <p class="comms-modal-peer" id="commsCallEndedPeer"></p>
        <p class="comms-modal-type" id="commsCallEndedReason">The call has ended.</p>
        <div class="comms-modal-actions comms-modal-actions--ended">
            <button type="button" class="comms-btn comms-btn--success" id="commsCallEndedRedial" hidden>Redial</button>
            <button type="button" class="comms-btn comms-btn--ghost-x" id="commsCallEndedCloseSide" aria-label="Close">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.25" aria-hidden="true">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
    </div>
</div>
