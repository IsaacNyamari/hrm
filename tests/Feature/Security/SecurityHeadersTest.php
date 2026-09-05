<?php

declare(strict_types=1);

it('sends baseline hardening headers on web responses', function () {
    $response = $this->get('/');

    $response->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('X-Permitted-Cross-Domain-Policies', 'none');
});

it('does not send HSTS on a plain-HTTP request', function () {
    $this->get('/')->assertHeaderMissing('Strict-Transport-Security');
});
