<!-- Logout Confirmation Modal -->
<div id="logoutModal" class="modal">
    <div class="modal-content logout-modal-content">
        <button type="button" class="logout-modal-close" onclick="closeLogoutModal()">
            <i class="fas fa-times"></i>
        </button>
        <div class="logout-modal-body">
            <div class="logout-modal-icon-wrapper">
                <svg class="logout-modal-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
            </div>
            <h2 class="logout-modal-title">Logout Confirmation</h2>
            <p class="logout-modal-message">Are you sure you want to logout?</p>
            <div class="logout-modal-actions">
                <button type="button" class="logout-btn-cancel" onclick="closeLogoutModal()">
                    Cancel
                </button>
                <button type="button" class="logout-btn-confirm" onclick="confirmLogout()">
                    Yes, Logout
                    <svg class="logout-btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="5 12 19 12"></polyline>
                        <polyline points="12 5 19 12 12 19"></polyline>
                    </svg>
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    /* Logout Modal Styles */
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
        from {
            opacity: 0;
            transform: scale(0.95) translateY(-20px);
        }
        to {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }

    .logout-modal-close {
        position: absolute;
        top: 16px;
        left: 16px;
        width: 36px;
        height: 36px;
        border: none;
        background: transparent;
        color: #94a3b8;
        font-size: 18px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        transition: all 0.2s ease;
        z-index: 10;
    }

    .logout-modal-close:hover {
        background: #f1f5f9;
        color: #475569;
    }

    .logout-modal-body {
        padding: 56px 40px 40px;
        text-align: center;
    }

    .logout-modal-icon-wrapper {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 28px;
        box-shadow: 0 12px 32px rgba(239, 68, 68, 0.2);
        animation: logoutIconPulse 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        position: relative;
    }

    .logout-modal-icon-wrapper::before {
        content: '';
        position: absolute;
        width: 100%;
        height: 100%;
        border-radius: 50%;
        background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
        opacity: 0.3;
        animation: logoutIconRing 1.5s ease-out infinite;
    }

    @keyframes logoutIconPulse {
        0% {
            transform: scale(0) rotate(-180deg);
            opacity: 0;
        }
        50% {
            transform: scale(1.1) rotate(10deg);
        }
        100% {
            transform: scale(1) rotate(0deg);
            opacity: 1;
        }
    }

    @keyframes logoutIconRing {
        0% {
            transform: scale(1);
            opacity: 0.3;
        }
        50% {
            transform: scale(1.15);
            opacity: 0.15;
        }
        100% {
            transform: scale(1.3);
            opacity: 0;
        }
    }

    .logout-modal-icon {
        width: 48px;
        height: 48px;
        color: #dc2626;
        position: relative;
        z-index: 1;
    }

    .logout-modal-title {
        font-size: 26px;
        font-weight: 700;
        color: #0f172a;
        margin: 0 0 12px 0;
        letter-spacing: -0.5px;
    }

    .logout-modal-message {
        font-size: 15px;
        color: #64748b;
        line-height: 1.6;
        margin: 0 0 32px 0;
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

    .logout-btn-cancel:active {
        transform: scale(0.98);
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
        display: inline-flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 6px 20px rgba(220, 38, 38, 0.3);
        min-width: 160px;
        justify-content: center;
    }

    .logout-btn-confirm:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 28px rgba(220, 38, 38, 0.4);
    }

    .logout-btn-confirm:active {
        transform: translateY(0);
    }

    .logout-btn-icon {
        width: 18px;
        height: 18px;
        transition: transform 0.2s ease;
    }

    .logout-btn-confirm:hover .logout-btn-icon {
        transform: translateX(2px);
    }

    @media (max-width: 640px) {
        .logout-modal-content {
            max-width: 95%;
        }

        .logout-modal-body {
            padding: 48px 24px 32px;
        }

        .logout-modal-icon-wrapper {
            width: 88px;
            height: 88px;
            margin-bottom: 24px;
        }

        .logout-modal-icon {
            width: 44px;
            height: 44px;
        }

        .logout-modal-title {
            font-size: 22px;
        }

        .logout-modal-message {
            font-size: 14px;
            margin-bottom: 28px;
        }

        .logout-modal-actions {
            gap: 10px;
        }

        .logout-btn-cancel,
        .logout-btn-confirm {
            padding: 11px 20px;
            font-size: 14px;
            min-width: 100px;
        }

        .logout-btn-confirm {
            min-width: 140px;
        }
    }

    @media (max-width: 480px) {
        .logout-modal-body {
            padding: 40px 20px 28px;
        }

        .logout-modal-actions {
            flex-direction: column;
            gap: 10px;
        }

        .logout-btn-cancel,
        .logout-btn-confirm {
            width: 100%;
            min-width: unset;
        }
    }
</style>

<script>
    // Show logout confirmation modal
    function showLogoutModal() {
        document.getElementById('logoutModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    // Close logout modal
    function closeLogoutModal() {
        document.getElementById('logoutModal').style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    // Confirm logout and submit form
    function confirmLogout() {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        
        // Use fetch to logout, then replace location to prevent back navigation
        fetch("{{ route('logout') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            credentials: 'same-origin',
        }).then(() => {
            // Use replace to prevent back navigation
            window.location.replace("{{ route('login') }}");
        }).catch(() => {
            // Fallback: use form submission
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = "{{ route('logout') }}";
            
            if (csrfToken) {
                const csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = '_token';
                csrfInput.value = csrfToken;
                form.appendChild(csrfInput);
            }
            
            document.body.appendChild(form);
            form.submit();
        });
    }

    // Close modal when clicking outside
    (function() {
        window.addEventListener('click', function(event) {
            const logoutModal = document.getElementById('logoutModal');
            if (event.target === logoutModal) {
                closeLogoutModal();
            }
        });
    })();
</script>
