<?php

return [
    'tenant_code' => env('SK_FED_TENANT_CODE', 'santa_cruz'),
    'required_role' => env('SK_FED_REQUIRED_ROLE', 'sk_fed'),

    'trusted_device' => [
        'expiration_days' => (int) env('SK_FED_TRUSTED_DEVICE_EXPIRATION_DAYS', 30),
        'enforce_every_request' => (bool) env('SK_FED_ENFORCE_TRUSTED_DEVICE', true),
    ],

    'rate_limit' => [
        'window_minutes' => (int) env('SK_FED_LOGIN_WINDOW_MINUTES', 15),
        'max_failures_per_window' => (int) env('SK_FED_LOGIN_MAX_FAILURES', 5),
        'max_lock_minutes' => (int) env('SK_FED_LOGIN_MAX_LOCK_MINUTES', 60),
    ],

    'password_reset' => [
        'rate_limit' => [
            'ip_per_minute' => (int) env('SK_FED_PASSWORD_RESET_IP_PER_MINUTE', 5),
            'email_per_hour' => (int) env('SK_FED_PASSWORD_RESET_EMAIL_PER_HOUR', 10),
            'reset_form_per_minute' => (int) env('SK_FED_PASSWORD_RESET_FORM_PER_MINUTE', 20),
        ],
        'password' => [
            'min_length' => (int) env('SK_FED_PASSWORD_RESET_MIN_LENGTH', 8),
            'max_length' => (int) env('SK_FED_PASSWORD_RESET_MAX_LENGTH', 64),
        ],
    ],

    'suspicious' => [
        'failure_threshold' => (int) env('SK_FED_SUSPICIOUS_FAILURE_THRESHOLD', 3),
        'night_start_hour' => (int) env('SK_FED_SUSPICIOUS_NIGHT_START', 0),
        'night_end_hour' => (int) env('SK_FED_SUSPICIOUS_NIGHT_END', 4),
    ],

    'retention' => [
        'attempt_days' => (int) env('SK_FED_LOGIN_ATTEMPT_RETENTION_DAYS', 90),
        'audit_days' => (int) env('SK_FED_AUDIT_RETENTION_DAYS', 180),
        'session_days' => (int) env('SK_FED_SESSION_RETENTION_DAYS', 30),
    ],

    'verification' => [
        'wait_minutes' => (int) env('SK_FED_EMAIL_VERIFICATION_WAIT_MINUTES', 10),
    ],

    'single_session' => [
        'heartbeat_interval_seconds' => (int) env('SK_FED_HEARTBEAT_INTERVAL_SECONDS', 30),
        'heartbeat_timeout_seconds' => (int) env('SK_FED_HEARTBEAT_TIMEOUT_SECONDS', 120),
        'otp_expiration_minutes' => (int) env('SK_FED_OTP_EXPIRATION_MINUTES', 5),
        'otp_max_attempts' => (int) env('SK_FED_OTP_MAX_ATTEMPTS', 5),
        'otp_resend_cooldown_seconds' => (int) env('SK_FED_OTP_RESEND_COOLDOWN_SECONDS', 60),
    ],

    'feature_flags' => [
        'features.device_verification' => (bool) env('SK_FED_FEATURE_DEVICE_VERIFICATION', true),
        'features.login_alert_notifications' => (bool) env('SK_FED_FEATURE_LOGIN_ALERTS', true),
        'features.suspicious_login_detection' => (bool) env('SK_FED_FEATURE_SUSPICIOUS_LOGIN', true),
    ],
];
