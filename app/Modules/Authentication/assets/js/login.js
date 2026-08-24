/**
 * SK Federations — Login form with Cloudflare Turnstile
 *
 * Flow: validate fields → show Turnstile modal → user checks box → token → submit once.
 * loginForm.submit() is a native DOM call — it does NOT fire the submit event.
 */

(function () {
    'use strict';

    var isSubmitting = false;
    var turnstileInProgress = false;

    var loginForm, emailInput, passwordInput,
        emailError, passwordError, loginBtn, loginBtnText;

    function turnstileMsg(key) {
        if (window.FedTurnstileGate && window.FedTurnstileGate.messages) {
            return window.FedTurnstileGate.messages[key] || '';
        }
        return '';
    }

    function validEmail(v) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
    }

    function showErr(input, el, msg) {
        if (input) input.classList.add('is-invalid');
        if (el) { el.textContent = msg; el.style.display = 'block'; }
    }

    function clearErr(input, el) {
        if (input) input.classList.remove('is-invalid');
        if (el) { el.style.display = 'none'; el.textContent = ''; }
    }

    function showFormTurnstileError(msg) {
        var host = loginForm.querySelector('.form-header');
        if (!host) return;
        var existing = loginForm.querySelector('.turnstile-form-error');
        if (existing) existing.remove();
        var alertEl = document.createElement('div');
        alertEl.className = 'alert alert-danger access-denied-alert turnstile-form-error';
        alertEl.setAttribute('role', 'alert');
        alertEl.textContent = msg;
        host.insertAdjacentElement('afterend', alertEl);
    }

    function clearFormTurnstileError() {
        var existing = loginForm ? loginForm.querySelector('.turnstile-form-error') : null;
        if (existing) existing.remove();
    }

    function validateFields() {
        clearErr(emailInput, emailError);
        clearErr(passwordInput, passwordError);
        clearFormTurnstileError();

        var ok    = true;
        var email = emailInput ? emailInput.value.trim() : '';
        var pass  = passwordInput ? passwordInput.value : '';

        if (!email) {
            showErr(emailInput, emailError, 'Email address is required.');
            ok = false;
        } else if (!validEmail(email)) {
            showErr(emailInput, emailError, 'Please enter a valid email address.');
            ok = false;
        }

        if (!pass) {
            showErr(passwordInput, passwordError, 'Password is required.');
            ok = false;
        } else if (pass.length < 8) {
            showErr(passwordInput, passwordError, 'Password must be at least 8 characters.');
            ok = false;
        } else if (pass.length > 64) {
            showErr(passwordInput, passwordError, 'Password must not exceed 64 characters.');
            ok = false;
        }

        return ok;
    }

    function waitForGate(maxWaitMs) {
        maxWaitMs = maxWaitMs || 8000;
        return new Promise(function (resolve, reject) {
            var start = Date.now();
            var check = function () {
                if (window.FedTurnstileGate && window.FedTurnstileGate.challenge) {
                    resolve(window.FedTurnstileGate);
                    return;
                }
                if (Date.now() - start > maxWaitMs) {
                    reject(new Error(turnstileMsg('loadFailed') || 'Cloudflare verification failed to load. Please refresh the page.'));
                    return;
                }
                window.setTimeout(check, 50);
            };
            check();
        });
    }

    function runTurnstileThenSubmit() {
        return waitForGate().then(function (gate) {
            return gate.challenge();
        }).then(function (token) {
            if (!token || String(token).trim() === '') {
                throw new Error(turnstileMsg('missingToken') || 'Please complete the Cloudflare verification first.');
            }
            window.FedTurnstileGate.injectToken(loginForm, token);
            isSubmitting = true;
            turnstileInProgress = false;
            setSigningIn();
            loginForm.submit();
        });
    }

    function lockAuthFields() {
        if (emailInput) emailInput.readOnly = true;
        if (passwordInput) passwordInput.readOnly = true;
    }

    function unlockAuthFields() {
        if (emailInput) emailInput.readOnly = false;
        if (passwordInput) passwordInput.readOnly = false;
    }

    function setSigningIn() {
        lockAuthFields();
        if (!loginBtn) return;
        loginBtn.disabled = true;
        loginBtn.classList.remove('waiting-for-turnstile');
        loginBtn.classList.add('loading');
        if (loginBtnText) loginBtnText.textContent = 'Logging in...';
    }

    function resetLoginBtn() {
        unlockAuthFields();
        if (!loginBtn) return;
        loginBtn.disabled = false;
        loginBtn.classList.remove('waiting-for-turnstile');
        loginBtn.classList.remove('loading');
        if (loginBtnText) loginBtnText.textContent = 'Login';
    }

    function onFieldEdit() {
        if (isSubmitting || turnstileInProgress) return;
        clearFormTurnstileError();
        if (window.FedTurnstileGate && window.FedTurnstileGate.isOpen &&
            window.FedTurnstileGate.isOpen()) {
            window.FedTurnstileGate.cancel();
        }
        resetLoginBtn();
    }

    function initPasswordToggle() {
        var btn = document.getElementById('pwToggleBtn');
        if (!btn || !passwordInput) return;
        btn.addEventListener('click', function () {
            var show = passwordInput.type === 'password';
            passwordInput.type = show ? 'text' : 'password';
            btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            btn.classList.toggle('pw-visible', show);
        });
    }

    function onFormSubmit(e) {
        if (isSubmitting || turnstileInProgress) {
            e.preventDefault();
            e.stopPropagation();
            return;
        }

        e.preventDefault();

        if (!validateFields()) return;

        var turnstileRequired = loginForm.dataset.turnstileEnabled ||
            (window.FedTurnstileGate && window.FedTurnstileGate.isEnabled());

        if (!turnstileRequired) {
            isSubmitting = true;
            setSigningIn();
            loginForm.submit();
            return;
        }

        turnstileInProgress = true;
        lockAuthFields();
        if (loginBtn) {
            loginBtn.disabled = true;
            loginBtn.classList.add('waiting-for-turnstile');
        }

        runTurnstileThenSubmit().catch(function (err) {
            isSubmitting = false;
            turnstileInProgress = false;
            resetLoginBtn();
            if (err && err.message && err.message.indexOf('cancelled') === -1) {
                showFormTurnstileError(err.message);
            }
        });
    }

    function init() {
        loginForm     = document.getElementById('loginForm');
        emailInput    = document.getElementById('email');
        passwordInput = document.getElementById('password');
        emailError    = document.getElementById('email-error');
        passwordError = document.getElementById('password-error');
        loginBtn      = document.getElementById('loginBtn');
        loginBtnText  = document.getElementById('loginBtnText');

        if (!loginForm || !emailInput || !passwordInput) return;

        initPasswordToggle();

        emailInput.addEventListener('input', function () {
            clearErr(emailInput, emailError);
            onFieldEdit();
        });
        passwordInput.addEventListener('input', function () {
            clearErr(passwordInput, passwordError);
            onFieldEdit();
        });

        loginForm.addEventListener('submit', onFormSubmit, false);

        var forgotBtn = document.getElementById('forgotBtn');
        if (forgotBtn) {
            forgotBtn.addEventListener('click', function (e) {
                e.preventDefault();
                window.location.href = forgotBtn.href;
            });
        }

        var serverErrEl = document.getElementById('turnstile-server-error');
        if (serverErrEl && serverErrEl.textContent.trim()) {
            showFormTurnstileError(serverErrEl.textContent.trim());
        }

        window.history.pushState(null, '', window.location.href);
        window.addEventListener('popstate', function () {
            window.history.pushState(null, '', window.location.href);
        });

        setInterval(function refreshCsrfToken() {
            var metaTag = document.querySelector('meta[name="csrf-token"]');
            var csrfInput = loginForm.querySelector('input[name="_token"]');

            if (!metaTag || !csrfInput) return;

            fetch('/csrf-token', {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (data && data.token) {
                    metaTag.setAttribute('content', data.token);
                    csrfInput.value = data.token;
                }
            })
            .catch(function (err) {
                console.warn('[CSRF] Token refresh failed:', err);
            });
        }, 10 * 60 * 1000);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

}());
