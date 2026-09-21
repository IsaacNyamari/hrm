@php
    $details = [
        'paystack' => ['credit-card', __('Hosted checkout — card, M-Pesa or bank, whatever your Paystack account enables.'), 'PAYSTACK_SECRET_KEY'],
        'mpesa' => ['device-phone-mobile', __('Safaricom Daraja STK push — tenants approve with their M-Pesa PIN.'), 'MPESA_CONSUMER_KEY / MPESA_CONSUMER_SECRET'],
    ];
@endphp

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Settings') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Installation-wide settings that apply to every organization.') }}</flux:text>
    </div>

    @if ($notice)
        <flux:callout variant="success" icon="check-circle" :heading="$notice" />
    @endif

    <form wire:submit="save" class="max-w-3xl rounded-xl border border-neutral-200 p-6 dark:border-neutral-700">
        <flux:heading size="lg">{{ __('Payment gateway') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Organizations pay their subscriptions through the gateway selected here. They cannot choose a different one.') }}</flux:text>

        <div class="mt-5 grid gap-3 sm:grid-cols-2">
            @foreach ($providers as $option)
                @php([$icon, $description, $envKeys] = $details[$option->value] ?? ['banknotes', '', ''])
                <label wire:key="gateway-{{ $option->value }}"
                       class="relative flex cursor-pointer flex-col gap-2 rounded-xl border p-4 transition {{ $paymentProvider === $option->value ? 'border-brand bg-brand/5 ring-2 ring-brand/20' : 'border-neutral-200 hover:border-highlight dark:border-neutral-700' }}">
                    <input type="radio" wire:model.live="paymentProvider" value="{{ $option->value }}" class="sr-only" />
                    <span class="flex items-center gap-2 font-semibold text-zinc-900 dark:text-white">
                        <span class="grid size-8 place-items-center rounded-lg bg-brand/10 text-brand dark:text-highlight">
                            <flux:icon :name="$icon" variant="mini" />
                        </span>
                        {{ $option === \App\Enums\PaymentProvider::Mpesa ? __('M-Pesa (Daraja)') : $option->label() }}
                        @if ($paymentProvider === $option->value)
                            <flux:icon.check-circle variant="mini" class="ms-auto text-brand dark:text-highlight" />
                        @endif
                    </span>
                    <span class="text-xs text-zinc-500">{{ $description }}</span>
                    @if ($configured[$option->value] ?? false)
                        <flux:badge size="sm" color="green" class="self-start">{{ __('Credentials configured') }}</flux:badge>
                    @else
                        <flux:badge size="sm" color="amber" class="self-start">{{ __('Set :keys in .env', ['keys' => $envKeys]) }}</flux:badge>
                    @endif
                </label>
            @endforeach
        </div>
        <flux:error name="paymentProvider" class="mt-2" />

        <div class="mt-6 flex justify-end">
            <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
        </div>
    </form>
</div>
