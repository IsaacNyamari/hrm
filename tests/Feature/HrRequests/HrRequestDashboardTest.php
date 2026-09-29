<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Livewire\HrRequests\Board;
use App\Models\Employee;
use App\Models\HrRequest;
use App\Support\TenantContext;
use Livewire\Livewire;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    $this->tenant = tenantWithRoles(['slug' => 'hrq-co']);
    app(TenantContext::class)->set($this->tenant);

    $this->employeeUser = userForTenant($this->tenant, Role::Employee);
    $this->employee = Employee::factory()->forTenant($this->tenant)->create(['user_id' => $this->employeeUser->id]);

    $this->hr = userForTenant($this->tenant, Role::HrManager); // employees.view
});

it('lets an employee submit an hr request from the dashboard', function () {
    $this->actingAs($this->employeeUser);

    Livewire::test(Board::class)
        ->call('startCreate')
        ->set('form.type', 'salary_certificate')
        ->set('form.subject', 'Need a salary certificate for visa')
        ->set('form.priority', 'high')
        ->call('submit')
        ->assertDispatched('toast-show')
        ->assertSet('showForm', false);

    $request = HrRequest::query()->where('subject', 'Need a salary certificate for visa')->firstOrFail();
    expect($request->employee_id)->toBe($this->employee->id)
        ->and($request->status->value)->toBe('open')
        ->and($request->reference)->toStartWith('HR-');
});

it('lets hr assign, change status, and comment on a request from the dashboard', function () {
    $request = HrRequest::factory()->forEmployee($this->employee)->create();

    $this->actingAs($this->hr);

    Livewire::test(Board::class)
        ->call('view', $request->uuid)
        ->assertSet('statusValue', 'open')
        ->call('assignToMe', $request->uuid)
        ->assertDispatched('toast-show');

    expect($request->fresh()->assigned_to)->toBe($this->hr->id)
        ->and($request->fresh()->status->value)->toBe('assigned');

    Livewire::test(Board::class)
        ->call('view', $request->uuid)
        ->set('statusValue', 'resolved')
        ->call('saveStatus')
        ->assertDispatched('toast-show');

    expect($request->fresh()->status->value)->toBe('resolved')
        ->and($request->fresh()->resolved_at)->not->toBeNull();

    Livewire::test(Board::class)
        ->call('view', $request->uuid)
        ->set('newComment', 'Certificate attached.')
        ->set('newCommentInternal', true)
        ->call('addComment')
        ->assertDispatched('toast-show');

    expect($request->comments()->where('is_internal', true)->exists())->toBeTrue();
});

it('lets the requester view their own request but hides internal comments', function () {
    $request = HrRequest::factory()->forEmployee($this->employee)->create();
    $request->comments()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->hr->id,
        'body' => 'Internal note',
        'is_internal' => true,
    ]);
    $request->comments()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->hr->id,
        'body' => 'Visible reply',
        'is_internal' => false,
    ]);

    $this->actingAs($this->employeeUser);

    Livewire::test(Board::class)
        ->call('view', $request->uuid)
        ->assertSee('Visible reply')
        ->assertDontSee('Internal note');
});

it('blocks a bystander from viewing someone else\'s hr request', function () {
    $request = HrRequest::factory()->forEmployee($this->employee)->create();
    $bystander = userForTenant($this->tenant, Role::Employee);

    $this->actingAs($bystander);

    Livewire::test(Board::class)->call('view', $request->uuid)->assertForbidden();
});

it('serves the hr requests page as html, not raw json', function () {
    $this->actingAs($this->hr)
        ->get('http://hrq-co.hrm.test/hr-requests-board')
        ->assertOk()
        ->assertSee('HR requests');
});
