<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Two-factor authentication for platform operators
    |--------------------------------------------------------------------------
    |
    | Platform administrators can suspend organizations, change plans and deploy
    | code, so they must have two-factor authentication confirmed before the
    | platform area opens. They are sent to their security settings to enable it.
    | Set REQUIRE_PLATFORM_2FA=false only for local development.
    |
    */
    'require_two_factor_platform' => (bool) env('REQUIRE_PLATFORM_2FA', true),

    /*
    |--------------------------------------------------------------------------
    | API token lifetime
    |--------------------------------------------------------------------------
    |
    | Integration tokens expire so a forgotten or leaked token stops working.
    | Users may pick a shorter lifetime, never a longer one than the maximum.
    |
    */
    'api_token_default_days' => (int) env('API_TOKEN_DEFAULT_DAYS', 90),
    'api_token_max_days' => (int) env('API_TOKEN_MAX_DAYS', 365),

];
