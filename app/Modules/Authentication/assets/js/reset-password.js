/**
 * SK Federations — Set New Password
 * Realtime password rules + confirm-password match feedback.
 */
(function () {
    'use strict';

    var form = document.getElementById('reset-password-form');
    var newPasswordInput = document.getElementById('new-password');
    var confirmPasswordInput = document.getElementById('confirm-password');
    var newPasswordError = document.getElementById('new-password-error');
    var confirmPasswordError = document.getElementById('confirm-password-error');
    var passwordRules = document.getElementById('passwordRules');

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

        if (isValid && window.LoadingScreen) {
            window.LoadingScreen.show('Resetting Password', 'Please wait...');
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
}());
