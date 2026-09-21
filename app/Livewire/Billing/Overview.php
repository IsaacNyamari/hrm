<?php

declare(strict_types=1);

namespace App\Livewire\Billing;

use App\Actions\Billing\ChargeInvoice;
use App\Enums\PaymentProvider;
use App\Enums\Permission;
use App\Enums\SubscriptionStatus;
use App\Facades\Tenancy;
use App\Livewire\Concerns\BindsTenant;
use App\Models\Invoice;
use App\Models\Plan;
use App\Services\Subscription\SubscriptionManager;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * The tenant's billing page. Every user of a lapsed tenant is redirected here
 * by the `subscription` middleware, so it must render for all of them: users
 * without `billing.view` only see that access is paused.
 */
class Overview extends Component
{
    use BindsTenant;

    public string $selectedPlan = '';

    public string $payer = '';

    public bool $showPayment = false;

    public function choosePlan(string $slug): void
    {
        abort_unless(auth()->user()?->can(Permission::SubscriptionManage->value), 403);

        $this->resetErrorBag();
        $this->selectedPlan = $slug;
        $this->showPayment = true;
    }

    public function cancelChoice(): void
    {
        $this->reset('selectedPlan', 'payer', 'showPayment');
    }

    public function subscribe(SubscriptionManager $subscriptions, ChargeInvoice $charge): void
    {
        abort_unless(auth()->user()?->can(Permission::SubscriptionManage->value), 403);

        $this->payer = preg_replace('/[\s-]/', '', $this->payer) ?? '';

        $this->validate([
            'selectedPlan' => ['required', 'string', 'exists:plans,slug'],
            'payer' => ['required', 'string', 'regex:/^(?:\+?254|0)?[17]\d{8}$/'],
        ], [
            'payer.regex' => __('Enter a valid M-Pesa phone number, e.g. 0712345678.'),
        ]);

        $plan = Plan::query()->where('slug', $this->selectedPlan)->where('is_active', true)->firstOrFail();

        [, , $payment] = $subscriptions->subscribe(
            Tenancy::getOrFail(),
            $plan,
            PaymentProvider::Mpesa,
            $this->normalisedPhone(),
        );

        if ($payment === null) {
            Flux::toast(variant: 'success', text: __('You are now on the :plan plan.', ['plan' => $plan->name]));
        } else {
            $result = $charge->handle($payment);

            $result->pending() || $result->successful()
                ? Flux::toast(variant: 'success', text: __('Check your phone and enter your M-Pesa PIN to complete the payment.'))
                : Flux::toast(variant: 'danger', text: $result->failureReason ?? __('The payment could not be started. Please try again.'));
        }

        $this->reset('selectedPlan', 'payer', 'showPayment');
    }

    public function render(): View
    {
        $user = auth()->user();
        $tenant = Tenancy::getOrFail();
        $canView = (bool) $user?->can(Permission::BillingView->value);
        $current = $tenant->subscription()->with('plan')->first();
        $subscription = $canView ? $current : null;

        return view('livewire.billing.overview', [
            'tenant' => $tenant,
            'hasAccess' => $tenant->hasAppAccess(),
            'canView' => $canView,
            'canManage' => (bool) $user?->can(Permission::SubscriptionManage->value),
            'subscription' => $subscription,
            'trialEnded' => $current?->status === SubscriptionStatus::Trialing && ! $current->onTrial(),
            'plans' => $canView ? Plan::query()->where('is_active', true)->orderBy('sort_order')->get() : collect(),
            'invoices' => $canView ? Invoice::query()->latest()->limit(20)->get() : collect(),
        ]);
    }

    /**
     * Daraja expects the payer MSISDN as 2547XXXXXXXX / 2541XXXXXXXX.
     */
    private function normalisedPhone(): string
    {
        $digits = preg_replace('/\D/', '', $this->payer) ?? '';

        return '254'.substr($digits, -9);
    }
}
