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

    /*
    |--------------------------------------------------------------------------
    | Control plane (formerly dns-console-web, now in-process)
    |--------------------------------------------------------------------------
    |
    | The control plane lives inside portal-web; resolver and geodns call
    | the public Agent / shared-token-protected routes below. The *_FILE
    | entries are read at request time so we can rotate secrets without
    | a deploy. If the *non-FILE* env var is set, it is used instead of
    | the file — useful for local dev and CI.
    */
    'console' => [
        'bootstrap_token' => env('BOOTSTRAP_SHARED_TOKEN'),
        'bootstrap_token_file' => env('BOOTSTRAP_SHARED_TOKEN_FILE'),
        'internal_token' => env('INTERNAL_SHARED_TOKEN'),
        'internal_token_file' => env('INTERNAL_SHARED_TOKEN_FILE'),
        'admin_token' => env('ADMIN_SHARED_TOKEN'),
        'admin_token_file' => env('ADMIN_SHARED_TOKEN_FILE'),
    ],

    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'fake' => env('STRIPE_FAKE', true),
    ],

];
