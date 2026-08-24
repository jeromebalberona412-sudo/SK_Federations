<div id="completeTurnoverModal" class="turnover-modal" hidden>
    <div class="turnover-modal-backdrop" data-close-modal></div>
    <div class="turnover-modal-box">
        <div class="turnover-modal-header">
            <h2>Complete Federation Turnover</h2>
            <button type="button" class="turnover-modal-close" data-close-modal>&times;</button>
        </div>
        <div class="turnover-modal-body">
            <div class="turnover-warning-box">
                <i class="fas fa-exclamation-triangle"></i>
                <p><strong>This action cannot be undone.</strong></p>
                <p>Once confirmed, the previous Federation President and Vice President accounts will be deactivated and archived. Their credentials will no longer work.</p>
            </div>
            <div class="form-group-light">
                <label class="form-label-light required">Type <strong>Confirm</strong> to proceed</label>
                <input type="text" id="completeTurnoverConfirm" class="form-input-light" maxlength="20" placeholder="Confirm" autocomplete="off">
            </div>
        </div>
        <div class="turnover-modal-footer">
            <button type="button" class="btn-secondary-modern" data-close-modal>Cancel</button>
            <button type="button" class="btn-danger-modern" id="confirmCompleteTurnover" disabled>Complete Turnover</button>
        </div>
    </div>
</div>
