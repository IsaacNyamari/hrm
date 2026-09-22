<?php

use App\Http\Controllers\Api\AttendanceDeviceWebhookController;
use App\Http\Controllers\Api\MpesaWebhookController;
use App\Http\Controllers\Api\PaystackWebhookController;
use App\Http\Controllers\Api\V1\DepartmentController;
use App\Http\Controllers\Api\V1\EmployeeController;
use App\Http\Controllers\Api\V1\LeaveRequestController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\Mobile;
use App\Http\Controllers\Api\V1\PayslipController;
use App\Http\Controllers\Api\V1\TaskController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

/*
| Provider webhooks. Unauthenticated (no session), verified inside each
| gateway's handleWebhook(). Processing is idempotent.
*/
Route::post('webhooks/paystack', PaystackWebhookController::class)->name('webhooks.paystack');
Route::post('webhooks/mpesa', MpesaWebhookController::class)->name('webhooks.mpesa');

// Attendance device ingestion — authenticated by the device's shared secret.
Route::post('webhooks/attendance/{device}', AttendanceDeviceWebhookController::class)->name('webhooks.attendance');

/*
| Public API v1. Personal-access-token auth (Sanctum). The tenant is resolved
| from the token owner (never the client). Per-route token abilities gate
| read vs write. 120 req/min per token.
*/
Route::prefix('v1')
    ->middleware(['auth:sanctum', 'api.tenant', 'subscription', 'throttle:api'])
    ->name('api.v1.')
    ->group(function (): void {
        Route::get('me', MeController::class)->name('me');

        Route::middleware('abilities:employees:read')->group(function (): void {
            Route::get('employees', [EmployeeController::class, 'index'])->name('employees.index');
            Route::get('employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show');
        });

        Route::get('departments', [DepartmentController::class, 'index'])
            ->middleware('abilities:departments:read')->name('departments.index');

        Route::middleware('abilities:leave:read')->get('leave-requests', [LeaveRequestController::class, 'index'])->name('leave-requests.index');
        Route::middleware('abilities:leave:write')->post('leave-requests', [LeaveRequestController::class, 'store'])->name('leave-requests.store');

        Route::middleware('abilities:payroll:read')->group(function (): void {
            Route::get('payslips', [PayslipController::class, 'index'])->name('payslips.index');
            Route::get('payslips/{payslip}', [PayslipController::class, 'show'])->name('payslips.show');
        });

        Route::middleware('abilities:tasks:read')->get('tasks', [TaskController::class, 'index'])->name('tasks.index');
        Route::middleware('abilities:tasks:write')->post('tasks', [TaskController::class, 'store'])->name('tasks.store');
    });

/*
| Mobile app (Android / iOS). Tokens are issued by `auth/login` with the single
| `mobile` ability, so they can reach only these routes (and integration tokens
| cannot reach these). The tenant always comes from the token owner.
*/
Route::prefix('v1')->name('api.v1.mobile.')->group(function (): void {
    // Public: start-up config, sign-in, the job board and application tracking.
    Route::middleware('throttle:api')->group(function (): void {
        Route::get('config', Mobile\ConfigController::class)->name('config');

        Route::post('auth/login', [Mobile\AuthController::class, 'login'])->name('auth.login');
        Route::post('auth/two-factor', [Mobile\AuthController::class, 'twoFactor'])->name('auth.two-factor');
        Route::post('auth/forgot-password', [Mobile\AuthController::class, 'forgotPassword'])->name('auth.forgot-password');

        Route::prefix('public')->name('public.')->group(function (): void {
            Route::get('jobs', [Mobile\Public\JobController::class, 'index'])->name('jobs.index');
            Route::get('jobs/{organization}/{vacancy}', [Mobile\Public\JobController::class, 'show'])->name('jobs.show');
            Route::post('jobs/{organization}/{vacancy}/apply', [Mobile\Public\JobController::class, 'apply'])->name('jobs.apply');
            Route::post('applications/track', [Mobile\Public\ApplicationTrackingController::class, 'track'])->name('applications.track');
            Route::post('applications/respond', [Mobile\Public\ApplicationTrackingController::class, 'respond'])->name('applications.respond');
        });
    });

    Route::middleware(['auth:sanctum', 'abilities:mobile', 'api.tenant', 'throttle:api'])->group(function (): void {
        // Reachable even when the subscription has lapsed, so the app can show
        // the "subscription needs attention" screen and still sign out.
        Route::post('auth/logout', [Mobile\AuthController::class, 'logout'])->name('auth.logout');
        Route::get('me/profile', Mobile\ProfileController::class)->name('me.profile');
        Route::get('me/sessions', [Mobile\DeviceSessionController::class, 'index'])->name('me.sessions.index');
        Route::delete('me/sessions', [Mobile\DeviceSessionController::class, 'destroyOthers'])->name('me.sessions.destroy-others');
        Route::delete('me/sessions/{session}', [Mobile\DeviceSessionController::class, 'destroy'])->whereNumber('session')->name('me.sessions.destroy');
        Route::post('devices', [Mobile\DeviceController::class, 'store'])->name('devices.store');
        Route::delete('devices/{token}', [Mobile\DeviceController::class, 'destroy'])->name('devices.destroy');

        Route::middleware('subscription')->group(function (): void {
            // Self-service ("My HR")
            Route::get('me/dashboard', Mobile\DashboardController::class)->name('me.dashboard');

            Route::get('attendance/today', [Mobile\AttendanceController::class, 'today'])->name('attendance.today');
            Route::get('attendance/history', [Mobile\AttendanceController::class, 'history'])->name('attendance.history');
            Route::post('attendance/clock-in', [Mobile\AttendanceController::class, 'clockIn'])->name('attendance.clock-in');
            Route::post('attendance/clock-out', [Mobile\AttendanceController::class, 'clockOut'])->name('attendance.clock-out');
            Route::post('attendance/break/start', [Mobile\AttendanceController::class, 'startBreak'])->name('attendance.break.start');
            Route::post('attendance/break/end', [Mobile\AttendanceController::class, 'endBreak'])->name('attendance.break.end');

            Route::get('leave/types', [Mobile\LeaveController::class, 'types'])->name('leave.types');
            Route::get('leave/balances', [Mobile\LeaveController::class, 'balances'])->name('leave.balances');
            Route::get('leave/requests', [Mobile\LeaveController::class, 'index'])->name('leave.requests.index');
            Route::post('leave/requests', [Mobile\LeaveController::class, 'store'])->name('leave.requests.store');
            Route::post('leave/requests/{leaveRequest}/cancel', [Mobile\LeaveController::class, 'cancel'])->name('leave.requests.cancel');

            // `payslips` and `tasks` belong to the integration API above, so the
            // employee's own copies live under `me/`.
            Route::get('me/payslips', [Mobile\PayslipController::class, 'index'])->name('me.payslips.index');
            Route::get('me/payslips/{payslip}', [Mobile\PayslipController::class, 'show'])->name('me.payslips.show');
            Route::get('me/payslips/{payslip}/pdf', [Mobile\PayslipController::class, 'pdf'])->name('me.payslips.pdf');

            Route::get('me/tasks', [Mobile\TaskController::class, 'index'])->name('me.tasks.index');
            Route::post('me/tasks/{task}/start', [Mobile\TaskController::class, 'start'])->name('me.tasks.start');
            Route::post('me/tasks/{task}/complete', [Mobile\TaskController::class, 'complete'])->name('me.tasks.complete');

            Route::get('notifications', [Mobile\NotificationController::class, 'index'])->name('notifications.index');
            Route::get('notifications/unread-count', [Mobile\NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
            Route::post('notifications/read-all', [Mobile\NotificationController::class, 'markAllRead'])->name('notifications.read-all');
            Route::post('notifications/{notification}/read', [Mobile\NotificationController::class, 'markRead'])->name('notifications.read');

            Route::get('announcements', [Mobile\AnnouncementController::class, 'index'])->name('announcements.index');
            Route::post('announcements/{announcement}/read', [Mobile\AnnouncementController::class, 'markRead'])->name('announcements.read');

            // Managers and HR — gated by the same policies as the web.
            Route::get('approvals/leave', [Mobile\LeaveApprovalController::class, 'index'])->name('approvals.leave.index');
            Route::post('approvals/leave/{leaveRequest}/approve', [Mobile\LeaveApprovalController::class, 'approve'])->name('approvals.leave.approve');
            Route::post('approvals/leave/{leaveRequest}/reject', [Mobile\LeaveApprovalController::class, 'reject'])->name('approvals.leave.reject');
            Route::get('team/attendance/today', Mobile\TeamAttendanceController::class)->name('team.attendance.today');
            Route::get('directory', [Mobile\DirectoryController::class, 'index'])->name('directory.index');
            Route::get('directory/{employee}', [Mobile\DirectoryController::class, 'show'])->name('directory.show');
        });
    });
});

/*
| Private-channel authorisation for bearer tokens (the mobile app's Laravel
| Echo client): POST /api/broadcasting/auth. The web keeps its session-based
| /broadcasting/auth. Same channel rules (routes/channels.php) for both.
*/
Broadcast::routes(['middleware' => ['auth:sanctum', 'api.tenant']]);
