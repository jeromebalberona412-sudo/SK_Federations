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
                // ── Archive & Records modules ─────────────────────────────
                'app/Modules/Deleted_Posts/assets/css/deleted-posts.css',
                'app/Modules/Deleted_Posts/assets/js/deleted-posts.js',
                'app/Modules/Deleted_SK_Officials/assets/css/deleted-sk-officials.css',
                'app/Modules/Deleted_SK_Officials/assets/js/deleted-sk-officials.js',
                'app/Modules/SK_Officials_Records/assets/css/SK_officials.css',
                'app/Modules/SK_Officials_Records/assets/js/SK_officials.js',
                'app/Modules/SK_Federation_Records/assets/css/SK_federation.css',
                'app/Modules/SK_Federation_Records/assets/js/SK_federation.js',

                // ── Authentication — shared ────────────────────────────────
                'app/Modules/Authentication/assets/css/auth-base.css',
                'app/Modules/Authentication/assets/css/auth-legal.css',
                'app/Modules/Authentication/assets/js/auth-legal.js',
                'app/Modules/Authentication/assets/css/style.css',
                'app/Modules/Authentication/assets/js/script.js',

                // ── Authentication — shared Turnstile ───────────────────────
                'app/Modules/Authentication/assets/css/turnstile-gate.css',
                'app/Modules/Authentication/assets/js/turnstile-gate.js',

                // ── Authentication — Login ─────────────────────────────────
                'app/Modules/Authentication/assets/css/login.css',
                'app/Modules/Authentication/assets/js/login.js',

                // ── Authentication — Forgot Password ──────────────────────
                'app/Modules/Authentication/assets/css/forgot-password.css',
                'app/Modules/Authentication/assets/js/forgot-password.js',

                // ── Authentication — Set New Password ─────────────────────
                'app/Modules/Authentication/assets/css/reset-password.css',
                'app/Modules/Authentication/assets/js/reset-password.js',

                // ── Authentication — Forgot Password / Verify Email ────────
                'app/Modules/Authentication/assets/css/fp-verify-email.css',
                'app/Modules/Authentication/assets/js/fp-verify-email.js',

                // ── Authentication — Email Verify Wait ────────────────────
                'app/Modules/Authentication/assets/css/verify-wait.css',
                'app/Modules/Authentication/assets/js/verify-wait.js',

                // ── Barangay Logos ───────────────────────────────────────────
                'app/Modules/BarangayLogos/assets/css/barangay-logos.css',
                'app/Modules/BarangayLogos/assets/js/barangay-logos.js',

                // ── Barangay Monitoring ───────────────────────────────────
                'app/Modules/BarangayMonitoring/assets/css/barangay-monitoring.css',
                'app/Modules/BarangayMonitoring/assets/js/barangay-monitoring.js',
                'app/Modules/BarangayMonitoring/assets/js/barangay-monitoring-schedule.js',
                'app/Modules/BarangayMonitoring/assets/js/barangay-monitoring-show.js',

                // ── Calendar ───────────────────────────────────────────────
                'app/Modules/Calendar/assets/css/calendar.css',
                'app/Modules/Calendar/assets/js/calendar.js',

                // ── Deleted Calendar Notes ────────────────────────────────
                'app/Modules/Deleted_Calendar_Notes/assets/css/deleted-calendar-notes.css',
                'app/Modules/Deleted_Calendar_Notes/assets/js/deleted-calendar-notes.js',

                // ── Community Feed ────────────────────────────────────────
                'app/Modules/CommunityFeed/assets/css/community-feed.css',
                'app/Modules/CommunityFeed/assets/css/community-feed-public.css',
                'app/Modules/CommunityFeed/assets/js/prohibited-words.js',
                'app/Modules/CommunityFeed/assets/js/comment-spam-guard.js',
                'app/Modules/CommunityFeed/assets/js/community-feed-media.js',
                'app/Modules/CommunityFeed/assets/js/community-feed.js',
                'app/Modules/CommunityFeed/assets/css/community-feed-comment-preview.css',
                'app/Modules/CommunityFeed/assets/js/community-feed-comment-preview.js',
                'app/Modules/CommunityFeed/assets/js/barangay-profile.js',

                // ── Dashboard ───────────────────────────────────────────────
                'app/Modules/Dashboard/assets/css/dashboard.css',
                'app/Modules/Dashboard/assets/js/dashboard.js',
                'app/Modules/Dashboard/assets/css/statcard.css',
                'app/Modules/Dashboard/assets/js/dashboard-activity.js',
                'app/Modules/Dashboard/assets/js/dashboard-demographics.js',
                'app/Modules/Dashboard/assets/js/dashboard-audit.js',
                'app/Modules/Dashboard/assets/js/dashboard-purok.js',
                'app/Modules/Dashboard/assets/js/kkprofilingchart.js',

                // ── Kabataan Monitoring ───────────────────────────────────
                'app/Modules/KabataanMonitoring/assets/css/kabataan-monitoring.css',
                'app/Modules/KabataanMonitoring/assets/css/kabataan-monitoring-responsive.css',
                'app/Modules/KabataanMonitoring/assets/js/kabataan-monitoring.js',
                'app/Modules/KabataanMonitoring/assets/css/kk-profiling-form.css',
                'app/Modules/KabataanMonitoring/assets/js/kabataan-monitoring-upload.js',
                'app/Modules/KabataanMonitoring/assets/css/kk-questionnaire-view.css',
                'app/Modules/KabataanMonitoring/assets/css/kkprofiling.css',
                'app/Modules/KabataanMonitoring/assets/css/kabataan-print-questionnaire.css',
                'app/Modules/KabataanMonitoring/assets/js/kabataan-monitoring-table.js',
                'app/Modules/KabataanMonitoring/assets/js/kabataan-monitoring-validation.js',
                'app/Modules/KabataanMonitoring/assets/js/kabataan-monitoring-edit.js',

                // ── Layout ───────────────────────────────────────────────────
                'app/Modules/Layout/assets/css/layout.css',
                'app/Modules/Layout/assets/js/layout.js',

                // ── Notifications ───────────────────────────────────────────
                'app/Modules/Notifications/assets/css/notifications.css',
                'app/Modules/Notifications/assets/js/notifications.js',

                // ── Communications ─────────────────────────────────────────
                'app/Modules/Communications/assets/css/communication.css',
                'app/Modules/Communications/assets/js/communication.js',
                'app/Modules/Communications/assets/js/chat.js',
                'app/Modules/Communications/assets/js/realtime.js',
                'app/Modules/Communications/assets/js/webrtc.js',

                // ── Profile ─────────────────────────────────────────────────
                'app/Modules/Profile/assets/css/profile.css',
                'app/Modules/Profile/assets/js/profile.js',
                'app/Modules/Profile/assets/css/account-auth.css',
                'app/Modules/Profile/assets/css/change-email.css',
                'app/Modules/Profile/assets/js/change-email.js',
                'app/Modules/Profile/assets/js/change-email-verify.js',
                'app/Modules/Profile/assets/css/change-password.css',
                'app/Modules/Profile/assets/js/change-password.js',
                'app/Modules/Profile/assets/js/change-password-verify.js',
                'app/Modules/Profile/assets/css/sk-fed-account-auth.css',

                // ── Reports ────────────────────────────────────────────────
                'app/Modules/Reports/assets/css/reports.css',
                'app/Modules/Reports/assets/js/reports.js',

                // ── Turnover ───────────────────────────────────────────────
                'app/Modules/Turnover/assets/css/turnover.css',
                'app/Modules/Turnover/assets/js/turnover.js',

                // ── Tutorial Guide ──────────────────────────────────────────
                'app/Modules/Tutorial_Guide/assets/css/tutorial-guide.css',
                'app/Modules/Tutorial_Guide/assets/js/tutorial-guide.js',
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
