<!-- Logout Confirmation Modal -->
<div id="logoutModal" class="modal">
    <div class="modal-content logout-modal-content">
        <button type="button" class="logout-modal-close" onclick="closeLogoutModal()" aria-label="Close">
            &times;
        </button>
        <div class="logout-modal-body">
            <h2 class="logout-modal-title">Logout Confirmation</h2>
            <p class="logout-modal-message">Are you sure you want to logout?</p>
            <div class="logout-modal-actions">
                <button type="button" class="logout-btn-cancel" onclick="closeLogoutModal()">
                    Cancel
                </button>
                <button type="button" class="logout-btn-confirm" onclick="confirmLogout()">
                    Yes, Logout
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    .logout-modal-content {
        max-width: 420px;
        width: 90%;
        background: white;
        border-radius: 20px;
        position: relative;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
        animation: logoutModalSlideIn 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    }

    @keyframes logoutModalSlideIn {
        from { opacity: 0; transform: scale(0.95) translateY(-20px); }
        to { opacity: 1; transform: scale(1) translateY(0); }
    }

    .logout-modal-close {
        position: absolute;
        top: 16px;
        right: 16px;
        width: 36px;
        height: 36px;
        border: none;
        background: transparent;
        color: #94a3b8;
        font-size: 24px;
        line-height: 1;
        cursor: pointer;
        border-radius: 8px;
        transition: all 0.2s ease;
        z-index: 10;
    }

    .logout-modal-close:hover {
        background: #f1f5f9;
        color: #475569;
    }

    .logout-modal-body {
        padding: 40px 32px 32px;
        text-align: center;
    }

    .logout-modal-title {
        font-size: 24px;
        font-weight: 700;
        color: #0f172a;
        margin: 0 0 12px 0;
    }

    .logout-modal-message {
        font-size: 15px;
        color: #64748b;
        line-height: 1.6;
        margin: 0 0 28px 0;
    }

    .logout-modal-actions {
        display: flex;
        gap: 12px;
        justify-content: center;
        flex-wrap: wrap;
    }

    .logout-btn-cancel {
        background: white;
        color: #64748b;
        border: 2px solid #e2e8f0;
        padding: 12px 28px;
        border-radius: 10px;
        font-size: 15px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        min-width: 120px;
    }

    .logout-btn-cancel:hover {
        background: #f8fafc;
        border-color: #cbd5e1;
        color: #475569;
    }

    .logout-btn-confirm {
        background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
        color: white;
        border: none;
        padding: 12px 32px;
        border-radius: 10px;
        font-size: 15px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        box-shadow: 0 6px 20px rgba(220, 38, 38, 0.3);
        min-width: 140px;
    }

    .logout-btn-confirm:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 28px rgba(220, 38, 38, 0.4);
    }
</style>
