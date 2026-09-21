<div class="flex flex-col gap-6">
    <flux:heading size="xl">{{ __('Billing & subscription') }}</flux:heading>

    @if (session()->has('billing.status'))
        @php([$statusTone, $statusMessage] = session('billing.status'))
        <flux:callout :variant="$statusTone === 'success' ? 'success' : ($statusTone === 'danger' ? 'danger' : 'warning')" icon="credit-card" data-test="billing-status">
            <flux:callout.text>{{ $statusMessage }}</flux:callout.text>
        </flux:callout>
    @endif

    @unless ($hasAccess)
        <flux:callout variant="danger" icon="exclamation-triangle">
            <flux:callout.heading>
                {{ $trialEnded ? __('Your free trial has ended') : __('Your subscription is inactive') }}
            </flux:callout.heading>
            <flux:callout.text>
                @if ($canManage)
                    {{ __('Choose a plan below to restore access to :name.', ['name' => $tenant->name]) }}
                @else
                    {{ __('Access to :name is paused until the subscription is renewed. Please contact your administrator.', ['name' => $tenant->name]) }}
                @endif
            </flux:callout.text>
        </flux:callout>
    @endunless

    @if ($canView)
        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-800">
                <flux:text size="sm">{{ __('Current plan') }}</flux:text>
                <flux:heading size="lg">{{ $subscription?->plan?->name ?? __('None') }}</flux:heading>
            </div>
            <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-800">
                <flux:text size="sm">{{ __('Status') }}</flux:text>
                <flux:heading size="lg">
                    {{ $trialEnded ? __('Trial ended') : ($subscription?->status->label() ?? __('No subscription')) }}
                </flux:heading>
            </div>
            <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-800">
                @if ($subscription?->status === \App\Enums\SubscriptionStatus::Trialing)
                    <flux:text size="sm">{{ $trialEnded ? __('Trial ended on') : __('Trial ends on') }}</flux:text>
                    <flux:heading size="lg">{{ $subscription->trial_ends_at?->toFormattedDateString() ?? __('—') }}</flux:heading>
                @else
                    <flux:text size="sm">{{ __('Current period ends') }}</flux:text>
                    <flux:heading size="lg">{{ $subscription?->current_period_end?->toFormattedDateString() ?? __('—') }}</flux:heading>
                @endif
            </div>
        </div>

        <div>
            <flux:heading size="lg" class="mb-3">{{ __('Plans') }}</flux:heading>
            <div class="grid gap-4 md:grid-cols-3">
                @foreach ($plans as $plan)
                    <div wire:key="plan-{{ $plan->slug }}" class="flex flex-col gap-3 rounded-xl border border-neutral-200 p-5 dark:border-neutral-800">
                        <div class="flex items-center justify-between gap-2">
                            <flux:heading size="lg">{{ $plan->name }}</flux:heading>
                            @if ($subscription?->plan_id === $plan->id)
                                <flux:badge size="sm" color="blue">{{ __('Current') }}</flux:badge>
                            @endif
                        </div>
                        <p class="text-2xl font-semibold">
                            {{ $plan->currency }} {{ number_format($plan->price_cents / 100, 2) }}
                            <span class="text-sm font-normal text-neutral-500">/ {{ $plan->billing_interval->value }}</span>
                        </p>
                        @if ($plan->description)
                            <flux:text>{{ $plan->description }}</flux:text>
                        @endif
                        <flux:text size="sm">
                            {{ $plan->employee_limit === null ? __('Unlimited employees') : __('Up to :count employees', ['count' => $plan->employee_limit]) }}
                        </flux:text>
                        @if ($canManage)
                            <div class="mt-auto pt-2">
                                <flux:button class="w-full" variant="primary" wire:click="choosePlan('{{ $plan->slug }}')">
                                    {{ $subscription?->plan_id === $plan->id ? __('Renew plan') : __('Choose plan') }}
                                </flux:button>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        <div>
            <flux:heading size="lg" class="mb-3">{{ __('Invoices') }}</flux:heading>
            <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-neutral-800">
                <table class="w-full text-left text-sm">
                    <thead class="bg-neutral-50 text-xs uppercase text-neutral-500 dark:bg-neutral-900">
                        <tr>
                            <th class="px-4 py-3">{{ __('Number') }}</th>
                            <th class="px-4 py-3">{{ __('Period') }}</th>
                            <th class="px-4 py-3">{{ __('Amount') }}</th>
                            <th class="px-4 py-3">{{ __('Status') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                        @forelse ($invoices as $invoice)
                            <tr wire:key="invoice-{{ $invoice->uuid }}">
                                <td class="px-4 py-3 font-medium">{{ $invoice->number }}</td>
                                <td class="px-4 py-3 text-neutral-500">
                                    {{ $invoice->period_start?->toFormattedDateString() }} – {{ $invoice->period_end?->toFormattedDateString() }}
                                </td>
                                <td class="px-4 py-3">{{ $invoice->currency }} {{ number_format($invoice->total_cents / 100, 2) }}</td>
                                <td class="px-4 py-3"><x-status-badge :status="$invoice->status" /></td>
                                <td class="px-4 py-3 text-right"><flux:button size="sm" variant="ghost" icon="arrow-down-tray" :href="route('billing.invoices.pdf', $invoice)">{{ __('PDF') }}</flux:button></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-10 text-center text-neutral-500">{{ __('No invoices yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($canManage)
    <flux:modal wire:model.self="showPayment" class="max-w-lg">
        @php($isMpesa = $provider === \App\Enums\PaymentProvider::Mpesa)
        <form wire:submit="subscribe" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('Complete payment') }}</flux:heading>
                <flux:text class="mt-1">
                    {{ $isMpesa ? __('You will receive a prompt on your phone to enter your M-Pesa PIN.') : __('You will be taken to Paystack\'s secure checkout to pay by card, M-Pesa or bank.') }}
                </flux:text>
            </div>

            <div class="flex items-center gap-3 rounded-xl border border-brand/30 bg-brand/5 p-4">
                <span class="grid size-10 place-items-center rounded-lg bg-brand text-white">
                    <flux:icon :name="$isMpesa ? 'device-phone-mobile' : 'credit-card'" variant="mini" />
                </span>
                <div class="leading-tight">
                    <p class="text-sm font-semibold text-zinc-900 dark:text-white" data-test="payment-method">{{ $isMpesa ? __('M-Pesa') : $provider->label() }}</p>
                    <p class="text-xs text-zinc-500">{{ __('Payment method') }}</p>
                </div>
            </div>

            @if ($isMpesa)
                <flux:input wire:model="payer" :label="__('M-Pesa phone number')" placeholder="0712345678" required />
            @else
                <flux:input wire:model="payer" type="email" :label="__('Billing email')" :description="__('Your receipt is sent here.')" required />
            @endif
            <flux:error name="selectedPlan" />

            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="cancelChoice">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">
                    {{ $isMpesa ? __('Send payment request') : __('Continue to Paystack') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
    @endif
</div>
