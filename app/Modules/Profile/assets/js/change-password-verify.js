document.addEventListener('DOMContentLoaded', function () {
    const COOLDOWN_SECONDS = 60;
    const POLL_INTERVAL_MS = 3000;
    const BTN_LABEL = 'Resend Verification';

    const verifySection = document.getElementById('cpVerifySection');
    const statusUrl = verifySection?.dataset.statusUrl || '';
    const resendUrl = verifySection?.dataset.resendUrl || document.getElementById('cpResendForm')?.action || '';
    const accountEmail = verifySection?.dataset.email || 'default';
    const cooldownKey = `sk_password_change_resend_${accountEmail}`;

    const timerElement = document.getElementById('cpTimer');
    const timerCountElement = document.getElementById('cpTimerCount');
    const resendBtn = document.getElementById('cpResendBtn');
    const resendForm = document.getElementById('cpResendForm');
    const listeningBadge = document.getElementById('cpListeningBadge');
    const statusBadge = document.getElementById('cpStatusBadge');
    const infoBox = document.getElementById('cpInfoBox');

    let timerInterval = null;
    let confirmationHandled = false;
    let resendInFlight = false;
    const serverCooldown = Number(window.cpResendCooldown || 0);

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            || resendForm?.querySelector('input[name="_token"]')?.value
            || '';
    }

    function jsonHeaders() {
        return {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        };
    }

    function isLoggedOutResponse(response, payload) {
        if (!response) return false;
        if (response.status === 401 || response.status === 419) return true;
        if (response.redirected) return true;
        const contentType = response.headers.get('content-type') || '';
        if (contentType && !contentType.includes('application/json')) return true;
        return payload && payload.state === 'confirmed';
    }

    function clearResendCooldown() {
        localStorage.removeItem(cooldownKey);
    }

    function setResendCooldownExpiry(seconds) {
        localStorage.setItem(cooldownKey, String(Date.now() + Math.max(1, seconds || COOLDOWN_SECONDS) * 1000));
    }

    function storedRemaining() {
        const expiry = Number.parseInt(localStorage.getItem(cooldownKey) || '0', 10);
        return expiry > Date.now() ? Math.max(0, Math.ceil((expiry - Date.now()) / 1000)) : 0;
    }

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
        if (timerElement) {
            timerElement.style.display = 'none';
        }
        if (resendBtn && !resendInFlight) {
            resendBtn.disabled = false;
            resendBtn.textContent = BTN_LABEL;
        }
        clearResendCooldown();
    }

    function startTimer(seconds) {
        const remainingStart = Math.max(0, seconds);
        if (remainingStart <= 0) {
            timerExpired();
            return;
        }

        if (resendBtn) {
            resendBtn.disabled = true;
            resendBtn.textContent = BTN_LABEL;
        }
        if (timerElement) {
            timerElement.style.display = 'block';
        }
        updateTimerDisplay(remainingStart);

        if (timerInterval) {
            clearInterval(timerInterval);
        }

        timerInterval = setInterval(function () {
            const remaining = storedRemaining();
            if (remaining <= 0) {
                clearInterval(timerInterval);
                timerInterval = null;
                timerExpired();
            } else {
                updateTimerDisplay(remaining);
            }
        }, 1000);
    }

    function bootstrapTimer() {
        let remaining = storedRemaining();

        if (remaining <= 0 && serverCooldown > 0) {
            remaining = serverCooldown;
            setResendCooldownExpiry(remaining);
        }

        if (remaining > 0) {
            startTimer(remaining);
        } else {
            timerExpired();
        }
    }

    function markConfirmedUI(message) {
        confirmationHandled = true;

        if (verifySection) {
            verifySection.classList.add('is-confirmed');
        }
        if (listeningBadge) {
            listeningBadge.classList.add('is-confirmed');
            listeningBadge.innerHTML = '<span class="cp-listening-dot"></span> Password confirmed';
        }
        if (statusBadge) {
            statusBadge.textContent = 'Confirmed';
            statusBadge.style.background = '#dcfce7';
            statusBadge.style.color = '#166534';
        }
        if (infoBox) {
            infoBox.textContent = message || 'Password change confirmed. Signing you out...';
        }
    }

    function redirectToLogin(message, redirectUrl) {
        clearResendCooldown();
        if (timerInterval) {
            clearInterval(timerInterval);
        }
        markConfirmedUI(message);
        setTimeout(function () {
            window.location.replace(redirectUrl || '/login');
        }, 800);
    }

    async function parseJson(response) {
        try {
            return await response.json();
        } catch (error) {
            return {};
        }
    }

    async function checkConfirmationStatus() {
        if (confirmationHandled || !statusUrl) {
            return;
        }

        try {
            const response = await fetch(statusUrl, {
                method: 'GET',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            const payload = await parseJson(response);

            if (isLoggedOutResponse(response, payload) || payload.state === 'confirmed') {
                redirectToLogin(
                    payload.message || 'Password changed successfully. Please sign in with your new password.',
                    payload.redirect || '/login',
                );
                return;
            }

            if (!response.ok) {
                setTimeout(checkConfirmationStatus, POLL_INTERVAL_MS + 2000);
                return;
            }

            if (payload.state === 'pending') {
                setTimeout(checkConfirmationStatus, POLL_INTERVAL_MS);
                return;
            }

            if (payload.state === 'cancelled') {
                clearResendCooldown();
                window.location.replace(payload.redirect || '/change-password');
            }
        } catch (error) {
            setTimeout(checkConfirmationStatus, POLL_INTERVAL_MS + 2000);
        }
    }

    async function submitResend() {
        if (confirmationHandled || resendInFlight || storedRemaining() > 0 || !resendUrl) {
            return;
        }

        resendInFlight = true;
        if (resendBtn) {
            resendBtn.disabled = true;
            resendBtn.textContent = 'Sending…';
        }

        try {
            const response = await fetch(resendUrl, {
                method: 'POST',
                headers: jsonHeaders(),
                credentials: 'same-origin',
                body: JSON.stringify({ _token: csrfToken() }),
            });

            const payload = await parseJson(response);

            if (isLoggedOutResponse(response, payload) || payload.state === 'confirmed') {
                redirectToLogin(
                    payload.message || 'Password changed successfully. Please sign in with your new password.',
                    payload.redirect || '/login',
                );
                return;
            }

            if (!response.ok || payload.ok === false) {
                if (resendBtn) {
                    resendBtn.textContent = BTN_LABEL;
                }
                resendInFlight = false;
                const cooldown = Number(payload.resend_cooldown || payload.cooldown || 0);
                if (cooldown > 0) {
                    setResendCooldownExpiry(cooldown);
                    startTimer(cooldown);
                } else {
                    timerExpired();
                }
                return;
            }

            const cooldown = Number(payload.resend_cooldown || payload.cooldown || COOLDOWN_SECONDS);
            setResendCooldownExpiry(cooldown);
            resendInFlight = false;
            startTimer(cooldown);
            if (infoBox) {
                infoBox.textContent = payload.message || 'Verification email resent. Check your inbox.';
            }
        } catch (error) {
            resendInFlight = false;
            if (resendBtn) {
                resendBtn.textContent = BTN_LABEL;
                resendBtn.disabled = false;
            }
        }
    }

    bootstrapTimer();
    checkConfirmationStatus();

    if (resendBtn) {
        resendBtn.addEventListener('click', function (event) {
            event.preventDefault();
            submitResend();
        });
    }

    if (resendForm) {
        resendForm.addEventListener('submit', function (event) {
            event.preventDefault();
        });
    }

    const cancelForm = document.getElementById('cpCancelForm');
    if (cancelForm) {
        cancelForm.addEventListener('submit', function () {
            clearResendCooldown();
            if (timerInterval) {
                clearInterval(timerInterval);
            }
        });
    }
});
