<div id="forgotPasswordModal" class="modal" style="display:none;">
    <div class="modal-content" style="max-width:420px;">
        <div class="modal-header">
            <h2>Forgot Password?</h2>
            <button type="button" class="modal-close" onclick="closeForgotPasswordModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <p style="color:#64748b;font-size:14px;line-height:1.6;margin:0 0 20px;">
                You will be logged out and redirected to the password reset page.
            </p>
            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <button type="button" class="btn btn-secondary" onclick="closeForgotPasswordModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="proceedToForgotPassword()">Continue</button>
            </div>
        </div>
    </div>
</div>

<div id="passwordSuccessModal" class="modal" style="display:none;">
    <div class="modal-content" style="max-width:420px;text-align:center;padding:32px;">
        <div style="width:72px;height:72px;border-radius:50%;background:#dcfce7;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
            <i class="fas fa-check" style="font-size:28px;color:#16a34a;"></i>
        </div>
        <h2 style="margin:0 0 8px;font-size:20px;">Password Updated</h2>
        <p style="color:#64748b;font-size:14px;margin:0;">Logging you out for security...</p>
    </div>
</div>
