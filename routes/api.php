<?php

use App\Http\Controllers\Api\AttendanceDeviceWebhookController;
use App\Http\Controllers\Api\MpesaWebhookController;
use Illuminate\Support\Facades\Route;

/*
| Provider webhooks. Unauthenticated (no session), verified inside each
| gateway's handleWebhook(). Processing is idempotent.
*/
Route::post('webhooks/mpesa', MpesaWebhookController::class)->name('webhooks.mpesa');

// Attendance device ingestion — authenticated by the device's shared secret.
Route::post('webhooks/attendance/{device}', AttendanceDeviceWebhookController::class)->name('webhooks.attendance');
