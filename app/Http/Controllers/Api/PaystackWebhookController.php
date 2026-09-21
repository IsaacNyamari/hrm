<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Services\Payment\PaymentManager;
use App\Services\Payment\PaymentReconciler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaystackWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentManager $payments, PaymentReconciler $reconciler): JsonResponse
    {
        $event = $payments->gateway(PaymentProvider::Paystack)->handleWebhook($request);

        if (! $event->verified) {
            Log::warning('Rejected unverified Paystack webhook', ['ip' => $request->ip()]);

            return response()->json(['status' => false], 401);
        }

        // Events other than charge outcomes (transfers, subscriptions, ...) are acknowledged and ignored.
        if ($event->status !== PaymentStatus::Pending && $event->externalId !== null) {
            $reconciler->reconcile($event);
        }

        return response()->json(['status' => true]);
    }
}
