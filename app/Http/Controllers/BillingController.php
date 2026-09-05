<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Facades\Tenancy;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can(Permission::BillingView->value), 403);

        $tenant = Tenancy::getOrFail();
        $subscription = $tenant->subscription;

        return response()->json([
            'data' => [
                'status' => $subscription?->status->value,
                'plan' => $subscription?->plan->only(['name', 'slug', 'price_cents', 'currency']),
                'trial_ends_at' => $subscription?->trial_ends_at?->toIso8601String(),
                'current_period_end' => $subscription?->current_period_end?->toIso8601String(),
                'has_app_access' => $tenant->hasAppAccess(),
                'invoices' => Invoice::query()
                    ->latest()
                    ->limit(20)
                    ->get(['uuid', 'number', 'status', 'total_cents', 'amount_paid_cents', 'currency', 'period_start', 'period_end', 'paid_at']),
            ],
        ]);
    }
}
