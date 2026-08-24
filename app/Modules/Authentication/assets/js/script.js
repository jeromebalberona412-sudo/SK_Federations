// Password Toggle Functionality
function togglePassword() {
    const passwordInput = document.getElementById('password');
    const eyeIcon = document.getElementById('eye-icon');
    const eyeOffIcon = document.getElementById('eye-off-icon');

    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        eyeIcon.style.display = 'none';
        eyeOffIcon.style.display = 'block';
    } else {
        passwordInput.type = 'password';
        eyeIcon.style.display = 'block';
        eyeOffIcon.style.display = 'none';
    }
}

// Form validation feedback with HTML5 constraint validation
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('.login-form');

    if (form) {
        const email = document.getElementById('email');
        const password = document.getElementById('password');

        // Set custom validation messages
        if (email) {
            email.addEventListener('invalid', function(e) {
                if (this.validity.valueMissing) {
                    this.setCustomValidity('Please enter your email address');
                } else if (this.validity.typeMismatch) {
                    this.setCustomValidity('Please enter a valid email address with @');
                } else if (this.validity.tooLong) {
                    this.setCustomValidity('Email must not exceed 150 characters');
                } else {
                    this.setCustomValidity('Please enter a valid email address');
                }
            });

            email.addEventListener('input', function() {
                this.setCustomValidity('');
            });
        }

        if (password) {
            password.addEventListener('invalid', function(e) {
                if (this.validity.valueMissing) {
                    this.setCustomValidity('Please enter your password');
                } else if (this.validity.tooShort) {
                    this.setCustomValidity('Password must be at least 8 characters');
                } else if (this.validity.tooLong) {
                    this.setCustomValidity('Password must not exceed 64 characters');
                } else {
                    this.setCustomValidity('Please enter a valid password');
                }
            });

            password.addEventListener('input', function() {
                this.setCustomValidity('');
            });
        }

        // Let browser handle validation with constraint validation API
        form.addEventListener('submit', function(e) {
            // Browser will automatically show validation tooltips
            // Only prevent if form is invalid
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    }

    // Auto-dismiss error alerts after 5 seconds
    const errorAlert = document.getElementById('error-alert');
    if (errorAlert) {
        setTimeout(function() {
            errorAlert.style.transition = 'opacity 0.3s ease';
            errorAlert.style.opacity = '0';
            setTimeout(function() {
                errorAlert.remove();
            }, 300);
        }, 5000);
    }

    // Auto-dismiss inline error messages after 5 seconds
    const inlineErrors = document.querySelectorAll('.invalid-feedback');
    if (inlineErrors.length > 0) {
        setTimeout(function() {
            inlineErrors.forEach(function(error) {
                error.classList.add('fade-out');
                setTimeout(function() {
                    error.remove();
                    // Remove is-invalid class from input
                    const input = error.previousElementSibling;
                    if (input && input.classList.contains('is-invalid')) {
                        input.classList.remove('is-invalid');
                    }
                }, 300);
            });
        }, 5000);
    }
});

