<?php

declare(strict_types=1);

use App\Enums\Gender;
use App\Enums\LeaveStatus;
use App\Enums\Role;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\Leave\LeaveService;
use App\Services\SelfService\SelfServiceDashboard;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->travelTo(CarbonImmutable::parse('2026-09-22 09:00'));

    $this->tenant = tenantWithRoles(['slug' => 'leave-co']);
    app(TenantContext::class)->set($this->tenant);
    $this->user = userForTenant($this->tenant, Role::Employee);
    $this->employee = Employee::factory()->forTenant($this->tenant)->create(['user_id' => $this->user->id, 'gender' => Gender::Male]);
    $this->token = mobileToken($this->user);

    $this->annual = LeaveType::query()->where('code', 'ANNUAL')->firstOrFail();
    app(LeaveService::class)->adjustBalance($this->employee, $this->annual, 2026, 21);
});

it('lists the leave types the employee can take', function () {
    $response = $this->withToken($this->token)->getJson('/api/v1/leave/types')->assertOk();

    $codes = collect($response->json('data'))->pluck('code');
    expect($codes)->toContain('ANNUAL', 'SICK')
        ->and($codes)->not->toContain('MATERNITY'); // restricted to female employees
});

it('returns the same balances as My HR', function () {
    $response = $this->withToken($this->token)->getJson('/api/v1/leave/balances')->assertOk();

    $annual = collect($response->json('data'))->firstWhere('type', 'Annual Leave');

    expect($response->json('data'))->toEqual(app(SelfServiceDashboard::class)->leaveBalances($this->employee, 2026))
        ->and($annual['remaining'])->toEqual($annual['entitled'] - $annual['used'] - $annual['pending'])
        ->and($response->json('meta.year'))->toBe(2026);
});

it('requests leave with half days and a reason', function () {
    $response = $this->withToken($this->token)
        ->postJson('/api/v1/leave/requests', [
            'leave_type_uuid' => $this->annual->uuid,
            'start_date' => '2026-10-05',
            'end_date' => '2026-10-07',
            'half_day_start' => true,
            'reason' => 'Family visit',
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.half_day_start', true)
        ->assertJsonPath('data.leave_type', 'Annual Leave')
        ->assertJsonPath('data.cancellable', true);

    expect((float) $response->json('data.days'))->toBe(2.5);
});

it('uploads an attachment for leave that requires one', function () {
    $sick = LeaveType::query()->where('code', 'SICK')->firstOrFail();

    $this->withToken($this->token)
        ->postJson('/api/v1/leave/requests', ['leave_type_uuid' => $sick->uuid, 'start_date' => '2026-09-23', 'end_date' => '2026-09-23'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'This leave type requires a supporting document.');

    $this->withToken($this->token)
        ->post('/api/v1/leave/requests', [
            'leave_type_uuid' => $sick->uuid,
            'start_date' => '2026-09-23',
            'end_date' => '2026-09-23',
            'attachment' => UploadedFile::fake()->create('sick-note.pdf', 200, 'application/pdf'),
        ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.has_attachment', true);

    $path = LeaveRequest::query()->latest('id')->value('attachment_path');
    Storage::disk('local')->assertExists($path);
    expect($path)->toStartWith('tenants/'.$this->tenant->uuid.'/leave-attachments/');
});

it('surfaces leave rules (overlap, balance) as 422s and validates input', function () {
    $this->withToken($this->token)
        ->postJson('/api/v1/leave/requests', ['leave_type_uuid' => $this->annual->uuid, 'start_date' => '2026-10-05', 'end_date' => '2026-10-02'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('end_date');

    $payload = ['leave_type_uuid' => $this->annual->uuid, 'start_date' => '2026-10-05', 'end_date' => '2026-10-06'];
    $this->withToken($this->token)->postJson('/api/v1/leave/requests', $payload)->assertCreated();
    $this->withToken($this->token)->postJson('/api/v1/leave/requests', $payload)->assertStatus(422)->assertJsonStructure(['message']);

    $this->withToken($this->token)
        ->postJson('/api/v1/leave/requests', ['leave_type_uuid' => $this->annual->uuid, 'start_date' => '2026-11-02', 'end_date' => '2026-12-31'])
        ->assertStatus(422);
});

it('rejects a leave type from another organisation', function () {
    $other = tenantWithRoles(['slug' => 'other-leave']);
    $foreignType = app(TenantContext::class)->run($other, fn () => LeaveType::query()->where('code', 'ANNUAL')->firstOrFail());
    app(TenantContext::class)->set($this->tenant);

    $this->withToken($this->token)
        ->postJson('/api/v1/leave/requests', ['leave_type_uuid' => $foreignType->uuid, 'start_date' => '2026-10-05', 'end_date' => '2026-10-06'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('leave_type_uuid');
});

it('lists only the employee\'s own requests and cancels a pending one', function () {
    $mine = app(LeaveService::class)->request($this->employee, $this->annual, CarbonImmutable::parse('2026-10-05'), CarbonImmutable::parse('2026-10-06'));
    $colleague = Employee::factory()->forTenant($this->tenant)->create();
    app(LeaveService::class)->adjustBalance($colleague, $this->annual, 2026, 21);
    $theirs = app(LeaveService::class)->request($colleague, $this->annual, CarbonImmutable::parse('2026-10-05'), CarbonImmutable::parse('2026-10-06'));

    $this->withToken($this->token)
        ->getJson('/api/v1/leave/requests')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.uuid', $mine->uuid);

    $this->withToken($this->token)->postJson("/api/v1/leave/requests/{$theirs->uuid}/cancel")->assertNotFound();

    $this->withToken($this->token)
        ->postJson("/api/v1/leave/requests/{$mine->uuid}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    expect($mine->fresh()->status)->toBe(LeaveStatus::Cancelled);
});
