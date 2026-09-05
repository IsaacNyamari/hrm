<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceDeviceController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DesignationController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeExportController;
use App\Http\Controllers\EmployeeImportController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\LeaveBalanceController;
use App\Http\Controllers\LeaveCalendarController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\LeaveTypeController;
use App\Http\Controllers\OrgChartController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\UserInvitationController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

/*
| Invitation acceptance — guest, tenant is carried by the single-use token.
*/
Route::get('invitations/{invitation}', [InvitationController::class, 'show'])->name('invitations.show');
Route::post('invitations/{invitation}', [InvitationController::class, 'store'])->name('invitations.accept');

/*
| Tenant application. Every request is tenant-resolved, the user is verified to
| belong to that tenant, and the subscription is checked (billing.* and
| subscription.* stay reachable when it has lapsed). Queries are auto-scoped by
| App\Models\Scopes\TenantScope.
*/
Route::middleware(['auth', 'verified', 'tenant', 'tenant.user', 'subscription'])->group(function () {
    Route::get('billing', [BillingController::class, 'index'])->name('billing.index');
    Route::post('subscription', [SubscriptionController::class, 'store'])->name('subscription.store');
    Route::delete('subscription', [SubscriptionController::class, 'destroy'])->name('subscription.destroy');

    Route::get('employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::post('employees', [EmployeeController::class, 'store'])->name('employees.store');
    Route::get('employees/export', EmployeeExportController::class)->name('employees.export');
    Route::post('employees/import/preview', [EmployeeImportController::class, 'preview'])->name('employees.import.preview');
    Route::post('employees/import/{import}/confirm', [EmployeeImportController::class, 'confirm'])->name('employees.import.confirm');
    Route::get('employees/import/{import}', [EmployeeImportController::class, 'show'])->name('employees.import.show');
    Route::get('employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show');
    Route::put('employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
    Route::delete('employees/{employee}', [EmployeeController::class, 'destroy'])->name('employees.destroy');

    Route::post('users/invitations', [UserInvitationController::class, 'store'])->name('users.invitations.store');

    // Organization structure
    Route::get('org-chart', OrgChartController::class)->name('org-chart');
    Route::get('departments/tree', [DepartmentController::class, 'tree'])->name('departments.tree');
    Route::apiResource('branches', BranchController::class);
    Route::apiResource('departments', DepartmentController::class);
    Route::apiResource('designations', DesignationController::class);

    // Attendance — self-service clock actions need only a linked employee record.
    Route::get('attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::get('attendance/today', [AttendanceController::class, 'today'])->name('attendance.today');
    Route::post('attendance/clock-in', [AttendanceController::class, 'clockIn'])->name('attendance.clock-in');
    Route::post('attendance/clock-out', [AttendanceController::class, 'clockOut'])->name('attendance.clock-out');
    Route::post('attendance/break/start', [AttendanceController::class, 'startBreak'])->name('attendance.break.start');
    Route::post('attendance/break/end', [AttendanceController::class, 'endBreak'])->name('attendance.break.end');
    Route::post('attendance', [AttendanceController::class, 'store'])->name('attendance.store');
    Route::put('attendance/{attendance}', [AttendanceController::class, 'update'])->name('attendance.update');

    Route::post('employees/{employee}/shift', [ShiftController::class, 'assign'])->name('employees.shift.assign');
    Route::apiResource('shifts', ShiftController::class);
    Route::apiResource('attendance-devices', AttendanceDeviceController::class);

    // Leave
    Route::view('leave', 'leave.index')->name('leave');
    Route::apiResource('leave-types', LeaveTypeController::class);
    Route::apiResource('holidays', HolidayController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('leave-calendar', LeaveCalendarController::class)->name('leave-calendar');
    Route::get('leave-balances', [LeaveBalanceController::class, 'index'])->name('leave-balances.index');
    Route::get('leave-requests', [LeaveRequestController::class, 'index'])->name('leave-requests.index');
    Route::post('leave-requests', [LeaveRequestController::class, 'store'])->name('leave-requests.store');
    Route::get('leave-requests/{leaveRequest}', [LeaveRequestController::class, 'show'])->name('leave-requests.show');
    Route::post('leave-requests/{leaveRequest}/approve', [LeaveRequestController::class, 'approve'])->name('leave-requests.approve');
    Route::post('leave-requests/{leaveRequest}/reject', [LeaveRequestController::class, 'reject'])->name('leave-requests.reject');
    Route::post('leave-requests/{leaveRequest}/cancel', [LeaveRequestController::class, 'cancel'])->name('leave-requests.cancel');
});

require __DIR__.'/settings.php';
