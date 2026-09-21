<?php

declare(strict_types=1);

use App\Enums\AttendanceSource;
use App\Enums\Role;
use App\Events\AttendanceLogged;
use App\Livewire\Attendance\LiveToday;
use App\Livewire\Notifications\Bell;
use App\Models\Employee;
use App\Services\Attendance\AttendanceService;
use App\Support\Attendance\PunchData;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = tenantWithRoles(['slug' => 'punch-co']);
    app(TenantContext::class)->set($this->tenant);
    $this->hr = userForTenant($this->tenant, Role::HrManager);
    $this->staffUser = userForTenant($this->tenant, Role::Employee);
    $this->employee = Employee::factory()->forTenant($this->tenant)->create([
        'first_name' => 'Wanjiru', 'last_name' => 'Mwangi', 'user_id' => $this->staffUser->id,
    ]);
    $this->travelTo(CarbonImmutable::parse('2026-09-22 08:05'));
});

it('broadcasts every clock-in to HR and to the employee', function () {
    Event::fake([AttendanceLogged::class]);

    app(AttendanceService::class)->clockIn($this->employee, new PunchData(AttendanceSource::Web));

    Event::assertDispatched(AttendanceLogged::class, function (AttendanceLogged $event): bool {
        $channels = collect($event->broadcastOn())->map->name->all();

        return $event->action === AttendanceLogged::CLOCK_IN
            && $channels === ['private-tenant.'.$this->tenant->id.'.attendance', 'private-App.Models.User.'.$this->staffUser->id]
            && str_starts_with($event->message(), 'Wanjiru Mwangi clocked in at 08:05');
    });
});

it('broadcasts device punches, breaks, clock-outs and HR corrections', function () {
    Event::fake([AttendanceLogged::class]);
    $service = app(AttendanceService::class);

    $service->clockIn($this->employee, new PunchData(AttendanceSource::Biometric));
    $service->startBreak($this->employee, new PunchData(AttendanceSource::Biometric));
    $service->endBreak($this->employee, new PunchData(AttendanceSource::Biometric));
    $this->travelTo(CarbonImmutable::parse('2026-09-22 17:10'));
    $service->clockOut($this->employee, new PunchData(AttendanceSource::Biometric));
    $service->recordManual($this->employee, CarbonImmutable::parse('2026-09-22'), ['notes' => 'Fixed'], $this->hr->id);

    $actions = [];
    Event::assertDispatched(AttendanceLogged::class, function (AttendanceLogged $e) use (&$actions): bool {
        $actions[] = $e->action;

        return true;
    });

    expect($actions)->toBe([
        AttendanceLogged::CLOCK_IN, AttendanceLogged::BREAK_START, AttendanceLogged::BREAK_END,
        AttendanceLogged::CLOCK_OUT, AttendanceLogged::CORRECTED,
    ]);
});

it('shows today\'s attendance live on the HR dashboard', function () {
    app(AttendanceService::class)->clockIn($this->employee, new PunchData(AttendanceSource::Web));
    Employee::factory()->forTenant($this->tenant)->create(); // not in yet
    $this->actingAs($this->hr);

    Livewire::test(LiveToday::class)
        ->assertSee('Wanjiru Mwangi')
        ->assertSee('08:05')
        ->call('attendanceLogged', ['message' => 'Wanjiru Mwangi clocked in at 08:05'])
        ->assertSee('Wanjiru Mwangi clocked in at 08:05');

    $this->get(route('dashboard'))->assertOk()->assertSee('data-test="attendance-live-today"', false);
});

it('keeps the live attendance panel from employees', function () {
    $this->actingAs($this->staffUser);

    Livewire::test(LiveToday::class)->assertForbidden();
});

it('toasts HR about punches from any page', function () {
    $this->actingAs($this->hr);

    Livewire::test(Bell::class)
        ->call('attendanceLogged', ['message' => 'Wanjiru Mwangi clocked in at 08:05 (Late)', 'status' => 'Late'])
        ->assertOk();
});

it('only lets attendance staff of the organization join its attendance channel', function () {
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => 'test-app',
    ]);
    Broadcast::purge();
    require base_path('routes/channels.php');

    $channel = 'private-tenant.'.$this->tenant->id.'.attendance';
    $auth = fn ($user) => $this->actingAs($user)->post('/broadcasting/auth', ['channel_name' => $channel, 'socket_id' => '1234.5678']);

    $auth($this->hr)->assertOk();
    $auth($this->staffUser)->assertForbidden();
    $auth(userForTenant(tenantWithRoles(['slug' => 'other-punch']), Role::HrManager))->assertForbidden();
});
