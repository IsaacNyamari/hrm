<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\PaymentProvider;
use App\Http\Controllers\Controller;
use App\Services\Payment\PaymentManager;
use App\Services\Payment\PaymentReconciler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MpesaWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentManager $payments, PaymentReconciler $reconciler): JsonResponse
    {
        $event = $payments->gateway(PaymentProvider::Mpesa)->handleWebhook($request);

        if (! $event->verified) {
            Log::warning('Rejected unverified M-Pesa callback', ['ip' => $request->ip()]);

            return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Rejected'], 401);
        }

        $reconciler->reconcile($event);

        // Safaricom expects a 200 with this body to stop retrying.
        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }
}
