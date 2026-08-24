/**
 * Shared Cloudflare Turnstile challenge for SK Federations auth forms.
 * Renders a fresh widget only while the modal is visible (mobile-safe).
 */
(function () {
    'use strict';

    var widgetId = null;
    var rendered = false;
    var errorRetries = 0;
    var pending = null;
    var mountTimer = null;
    var successHandled = false;
    var completing = false;

    var MSG = {
        missingToken: 'Please complete the Cloudflare verification first.',
        failed: 'Cloudflare verification failed. Please try again.',
        tryAgain: 'Please complete the Cloudflare verification and try again.',
        cancelled: 'Verification cancelled.',
        loadFailed: 'Cloudflare verification failed to load. Please refresh the page.',
        initFailed: 'Cloudflare verification failed to initialize. Please try again.',
    };

    function config() {
        return document.getElementById('turnstile-gate-config');
    }

    function isEnabled() {
        var el = config();
        return Boolean(el && el.dataset.enabled === '1' && el.dataset.sitekey);
    }

    function siteKey() {
        var el = config();
        return el ? (el.dataset.sitekey || '') : '';
    }

    function modal() {
        return document.getElementById('turnstile-modal');
    }

    function isModalOpen() {
        var modalEl = modal();
        return Boolean(modalEl && modalEl.classList.contains('turnstile-modal-visible'));
    }

    function isSmallViewport() {
        return window.matchMedia('(max-width: 480px)').matches;
    }

    function isMobileViewport() {
        return window.matchMedia('(max-width: 768px)').matches;
    }

    function widgetSize() {
        return 'normal';
    }

    function afterModalPaint(callback) {
        var delay = 80;
        if (isSmallViewport()) {
            delay = 520;
        } else if (isMobileViewport()) {
            delay = 400;
        }
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                setTimeout(callback, delay);
            });
        });
    }

    function waitForApi(maxWaitMs) {
        maxWaitMs = maxWaitMs || 10000;
        return new Promise(function (resolve, reject) {
            if (typeof window.turnstile !== 'undefined') {
                resolve();
                return;
            }
            var start = Date.now();
            var iv = setInterval(function () {
                if (typeof window.turnstile !== 'undefined') {
                    clearInterval(iv);
                    resolve();
                } else if (Date.now() - start > maxWaitMs) {
                    clearInterval(iv);
                    reject(new Error(MSG.loadFailed));
                }
            }, 100);
        });
    }

    function setError(message) {
        var modalEl = modal();
        if (!modalEl || !isModalOpen()) {
            return;
        }
        var errEl = modalEl.querySelector('.turnstile-modal-error');
        if (!errEl) {
            errEl = document.createElement('div');
            errEl.className = 'turnstile-modal-error';
            var body = modalEl.querySelector('.turnstile-modal-body');
            if (body) {
                body.appendChild(errEl);
            }
        }
        errEl.textContent = message;
        errEl.style.display = 'block';
    }

    function clearError() {
        var modalEl = modal();
        var errEl = modalEl ? modalEl.querySelector('.turnstile-modal-error') : null;
        if (errEl) {
            errEl.remove();
        }
    }

    function showModal() {
        var modalEl = modal();
        if (!modalEl) {
            return;
        }
        if (modalEl.parentElement !== document.body) {
            document.body.appendChild(modalEl);
        }
        modalEl.classList.add('turnstile-modal-visible');
        document.body.style.overflow = 'hidden';
    }

    function hideModal() {
        var modalEl = modal();
        if (!modalEl) {
            return;
        }
        modalEl.classList.remove('turnstile-modal-visible');
        document.body.style.overflow = '';
        clearError();
    }

    function recreateContainer() {
        var modalEl = modal();
        if (!modalEl) {
            return null;
        }
        var body = modalEl.querySelector('.turnstile-modal-body');
        if (!body) {
            return null;
        }
        var existing = body.querySelector('#turnstile-container');
        if (existing) {
            existing.remove();
        }
        var fresh = document.createElement('div');
        fresh.id = 'turnstile-container';
        body.insertBefore(fresh, body.firstChild);
        return fresh;
    }

    function clearWidget() {
        if (mountTimer !== null) {
            clearTimeout(mountTimer);
            mountTimer = null;
        }
        if (rendered && widgetId !== null && typeof window.turnstile !== 'undefined') {
            try {
                window.turnstile.remove(widgetId);
            } catch (err) {
                console.warn('[Turnstile] remove failed:', err);
            }
        }
        widgetId = null;
        rendered = false;
    }

    function renderWidget() {
        if (rendered || !isModalOpen()) {
            return;
        }

        var mount = recreateContainer();
        var key = siteKey();
        if (!mount || !key || typeof window.turnstile === 'undefined') {
            throw new Error('Verification config missing. Please refresh the page.');
        }

        widgetId = window.turnstile.render(mount, {
            sitekey: key,
            theme: 'light',
            size: widgetSize(),
            retry: 'never',
            'refresh-expired': 'manual',
            callback: onSuccess,
            'error-callback': onError,
            'expired-callback': onExpired,
        });
        rendered = true;
    }

    function mountWidget() {
        if (!isModalOpen()) {
            return;
        }

        clearWidget();

        waitForApi(10000).then(function () {
            if (!isModalOpen()) {
                return;
            }
            afterModalPaint(function () {
                try {
                    renderWidget();
                } catch (err) {
                    if (pending && pending.reject) {
                        pending.reject(new Error(MSG.initFailed));
                    }
                    pending = null;
                    setError(MSG.initFailed);
                }
            });
        }).catch(function (err) {
            if (pending && pending.reject) {
                pending.reject(new Error(MSG.loadFailed));
            }
            pending = null;
            setError(MSG.loadFailed);
        });
    }

    function scheduleRemount(delayMs) {
        if (mountTimer !== null) {
            clearTimeout(mountTimer);
        }
        mountTimer = setTimeout(function () {
            mountTimer = null;
            if (!isModalOpen()) {
                return;
            }
            clearWidget();
            afterModalPaint(renderWidget);
        }, delayMs);
    }

    function preloadTurnstileApi() {
        if (!isEnabled()) {
            return;
        }
        waitForApi(15000).catch(function () {
            // mountWidget() will surface the error when the modal opens.
        });
    }

    function rejectPending(message) {
        if (completing) {
            return;
        }
        if (pending && pending.reject) {
            pending.reject(new Error(message || MSG.cancelled));
        }
        pending = null;
        successHandled = false;
        hideModal();
        clearWidget();
    }

    function onSuccess(token) {
        if (successHandled || completing || !pending) {
            return;
        }
        if (!token || typeof token !== 'string' || token.trim() === '') {
            setError(MSG.missingToken);
            if (pending && pending.reject) {
                pending.reject(new Error(MSG.missingToken));
            }
            pending = null;
            successHandled = false;
            completing = false;
            return;
        }

        successHandled = true;
        completing = true;

        var resolve = pending.resolve;
        pending = null;

        if (resolve) {
            resolve(token);
        }

        hideModal();

        window.setTimeout(function () {
            clearWidget();
            completing = false;
        }, 120);
    }

    function onError(errorCode) {
        if (!isModalOpen() || successHandled || completing) {
            return;
        }

        console.warn('[Turnstile] error:', errorCode);
        clearError();
        clearWidget();

        if (errorRetries < 2) {
            errorRetries += 1;
            var delay = errorRetries === 1 ? 700 : 1400;
            scheduleRemount(delay);
            return;
        }

        var msg = MSG.failed;
        if (errorCode === '110200' || errorCode === 110200) {
            msg = 'Domain not authorized in Cloudflare Turnstile dashboard. Please add this domain (or enable localhost) in your Cloudflare widget settings.';
        } else if (errorCode === '110100' || errorCode === 110100) {
            msg = 'Invalid Turnstile site key. Please check your configuration.';
        } else if (errorCode === '600010' || errorCode === 600010) {
            msg = MSG.tryAgain;
        }
        setError(msg);
    }

    function onExpired() {
        if (!isModalOpen() || successHandled || completing) {
            return;
        }
        clearError();
        clearWidget();
        afterModalPaint(renderWidget);
    }

    function challenge() {
        if (!isEnabled()) {
            return Promise.resolve('');
        }

        if (pending && !completing) {
            rejectPending(MSG.cancelled);
        }

        return new Promise(function (resolve, reject) {
            pending = { resolve: resolve, reject: reject };
            errorRetries = 0;
            successHandled = false;
            completing = false;
            showModal();
            clearError();
            mountWidget();
        });
    }

    function injectToken(form, token) {
        if (!form) {
            return;
        }
        form.querySelectorAll('input[name="cf-turnstile-response"]').forEach(function (el) {
            el.remove();
        });
        if (!token) {
            return;
        }
        var hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'cf-turnstile-response';
        hidden.value = token;
        form.appendChild(hidden);
    }

    function submitForm(form) {
        return challenge().then(function (token) {
            if (!token || String(token).trim() === '') {
                return Promise.reject(new Error(MSG.missingToken));
            }
            injectToken(form, token);
            HTMLFormElement.prototype.submit.call(form);
        });
    }

    function hasValidToken(form) {
        if (!form) {
            return false;
        }
        var field = form.querySelector('input[name="cf-turnstile-response"]');
        return Boolean(field && String(field.value || '').trim() !== '');
    }

    function bindClose() {
        var closeBtn = document.getElementById('turnstile-close-btn');
        var cancelBtn = document.getElementById('turnstile-cancel-btn');
        var backdrop = document.getElementById('turnstile-modal-backdrop');
        function onClose() {
            if (completing) {
                return;
            }
            rejectPending(MSG.cancelled);
        }
        if (closeBtn) {
            closeBtn.addEventListener('click', onClose);
        }
        if (cancelBtn) {
            cancelBtn.addEventListener('click', onClose);
        }
        if (backdrop) {
            backdrop.addEventListener('click', onClose);
        }
        var card = document.querySelector('.turnstile-modal-card');
        if (card) {
            card.addEventListener('click', function (e) {
                e.stopPropagation();
            });
        }
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && isModalOpen()) {
                onClose();
            }
        });
    }

    bindClose();

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', preloadTurnstileApi);
    } else {
        preloadTurnstileApi();
    }

    window.FedTurnstileGate = {
        isEnabled: isEnabled,
        isOpen: isModalOpen,
        challenge: challenge,
        cancel: function () {
            rejectPending(MSG.cancelled);
        },
        injectToken: injectToken,
        submitForm: submitForm,
        hasValidToken: hasValidToken,
        messages: MSG,
    };

    window.fedTurnstileChallenge = function () {
        if (!isEnabled()) {
            return Promise.resolve('');
        }

        return new Promise(function (resolve, reject) {
            var started = Date.now();
            var wait = function () {
                if (window.FedTurnstileGate && window.FedTurnstileGate.challenge) {
                    window.FedTurnstileGate.challenge().then(resolve).catch(reject);
                    return;
                }
                if (Date.now() - started > 8000) {
                    reject(new Error(MSG.loadFailed));
                    return;
                }
                window.setTimeout(wait, 50);
            };
            wait();
        });
    };

    window.fedTurnstileSubmitForm = function (form) {
        if (window.FedTurnstileGate && window.FedTurnstileGate.submitForm) {
            return window.FedTurnstileGate.submitForm(form);
        }
        return Promise.reject(new Error(MSG.loadFailed));
    };
}());
