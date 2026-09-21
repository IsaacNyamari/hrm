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

    /*
    |--------------------------------------------------------------------------
    | Public contact details (marketing website)
    |--------------------------------------------------------------------------
    |
    | Shown on the Contact page and footer only when set.
    |
    */

    'contact' => [
        'email' => env('CONTACT_EMAIL'),
        'phone' => env('CONTACT_PHONE'),
        'location' => env('CONTACT_LOCATION', 'Nairobi, Kenya'),
    ],

    'admin' => [
        'name' => env('PLATFORM_ADMIN_NAME', 'Platform Super Admin'),
        'email' => env('PLATFORM_ADMIN_EMAIL', 'superadmin@hrm.test'),
        'password' => env('PLATFORM_ADMIN_PASSWORD', 'password'),
    ],

];
