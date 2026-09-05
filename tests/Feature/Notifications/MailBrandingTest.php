<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Listeners\ApplyTenantMailBranding;
use App\Models\TenantBranding;
use App\Support\TenantContext;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Mime\Email;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    Storage::fake('public');
    $this->tenant = tenantWithRoles(['slug' => 'brand-co', 'name' => 'Brand Co']);
    app(TenantContext::class)->set($this->tenant);
    $this->admin = userForTenant($this->tenant, Role::HrAdministrator); // settings.manage
});

it('rewrites the From address to the tenant branding on outgoing mail', function () {
    TenantBranding::factory()->forTenant($this->tenant)->create([
        'from_name' => 'Brand Co People', 'from_email' => 'people@brand-co.example', 'reply_to_email' => 'hr@brand-co.example',
    ]);

    $message = (new Email)->from('default@example.com')->to('someone@example.com')->text('hi');
    app(ApplyTenantMailBranding::class)->handle(new MessageSending($message));

    expect($message->getFrom()[0]->getAddress())->toBe('people@brand-co.example')
        ->and($message->getFrom()[0]->getName())->toBe('Brand Co People')
        ->and($message->getReplyTo()[0]->getAddress())->toBe('hr@brand-co.example');
});

it('leaves platform mail untouched when no tenant is bound', function () {
    app(TenantContext::class)->forget();

    $message = (new Email)->from('platform@example.com')->to('x@example.com')->text('hi');
    app(ApplyTenantMailBranding::class)->handle(new MessageSending($message));

    expect($message->getFrom()[0]->getAddress())->toBe('platform@example.com');
});

it('lets an admin read and update the branding', function () {
    $this->actingAs($this->admin)
        ->putJson('http://brand-co.hrm.test/branding', [
            'brand_name' => 'Brand Co', 'primary_color' => '#123abc', 'from_email' => 'noreply@brand-co.example',
        ])
        ->assertOk()
        ->assertJsonPath('data.primary_color', '#123abc')
        ->assertJsonPath('data.from_email', 'noreply@brand-co.example');

    $this->actingAs($this->admin)
        ->getJson('http://brand-co.hrm.test/branding')
        ->assertOk()
        ->assertJsonPath('data.brand_name', 'Brand Co');
});

it('rejects an invalid colour', function () {
    $this->actingAs($this->admin)
        ->putJson('http://brand-co.hrm.test/branding', ['primary_color' => 'not-a-color'])
        ->assertStatus(422);
});

it('forbids branding changes without settings.manage', function () {
    $staff = userForTenant($this->tenant, Role::Employee);

    $this->actingAs($staff)
        ->getJson('http://brand-co.hrm.test/branding')
        ->assertForbidden();
});
