import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',

                // ── Other modules ──────────────────────────────────────────
                'app/Modules/Barangay_ABYIP/Assets/js/barangay_abyip.js',
                'app/Modules/Barangay_ABYIP/Assets/css/barangay_abyip.css',
                'app/Modules/Accounts/assets/css/account.css',
                'app/Modules/Accounts/assets/js/account-date-fields.js',
                'app/Modules/Accounts/assets/js/account.js',
                'app/Modules/AuditLog/assets/css/auditlogs.css',
                'app/Modules/AuditLog/assets/js/auditlogs.js',
                'app/Modules/Archive_Management/assets/css/deleted-sk-officials.css',
                'app/Modules/Archive_Management/assets/js/deleted-sk-officials.js',
                'app/Modules/Archive_Management/assets/css/SK_federation.css',
                'app/Modules/Archive_Management/assets/js/SK_federation.js',
                'app/Modules/Archive_Management/assets/css/SK_officials.css',
                'app/Modules/Archive_Management/assets/js/SK_officials.js',

                // ── Authentication — shared ────────────────────────────────
                'app/Modules/Authentication/assets/css/auth-base.css',
                'app/Modules/Authentication/assets/css/auth-legal.css',
                'app/Modules/Authentication/assets/js/auth-legal.js',

                // ── Authentication — shared Turnstile ───────────────────────
                'app/Modules/Authentication/assets/css/turnstile-gate.css',
                'app/Modules/Authentication/assets/js/turnstile-gate.js',

                // ── Authentication — Login ─────────────────────────────────
                'app/Modules/Authentication/assets/css/login.css',
                'app/Modules/Authentication/assets/js/login.js',

                // ── Authentication — Forgot Password ──────────────────────
                'app/Modules/Authentication/assets/css/forgot-password.css',
                'app/Modules/Authentication/assets/js/forgot-password.js',

                // ── Authentication — Forgot Password / Verify Email ────────
                'app/Modules/Authentication/assets/css/fp-verify-email.css',
                'app/Modules/Authentication/assets/js/fp-verify-email.js',

                // ── Authentication — Email Verify Wait ────────────────────
                'app/Modules/Authentication/assets/css/verify-wait.css',
                'app/Modules/Authentication/assets/js/verify-wait.js',
            ],
            refresh: true,
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
