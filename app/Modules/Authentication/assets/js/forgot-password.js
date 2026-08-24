/**
 * SK Federations — Forgot Password JS
 *
 * Flow: validate email → show Turnstile → user checks box → token → submit once.
 */
document.addEventListener('DOMContentLoaded', function () {
    const form          = document.getElementById('forgotPasswordForm');
    const emailInput    = document.getElementById('email');
    const emailError    = document.getElementById('email-error');
    const submitBtn     = document.getElementById('submitBtn');
    const fpBtnText     = document.getElementById('fpBtnText');
    const cooldownNotice = document.getElementById('fpCooldownNotice');
    const cooldownCount  = document.getElementById('fpCooldownCount');

    if (!form) return;

    const COOLDOWN_DURATION = 60;
    const emailSent = form.dataset.emailSent === '1';
    const COOLDOWN_KEY = form.dataset.cooldownKey || 'sk_fed_fp_cooldown_until';

    let cooldownInterval = null;
    let submitInProgress = false;
    let turnstileInProgress = false;

    function turnstileMsg(key) {
        if (window.FedTurnstileGate && window.FedTurnstileGate.messages) {
            return window.FedTurnstileGate.messages[key] || '';
        }
        return '';
    }

    function setInputError(input, errorEl, msg) {
        if (!input || !errorEl) return;
        input.classList.add('is-invalid');
        errorEl.textContent = msg;
        errorEl.hidden = false;
    }

    function clearInputError(input, errorEl) {
        if (!input || !errorEl) return;
        input.classList.remove('is-invalid');
        errorEl.textContent = '';
        errorEl.hidden = true;
    }

    function fmt(s) {
        return String(Math.max(0, s));
    }

    function refreshCsrfToken() {
        const csrfInput = form.querySelector('input[name="_token"]');
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');

        if (csrfInput && csrfMeta) {
            csrfInput.value = csrfMeta.content;
        }
    }

    setInterval(refreshCsrfToken, 5 * 60 * 1000);

    function getStoredUntil() {
        const v = localStorage.getItem(COOLDOWN_KEY);
        if (!v) return 0;
        const ts = parseInt(v, 10);
        return Number.isNaN(ts) ? 0 : ts;
    }

    function storeUntil(ms) {
        localStorage.setItem(COOLDOWN_KEY, String(ms));
    }

    function clearStored() {
        localStorage.removeItem(COOLDOWN_KEY);
    }

    function runTick(until) {
        clearInterval(cooldownInterval);

        function tick() {
            const remaining = Math.ceil((until - Date.now()) / 1000);

            if (remaining <= 0) {
                clearInterval(cooldownInterval);
                clearStored();
                if (submitBtn)     submitBtn.disabled = false;
                if (fpBtnText)     fpBtnText.textContent = 'Resend Reset Link';
                if (cooldownNotice) cooldownNotice.hidden = true;
                return;
            }

            if (submitBtn)      submitBtn.disabled = true;
            if (fpBtnText)      fpBtnText.textContent = 'Resend Reset Link';
            if (cooldownNotice) { cooldownNotice.hidden = false; }
            if (cooldownCount)  cooldownCount.textContent = fmt(remaining);
        }

        tick();
        cooldownInterval = setInterval(tick, 1000);
    }

    function startFreshCooldown() {
        const until = Date.now() + COOLDOWN_DURATION * 1000;
        storeUntil(until);
        runTick(until);
    }

    if (emailSent) {
        const until = getStoredUntil();
        if (until > Date.now()) {
            runTick(until);
        } else {
            startFreshCooldown();
        }
    } else {
        clearStored();
    }

    if (emailInput && emailInput.type === 'email') {
        emailInput.addEventListener('input', function () {
            if (turnstileInProgress || submitInProgress) return;
            this.classList.remove('is-invalid');
            if (emailError) emailError.hidden = true;
        });
    }

    function resetSubmitButton() {
        submitInProgress = false;
        turnstileInProgress = false;
        if (submitBtn && !(emailSent && getStoredUntil() > Date.now())) {
            submitBtn.disabled = false;
        }
        if (fpBtnText) {
            fpBtnText.textContent = emailSent ? 'Resend Reset Link' : 'Send Reset Link';
        }
    }

    function runTurnstileThenSubmit() {
        const turnstileRequired = window.FedTurnstileGate && window.FedTurnstileGate.isEnabled();

        if (!turnstileRequired) {
            submitInProgress = true;
            if (submitBtn) submitBtn.disabled = true;
            if (fpBtnText) fpBtnText.textContent = 'Sending...';
            HTMLFormElement.prototype.submit.call(form);
            return Promise.resolve();
        }

        turnstileInProgress = true;
        if (submitBtn) submitBtn.disabled = true;

        return window.fedTurnstileChallenge().then(function (token) {
            if (!token || String(token).trim() === '') {
                throw new Error(turnstileMsg('missingToken') || 'Please complete the Cloudflare verification first.');
            }
            window.FedTurnstileGate.injectToken(form, token);
            turnstileInProgress = false;
            submitInProgress = true;
            if (fpBtnText) fpBtnText.textContent = 'Sending...';
            HTMLFormElement.prototype.submit.call(form);
        });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        if (submitInProgress || turnstileInProgress) {
            return;
        }

        refreshCsrfToken();

        const email = emailInput ? emailInput.value.trim() : '';
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        if (emailInput && emailInput.type === 'email') {
            emailInput.classList.remove('is-invalid');
            if (emailError) emailError.hidden = true;

            if (!email) {
                setInputError(emailInput, emailError, 'Please enter your email address.');
                return;
            }

            if (!emailRegex.test(email)) {
                setInputError(emailInput, emailError, 'Please enter a valid email address.');
                return;
            }
        }

        if (submitBtn && submitBtn.disabled) {
            return;
        }

        runTurnstileThenSubmit().catch(function (err) {
            resetSubmitButton();
            if (err && err.message && err.message.indexOf('cancelled') === -1) {
                if (emailInput && emailError) {
                    setInputError(emailInput, emailError, err.message);
                }
            }
        });
    });
});
