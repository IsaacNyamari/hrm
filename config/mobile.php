<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mobile app
    |--------------------------------------------------------------------------
    |
    | Public values the HRM mobile app reads from GET /api/v1/config at start-up
    | so nothing is hard-coded in the build. The Reverb values are the ones a
    | client connects to (the same public host the browser uses), not the
    | server-to-server host Laravel publishes events through.
    |
    */

    'min_version' => env('MOBILE_MIN_VERSION', '1.0.0'),

    'latest_version' => env('MOBILE_LATEST_VERSION', '1.0.0'),

    'store_urls' => [
        'ios' => env('MOBILE_APP_STORE_URL'),
        'android' => env('MOBILE_PLAY_STORE_URL'),
    ],

    'reverb' => [
        'key' => env('REVERB_APP_KEY'),
        'host' => env('MOBILE_REVERB_HOST', env('VITE_REVERB_HOST', env('REVERB_HOST'))),
        'port' => (int) env('MOBILE_REVERB_PORT', env('VITE_REVERB_PORT', env('REVERB_PORT', 443))),
        'scheme' => env('MOBILE_REVERB_SCHEME', env('VITE_REVERB_SCHEME', env('REVERB_SCHEME', 'https'))),
    ],

    'support' => [
        'email' => env('MOBILE_SUPPORT_EMAIL', env('MAIL_FROM_ADDRESS')),
        'phone' => env('MOBILE_SUPPORT_PHONE'),
        'url' => env('MOBILE_SUPPORT_URL', rtrim((string) env('APP_URL'), '/').'/contact'),
    ],

    'privacy_url' => env('MOBILE_PRIVACY_URL'),

    /*
    | Two-factor sign-in: how long the challenge issued after a correct password
    | stays valid, in seconds.
    */
    'two_factor_challenge_ttl' => 300,

    /*
    | Leave attachments uploaded from the app.
    */
    'leave_attachment_disk' => env('MOBILE_LEAVE_ATTACHMENT_DISK', 'local'),

    'leave_attachment_max_kb' => 5120,

];
