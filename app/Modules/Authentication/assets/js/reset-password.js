/**
 * SK Federations — Set New Password
 * Realtime password rules + confirm-password match feedback + loading state.
 */
(function () {
    'use strict';

    var form = document.getElementById('reset-password-form');
    var newPasswordInput = document.getElementById('new-password');
    var confirmPasswordInput = document.getElementById('confirm-password');
    var newPasswordError = document.getElementById('new-password-error');
    var confirmPasswordError = document.getElementById('confirm-password-error');
    var passwordRules = document.getElementById('passwordRules');
    var submitBtn = document.getElementById('resetSubmitBtn');
    var submitLabel = document.getElementById('resetSubmitLabel');
    var submitSpinner = document.getElementById('resetSubmitSpinner');
    var submitting = false;

    if (!form || !newPasswordInput || !confirmPasswordInput) {
        return;
    }

    function clearError(input, errorElement) {
        if (input) input.classList.remove('is-invalid');
        if (!errorElement) return;
        errorElement.style.display = 'none';
        errorElement.textContent = '';
    }

    function showError(input, errorElement, message) {
        if (input) input.classList.add('is-invalid');
        if (!errorElement) return;
        errorElement.textContent = message;
        errorElement.style.display = 'block';
    }

    function hideServerErrors() {
        form.querySelectorAll('[data-server-error="true"]').forEach(function (el) {
            el.style.display = 'none';
        });
    }

    function setSubmitting(isSubmitting) {
        submitting = isSubmitting;
        if (submitBtn) {
            submitBtn.disabled = isSubmitting;
            submitBtn.classList.toggle('loading', isSubmitting);
            submitBtn.classList.toggle('is-loading', isSubmitting);
            submitBtn.setAttribute('aria-busy', isSubmitting ? 'true' : 'false');
        }
        if (submitSpinner) {
            submitSpinner.hidden = !isSubmitting;
        }
        if (submitLabel) {
            submitLabel.textContent = isSubmitting ? 'Setting password...' : 'Set New Password';
        }
    }

    function validatePasswordStrength(password) {
        var minLength = Number.parseInt(form.dataset.passwordMinLength || '8', 10);

        return {
            isValid: password.length >= minLength
                && /[a-z]/.test(password)
                && /[A-Z]/.test(password)
                && /\d/.test(password)
                && /[^A-Za-z0-9]/.test(password),
            hasMinLength: password.length >= minLength,
            hasLowerCase: /[a-z]/.test(password),
            hasUpperCase: /[A-Z]/.test(password),
            hasNumber: /\d/.test(password),
            hasSpecial: /[^A-Za-z0-9]/.test(password),
        };
    }

    function updatePasswordRules(password) {
        if (!passwordRules) return;

        var state = validatePasswordStrength(password);
        [
            { id: 'rule-length', ok: state.hasMinLength },
            { id: 'rule-lowercase', ok: state.hasLowerCase },
            { id: 'rule-uppercase', ok: state.hasUpperCase },
            { id: 'rule-number', ok: state.hasNumber },
            { id: 'rule-special', ok: state.hasSpecial },
        ].forEach(function (rule) {
            var node = document.getElementById(rule.id);
            if (node) {
                node.classList.toggle('ok', rule.ok);
                node.classList.toggle('valid', rule.ok);
            }
        });

        var showRules = password.length > 0 && !state.isValid;
        passwordRules.hidden = !showRules;
        passwordRules.classList.toggle('active', showRules);
    }

    function syncConfirmMatch() {
        var confirmValue = confirmPasswordInput.value;
        hideServerErrors();

        if (confirmValue.length === 0) {
            clearError(confirmPasswordInput, confirmPasswordError);
            return true;
        }

        if (confirmValue !== newPasswordInput.value) {
            showError(confirmPasswordInput, confirmPasswordError, 'Passwords do not match.');
            return false;
        }

        clearError(confirmPasswordInput, confirmPasswordError);
        return true;
    }

    newPasswordInput.addEventListener('input', function () {
        hideServerErrors();
        clearError(newPasswordInput, newPasswordError);
        updatePasswordRules(newPasswordInput.value);
        syncConfirmMatch();
    });

    confirmPasswordInput.addEventListener('input', function () {
        hideServerErrors();
        syncConfirmMatch();
    });

    form.addEventListener('submit', function (e) {
        if (submitting) {
            e.preventDefault();
            return;
        }

        var maxLength = Number.parseInt(form.dataset.passwordMaxLength || '64', 10);
        var strength = validatePasswordStrength(newPasswordInput.value);
        var isValid = true;

        hideServerErrors();
        clearError(newPasswordInput, newPasswordError);
        clearError(confirmPasswordInput, confirmPasswordError);

        if (!strength.isValid) {
            e.preventDefault();
            showError(newPasswordInput, newPasswordError, 'Please meet all password requirements.');
            updatePasswordRules(newPasswordInput.value);
            isValid = false;
        } else if (newPasswordInput.value.length > maxLength) {
            e.preventDefault();
            showError(newPasswordInput, newPasswordError, 'Password must not exceed ' + maxLength + ' characters.');
            isValid = false;
        }

        if (!syncConfirmMatch() || confirmPasswordInput.value.length === 0) {
            e.preventDefault();
            if (confirmPasswordInput.value.length === 0) {
                showError(confirmPasswordInput, confirmPasswordError, 'Please confirm your password.');
            }
            isValid = false;
        }

        if (!isValid) {
            return;
        }

        setSubmitting(true);

        if (window.LoadingScreen) {
            window.LoadingScreen.show('Setting password...', 'Please wait while we update your account.');
        }
    });

    var newToggle = document.getElementById('newPwToggleBtn');
    var confirmToggle = document.getElementById('confirmPwToggleBtn');

    if (newToggle) {
        newToggle.addEventListener('click', function () {
            var show = newPasswordInput.type === 'password';
            newPasswordInput.type = show ? 'text' : 'password';
            newToggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            newToggle.classList.toggle('pw-visible', show);
        });
    }

    if (confirmToggle) {
        confirmToggle.addEventListener('click', function () {
            var show = confirmPasswordInput.type === 'password';
            confirmPasswordInput.type = show ? 'text' : 'password';
            confirmToggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            confirmToggle.classList.toggle('pw-visible', show);
        });
    }

    var backLink = document.getElementById('resetBackToLogin');
    if (backLink) {
        backLink.addEventListener('click', function (e) {
            e.preventDefault();
            if (window.LoadingScreen) {
                window.LoadingScreen.show('Redirecting', 'Taking you to login...');
            }
            window.setTimeout(function () {
                window.location.href = backLink.href;
            }, 300);
        });
    }

    // If the page reloads with validation errors, restore the idle button.
    setSubmitting(false);
}());
