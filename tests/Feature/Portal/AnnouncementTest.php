<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Announcement;
use App\Models\Department;
use App\Models\Employee;
use App\Notifications\AnnouncementPublishedNotification;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    $this->tenant = tenantWithRoles(['slug' => 'ann-co']);
    app(TenantContext::class)->set($this->tenant);

    $this->hr = userForTenant($this->tenant, Role::HrManager); // has employees.update
    $this->employeeUser = userForTenant($this->tenant, Role::Employee);
    $this->department = Department::factory()->forTenant($this->tenant)->create();
    $this->employee = Employee::factory()->forTenant($this->tenant)->create([
        'user_id' => $this->employeeUser->id, 'department_id' => $this->department->id,
    ]);
});

it('publishes a company-wide announcement and notifies employees', function () {
    Notification::fake();

    $this->actingAs($this->hr)
        ->postJson('http://ann-co.hrm.test/announcements', [
            'title' => 'Office closed Monday', 'body' => 'Public holiday.', 'audience' => 'all',
        ])
        ->assertCreated()
        ->assertJsonPath('data.published', true);

    Notification::assertSentTo($this->employeeUser, AnnouncementPublishedNotification::class);
});

it('forbids publishing without permission', function () {
    $this->actingAs($this->employeeUser)
        ->postJson('http://ann-co.hrm.test/announcements', ['title' => 'X', 'body' => 'Y', 'audience' => 'all'])
        ->assertForbidden();
});

it('only shows an employee announcements targeted at them', function () {
    Announcement::factory()->forTenant($this->tenant)->create(['title' => 'Everyone', 'audience' => 'all']);
    Announcement::factory()->forTenant($this->tenant)->create([
        'title' => 'My Dept', 'audience' => 'department', 'department_id' => $this->department->id,
    ]);
    $otherDept = Department::factory()->forTenant($this->tenant)->create();
    Announcement::factory()->forTenant($this->tenant)->create([
        'title' => 'Other Dept', 'audience' => 'department', 'department_id' => $otherDept->id,
    ]);
    Announcement::factory()->forTenant($this->tenant)->draft()->create(['title' => 'Unpublished']);

    $list = $this->actingAs($this->employeeUser)->getJson('http://ann-co.hrm.test/announcements')->assertOk();

    expect(collect($list->json('data'))->pluck('title'))
        ->toContain('Everyone', 'My Dept')
        ->not->toContain('Other Dept')
        ->not->toContain('Unpublished');
});

it('marks an announcement read', function () {
    $announcement = Announcement::factory()->forTenant($this->tenant)->create();

    $this->actingAs($this->employeeUser)
        ->postJson("http://ann-co.hrm.test/announcements/{$announcement->uuid}/read")
        ->assertOk()
        ->assertJsonPath('data.read', true);

    $this->actingAs($this->employeeUser)
        ->getJson('http://ann-co.hrm.test/announcements')
        ->assertJsonPath('data.0.read', true);
});
