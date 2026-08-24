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
    ],

    'cloudinary' => [
        'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
        'api_key' => env('CLOUDINARY_API_KEY'),
        'api_secret' => env('CLOUDINARY_API_SECRET'),
        'folder' => env('CLOUDINARY_FOLDER', 'sk_oneportal/sk_fed_posts'),
        'barangay_logos_folder' => env('CLOUDINARY_BARANGAY_LOGOS_FOLDER', 'sk_oneportal/barangay_logos'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Portal URLs for account invitation emails (password setup links)
    |--------------------------------------------------------------------------
    */
    'sk_officials_app_url' => env('SK_OFFICIALS_APP_URL', env('APP_URL')),
    'sk_fed_app_url' => env('SK_FED_APP_URL', env('APP_URL')),
    'kabataan_app_url' => env('KABATAAN_APP_URL', 'http://localhost:8002'),
    'account_activation_expire_minutes' => (int) env('ACCOUNT_ACTIVATION_EXPIRES_MINUTES', 60 * 24),

];
