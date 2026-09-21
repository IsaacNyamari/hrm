<?php

use App\Http\Controllers\Api\AttendanceDeviceWebhookController;
use App\Http\Controllers\Api\MpesaWebhookController;
use App\Http\Controllers\Api\PaystackWebhookController;
use App\Http\Controllers\Api\V1\DepartmentController;
use App\Http\Controllers\Api\V1\EmployeeController;
use App\Http\Controllers\Api\V1\LeaveRequestController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\PayslipController;
use App\Http\Controllers\Api\V1\TaskController;
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
