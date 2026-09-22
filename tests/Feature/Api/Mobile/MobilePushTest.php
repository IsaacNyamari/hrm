<?php

declare(strict_types=1);

use App\Enums\AttendanceSource;
use App\Enums\Role;
use App\Jobs\SendExpoPushNotification;
use App\Models\DeviceToken;
use App\Models\Employee;
use App\Models\Task;
use App\Notifications\TaskAssignedNotification;
use App\Services\Attendance\AttendanceService;
use App\Support\Attendance\PunchData;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\PersonalAccessToken;

beforeEach(function () {
    $this->tenant = tenantWithRoles(['slug' => 'push-co']);
    app(TenantContext::class)->set($this->tenant);
    $this->user = userForTenant($this->tenant, Role::Employee);
    $this->employee = Employee::factory()->forTenant($this->tenant)->create(['user_id' => $this->user->id]);
    $this->token = mobileToken($this->user);
});

it('registers a push token against the current device session', function () {
    $this->withToken($this->token)
        ->postJson('/api/v1/devices', ['expo_push_token' => 'ExponentPushToken[abc123]', 'platform' => 'android'])
        ->assertCreated();

    $device = DeviceToken::withoutTenantScope()->firstOrFail();
    expect($device->user_id)->toBe($this->user->id)
        ->and($device->tenant_id)->toBe($this->tenant->id)
        ->and($device->personal_access_token_id)->toBe(PersonalAccessToken::findToken($this->token)->id);

    // Registering again is idempotent.
    $this->withToken($this->token)
        ->postJson('/api/v1/devices', ['expo_push_token' => 'ExponentPushToken[abc123]', 'platform' => 'android'])
        ->assertOk();
    expect(DeviceToken::withoutTenantScope()->count())->toBe(1);
});

it('moves a phone\'s push token to whoever signs in on it next, even across organisations', function () {
    DeviceToken::factory()->forUser($this->user)->create(['expo_push_token' => 'ExponentPushToken[shared]']);

    $other = tenantWithRoles(['slug' => 'push-rival']);
    $newcomer = userForTenant($other, Role::Employee);

    asMobileUser($newcomer)
        ->postJson('/api/v1/devices', ['expo_push_token' => 'ExponentPushToken[shared]', 'platform' => 'ios'])
        ->assertOk();

    $device = DeviceToken::withoutTenantScope()->sole();
    expect($device->user_id)->toBe($newcomer->id)->and($device->tenant_id)->toBe($other->id);
});

it('validates push tokens', function () {
    $this->withToken($this->token)
        ->postJson('/api/v1/devices', ['expo_push_token' => 'not-a-token', 'platform' => 'windows'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['expo_push_token', 'platform']);
});

it('removes only the caller\'s push token', function () {
    DeviceToken::factory()->forUser($this->user)->create(['expo_push_token' => 'ExponentPushToken[mine]']);
    $stranger = userForTenant($this->tenant, Role::Employee);
    DeviceToken::factory()->forUser($stranger)->create(['expo_push_token' => 'ExponentPushToken[theirs]']);

    $this->withToken($this->token)->deleteJson('/api/v1/devices/'.rawurlencode('ExponentPushToken[theirs]'))->assertNoContent();
    $this->withToken($this->token)->deleteJson('/api/v1/devices/'.rawurlencode('ExponentPushToken[mine]'))->assertNoContent();

    expect(DeviceToken::withoutTenantScope()->pluck('expo_push_token')->all())->toBe(['ExponentPushToken[theirs]']);
});

it('queues a push for every in-app notification sent to a user with a device', function () {
    Queue::fake([SendExpoPushNotification::class]);
    DeviceToken::factory()->forUser($this->user)->create();
    $task = Task::factory()->forEmployee($this->employee)->create(['title' => 'Sign the contract']);

    $this->user->notifyNow(new TaskAssignedNotification($task->load('employee')), ['database']);

    Queue::assertPushed(SendExpoPushNotification::class, fn (SendExpoPushNotification $job): bool => $job->userId === $this->user->id
        && $job->title === 'Task Assigned'
        && $job->body === 'Sign the contract'
        && $job->data['type'] === 'task_assigned'
        && $job->data['task_uuid'] === $task->uuid
        && isset($job->data['notification_id']));
});

it('skips users without a registered device', function () {
    Queue::fake([SendExpoPushNotification::class]);
    $task = Task::factory()->forEmployee($this->employee)->create();

    $this->user->notifyNow(new TaskAssignedNotification($task->load('employee')), ['database']);

    Queue::assertNotPushed(SendExpoPushNotification::class);
});

it('pushes attendance recorded for the employee elsewhere, but not their own app or browser punches', function () {
    Queue::fake([SendExpoPushNotification::class]);
    DeviceToken::factory()->forUser($this->user)->create();
    $service = app(AttendanceService::class);

    $service->clockIn($this->employee, new PunchData(AttendanceSource::Mobile));
    Queue::assertNotPushed(SendExpoPushNotification::class);

    // An HR correction.
    $service->recordManual($this->employee, CarbonImmutable::today(), ['notes' => 'Adjusted'], userForTenant($this->tenant, Role::HrManager)->id);
    Queue::assertPushed(SendExpoPushNotification::class, fn (SendExpoPushNotification $job): bool => $job->data['type'] === 'attendance_logged'
        && $job->title === 'Attendance recorded'
        && str_contains($job->body, 'had their attendance updated'));
});

it('pushes a biometric device punch', function () {
    Queue::fake([SendExpoPushNotification::class]);
    DeviceToken::factory()->forUser($this->user)->create();

    app(AttendanceService::class)->clockIn($this->employee, new PunchData(AttendanceSource::Biometric));

    Queue::assertPushed(SendExpoPushNotification::class, fn (SendExpoPushNotification $job): bool => $job->data['action'] === 'clock_in');
});

it('delivers through the Expo push API and prunes unregistered devices', function () {
    DeviceToken::factory()->forUser($this->user)->create(['expo_push_token' => 'ExponentPushToken[good]']);
    DeviceToken::factory()->forUser($this->user)->create(['expo_push_token' => 'ExponentPushToken[gone]']);

    Http::fake(['exp.host/*' => Http::response(['data' => [
        ['status' => 'ok', 'id' => 'ticket-1'],
        ['status' => 'error', 'message' => 'not registered', 'details' => ['error' => 'DeviceNotRegistered']],
    ]])]);

    (new SendExpoPushNotification($this->user->id, 'Leave Decision', 'Approved', ['type' => 'leave_decision']))->handle();

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://exp.host/--/api/v2/push/send'
        && count($request->data()) === 2
        && $request->data()[0]['title'] === 'Leave Decision'
        && json_decode($request->body(), true)[0]['data']['type'] === 'leave_decision');

    expect(DeviceToken::withoutTenantScope()->pluck('expo_push_token')->all())->toBe(['ExponentPushToken[good]']);
});
