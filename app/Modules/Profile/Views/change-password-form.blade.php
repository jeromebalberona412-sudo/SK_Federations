<div id="change-password" class="tab-content">
    @if ($errors->has('password'))
        <div class="success-message show">{{ $errors->first('password') }}</div>
    @endif
    @if (session('password_status'))
        <div class="success-message show">{{ session('password_status') }}</div>
    @else
        <div class="success-message"></div>
    @endif

    <form id="changePasswordForm">
        <div class="form-section">
            <h3 class="form-section-title">Change Your Password</h3>

            <div class="form-group">
                <label for="current_password">Current Password <span style="color: #d0242b;">*</span></label>
                <div class="password-input-wrapper">
                    <input 
                        type="password" 
                        id="current_password" 
                        name="current_password" 
                        class="form-control password-input" 
                        minlength="8" 
                        maxlength="150" 
                        required
                        title="Password must be between 8 and 150 characters">
                    <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('current_password')">
                        <i class="fas fa-eye" id="current_password_icon"></i>
                    </button>
                </div>
                <small style="color: #64748b; font-size: 12px; margin-top: 4px; display: block;"></small>
                <a href="javascript:void(0);" onclick="showForgotPasswordModal()" style="display: inline-block; margin-top: 8px; font-size: 13px; color: #213F99; text-decoration: none; font-weight: 500; transition: color 0.3s ease;">
                    <i class="fas fa-question-circle" style="margin-right: 4px;"></i>
                    Forgot Current Password?
                </a>
            </div>

            <div class="form-group">
                <label for="new_password">New Password <span style="color: #d0242b;">*</span></label>
                <div class="password-input-wrapper">
                    <input 
                        type="password" 
                        id="new_password" 
                        name="new_password" 
                        class="form-control password-input" 
                        minlength="8" 
                        maxlength="150" 
                        required
                        title="Password must be between 8 and 150 characters">
                    <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('new_password')">
                        <i class="fas fa-eye" id="new_password_icon"></i>
                    </button>
                </div>
                <small style="color: #64748b; font-size: 12px; margin-top: 4px; display: block;"></small>
            </div>

            <div class="form-group">
                <label for="new_password_confirmation">Confirm New Password <span style="color: #d0242b;">*</span></label>
                <div class="password-input-wrapper">
                    <input 
                        type="password" 
                        id="new_password_confirmation" 
                        name="new_password_confirmation" 
                        class="form-control password-input" 
                        minlength="8" 
                        maxlength="150" 
                        required
                        title="Password must be between 8 and 150 characters">
                    <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('new_password_confirmation')">
                        <i class="fas fa-eye" id="new_password_confirmation_icon"></i>
                    </button>
                </div>
                <small style="color: #64748b; font-size: 12px; margin-top: 4px; display: block;"></small>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i>
                Save Changes
            </button>
        </div>
    </form>
</div>
