/**
 * SK Federation — Change Password
 */
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('change-password-form');
    const emailInput = document.getElementById('cpEmail');
    const emailError = document.getElementById('cpEmailClientError');
    const passwordInput = document.getElementById('password');
    const confirmInput = document.getElementById('password_confirmation');
    const passwordRules = document.getElementById('passwordRules');
    const clientError = document.getElementById('password-client-error');
    const confirmError = document.getElementById('passwordConfirmClientError');
    const submitBtn = document.getElementById('cpSubmitBtn');
    const btnText = document.getElementById('cpBtnText');

    if (!form) {
        return;
    }

    const maxLength = Number.parseInt(form.dataset.passwordMaxLength || '64', 10);
    const minLength = Number.parseInt(form.dataset.passwordMinLength || '8', 10);
    const accountEmail = emailInput
        ? (emailInput.defaultValue || emailInput.value || '').trim().toLowerCase()
        : '';

    function showEmailError(message) {
        if (!emailInput || !emailError) return;
        emailError.textContent = message;
        emailError.hidden = false;
        emailInput.classList.add('is-invalid');
    }

    function clearEmailError() {
        if (!emailInput) return;
        if (emailError) {
            emailError.textContent = '';
            emailError.hidden = true;
        }
        emailInput.classList.remove('is-invalid');
    }

    function validateEmailStep() {
        if (!emailInput) return true;
        clearEmailError();
        const value = emailInput.value.trim();
        if (!value) {
            showEmailError('Please enter your account email.');
            emailInput.focus();
            return false;
        }
        if (accountEmail && value.toLowerCase() !== accountEmail) {
            showEmailError('The email address does not match your account.');
            emailInput.focus();
            return false;
        }
        return true;
    }

    if (emailInput) {
        emailInput.addEventListener('input', clearEmailError);
    }

    function showSubmitLoading() {
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.classList.add('is-loading');
        }
        if (btnText) {
            btnText.textContent = 'Sending verification...';
        }
    }

    function validatePasswordStrength(password) {
        const hasLowerCase = /[a-z]/.test(password);
        const hasUpperCase = /[A-Z]/.test(password);
        const hasNumber = /[0-9]/.test(password);
        const hasSpecial = /[^A-Za-z0-9]/.test(password);
        const hasMinLength = password.length >= minLength;

        return {
            isValid: hasMinLength && hasLowerCase && hasUpperCase && hasNumber && hasSpecial,
            hasLowerCase,
            hasUpperCase,
            hasNumber,
            hasSpecial,
            hasMinLength,
        };
    }

    function updatePasswordRules(password) {
        if (!passwordRules) return;

        const state = validatePasswordStrength(password);
        [
            { id: 'rule-length', ok: state.hasMinLength },
            { id: 'rule-lowercase', ok: state.hasLowerCase },
            { id: 'rule-uppercase', ok: state.hasUpperCase },
            { id: 'rule-number', ok: state.hasNumber },
            { id: 'rule-special', ok: state.hasSpecial },
        ].forEach(function (rule) {
            const node = document.getElementById(rule.id);
            if (node) {
                node.classList.toggle('ok', rule.ok);
                node.classList.toggle('valid', rule.ok);
                node.classList.toggle('fail', !rule.ok);
                node.hidden = false;
            }
        });

        if (password.length > 0 && !state.isValid) {
            passwordRules.hidden = false;
            passwordRules.classList.add('active');
        } else {
            passwordRules.hidden = true;
            passwordRules.classList.remove('active');
        }
    }

    function updateConfirmPasswordMatch() {
        if (!confirmInput || !passwordInput) return;

        const password = passwordInput.value;
        const confirm = confirmInput.value;

        if (!confirm) {
            if (confirmError) {
                confirmError.textContent = '';
                confirmError.hidden = true;
            }
            confirmInput.classList.remove('is-invalid');
            return;
        }

        if (password !== confirm) {
            if (confirmError) {
                confirmError.textContent = 'Passwords do not match.';
                confirmError.hidden = false;
            }
            confirmInput.classList.add('is-invalid');
            return;
        }

        if (confirmError) {
            confirmError.textContent = '';
            confirmError.hidden = true;
        }
        confirmInput.classList.remove('is-invalid');
    }

    function showClientError(message) {
        if (!clientError || !passwordInput) return;
        clientError.textContent = message;
        clientError.hidden = false;
        passwordInput.classList.add('is-invalid');
    }

    function clearClientError() {
        if (clientError) {
            clientError.textContent = '';
            clientError.hidden = true;
        }
        if (passwordInput) passwordInput.classList.remove('is-invalid');
        if (confirmInput) confirmInput.classList.remove('is-invalid');
    }

    if (passwordInput) {
        passwordInput.addEventListener('input', function () {
            clearClientError();
            updatePasswordRules(this.value);
            updateConfirmPasswordMatch();
        });
        if (passwordInput.value) {
            updatePasswordRules(passwordInput.value);
        }
    }

    if (confirmInput) {
        confirmInput.addEventListener('input', function () {
            clearClientError();
            updateConfirmPasswordMatch();
        });
    }

    form.addEventListener('submit', function (e) {
        if (emailInput && !validateEmailStep()) {
            e.preventDefault();
            return;
        }

        if (!passwordInput) return;

        const strength = validatePasswordStrength(passwordInput.value);
        clearClientError();

        if (!strength.isValid) {
            e.preventDefault();
            showClientError('Please meet all password requirements.');
            updatePasswordRules(passwordInput.value);
            passwordInput.focus();
            return;
        }

        if (passwordInput.value.length > maxLength) {
            e.preventDefault();
            showClientError('Password must not exceed ' + maxLength + ' characters.');
            return;
        }

        if (confirmInput && passwordInput.value !== confirmInput.value) {
            e.preventDefault();
            updateConfirmPasswordMatch();
            confirmInput.focus();
            return;
        }

        showSubmitLoading();
    });

    document.querySelectorAll('.pw-toggle-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const targetId = btn.getAttribute('data-target');
            const input = document.getElementById(targetId);
            if (!input) return;

            const isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            btn.classList.toggle('pw-visible', isHidden);
            btn.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
        });
    });
});
