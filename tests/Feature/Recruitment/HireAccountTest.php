<?php

declare(strict_types=1);

use App\Actions\Employees\CreateStaffAccount;
use App\Enums\OfferStatus;
use App\Enums\Role;
use App\Livewire\Recruitment\Board;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\Employee;
use App\Models\Offer;
use App\Models\User;
use App\Models\Vacancy;
use App\Notifications\WelcomeNewHireNotification;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();
    config(['tenancy.central_domains' => ['hrm.test']]);

    $this->tenant = tenantWithRoles(['slug' => 'hire-co', 'name' => 'Hire Co']);
    app(TenantContext::class)->set($this->tenant);
    $this->hr = userForTenant($this->tenant, Role::HrAdministrator);

    $vacancy = Vacancy::factory()->forTenant($this->tenant)->open()->create(['title' => 'Driver']);
    $applicant = Applicant::factory()->forTenant($this->tenant)->create(['first_name' => 'Otieno', 'last_name' => 'Kamau', 'email' => 'otieno@example.com']);
    $application = Application::factory()->forVacancy($vacancy)->forApplicant($applicant)->create(['stage' => 'hired', 'status' => 'hired']);
    $this->offer = Offer::factory()->forApplication($application)->create(['status' => OfferStatus::Accepted, 'job_title' => 'Driver', 'salary_cents' => 4_500_000]);
});

it('hires from the board: employee, Employee-role login and welcome email', function () {
    $this->actingAs($this->hr);

    Livewire::test(Board::class)
        ->assertSee('Hire & create account')
        ->call('hire', $this->offer->uuid);

    $employee = Employee::query()->where('email', 'otieno@example.com')->firstOrFail();
    $user = User::query()->where('email', 'otieno@example.com')->firstOrFail();

    expect($employee->user_id)->toBe($user->id)
        ->and($user->tenant_id)->toBe($this->tenant->id)
        ->and($user->hasRole(Role::Employee->value))->toBeTrue()
        ->and($user->isSelfServiceOnly())->toBeTrue()
        ->and($this->offer->fresh()->converted_employee_id)->toBe($employee->id);

    Notification::assertSentTo($user, WelcomeNewHireNotification::class);
});

it('lets the new hire set a password once, then lands them on My HR', function () {
    $this->actingAs($this->hr);
    Livewire::test(Board::class)->call('hire', $this->offer->uuid);
    auth()->logout();

    $user = User::query()->where('email', 'otieno@example.com')->firstOrFail();
    $url = CreateStaffAccount::welcomeUrl($user);

    $this->get($url)->assertOk()->assertSee('Welcome, Otieno!');

    $this->post($url, ['password' => 'Sup3r-Secret!pass', 'password_confirmation' => 'Sup3r-Secret!pass'])
        ->assertRedirect(route('portal'));

    $this->assertAuthenticatedAs($user->fresh());

    auth()->logout();
    $this->get($url)->assertOk()->assertSee('This link is no longer valid');
});

it('rejects a tampered welcome link', function () {
    $this->actingAs($this->hr);
    Livewire::test(Board::class)->call('hire', $this->offer->uuid);
    auth()->logout();

    $user = User::query()->where('email', 'otieno@example.com')->firstOrFail();

    $this->get(CreateStaffAccount::welcomeUrl($user).'x')->assertSee('This link is no longer valid');
});

it('does not hijack an email that belongs to another organization', function () {
    $other = tenantWithRoles(['slug' => 'elsewhere']);
    $existing = User::factory()->forTenant($other)->create(['email' => 'otieno@example.com']);
    $this->actingAs($this->hr);

    Livewire::test(Board::class)->call('hire', $this->offer->uuid);

    expect(Employee::query()->where('email', 'otieno@example.com')->firstOrFail()->user_id)->toBeNull()
        ->and($existing->fresh()->tenant_id)->toBe($other->id);
    Notification::assertNothingSent();
});

it('sends staff with only the Employee role to My HR instead of the manager dashboard', function () {
    $user = userForTenant($this->tenant, Role::Employee);
    Employee::factory()->forTenant($this->tenant)->create(['user_id' => $user->id]);

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('portal'));
    $this->actingAs($this->hr)->get(route('dashboard'))->assertOk();
});
