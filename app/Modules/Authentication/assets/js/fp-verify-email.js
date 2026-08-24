/**
 * SK Federations — Forgot Password / Verify Email
 *
 * Timer rules (compliant with spec):
 *
 * ┌─────────────────────────────────────────────────────────────────────────┐
 * │ Source of truth: backend `resend_available_at` ISO timestamp            │
 * │ Client-side localStorage stores the absolute deadline (Unix ms)         │
 * │ so the countdown continues correctly across page refreshes.             │
 * │                                                                         │
 * │ On first arrival  → seed localStorage from backend timestamp            │
 * │ On refresh        → resume from localStorage (not reset)                │
 * │ On resend click   → POST to backend, get new timestamp, update storage  │
 * │ Backend always enforces cooldown; frontend timer is UI only             │
 * └─────────────────────────────────────────────────────────────────────────┘
 */
(function () {
    'use strict';

    // ── Constants ─────────────────────────────────────────────────────────────
    const FALLBACK_COOLDOWN_MS = 60_000;

    // ── Boot ──────────────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', init);

    function init() {
        // ── DOM refs ──────────────────────────────────────────────────────────
        const dataEl      = document.getElementById('fp-verify-data');
        const statusEl    = document.getElementById('fpve-status');
        const cooldownEl  = document.getElementById('fpve-cooldown');
        const countdownEl = document.getElementById('fpve-countdown');
        const resendBtn   = document.getElementById('fpve-resend-btn');
        const resendLabel = document.getElementById('fpve-resend-label');
        const resendSpinner = document.getElementById('fpve-resend-spinner');

        if (!dataEl || !resendBtn) return;

        // ── Config from blade ─────────────────────────────────────────────────
        const email           = dataEl.dataset.email        || '';
        const resendUrl       = dataEl.dataset.resendUrl    || '';
        const csrfToken       = document.querySelector('meta[name="csrf-token"]')?.content || '';

        // Backend-provided deadline (ISO string) — source of truth on first load
        const backendDeadline  = dataEl.dataset.resendAvailableAt || '';

        // localStorage key is scoped to this email so different addresses
        // never share a timer
        const STORAGE_KEY = 'sk_fed_fpve_until_' + simpleHash(email);

        // ── Timer state ───────────────────────────────────────────────────────
        let tickInterval  = null;
        let resendInFlight = false;

        // ── Bootstrap the timer ───────────────────────────────────────────────
        // 1. Check localStorage first (survives refresh)
        // 2. If nothing stored, or stored deadline already passed, seed from backend
        // 3. If backend deadline also passed → button is immediately available
        bootstrapTimer();

        // ── Resend button ──────────────────────────────────────────────────────
        resendBtn.addEventListener('click', handleResend);

        // ──────────────────────────────────────────────────────────────────────
        // FUNCTIONS
        // ──────────────────────────────────────────────────────────────────────

        function bootstrapTimer() {
            const stored  = getStoredDeadline();
            const backendMs = backendDeadline ? new Date(backendDeadline).getTime() : 0;
            const now     = Date.now();

            let deadlineMs;

            if (stored > now) {
                // Active stored deadline — resume (handles refresh correctly)
                deadlineMs = stored;
            } else if (backendMs > now) {
                // No valid stored deadline — seed from backend and persist
                deadlineMs = backendMs;
                persistDeadline(deadlineMs);
            } else {
                // Both expired or missing — button should be available immediately
                clearStoredDeadline();
                enableResend();
                return;
            }

            startTicking(deadlineMs);
        }

        function startTicking(deadlineMs) {
            // Always clear any previous interval first — prevents duplicate tickers
            if (tickInterval) {
                clearInterval(tickInterval);
                tickInterval = null;
            }

            function tick() {
                const remaining = Math.ceil((deadlineMs - Date.now()) / 1000);

                if (remaining <= 0) {
                    clearInterval(tickInterval);
                    tickInterval = null;
                    clearStoredDeadline();
                    enableResend();
                    return;
                }

                disableResend(remaining);
            }

            tick(); // run immediately so there is no 1s blank flash
            tickInterval = setInterval(tick, 500); // 500ms for smooth display
        }

        function disableResend(remainingSecs) {
            resendBtn.disabled = true;
            if (cooldownEl) {
                cooldownEl.hidden = false;
            }
            if (countdownEl) {
                countdownEl.textContent = formatTime(remainingSecs);
            }
        }

        function enableResend() {
            resendBtn.disabled = false;
            if (cooldownEl) cooldownEl.hidden = true;
            if (countdownEl) countdownEl.textContent = '';
            setLabel('Resend Reset Link');
        }

        async function handleResend() {
            // Hard guard: block if already in-flight OR button is disabled (cooldown/loading)
            if (resendInFlight || resendBtn.disabled) return;

            resendInFlight = true;

            // Immediately disable the button and show the spinner so rapid
            // taps/clicks cannot queue a second request before the fetch starts.
            resendBtn.disabled = true;
            if (resendSpinner) resendSpinner.hidden = false;
            setLabel('Sending…');
            clearStatus();

            try {
                let turnstileToken = '';
                if (window.fedTurnstileChallenge) {
                    try {
                        turnstileToken = await window.fedTurnstileChallenge();
                    } catch {
                        if (resendSpinner) resendSpinner.hidden = true;
                        enableResend();
                        return;
                    }
                } else if (window.FedTurnstileGate && window.FedTurnstileGate.challenge) {
                    try {
                        turnstileToken = await window.FedTurnstileGate.challenge();
                    } catch {
                        if (resendSpinner) resendSpinner.hidden = true;
                        enableResend();
                        return;
                    }
                }

                const response = await fetch(resendUrl, {
                    method: 'POST',
                    headers: {
                        'Accept':           'application/json',
                        'Content-Type':     'application/json',
                        'X-CSRF-TOKEN':     csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        email,
                        'cf-turnstile-response': turnstileToken,
                    }),
                });

                const data = await response.json().catch(() => ({}));

                if (response.status === 410 || data.expired) {
                    showStatus('Your session has expired. Redirecting...', 'error');
                    setTimeout(() => {
                        window.location.href = dataEl.dataset.loginUrl || '/forgot-password';
                    }, 1500);
                    return;
                }

                if (!response.ok || !data.ok) {
                    const msg = data.message || 'Unable to resend. Please try again.';
                    showStatus(msg, 'error');

                    if (data.resend_available_at) {
                        const newDeadline = new Date(data.resend_available_at).getTime();
                        if (newDeadline > Date.now()) {
                            persistDeadline(newDeadline);
                            // Hide spinner before starting the cooldown tick
                            if (resendSpinner) resendSpinner.hidden = true;
                            setLabel('Resend Reset Link');
                            startTicking(newDeadline);
                            return;
                        }
                    }

                    // No cooldown from backend — re-enable so user can retry
                    if (resendSpinner) resendSpinner.hidden = true;
                    enableResend();
                    return;
                }

                // ── Success ──────────────────────────────────────────────────
                showStatus(data.message || 'Reset link resent. Check your inbox.', 'success');

                const newDeadline = data.resend_available_at
                    ? new Date(data.resend_available_at).getTime()
                    : Date.now() + FALLBACK_COOLDOWN_MS;

                persistDeadline(newDeadline);
                // Hide spinner — startTicking will disable the button via disableResend()
                if (resendSpinner) resendSpinner.hidden = true;
                setLabel('Resend Reset Link');
                startTicking(newDeadline);

            } catch {
                showStatus('Network error. Please check your connection and try again.', 'error');
                if (resendSpinner) resendSpinner.hidden = true;
                // Re-enable so the user can retry after a network failure
                enableResend();
            } finally {
                // Always clear the in-flight flag.
                // Do NOT call setLoading(false) here — the button state is
                // already managed above (either cooldown started or re-enabled).
                resendInFlight = false;
            }
        }

        // ── UI helpers ────────────────────────────────────────────────────────

        function setLabel(text) {
            if (resendLabel) resendLabel.textContent = text;
        }

        function showStatus(msg, type) {
            if (!statusEl) return;
            statusEl.hidden      = false;
            statusEl.textContent = msg;
            statusEl.className   = 'fpve-status fpve-status-' + type;
        }

        function clearStatus() {
            if (!statusEl) return;
            statusEl.hidden      = true;
            statusEl.textContent = '';
            statusEl.className   = 'fpve-status';
        }

        // ── Storage helpers ───────────────────────────────────────────────────

        function getStoredDeadline() {
            const v = localStorage.getItem(STORAGE_KEY);
            if (!v) return 0;
            const n = Number(v);
            return Number.isFinite(n) ? n : 0;
        }

        function persistDeadline(ms) {
            localStorage.setItem(STORAGE_KEY, String(ms));
        }

        function clearStoredDeadline() {
            localStorage.removeItem(STORAGE_KEY);
        }

        // ── Utilities ─────────────────────────────────────────────────────────

        function formatTime(totalSecs) {
            const m = Math.floor(totalSecs / 60);
            const s = totalSecs % 60;
            return m + ':' + String(s).padStart(2, '0');
        }

        /** Quick non-cryptographic hash to make the localStorage key email-specific */
        function simpleHash(str) {
            let h = 0;
            for (let i = 0; i < str.length; i++) {
                h = (Math.imul(31, h) + str.charCodeAt(i)) | 0;
            }
            return Math.abs(h).toString(36);
        }
    }

    // simpleHash needs to be available before DOMContentLoaded too
    // (used to compute STORAGE_KEY), so define it at module scope as well.
    function simpleHash(str) {
        let h = 0;
        for (let i = 0; i < str.length; i++) {
            h = (Math.imul(31, h) + str.charCodeAt(i)) | 0;
        }
        return Math.abs(h).toString(36);
    }

}());
