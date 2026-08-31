{{-- Optional inactivity warning (server-side SessionTimeout remains authoritative) --}}
@php
    $sessionTimeoutMinutes = max(1, (int) config('session.timeout', 120));
    $sessionWarningMinutes = max(0, (int) config('session.timeout_warning_minutes', 5));
    $lastActivityAt = (int) session(\App\Modules\Authentication\Middleware\SessionTimeout::SESSION_KEY, now()->timestamp);
@endphp

@if($sessionWarningMinutes > 0 && $sessionWarningMinutes < $sessionTimeoutMinutes)
<div id="sessionTimeoutModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="sessionTimeoutTitle" aria-hidden="true" hidden>
    <div class="modal-content logout-modal-content">
        <div class="logout-modal-body">
            <h2 class="logout-modal-title" id="sessionTimeoutTitle">Session Expiring Soon</h2>
            <p class="logout-modal-message" id="sessionTimeoutMessage">
                Your session will expire soon due to inactivity.
            </p>
            <div class="logout-modal-actions">
                <button type="button" class="logout-btn-cancel" id="sessionTimeoutLogoutBtn">
                    Logout
                </button>
                <button type="button" class="logout-btn-confirm" id="sessionTimeoutContinueBtn" style="background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); box-shadow: 0 6px 20px rgba(37, 99, 235, 0.3);">
                    Continue Session
                </button>
            </div>
        </div>
    </div>
</div>
@endif
