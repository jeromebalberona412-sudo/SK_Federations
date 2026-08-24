document.addEventListener('DOMContentLoaded', function () {
    const TIMER_DURATION = 60;
    const TIMER_START_KEY = 'sk_email_change_timer_start';

    const timerElement = document.getElementById('ceTimer');
    const timerCountElement = document.getElementById('ceTimerCount');
    const resendBtn = document.getElementById('ceResendBtn');
    const resendForm = document.getElementById('ceResendForm');

    let timerInterval = null;
    const serverCooldown = Number(window.ceResendCooldown || 0);

    function formatCountdown(seconds) {
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${mins}:${String(secs).padStart(2, '0')}`;
    }

    function updateTimerDisplay(seconds) {
        if (timerCountElement) {
            timerCountElement.textContent = formatCountdown(seconds);
        }
    }

    function timerExpired() {
        if (timerElement) timerElement.style.display = 'none';
        if (resendBtn) {
            resendBtn.disabled = false;
            resendBtn.textContent = 'Resend Verification';
        }
        localStorage.removeItem(TIMER_START_KEY);
    }

    function getRemainingSeconds() {
        const storedStart = localStorage.getItem(TIMER_START_KEY);
        if (storedStart) {
            const elapsed = Math.floor((Date.now() - parseInt(storedStart, 10)) / 1000);
            return Math.max(0, TIMER_DURATION - elapsed);
        }

        return serverCooldown > 0 ? serverCooldown : 0;
    }

    function startTimer(seconds) {
        let remaining = seconds;

        if (remaining <= 0) {
            timerExpired();
            return;
        }

        if (resendBtn) {
            resendBtn.disabled = true;
            resendBtn.textContent = 'Resend Verification';
        }
        if (timerElement) timerElement.style.display = 'block';
        updateTimerDisplay(remaining);

        if (timerInterval) clearInterval(timerInterval);

        timerInterval = setInterval(function () {
            remaining--;
            if (remaining <= 0) {
                clearInterval(timerInterval);
                timerExpired();
            } else {
                updateTimerDisplay(remaining);
            }
        }, 1000);
    }

    function bootstrapTimer() {
        let remaining = getRemainingSeconds();

        if (window.ceFreshVerification === true || window.ceFreshVerification === 'true') {
            const startedAt = Date.now();
            localStorage.setItem(TIMER_START_KEY, startedAt.toString());
            remaining = TIMER_DURATION;
        }

        if (remaining > 0) {
            if (!localStorage.getItem(TIMER_START_KEY)) {
                const startedAt = Date.now() - ((TIMER_DURATION - remaining) * 1000);
                localStorage.setItem(TIMER_START_KEY, startedAt.toString());
            }
            startTimer(remaining);
        } else if (serverCooldown > 0) {
            const startedAt = Date.now() - ((TIMER_DURATION - serverCooldown) * 1000);
            localStorage.setItem(TIMER_START_KEY, startedAt.toString());
            startTimer(serverCooldown);
        } else {
            timerExpired();
        }
    }

    bootstrapTimer();

    if (resendForm) {
        resendForm.addEventListener('submit', function () {
            if (resendBtn) {
                resendBtn.disabled = true;
                resendBtn.textContent = 'Sending…';
            }
            localStorage.setItem(TIMER_START_KEY, Date.now().toString());
        });
    }

    const cancelForm = document.getElementById('ceCancelForm');
    if (cancelForm) {
        cancelForm.addEventListener('submit', function () {
            localStorage.removeItem(TIMER_START_KEY);
            if (timerInterval) clearInterval(timerInterval);
        });
    }
});
