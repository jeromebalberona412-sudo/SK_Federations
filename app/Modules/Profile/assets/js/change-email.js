document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('ceForm');
    const submitBtn = document.getElementById('ceSubmitBtn');
    const btnText = document.getElementById('ceBtnText');
    const newEmailInput = document.getElementById('ceNewEmail');
    const currentEmailInput = document.getElementById('ceCurrentEmail');
    const newEmailError = document.getElementById('ceNewEmailError');

    function forceLowercase(input) {
        if (!input) return;
        input.addEventListener('input', () => {
            const start = input.selectionStart;
            const end = input.selectionEnd;
            const lower = input.value.toLowerCase();
            if (input.value !== lower) {
                input.value = lower;
                if (typeof start === 'number' && typeof end === 'number') {
                    input.setSelectionRange(start, end);
                }
            }
        });
        input.addEventListener('blur', () => {
            input.value = input.value.trim().toLowerCase();
        });
        if (input.value) {
            input.value = input.value.toLowerCase();
        }
    }

    function clearNewEmailError() {
        if (newEmailInput) {
            newEmailInput.classList.remove('is-invalid');
            newEmailInput.setAttribute('aria-invalid', 'false');
        }
        if (newEmailError) {
            newEmailError.textContent = '';
            newEmailError.hidden = true;
        }
        document.querySelector('.alert-danger')?.remove();
    }

    forceLowercase(currentEmailInput);
    forceLowercase(newEmailInput);

    if (newEmailInput) {
        newEmailInput.addEventListener('input', clearNewEmailError);
    }

    if (!form) return;

    document.querySelectorAll('.pw-toggle-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            const target = document.getElementById(btn.dataset.target || '');
            if (!target) return;

            const eyeOpen = btn.querySelector('.pw-eye-show');
            const eyeClosed = btn.querySelector('.pw-eye-hide');
            const isPassword = target.type === 'password';

            target.type = isPassword ? 'text' : 'password';

            if (eyeOpen && eyeClosed) {
                eyeOpen.style.display = isPassword ? 'none' : 'block';
                eyeClosed.style.display = isPassword ? 'block' : 'none';
            }
        });
    });

    form.addEventListener('submit', (e) => {
        const currentEmail = document.getElementById('ceCurrentEmail')?.value.trim().toLowerCase() || '';
        const newEmail = document.getElementById('ceNewEmail')?.value.trim().toLowerCase() || '';
        const password = document.getElementById('cePassword')?.value || '';

        if (currentEmailInput) currentEmailInput.value = currentEmail;
        if (newEmailInput) newEmailInput.value = newEmail;

        let valid = true;

        if (!currentEmail) {
            valid = false;
        }

        if (!newEmail || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(newEmail)) {
            valid = false;
        } else if (newEmail === currentEmail) {
            valid = false;
        }

        if (!password) {
            valid = false;
        }

        if (!valid) {
            e.preventDefault();
            return;
        }

        if (submitBtn) submitBtn.disabled = true;
        if (btnText) btnText.textContent = 'Sending…';
    });
});
