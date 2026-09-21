<?php

use App\Enums\PaymentProvider;

return [

    // Gateway tenants pay through until a platform admin picks one under
    // Platform → Settings (stored in platform_settings, which takes precedence).
    'default_provider' => env('BILLING_DEFAULT_PROVIDER', PaymentProvider::Paystack->value),

    // Gateways the platform admin can choose from. Tenants never choose.
    'providers' => [
        PaymentProvider::Paystack->value,
        PaymentProvider::Mpesa->value,
    ],

    'currency' => env('BILLING_CURRENCY', 'KES'),

    // Percentage applied to the invoice subtotal (e.g. 16 for 16% VAT).
    'tax_rate' => (float) env('BILLING_TAX_RATE', 0),

    'invoice_number_prefix' => env('BILLING_INVOICE_PREFIX', 'INV'),

    // Days a tenant keeps access after a payment first fails before the
    // subscription is expired.
    'grace_days' => (int) env('BILLING_GRACE_DAYS', 3),

    // Route names always reachable even when a subscription has lapsed.
    'always_accessible_routes' => [
        'billing.*',
        'subscription.*',
        'settings.*',
        'support.*',
        'logout',
    ],

    // Days-remaining marks at which a trial-ending reminder is sent.
    'trial_reminder_days' => [7, 3, 1],

];
