<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Platform Super Admin
    |--------------------------------------------------------------------------
    |
    | The operator account created by Database\Seeders\PlatformSeeder. Set real
    | values in production .env; the defaults are for local development only.
    |
    */

    'admin' => [
        'name' => env('PLATFORM_ADMIN_NAME', 'Platform Super Admin'),
        'email' => env('PLATFORM_ADMIN_EMAIL', 'superadmin@hrm.test'),
        'password' => env('PLATFORM_ADMIN_PASSWORD', 'password'),
    ],

];
