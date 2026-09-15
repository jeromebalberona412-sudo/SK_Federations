{{-- Optional inactivity warning (server-side SessionTimeout remains authoritative) --}}
@php
    $sessionTimeoutMinutes = max(1, (int) config('session.timeout', 120));
    $sessionWarningMinutes = max(0, (int) config('session.timeout_warning_minutes', 5));
    $lastActivityAt = (int) session(\App\Modules\Authentication\Middleware\SessionTimeout::SESSION_KEY, now()->timestamp);
@endphp

@if($sessionWarningMinutes > 0 && $sessionWarningMinutes < $sessionTimeoutMinutes)
<div id="sessionTimeoutModal" class="session-timeout-modal" role="dialog" aria-modal="true" aria-labelledby="sessionTimeoutTitle" aria-hidden="true" hidden>
    <div class="session-timeout-modal__backdrop" data-session-timeout-dismiss></div>
    <div class="session-timeout-modal__panel" role="document">
        <div class="session-timeout-modal__icon" aria-hidden="true">
            <i class="fas fa-clock"></i>
        </div>
        <h2 class="session-timeout-modal__title" id="sessionTimeoutTitle">Session Expiring Soon</h2>
        <p class="session-timeout-modal__message" id="sessionTimeoutMessage">
            Your session will expire soon due to inactivity.
        </p>
        <div class="session-timeout-modal__actions">
            <button type="button" class="session-timeout-modal__btn session-timeout-modal__btn--logout" id="sessionTimeoutLogoutBtn">
                Logout
            </button>
            <button type="button" class="session-timeout-modal__btn session-timeout-modal__btn--continue" id="sessionTimeoutContinueBtn">
                Continue Session
            </button>
        </div>
    </div>
</div>

<style>
    #sessionTimeoutModal.session-timeout-modal {
        display: none;
        position: fixed;
        inset: 0;
        z-index: 50000;
        margin: 0;
        padding: 16px;
        align-items: center;
        justify-content: center;
        -webkit-tap-highlight-color: transparent;
    }

    #sessionTimeoutModal.session-timeout-modal.show {
        display: flex !important;
    }

    #sessionTimeoutModal .session-timeout-modal__backdrop {
        position: absolute;
        inset: 0;
        background: rgba(15, 23, 42, 0.55);
        backdrop-filter: blur(4px);
    }

    #sessionTimeoutModal .session-timeout-modal__panel {
        position: relative;
        z-index: 1;
        width: 100%;
        max-width: 440px;
        background: #ffffff;
        border-radius: 16px;
        padding: 28px 24px 24px;
        box-shadow: 0 20px 50px rgba(15, 23, 42, 0.28);
        text-align: center;
    }

    #sessionTimeoutModal .session-timeout-modal__icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 56px;
        height: 56px;
        margin: 0 auto 14px;
        border-radius: 50%;
        background: #eff6ff;
        color: #1d4ed8;
        font-size: 22px;
    }

    #sessionTimeoutModal .session-timeout-modal__title {
        margin: 0 0 10px;
        font-size: 22px;
        font-weight: 700;
        line-height: 1.3;
        color: #0f172a !important;
        background: transparent !important;
    }

    #sessionTimeoutModal .session-timeout-modal__message {
        margin: 0 0 24px;
        font-size: 15px;
        line-height: 1.55;
        color: #64748b !important;
        background: transparent !important;
    }

    #sessionTimeoutModal .session-timeout-modal__actions {
        display: flex;
        gap: 12px;
        justify-content: center;
        flex-wrap: wrap;
    }

    #sessionTimeoutModal .session-timeout-modal__btn {
        appearance: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 132px;
        padding: 12px 20px;
        border-radius: 10px;
        border: 2px solid transparent;
        font-family: inherit;
        font-size: 15px;
        font-weight: 600;
        line-height: 1.2;
        text-decoration: none !important;
        cursor: pointer;
        touch-action: manipulation;
        transition: background 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease, color 0.15s ease;
    }

    #sessionTimeoutModal .session-timeout-modal__btn--logout {
        background: #ffffff !important;
        color: #475569 !important;
        border-color: #e2e8f0 !important;
        box-shadow: none !important;
    }

    #sessionTimeoutModal .session-timeout-modal__btn--logout:hover,
    #sessionTimeoutModal .session-timeout-modal__btn--logout:focus-visible {
        background: #f8fafc !important;
        border-color: #cbd5e1 !important;
        color: #334155 !important;
    }

    #sessionTimeoutModal .session-timeout-modal__btn--continue {
        background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%) !important;
        color: #ffffff !important;
        border-color: transparent !important;
        box-shadow: 0 8px 20px rgba(37, 99, 235, 0.32) !important;
        min-width: 168px;
    }

    #sessionTimeoutModal .session-timeout-modal__btn--continue:hover,
    #sessionTimeoutModal .session-timeout-modal__btn--continue:focus-visible {
        background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%) !important;
        color: #ffffff !important;
    }

    #sessionTimeoutModal .session-timeout-modal__btn:disabled {
        opacity: 0.7;
        cursor: wait;
        pointer-events: none;
    }

    @media (max-width: 480px) {
        #sessionTimeoutModal .session-timeout-modal__panel {
            padding: 24px 18px 20px;
        }

        #sessionTimeoutModal .session-timeout-modal__title {
            font-size: 20px;
        }

        #sessionTimeoutModal .session-timeout-modal__actions {
            flex-direction: column-reverse;
        }

        #sessionTimeoutModal .session-timeout-modal__btn {
            width: 100%;
            min-width: 0;
        }
    }
</style>
@endif
