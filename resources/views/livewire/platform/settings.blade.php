@php
    $details = [
        'paystack' => ['credit-card', __('Hosted checkout — card, M-Pesa or bank, whatever your Paystack account enables.')],
        'mpesa' => ['device-phone-mobile', __('Safaricom Daraja STK push — tenants approve with their M-Pesa PIN.')],
    ];
    $secretHint = fn (bool $set) => $set ? __('Saved — leave blank to keep it') : __('Not set');
@endphp

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Settings') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Installation-wide settings that apply to every organization.') }}</flux:text>
    </div>

    @if ($notice)
        <flux:callout variant="success" icon="check-circle" :heading="$notice" />
    @endif

    {{-- Active gateway --}}
    <form wire:submit="save" class="max-w-4xl rounded-xl border border-neutral-200 p-6 dark:border-neutral-700">
        <flux:heading size="lg">{{ __('Payment gateway') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Organizations pay their subscriptions through the gateway selected here. They cannot choose a different one.') }}</flux:text>

        <div class="mt-5 grid gap-3 sm:grid-cols-2">
            @foreach ($providers as $option)
                @php([$icon, $description] = $details[$option->value] ?? ['banknotes', ''])
                <label wire:key="gateway-{{ $option->value }}"
                       class="relative flex min-w-0 cursor-pointer flex-col gap-2 rounded-xl border p-4 transition {{ $paymentProvider === $option->value ? 'border-brand bg-brand/5 ring-2 ring-brand/20' : 'border-neutral-200 hover:border-highlight dark:border-neutral-700' }}">
                    <input type="radio" wire:model.live="paymentProvider" value="{{ $option->value }}" class="sr-only" />
                    <span class="flex items-center gap-2 font-semibold text-zinc-900 dark:text-white">
                        <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-brand/10 text-brand dark:text-highlight">
                            <flux:icon :name="$icon" variant="mini" />
                        </span>
                        {{ $option === \App\Enums\PaymentProvider::Mpesa ? __('M-Pesa (Daraja)') : $option->label() }}
                        @if ($paymentProvider === $option->value)
                            <flux:icon.check-circle variant="mini" class="ms-auto text-brand dark:text-highlight" />
                        @endif
                    </span>
                    <span class="text-xs text-zinc-500">{{ $description }}</span>
                    @if ($configured[$option->value] ?? false)
                        <span class="inline-flex w-fit items-center gap-1 rounded-md bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800 dark:bg-green-500/15 dark:text-green-300">
                            <flux:icon.check variant="micro" /> {{ __('Credentials configured') }}
                        </span>
                    @else
                        <span class="inline-flex w-fit max-w-full items-center gap-1 rounded-md bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-500/15 dark:text-amber-300">
                            <flux:icon.exclamation-triangle variant="micro" class="shrink-0" /> {{ __('Credentials missing — add them below') }}
                        </span>
                    @endif
                </label>
            @endforeach
        </div>
        <flux:error name="paymentProvider" class="mt-2" />

        <div class="mt-6 flex justify-end">
            <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
        </div>
    </form>

    {{-- Credentials --}}
    <div class="grid max-w-4xl gap-6 lg:grid-cols-2">
        <form wire:submit="saveCredentials('paystack')" class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-700">
            <div class="flex items-center justify-between gap-2">
                <flux:heading size="lg">{{ __('Paystack credentials') }}</flux:heading>
                @if (str_starts_with($paystack['public_key'], 'pk_live_'))
                    <flux:badge size="sm" color="green">{{ __('Live') }}</flux:badge>
                @elseif (str_starts_with($paystack['public_key'], 'pk_test_'))
                    <flux:badge size="sm" color="amber">{{ __('Test mode') }}</flux:badge>
                @endif
            </div>
            <flux:text class="mt-1 text-xs">{{ __('Paystack dashboard → Settings → API Keys & Webhooks.') }}</flux:text>

            <div class="mt-5 space-y-4">
                <flux:input wire:model="paystack.public_key" :label="__('Public key')" placeholder="pk_live_…" autocomplete="off" />
                <flux:input wire:model="paystack.secret_key" type="password" :label="__('Secret key')" placeholder="sk_live_…"
                            :description="$secretHint($secretsSet['paystack']['secret_key'] ?? false)" autocomplete="new-password" viewable />
                <div class="rounded-lg bg-zinc-50 p-3 text-xs dark:bg-white/5">
                    <p class="font-semibold text-zinc-700 dark:text-zinc-200">{{ __('Webhook URL (paste into Paystack)') }}</p>
                    <code class="mt-1 block break-all text-brand dark:text-highlight">{{ route('webhooks.paystack') }}</code>
                </div>
            </div>

            <div class="mt-6 flex justify-end">
                <flux:button type="submit" variant="primary">{{ __('Save Paystack') }}</flux:button>
            </div>
        </form>

        <form wire:submit="saveCredentials('mpesa')" class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-700">
            <flux:heading size="lg">{{ __('M-Pesa (Daraja) credentials') }}</flux:heading>
            <flux:text class="mt-1 text-xs">{{ __('Safaricom developer portal → your app → Keys; Lipa na M-Pesa shortcode and passkey.') }}</flux:text>

            <div class="mt-5 space-y-4">
                <flux:select wire:model="mpesa.environment" :label="__('Environment')">
                    <flux:select.option value="sandbox">{{ __('Sandbox (testing)') }}</flux:select.option>
                    <flux:select.option value="production">{{ __('Production (live)') }}</flux:select.option>
                </flux:select>
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="mpesa.consumer_key" type="password" :label="__('Consumer key')"
                                :description="$secretHint($secretsSet['mpesa']['consumer_key'] ?? false)" autocomplete="new-password" viewable />
                    <flux:input wire:model="mpesa.consumer_secret" type="password" :label="__('Consumer secret')"
                                :description="$secretHint($secretsSet['mpesa']['consumer_secret'] ?? false)" autocomplete="new-password" viewable />
                    <flux:input wire:model="mpesa.shortcode" :label="__('Shortcode')" placeholder="174379" autocomplete="off" />
                    <flux:input wire:model="mpesa.passkey" type="password" :label="__('Passkey')"
                                :description="$secretHint($secretsSet['mpesa']['passkey'] ?? false)" autocomplete="new-password" viewable />
                </div>
                <div class="rounded-lg bg-zinc-50 p-3 text-xs dark:bg-white/5">
                    <p class="font-semibold text-zinc-700 dark:text-zinc-200">{{ __('Callback URL') }}</p>
                    <code class="mt-1 block break-all text-brand dark:text-highlight">{{ config('services.mpesa.callback_url') ?: route('webhooks.mpesa') }}</code>
                </div>
            </div>

            <div class="mt-6 flex justify-end">
                <flux:button type="submit" variant="primary">{{ __('Save M-Pesa') }}</flux:button>
            </div>
        </form>
    </div>

    <flux:text class="max-w-4xl text-xs">
        <flux:icon.lock-closed variant="micro" class="inline" />
        {{ __('Secrets are encrypted before they are stored and are never shown again. Values saved here take precedence over the server .env file.') }}
    </flux:text>
</div>
