<?php

declare(strict_types=1);

use App\Enums\PaymentProvider;
use App\Enums\Role;
use App\Livewire\Platform\Settings;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Payment\PaymentManager;
use Livewire\Livewire;

it('defaults the active payment gateway to Paystack', function () {
    expect(app(PaymentManager::class)->activeProvider())->toBe(PaymentProvider::Paystack);
});

it('lets a platform admin switch the active payment gateway and audits it', function () {
    $this->actingAs(User::factory()->platformAdmin(Role::SuperAdmin)->create());

    $this->get(route('platform.settings'))->assertOk()->assertSee('Payment gateway');

    Livewire::test(Settings::class)
        ->assertSet('paymentProvider', 'paystack')
        ->set('paymentProvider', 'mpesa')
        ->call('save')
        ->assertHasNoErrors();

    expect(app(PaymentManager::class)->activeProvider())->toBe(PaymentProvider::Mpesa)
        ->and(AuditLog::query()->where('event', 'platform.settings.payment_provider_changed')->exists())->toBeTrue();
});

it('rejects a gateway that is not selectable', function () {
    $this->actingAs(User::factory()->platformAdmin(Role::SuperAdmin)->create());

    Livewire::test(Settings::class)
        ->set('paymentProvider', 'manual')
        ->call('save')
        ->assertHasErrors(['paymentProvider' => 'in']);

    expect(app(PaymentManager::class)->activeProvider())->toBe(PaymentProvider::Paystack);
});

it('forbids platform staff without settings permission', function () {
    $this->actingAs(User::factory()->platformAdmin(Role::SupportAdmin)->create());

    $this->get(route('platform.settings'))->assertForbidden();
});
