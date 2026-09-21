<?php

declare(strict_types=1);

use App\Enums\LeadKind;
use App\Enums\LeadStatus;
use App\Enums\Role;
use App\Livewire\Marketing\LeadForm;
use App\Models\Lead;
use App\Models\User;
use App\Notifications\NewLeadNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();
    RateLimiter::clear('lead-form:127.0.0.1');
});

it('stores a quote request and notifies platform admins', function () {
    $admin = User::factory()->platformAdmin(Role::SuperAdmin)->create();

    Livewire::test(LeadForm::class, ['kind' => 'quote', 'plan' => 'business'])
        ->set('name', 'Jane Wanjiru')
        ->set('email', 'Jane@Acme.co.ke')
        ->set('company', 'Acme Ltd')
        ->set('employee_count', 45)
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('sent', true);

    $lead = Lead::query()->sole();

    expect($lead->kind)->toBe(LeadKind::Quote)
        ->and($lead->status)->toBe(LeadStatus::New)
        ->and($lead->email)->toBe('jane@acme.co.ke')
        ->and($lead->plan_interest)->toBe('business');

    Notification::assertSentTo($admin, NewLeadNotification::class);
});

it('requires organization details for a quote but a message for contact', function () {
    Livewire::test(LeadForm::class, ['kind' => 'quote'])
        ->set('name', 'Jane')
        ->set('email', 'jane@example.com')
        ->call('submit')
        ->assertHasErrors(['company' => 'required', 'employee_count' => 'required']);

    Livewire::test(LeadForm::class, ['kind' => 'contact'])
        ->set('name', 'Jane')
        ->set('email', 'jane@example.com')
        ->call('submit')
        ->assertHasErrors(['message' => 'required'])
        ->assertHasNoErrors(['company', 'employee_count']);

    expect(Lead::query()->count())->toBe(0);
});

it('silently drops submissions that fill the honeypot', function () {
    Livewire::test(LeadForm::class, ['kind' => 'contact'])
        ->set('name', 'Bot')
        ->set('email', 'bot@example.com')
        ->set('message', 'Buy now')
        ->set('website', 'https://spam.example')
        ->call('submit')
        ->assertSet('sent', true);

    expect(Lead::query()->count())->toBe(0);
});

it('rate limits repeated submissions from one address', function () {
    foreach (range(1, 6) as $attempt) {
        $component = Livewire::test(LeadForm::class, ['kind' => 'contact'])
            ->set('name', 'Jane')
            ->set('email', "jane{$attempt}@example.com")
            ->set('message', 'Hello')
            ->call('submit');
    }

    $component->assertHasErrors(['email']);
    expect(Lead::query()->count())->toBe(5);
});
