{{-- Terms and Conditions modal --}}
<div class="auth-legal-modal" id="termsLegalModal" hidden aria-hidden="true" role="dialog" aria-labelledby="termsLegalModalTitle">
    <div class="auth-legal-modal-backdrop" data-close-legal-modal="termsLegalModal"></div>
    <div class="auth-legal-modal-dialog" role="document">
        <header class="auth-legal-modal-header">
            <h2 class="auth-legal-modal-title" id="termsLegalModalTitle">Terms and Conditions</h2>
            <button type="button" class="auth-legal-modal-close" data-close-legal-modal="termsLegalModal" aria-label="Close">&times;</button>
        </header>
        <div class="auth-legal-modal-body">
            @include('authentication::partials.terms-and-conditions')
        </div>
        <footer class="auth-legal-modal-footer">
            <label class="auth-legal-modal-ack auth-legal-modal-ack--locked">
                <input type="checkbox" id="termsModalAck" data-legal-ack="terms" disabled>
                <span>I have read, understood, and agreed to the Terms and Conditions governing my use of the SK OnePortal System for SK Federation Officials of Santa Cruz, Laguna.</span>
            </label>
            <button type="button" class="auth-legal-modal-btn" data-close-legal-modal="termsLegalModal" disabled>OK</button>
        </footer>
    </div>
</div>

{{-- Privacy Policy modal --}}
<div class="auth-legal-modal" id="privacyLegalModal" hidden aria-hidden="true" role="dialog" aria-labelledby="privacyLegalModalTitle">
    <div class="auth-legal-modal-backdrop" data-close-legal-modal="privacyLegalModal"></div>
    <div class="auth-legal-modal-dialog" role="document">
        <header class="auth-legal-modal-header">
            <h2 class="auth-legal-modal-title" id="privacyLegalModalTitle">Privacy Policy</h2>
            <button type="button" class="auth-legal-modal-close" data-close-legal-modal="privacyLegalModal" aria-label="Close">&times;</button>
        </header>
        <div class="auth-legal-modal-body">
            @include('authentication::partials.privacy-policy')
        </div>
        <footer class="auth-legal-modal-footer">
            <label class="auth-legal-modal-ack auth-legal-modal-ack--locked">
                <input type="checkbox" id="privacyModalAck" data-legal-ack="privacy" disabled>
                <span>I have read and understood the Privacy Policy of the SK OnePortal System. I voluntarily consent to the collection, processing, storage, and use of my personal information for SK Federation operations and other legitimate purposes of the Sangguniang Kabataan of Santa Cruz, Laguna, in accordance with Republic Act No. 10173 (Data Privacy Act of 2012).</span>
            </label>
            <button type="button" class="auth-legal-modal-btn" data-close-legal-modal="privacyLegalModal" disabled>OK</button>
        </footer>
    </div>
</div>
