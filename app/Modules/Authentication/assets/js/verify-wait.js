/**
 * SK Federations — Email Verify Wait
 *
 * Countdown rules:
 *  - First arrival (no cooldown stored yet): start fresh 60s immediately
 *    because the server already sent the verification email.
 *  - Page refresh while cooldown is running: resume from where it left off.
 *  - Clicking Resend: starts a new 60s from that moment.
 *  - After successful verification: clears cooldown and redirects instantly.
 */
document.addEventListener('DOMContentLoaded', () => {
    const POLL_INTERVAL_MS = 2000;
    const COOLDOWN_SECONDS = 60;
    const BTN_LABEL        = 'Resend Verification Email';

    if (typeof window.hideLoading === 'function') window.hideLoading();

    const verifyContent = document.querySelector('.verify-content');
    if (!verifyContent) return;

    const resendUrl    = verifyContent.dataset.resendUrl    || '';
    const dashboardUrl = verifyContent.dataset.dashboardUrl || '/dashboard';
    const email        = verifyContent.dataset.email        || '';
    const userId       = verifyContent.dataset.userId       || '';
    const sessionKey   = verifyContent.dataset.sessionKey   || email;

    // Status-poll URL — includes session context so the server can resolve it
    const statusUrl = (() => {
        const base = verifyContent.dataset.statusUrl || '';
        if (!base) return base;
        const p = new URLSearchParams();
        if (sessionKey) p.set('session_key', sessionKey);
        if (userId)     p.set('user_id', userId);
        const q = p.toString();
        return q ? `${base}${base.includes('?') ? '&' : '?'}${q}` : base;
    })();

    const stateEl         = document.getElementById('verification-state');
    const resendBtn       = document.getElementById('resend-btn');
    const resendBtnLabel  = document.getElementById('resend-btn-label');
    const resendBtnSpinner = document.getElementById('resend-btn-spinner');
    const cooldownEl      = document.getElementById('resend-cooldown');
    const cooldownCount   = document.getElementById('resend-cooldown-count');
    const statusEl        = document.getElementById('resend-status');

    // Per-session key — unique per login attempt so it auto-expires with the session
    const COOLDOWN_KEY = `sk_fed_resend_until_${sessionKey}`;

    let timerInterval       = null;
    let pollTimeout         = null;
    let resendInFlight      = false;
    let verificationDone    = false;
    let redirectStarted     = false;

    // ── Cooldown storage helpers ──────────────────────────────────────────────

    function cooldownExpiry() {
        return Number.parseInt(localStorage.getItem(COOLDOWN_KEY) || '0', 10);
    }

    function remaining() {
        const expiry = cooldownExpiry();
        return expiry > Date.now() ? Math.max(0, Math.ceil((expiry - Date.now()) / 1000)) : 0;
    }

    function startCooldown(seconds) {
        localStorage.setItem(COOLDOWN_KEY, String(Date.now() + (seconds || COOLDOWN_SECONDS) * 1000));
    }

    function clearCooldown() {
        localStorage.removeItem(COOLDOWN_KEY);
    }

    function fmt(s) {
        return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
    }

    // ── UI helpers ────────────────────────────────────────────────────────────

    function setLabel(text) {
        if (resendBtnLabel) resendBtnLabel.textContent = text;
        else if (resendBtn) resendBtn.textContent = text;
    }

    function setLoading(loading) {
        if (resendBtn) { resendBtn.classList.toggle('is-loading', loading); resendBtn.disabled = loading; }
        if (resendBtnSpinner) resendBtnSpinner.hidden = !loading;
        setLabel(loading ? 'Sending…' : BTN_LABEL);
    }

    function setStatus(msg, type = 'success') {
        if (!statusEl) return;
        if (!msg) { statusEl.hidden = true; statusEl.textContent = ''; statusEl.className = 'resend-status'; return; }
        statusEl.hidden = false;
        statusEl.textContent = msg;
        statusEl.className = `resend-status resend-status-${type}`;
    }

    function refreshButton() {
        const rem = remaining();
        if (rem > 0) {
            if (resendBtn)        { resendBtn.disabled = true; resendBtn.classList.remove('is-loading'); }
            if (resendBtnSpinner) resendBtnSpinner.hidden = true;
            setLabel(BTN_LABEL);
            if (cooldownEl)    cooldownEl.style.display = 'block';
            if (cooldownCount) cooldownCount.textContent = fmt(rem);
            return;
        }
        if (cooldownEl) cooldownEl.style.display = 'none';
        if (!resendInFlight && resendBtn) resendBtn.disabled = false;
        if (!resendInFlight) setLabel(BTN_LABEL);
    }

    // ── Bootstrap on page load ────────────────────────────────────────────────
    // If no cooldown exists in localStorage yet, this is the first visit for this
    // session — start the 60s countdown immediately (the server already sent the email).
    // If a cooldown already exists, the user refreshed — resume it as-is.

    function bootstrap() {
        if (remaining() <= 0) {
            // No existing entry → first arrival → seed the cooldown now
            startCooldown(COOLDOWN_SECONDS);
        }
        // Always run the tick loop (either fresh or resumed)
        refreshButton();
        if (timerInterval) clearInterval(timerInterval);
        timerInterval = setInterval(refreshButton, 1000);
    }

    // ── Redirect to dashboard ─────────────────────────────────────────────────

    function goToDashboard(url) {
        if (redirectStarted) return;
        redirectStarted = true;
        verificationDone = true;

        if (stateEl) {
            stateEl.className = 'alert alert-success';
            stateEl.textContent = 'Email verified! Redirecting to dashboard...';
        }

        const modal = document.getElementById('success-modal');
        if (modal) modal.classList.add('show');

        if (typeof LoadingScreen !== 'undefined') {
            LoadingScreen.show('Redirecting', 'Taking you to dashboard...');
        }

        clearCooldown();
        window.location.replace(url || dashboardUrl);
    }

    // ── Resend (button click only) ────────────────────────────────────────────

    async function resend() {
        if (verificationDone || resendInFlight) return;

        const rem = remaining();
        if (rem > 0) {
            setStatus(`Please wait ${fmt(rem)} before resending.`, 'error');
            return;
        }

        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        resendInFlight = true;
        setStatus('');
        setLoading(true);

        try {
            const res = await fetch(resendUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ email, session_key: sessionKey, user_id: userId }),
            });

            const data = await res.json().catch(() => ({}));

            if (data.state === 'verified' && data.redirect) {
                goToDashboard(data.redirect);
                return;
            }

            if (!res.ok || !data.ok) {
                setStatus(data.message || 'Unable to resend. Please try again.', 'error');
                setLoading(false);
                if (Number(data.resend_cooldown) > 0) startCooldown(Number(data.resend_cooldown));
                refreshButton();
                return;
            }

            setStatus(data.message || 'Verification email resent. Check your inbox.', 'success');
            // Start a fresh 60s from right now
            startCooldown(Number(data.resend_cooldown) || COOLDOWN_SECONDS);
            setLoading(false);
            refreshButton();
        } catch {
            setStatus('Unable to resend. Please try again.', 'error');
            setLoading(false);
            refreshButton();
        } finally {
            resendInFlight = false;
        }
    }

    if (resendBtn) resendBtn.addEventListener('click', e => { e.preventDefault(); resend(); });

    // ── Status polling ────────────────────────────────────────────────────────

    async function poll() {
        if (verificationDone) return;

        try {
            const res  = await fetch(statusUrl, { method: 'GET', headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' });
            const data = await res.json().catch(() => ({}));

            if (data.state === 'verified' && data.redirect) {
                goToDashboard(data.redirect);
                return;
            }

            if (data.state === 'expired') {
                if (stateEl) { stateEl.className = 'alert alert-warning'; stateEl.textContent = 'Verification window expired. Please sign in again.'; }
                clearCooldown();
                return; // stop polling
            }
        } catch { /* network hiccup — retry */ }

        pollTimeout = setTimeout(poll, POLL_INTERVAL_MS);
    }

    // ── Init ─────────────────────────────────────────────────────────────────

    bootstrap();
    poll();

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden && !verificationDone) {
            if (pollTimeout) clearTimeout(pollTimeout);
            poll();
        }
    });

    window.addEventListener('pageshow', () => {
        if (typeof window.hideLoading === 'function') window.hideLoading();
        refreshButton();
        if (!verificationDone) {
            if (pollTimeout) clearTimeout(pollTimeout);
            poll();
        }
    });
});
