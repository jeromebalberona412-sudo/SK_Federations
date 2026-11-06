<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'turnstile' => [
        'enabled' => (bool) env('TURNSTILE_ENABLED', false),
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
        'verify_url' => env('TURNSTILE_VERIFY_URL', 'https://challenges.cloudflare.com/turnstile/v0/siteverify'),
        'failed_attempts' => (int) env('AUTH_TURNSTILE_FAILED_ATTEMPTS', 3),
        'state_ttl_seconds' => (int) env('AUTH_TURNSTILE_STATE_TTL', 3600),
    ],

    'cloudinary' => [
        'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
        'api_key' => env('CLOUDINARY_API_KEY'),
        'api_secret' => env('CLOUDINARY_API_SECRET'),
        'folder' => env('CLOUDINARY_FOLDER', 'sk_oneportal/sk_fed_posts'),
        'barangay_logos_folder' => env('CLOUDINARY_BARANGAY_LOGOS_FOLDER', 'sk_oneportal/barangay_logos'),
        'communication_folder' => env('CLOUDINARY_COMMUNICATION_FOLDER', 'communication'),
        'communication_upload_preset' => env('CLOUDINARY_COMMUNICATION_UPLOAD_PRESET', ''),
        'group_chat_avatar_folder' => env('CLOUDINARY_GROUP_CHAT_AVATAR_FOLDER', 'Avatar_Profile_GroupChats'),
        'group_chat_avatar_upload_preset' => env(
            'CLOUDINARY_GROUP_CHAT_AVATAR_UPLOAD_PRESET',
            'Avatar_Profile_GroupChats'
        ),
        'community_video_folder' => env('CLOUDINARY_COMMUNITY_VIDEO_FOLDER', 'SK_Federations_community_video'),
        'community_video_upload_preset' => env(
            'CLOUDINARY_COMMUNITY_VIDEO_UPLOAD_PRESET',
            'SK_Federations_community_video'
        ),
        'community_video_max_bytes' => (int) env('CLOUDINARY_COMMUNITY_VIDEO_MAX_BYTES', 524288000), // 500 MB
        'community_video_max_count' => (int) env('CLOUDINARY_COMMUNITY_VIDEO_MAX_COUNT', 30),
    ],

    'google_drive' => [
        // Prefer service account (share Drive files/folders with the SA email).
        'api_key' => env('GOOGLE_DRIVE_API_KEY'),
        // Absolute path, project-relative path, or raw JSON string.
        // Default: project-root concise-rampart service account JSON (same as SK Officials).
        'service_account_json' => env(
            'GOOGLE_DRIVE_SERVICE_ACCOUNT_JSON',
            base_path('concise-rampart-500822-t7-d8f7d20cf1db.json')
        ),
        'service_account_email' => env(
            'GOOGLE_DRIVE_SERVICE_ACCOUNT_EMAIL',
            'sk-oneportal-drive@concise-rampart-500822-t7.iam.gserviceaccount.com'
        ),
        'folder_max_videos' => (int) env('GOOGLE_DRIVE_FOLDER_MAX_VIDEOS', 50),
    ],

    'supabase' => [
        'url' => env('SUPABASE_URL', env('VITE_SUPABASE_URL')),
        'service_role_key' => env('SUPABASE_SERVICE_ROLE_KEY'),
        'communication_bucket' => env('SUPABASE_COMMUNICATION_BUCKET', 'communication'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Portal URLs for account invitation emails (password setup links)
    |--------------------------------------------------------------------------
    |
    | Always include the scheme (https://). A bare hostname becomes a relative
    | path in email links and 404s as /your-host.example/activate-account/...
    |
    | SK Officials accounts → SK_OFFICIALS_APP_URL (required, must differ from Federation)
    | SK Federation accounts → SK_FED_APP_URL (defaults to APP_URL)
    |
    | Default Officials URL matches PortalAppUrl::CANONICAL_OFFICIALS_URL so Accounts
    | password-setup emails still work when the env key is missing after deploy.
    */
    'sk_officials_app_url' => env('SK_OFFICIALS_APP_URL', 'https://skofficials.skoneportal.com'),
    'sk_fed_app_url' => env('SK_FED_APP_URL', env('APP_URL')),
    'kabataan_app_url' => env('KABATAAN_APP_URL'),
    'account_activation_expire_minutes' => (int) env('ACCOUNT_ACTIVATION_EXPIRES_MINUTES', 60 * 24),

];
