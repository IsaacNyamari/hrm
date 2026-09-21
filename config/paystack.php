<?php

/*
| binkode/laravel-paystack. The app talks to Paystack only through
| App\Services\Payment\Gateways\PaystackGateway; its webhook is
| POST /api/webhooks/paystack (routes/api.php).
|
| The package also registers proxy routes for the whole Paystack API under
| /api and a generic /api/hooks webhook. Both stay disabled: exposing them
| would let anyone drive the Paystack account through this app.
*/
return [
    'public_key' => env('PAYSTACK_PUBLIC_KEY'),
    'secret_key' => env('PAYSTACK_SECRET_KEY'),
    'url' => env('PAYSTACK_URL', 'https://api.paystack.co'),
    'merchant_email' => env('PAYSTACK_MERCHANT_EMAIL'),

    'route' => [
        'middleware' => ['paystack_route_disabled', 'api'],
        'prefix' => 'api/paystack',
        'hook_middleware' => ['paystack_route_disabled', 'api'],
    ],
];
