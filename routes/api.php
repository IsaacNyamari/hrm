<?php

use App\Http\Controllers\Api\MpesaWebhookController;
use Illuminate\Support\Facades\Route;

/*
| Provider webhooks. Unauthenticated (no session), verified inside each
| gateway's handleWebhook(). Processing is idempotent.
*/
Route::post('webhooks/mpesa', MpesaWebhookController::class)->name('webhooks.mpesa');
