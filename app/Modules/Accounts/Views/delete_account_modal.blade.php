{{-- Destructive delete modal — vanilla JS (AccountsDeleteModal in account.js) --}}
<div id="deleteAccountModal" class="modal-overlay delete-modal-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="deleteModalTitle">
    <div class="modal-content delete-confirm-modal">
        <div class="delete-confirm-loading" id="deleteModalLoading" hidden aria-live="polite" aria-busy="true">
            <div class="delete-confirm-spinner" aria-hidden="true"></div>
            <p class="delete-confirm-loading-text">Deleting account...</p>
            <p class="delete-confirm-loading-sub">Please wait</p>
        </div>
        <div class="delete-confirm-panel" id="deleteModalPanel">
        <div class="delete-confirm-header">
            <h3 id="deleteModalTitle">Delete Account</h3>
        </div>
        <div class="delete-confirm-body">
            <p class="delete-confirm-message" id="deleteModalMessage">
                Are you sure you want to permanently delete this account?
            </p>
            <p class="delete-confirm-desc">This action cannot be undone.</p>

            <label class="delete-confirm-label" for="deleteConfirmationInput">Type DELETE to confirm</label>
            <input type="text" id="deleteConfirmationInput" class="delete-confirm-input"
                   placeholder="DELETE" autocomplete="off" autocapitalize="none" spellcheck="false">

            <p class="delete-confirm-hint delete-confirm-hint-error" id="deleteConfirmHintError" style="display:none;">
                Please type &quot;DELETE&quot; to continue (any capitalization is fine).
            </p>
            <p class="delete-confirm-hint delete-confirm-hint-success" id="deleteConfirmHintSuccess" style="display:none;">
                ✓ Confirmation text matched.
            </p>
        </div>
        <div class="delete-confirm-footer">
            <button type="button" class="btn-cancel-delete" id="deleteModalCancelBtn">Cancel</button>
            <button type="button" class="btn-confirm-delete is-disabled" id="deleteModalConfirmBtn" disabled>Delete Account</button>
        </div>
        </div>
    </div>
</div>
