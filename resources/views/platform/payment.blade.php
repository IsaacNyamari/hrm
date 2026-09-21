<x-layouts::app :title="$payment->reference">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <div>
            <flux:link :href="route('platform.billing')" wire:navigate class="text-sm">&larr; {{ __('Payments') }}</flux:link>
            <flux:heading size="xl" class="mt-1 font-mono">{{ $payment->reference }}</flux:heading>
            <flux:text class="mt-1">
                <x-money :cents="$payment->amount_cents" :currency="$payment->currency" /> ·
                {{ $payment->provider->label() }} ·
                <flux:badge size="sm" :color="$payment->status === \App\Enums\PaymentStatus::Succeeded ? 'green' : ($payment->status === \App\Enums\PaymentStatus::Failed ? 'red' : 'zinc')">{{ $payment->status->label() }}</flux:badge>
            </flux:text>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-xl border border-neutral-200 p-4 text-sm dark:border-neutral-700">
                <dl class="grid grid-cols-3 gap-y-2">
                    <dt class="text-neutral-500">{{ __('Organization') }}</dt>
                    <dd class="col-span-2">
                        @if ($payment->tenant)
                            <a href="{{ route('platform.tenants.show', $payment->tenant) }}" wire:navigate class="hover:underline">{{ $payment->tenant->name }}</a>
                        @else
                            {{ __('—') }}
                        @endif
                    </dd>
                    <dt class="text-neutral-500">{{ __('Invoice') }}</dt>
                    <dd class="col-span-2 flex items-center gap-3">
                        {{ $payment->invoice?->number ?? __('—') }}
                        @if ($payment->invoice)
                            <flux:button size="xs" variant="ghost" icon="arrow-down-tray" :href="route('platform.billing.invoices.pdf', $payment->invoice)">{{ __('PDF') }}</flux:button>
                        @endif
                    </dd>
                    <dt class="text-neutral-500">{{ __('Provider ref') }}</dt>
                    <dd class="col-span-2 font-mono text-xs">{{ $payment->provider_reference ?? __('—') }}</dd>
                    <dt class="text-neutral-500">{{ __('Payer') }}</dt>
                    <dd class="col-span-2">{{ $payment->payer ?? __('—') }}</dd>
                    <dt class="text-neutral-500">{{ __('Paid at') }}</dt>
                    <dd class="col-span-2">{{ $payment->paid_at?->format('Y-m-d H:i:s') ?? __('—') }}</dd>
                    @if ($payment->failure_reason)
                        <dt class="text-neutral-500">{{ __('Failure') }}</dt>
                        <dd class="col-span-2 text-red-600 dark:text-red-400">{{ $payment->failure_reason }}</dd>
                    @endif
                </dl>
            </div>

            @if (! empty($payment->metadata))
                <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
                    <p class="mb-2 text-xs uppercase tracking-wide text-neutral-500">{{ __('Metadata') }}</p>
                    <pre class="overflow-x-auto text-xs text-neutral-600 dark:text-neutral-400">{{ json_encode($payment->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                </div>
            @endif
        </div>

        <div class="rounded-xl border border-neutral-200 dark:border-neutral-700">
            <div class="border-b border-neutral-100 px-4 py-3 dark:border-neutral-800">
                <flux:heading size="lg">{{ __('Gateway transactions') }}</flux:heading>
            </div>
            <table class="w-full text-sm">
                <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                    @forelse ($payment->transactions as $txn)
                        <tr>
                            <td class="px-4 py-2 font-mono text-xs">{{ $txn->getKey() }}</td>
                            <td class="px-4 py-2 text-neutral-500">{{ $txn->created_at?->format('Y-m-d H:i:s') }}</td>
                        </tr>
                    @empty
                        <tr><td class="px-4 py-4 text-neutral-500">{{ __('No gateway transactions recorded.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts::app>
