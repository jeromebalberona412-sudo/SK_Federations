{{-- Terms and Conditions modal --}}
<div class="auth-legal-modal" id="termsLegalModal" hidden aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="termsLegalModalTitle">
    <div class="auth-legal-modal-backdrop"></div>
    <div class="auth-legal-modal-dialog" role="document">
        <header class="auth-legal-modal-header">
            <h2 class="auth-legal-modal-title" id="termsLegalModalTitle">Terms and Conditions</h2>
        </header>
        <div class="auth-legal-modal-body">
            <p class="auth-legal-scroll-notice" data-legal-scroll-notice role="status">
                <svg class="auth-legal-scroll-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9 5a1 1 0 012 0v5a1 1 0 11-2 0V5zm1 9.25a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
                </svg>
                <span class="auth-legal-scroll-text" data-legal-scroll-text>Please scroll down to read the entire document before acknowledging.</span>
                <span class="auth-legal-scroll-percent" data-legal-scroll-percent hidden>0%</span>
            </p>

            @include('authentication::partials.terms-and-conditions')

            <label class="auth-legal-modal-ack auth-legal-modal-ack--locked">
                <input type="checkbox" id="termsModalAck" data-legal-ack="terms" disabled>
                <span>I have read, understood, and agreed to the Terms and Conditions governing my use of the SK OnePortal System for SK Federation Officials of Santa Cruz, Laguna.</span>
            </label>
        </div>
        <footer class="auth-legal-modal-footer">
            <p class="auth-legal-modal-hint" data-legal-hint role="status"></p>
            <button type="button" class="auth-legal-modal-btn" data-close-legal-modal="termsLegalModal" disabled>OK</button>
        </footer>
    </div>
</div>

{{-- Privacy Policy modal --}}
<div class="auth-legal-modal" id="privacyLegalModal" hidden aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="privacyLegalModalTitle">
    <div class="auth-legal-modal-backdrop"></div>
    <div class="auth-legal-modal-dialog" role="document">
        <header class="auth-legal-modal-header">
            <h2 class="auth-legal-modal-title" id="privacyLegalModalTitle">Privacy Policy</h2>
        </header>
        <div class="auth-legal-modal-body">
            <p class="auth-legal-scroll-notice" data-legal-scroll-notice role="status">
                <svg class="auth-legal-scroll-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9 5a1 1 0 012 0v5a1 1 0 11-2 0V5zm1 9.25a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
                </svg>
                <span class="auth-legal-scroll-text" data-legal-scroll-text>Please scroll down to read the entire document before acknowledging.</span>
                <span class="auth-legal-scroll-percent" data-legal-scroll-percent hidden>0%</span>
            </p>

            @include('authentication::partials.privacy-policy')

            <label class="auth-legal-modal-ack auth-legal-modal-ack--locked">
                <input type="checkbox" id="privacyModalAck" data-legal-ack="privacy" disabled>
                <span>I have read and understood the Privacy Policy of the SK OnePortal System. I voluntarily consent to the collection, processing, storage, and use of my personal information for SK Federation operations and other legitimate purposes of the Sangguniang Kabataan of Santa Cruz, Laguna, in accordance with Republic Act No. 10173 (Data Privacy Act of 2012).</span>
            </label>
        </div>
        <footer class="auth-legal-modal-footer">
            <p class="auth-legal-modal-hint" data-legal-hint role="status"></p>
            <button type="button" class="auth-legal-modal-btn" data-close-legal-modal="privacyLegalModal" disabled>OK</button>
        </footer>
    </div>
</div>
