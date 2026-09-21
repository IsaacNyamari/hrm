<?php

declare(strict_types=1);

use App\Enums\AttendanceStatus;
use App\Enums\LeaveStatus;
use App\Enums\TaskStatus;
use App\Support\StatusTone;

it('maps statuses to badge colours', function (mixed $status, string $colour) {
    expect(StatusTone::for($status))->toBe($colour);
})->with([
    'approved leave is green' => [LeaveStatus::Approved, 'green'],
    'present attendance is green' => [AttendanceStatus::Present, 'green'],
    'completed task is green' => [TaskStatus::Completed, 'green'],
    'pending leave is amber' => [LeaveStatus::Pending, 'amber'],
    'rejected leave is red' => [LeaveStatus::Rejected, 'red'],
    'absent attendance is red' => [AttendanceStatus::Absent, 'red'],
    'unknown status is neutral' => ['draft', 'zinc'],
    'null is neutral' => [null, 'zinc'],
]);
