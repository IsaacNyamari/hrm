<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Central domains
    |--------------------------------------------------------------------------
    |
    | Hosts that are NOT tenant subdomains: the marketing site, the platform
    | admin area, tenant registration. A request whose host is a subdomain of
    | one of these resolves a tenant by slug (when subdomains are enabled).
    |
    */

    'central_domains' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('TENANCY_CENTRAL_DOMAINS', 'localhost,127.0.0.1')),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Subdomain resolution
    |--------------------------------------------------------------------------
    |
    | When enabled, "abc-company.hrm.test" resolves the tenant with slug
    | "abc-company". Disable to rely purely on custom domains and/or the
    | authenticated user's tenant.
    |
    */

    'subdomains_enabled' => (bool) env('TENANCY_SUBDOMAINS_ENABLED', true),

];
