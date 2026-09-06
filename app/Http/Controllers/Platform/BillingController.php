<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Enums\PlatformPermission;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BillingController extends Controller
{
    /**
     * Single payment detail. The list/browse screens are the
     * App\Livewire\Platform\Billing component.
     */
    public function showPayment(Request $request, Payment $payment): View
    {
        $user = $request->user();
        abort_unless($user !== null && $user->canOnPlatform(PlatformPermission::PaymentsView), 403);

        $payment->load(['tenant:id,name,slug', 'invoice:id,number,total_cents,currency', 'transactions']);

        return view('platform.payment', ['payment' => $payment]);
    }
}
