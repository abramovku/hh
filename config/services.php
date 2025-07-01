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
    'twin' => [
        'auth_url' => env('TWIN_AUTH_URL'),
        'api_url' => env('TWIN_API_URL'),
        'auth_email' => env('TWIN_AUTH_EMAIL'),
        'auth_password' => env('TWIN_AUTH_PASSWORD'),
        'chat_id' => env('TWIN_CHAT_ID'),
        'bot_id' => env('TWIN_BOT_ID'),
    ],
];
