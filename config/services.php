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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    'hh' => [
        'api_url' => env('HH_API_URL'),
        'api_auth_url' => env('HH_API_AUTH_URL'),
        'client_id' => env('HH_CLIENT_ID'),
        'client_secret' => env('HH_CLIENT_SECRET'),
        'redirect_uri' => env('HH_REDIRECT_URI'),
        'employer' => env('HH_EMPLOYER'),
    ],
    'estaff' => [
        'url' => env('ESTAFF_API_URL'),
        'token' => env('ESTAFF_TOKEN'),
    ],
    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('TELEGRAM_CHAT_ID'),
    ],
    'monitor' => [
        'failed_jobs_limit' => env('FAILED_JOBS_LIMIT', 5),
        'notify_cooldown' => env('FAILED_JOBS_NOTIFY_COOLDOWN', 3600),
    ],
    'twin' => [
        'auth_url' => env('TWIN_AUTH_URL'),
        'auth_email' => env('TWIN_AUTH_EMAIL'),
        'auth_password' => env('TWIN_AUTH_PASSWORD'),
        'chat_id' => env('TWIN_CHAT_ID'),
        'bot_id' => env('TWIN_BOT_ID'),
        'cold_bot_id' => env('TWIN_COLD_BOT_ID'),
        'provider_id' => env('TWIN_PROVIDER_ID'),
        'default_exec' => env('TWIN_DEFAULT_EXEC'),
        'cid' => env('TWIN_CID'),
        'sms_text' => env('TWIN_SMS_TEXT'),
        'sms_from' => env('TWIN_SMS_FROM', 'GloriaJeans'),
        'call_type' => env('TWIN_CALL_TYPE', 'Продавец-Кассир РФ'),
        'allowed_time_from' => env('TWIN_ALLOWED_TIME_FROM', '9:00:00'),
        'allowed_time_to' => env('TWIN_ALLOWED_TIME_TO', '22:00:00'),
    ],
];
