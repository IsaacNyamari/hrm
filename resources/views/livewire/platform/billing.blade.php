@php($active = 'text-neutral-800 dark:text-neutral-100 border-neutral-900 dark:border-neutral-100')
@php($idle = 'text-neutral-500 border-transparent hover:text-neutral-800 dark:hover:text-neutral-200')

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Billing') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Every payment, invoice and subscription across all organizations.') }}</flux:text>
    </div>

    @if ($notice)
        <flux:callout :variant="$noticeVariant ?? 'success'" icon="check-circle" :heading="$notice" />
    @endif

    <nav class="flex gap-6 border-b border-neutral-200 text-sm font-medium dark:border-neutral-700">
        <button type="button" wire:click="$set('tab', 'payments')" class="border-b-2 pb-3 {{ $tab === 'payments' ? $active : $idle }}">{{ __('Payments') }}</button>
        @if (auth()->user()?->canOnPlatform(\App\Enums\PlatformPermission::InvoicesManage))
            <button type="button" wire:click="$set('tab', 'invoices')" class="border-b-2 pb-3 {{ $tab === 'invoices' ? $active : $idle }}">{{ __('Invoices') }}</button>
        @endif
        @if (auth()->user()?->canOnPlatform(\App\Enums\PlatformPermission::SubscriptionsManage))
            <button type="button" wire:click="$set('tab', 'subscriptions')" class="border-b-2 pb-3 {{ $tab === 'subscriptions' ? $active : $idle }}">{{ __('Subscriptions') }}</button>
        @endif
    </nav>

    <div class="flex flex-wrap items-end gap-3">
        <flux:input wire:model.live.debounce.300ms="search" :label="__('Search')" placeholder="{{ __('Organization…') }}" class="max-w-xs" />
        <flux:select wire:model.live="status" :label="__('Status')" class="max-w-[10rem]">
            <flux:select.option value="">{{ __('Any') }}</flux:select.option>
            @foreach ($statuses as $s)
                <flux:select.option value="{{ $s->value }}">{{ $s->label() }}</flux:select.option>
            @endforeach
        </flux:select>
        @if ($tab === 'payments')
            <flux:select wire:model.live="provider" :label="__('Provider')" class="max-w-[10rem]">
                <flux:select.option value="">{{ __('Any') }}</flux:select.option>
                @foreach ($providers as $p)
                    <flux:select.option value="{{ $p->value }}">{{ $p->label() }}</flux:select.option>
                @endforeach
            </flux:select>
        @endif
    </div>

    @if ($tab === 'payments')
        <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
            <p class="text-xs uppercase tracking-wide text-neutral-500">{{ __('Collected (matching filter)') }}</p>
            <p class="mt-1 text-2xl font-semibold"><x-money :cents="$collectedCents" :decimals="0" /></p>
        </div>
    @endif

    <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-neutral-700">
        <table class="w-full text-sm">
            @if ($tab === 'payments')
                <thead class="bg-neutral-50 text-left text-neutral-500 dark:bg-neutral-900">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ __('Reference') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Organization') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Provider') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Amount') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Status') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Paid at') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                    @forelse ($rows as $payment)
                        <tr wire:key="pay-{{ $payment->id }}" class="hover:bg-neutral-50 dark:hover:bg-neutral-900">
                            <td class="px-4 py-3">
                                <a href="{{ route('platform.billing.payments.show', $payment) }}" wire:navigate class="font-mono text-xs hover:underline">{{ $payment->reference }}</a>
                                @if ($payment->invoice)<div class="text-xs text-neutral-500">{{ $payment->invoice->number }}</div>@endif
                            </td>
                            <td class="px-4 py-3">
                                @if ($payment->tenant)
                                    <a href="{{ route('platform.tenants.show', $payment->tenant) }}" wire:navigate class="hover:underline">{{ $payment->tenant->name }}</a>
                                @else <span class="text-neutral-400">—</span> @endif
                            </td>
                            <td class="px-4 py-3">{{ $payment->provider->label() }}</td>
                            <td class="px-4 py-3"><x-money :cents="$payment->amount_cents" :currency="$payment->currency" /></td>
                            <td class="px-4 py-3"><flux:badge size="sm" :color="$payment->status === \App\Enums\PaymentStatus::Succeeded ? 'green' : ($payment->status === \App\Enums\PaymentStatus::Failed ? 'red' : 'zinc')">{{ $payment->status->label() }}</flux:badge>@unless ($payment->is_live)<flux:badge size="sm" color="amber" class="ms-1">{{ __('Test') }}</flux:badge>@endunless</td>
                            <td class="px-4 py-3 text-neutral-500">{{ $payment->paid_at?->format('Y-m-d H:i') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-6 text-center text-neutral-500">{{ __('No payments match.') }}</td></tr>
                    @endforelse
                </tbody>
            @elseif ($tab === 'invoices')
                <thead class="bg-neutral-50 text-left text-neutral-500 dark:bg-neutral-900">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ __('Number') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Organization') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Plan') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Total') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Outstanding') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Status') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Due') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                    @forelse ($rows as $invoice)
                        <tr wire:key="inv-{{ $invoice->id }}" class="hover:bg-neutral-50 dark:hover:bg-neutral-900">
                            <td class="px-4 py-3 font-mono text-xs">{{ $invoice->number }}</td>
                            <td class="px-4 py-3">
                                @if ($invoice->tenant)
                                    <a href="{{ route('platform.tenants.show', $invoice->tenant) }}" wire:navigate class="hover:underline">{{ $invoice->tenant->name }}</a>
                                @else <span class="text-neutral-400">—</span> @endif
                            </td>
                            <td class="px-4 py-3">{{ $invoice->subscription?->plan?->name ?? '—' }}</td>
                            <td class="px-4 py-3"><x-money :cents="$invoice->total_cents" :currency="$invoice->currency" /></td>
                            <td class="px-4 py-3"><x-money :cents="$invoice->outstandingCents()" :currency="$invoice->currency" /></td>
                            <td class="px-4 py-3"><flux:badge size="sm" :color="$invoice->isPaid() ? 'green' : ($invoice->status === \App\Enums\InvoiceStatus::Uncollectible ? 'red' : 'zinc')">{{ $invoice->status->label() }}</flux:badge></td>
                            <td class="px-4 py-3 text-neutral-500">{{ $invoice->due_at?->format('Y-m-d') ?? '—' }}</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <flux:button size="sm" variant="ghost" icon="arrow-down-tray" :href="route('platform.billing.invoices.pdf', $invoice)">{{ __('PDF') }}</flux:button>
                                <flux:modal.trigger name="send-invoice-{{ $invoice->uuid }}">
                                    <flux:button size="sm" variant="primary" icon="paper-airplane">{{ __('Send') }}</flux:button>
                                </flux:modal.trigger>
                                <x-confirm-modal name="send-invoice-{{ $invoice->uuid }}" :heading="__('Send :number to the owner of :name?', ['number' => $invoice->number, 'name' => $invoice->tenant?->name ?? '—'])" :confirm-label="__('Send')" confirm-wire="sendInvoice('{{ $invoice->uuid }}')" variant="primary" />
                                @unless ($invoice->isPaid() || $invoice->amount_paid_cents > 0)
                                    <flux:modal.trigger name="delete-invoice-{{ $invoice->uuid }}">
                                        <flux:button size="sm" variant="danger" icon="trash">{{ __('Delete') }}</flux:button>
                                    </flux:modal.trigger>
                                    <x-confirm-modal name="delete-invoice-{{ $invoice->uuid }}" :heading="__('Delete invoice :number?', ['number' => $invoice->number])" :text="__('This cannot be undone.')" :confirm-label="__('Delete')" confirm-wire="deleteInvoice('{{ $invoice->uuid }}')" />
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-6 text-center text-neutral-500">{{ __('No invoices match.') }}</td></tr>
                    @endforelse
                </tbody>
            @else
                <thead class="bg-neutral-50 text-left text-neutral-500 dark:bg-neutral-900">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ __('Organization') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Plan') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Price') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Status') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Period ends') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Trial ends') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                    @forelse ($rows as $subscription)
                        <tr wire:key="sub-{{ $subscription->id }}" class="hover:bg-neutral-50 dark:hover:bg-neutral-900">
                            <td class="px-4 py-3">
                                @if ($subscription->tenant)
                                    <a href="{{ route('platform.tenants.show', $subscription->tenant) }}" wire:navigate class="hover:underline">{{ $subscription->tenant->name }}</a>
                                @else <span class="text-neutral-400">—</span> @endif
                            </td>
                            <td class="px-4 py-3">{{ $subscription->plan?->name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                @if ($subscription->plan)
                                    <x-money :cents="$subscription->plan->price_cents" :currency="$subscription->plan->currency" :decimals="0" />
                                    <span class="text-xs text-neutral-500">/ {{ $subscription->plan->billing_interval->label() }}</span>
                                @else — @endif
                            </td>
                            <td class="px-4 py-3"><flux:badge size="sm" :color="$subscription->status === \App\Enums\SubscriptionStatus::Active ? 'green' : (in_array($subscription->status, [\App\Enums\SubscriptionStatus::Cancelled, \App\Enums\SubscriptionStatus::Expired, \App\Enums\SubscriptionStatus::Suspended], true) ? 'red' : 'zinc')">{{ $subscription->status->label() }}</flux:badge></td>
                            <td class="px-4 py-3 text-neutral-500">{{ $subscription->current_period_end?->format('Y-m-d') ?? '—' }}</td>
                            <td class="px-4 py-3 text-neutral-500">{{ $subscription->trial_ends_at?->format('Y-m-d') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-6 text-center text-neutral-500">{{ __('No subscriptions match.') }}</td></tr>
                    @endforelse
                </tbody>
            @endif
        </table>
    </div>

    {{ $rows->links() }}
</div>
