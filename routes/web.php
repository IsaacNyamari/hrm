<?php

use App\Http\Controllers\BillingController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeExportController;
use App\Http\Controllers\EmployeeImportController;
use App\Http\Controllers\InvitationController;
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
});

require __DIR__.'/settings.php';
