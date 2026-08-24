<!-- View Account Modal -->
<div id="viewAccountModal" class="modal-overlay" style="display: none;">
    <div class="modal-content modal-large modal-light" id="viewAccountModalBox">
        <div class="modal-header modal-header-blue-grad">
            <h3 class="modal-title">Account Details</h3>
            @include('accounts::modal_window_controls', [
                'resizeId' => 'viewToggleBtn',
                'resizeFn' => 'toggleFullscreenViewModal',
                'closeFn' => 'closeViewModal',
            ])
        </div>

        <div class="modal-body modal-body-light account-modal-scroll" id="viewAccountBody">
            <!-- Content populated dynamically via JavaScript -->
        </div>

        <div class="modal-footer account-modal-footer">
            <button type="button" class="btn-cancel-light" onclick="closeViewModal()">Close</button>
        </div>
    </div>
</div>
