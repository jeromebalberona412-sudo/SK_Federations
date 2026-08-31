<!-- Logout Confirmation Modal -->
<form id="logoutForm" action="{{ route('logout') }}" method="POST" hidden>
    @csrf
</form>

<div id="logoutModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="logoutModalTitle" aria-hidden="true">
    <div class="modal-content logout-modal-content">
        <button type="button" class="logout-modal-close" id="logoutModalCloseBtn" aria-label="Close">
            &times;
        </button>
        <div class="logout-modal-body">
            <h2 class="logout-modal-title" id="logoutModalTitle">Logout Confirmation</h2>
            <p class="logout-modal-message">Are you sure you want to logout?</p>
            <div class="logout-modal-actions">
                <button type="button" class="logout-btn-cancel" id="logoutCancelBtn">
                    Cancel
                </button>
                <button type="button" class="logout-btn-confirm" id="logoutConfirmBtn">
                    Yes, Logout
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    #logoutModal.modal {
        display: none;
        position: fixed;
        z-index: 40000;
        inset: 0;
        margin: 0;
        padding: 16px;
        background: rgba(15, 23, 42, 0.45);
        align-items: center;
        justify-content: center;
        -webkit-tap-highlight-color: transparent;
    }

    #logoutModal.modal.show {
        display: flex !important;
    }

    #logoutModal .logout-modal-content {
        max-width: 420px;
        width: 100%;
        background: #fff;
        border-radius: 16px;
        position: relative;
        box-shadow: 0 16px 40px rgba(15, 23, 42, 0.18);
    }

    #logoutModal .logout-modal-close {
        position: absolute;
        top: 12px;
        right: 12px;
        width: 36px;
        height: 36px;
        border: none;
        background: transparent;
        color: #94a3b8;
        font-size: 24px;
        line-height: 1;
        cursor: pointer;
        border-radius: 8px;
        touch-action: manipulation;
    }

    #logoutModal .logout-modal-close:hover,
    #logoutModal .logout-modal-close:focus-visible {
        background: #f1f5f9;
        color: #475569;
    }

    #logoutModal .logout-modal-body {
        padding: 36px 28px 28px;
        text-align: center;
    }

    #logoutModal .logout-modal-title {
        font-size: 22px;
        font-weight: 700;
        color: #0f172a;
        margin: 0 0 10px;
    }

    #logoutModal .logout-modal-message {
        font-size: 15px;
        color: #64748b;
        line-height: 1.5;
        margin: 0 0 24px;
    }

    #logoutModal .logout-modal-actions {
        display: flex;
        gap: 10px;
        justify-content: center;
        flex-wrap: wrap;
    }

    #logoutModal .logout-btn-cancel,
    #logoutModal .logout-btn-confirm {
        padding: 11px 24px;
        border-radius: 10px;
        font-size: 15px;
        font-weight: 600;
        cursor: pointer;
        min-width: 120px;
        touch-action: manipulation;
        -webkit-tap-highlight-color: transparent;
    }

    #logoutModal .logout-btn-cancel {
        background: #fff;
        color: #64748b;
        border: 2px solid #e2e8f0;
    }

    #logoutModal .logout-btn-cancel:hover,
    #logoutModal .logout-btn-cancel:focus-visible {
        background: #f8fafc;
        border-color: #cbd5e1;
        color: #475569;
    }

    #logoutModal .logout-btn-confirm {
        background: #dc2626;
        color: #fff;
        border: none;
        min-width: 140px;
    }

    #logoutModal .logout-btn-confirm:hover,
    #logoutModal .logout-btn-confirm:focus-visible {
        background: #b91c1c;
    }

    #logoutModal .logout-btn-confirm:disabled,
    #logoutModal .logout-btn-cancel:disabled {
        opacity: 0.7;
        cursor: wait;
        pointer-events: none;
    }

    .profile-dropdown-item--logout {
        touch-action: manipulation;
        -webkit-tap-highlight-color: transparent;
    }
</style>
