<div class="flex flex-col gap-6">
    <h1 class="text-xl font-semibold text-neutral-900 dark:text-neutral-100">{{ __('Developers') }}</h1>

    @if ($notice)
        <p class="rounded-lg bg-green-50 px-3 py-2 text-sm text-green-700 dark:bg-green-950 dark:text-green-300">{{ $notice }}</p>
    @endif

    <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-800">
        <h2 class="text-sm font-semibold">{{ __('API tokens') }}</h2>
        <p class="mt-1 text-xs text-neutral-500">{{ __('Use a token as a Bearer credential against /api/v1. It is scoped to your organisation.') }}</p>

        @if ($newPlainToken)
            <div class="mt-3 rounded-lg bg-amber-50 p-3 text-sm dark:bg-amber-950/40">
                <p class="font-medium text-amber-800 dark:text-amber-300">{{ __('Copy this token now — it will not be shown again.') }}</p>
                <code class="mt-1 block break-all text-xs text-amber-900 dark:text-amber-200">{{ $newPlainToken }}</code>
            </div>
        @endif

        <form wire:submit="createToken" class="mt-3 flex flex-wrap items-end gap-3">
            <label class="text-sm">
                <span class="text-neutral-600 dark:text-neutral-400">{{ __('Name') }}</span>
                <input type="text" wire:model="tokenName" class="mt-1 w-56 rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900" />
            </label>
            <div class="text-sm">
                <span class="text-neutral-600 dark:text-neutral-400">{{ __('Abilities') }}</span>
                <div class="mt-1 flex flex-wrap gap-2">
                    @foreach ($abilities as $ability)
                        <label class="flex items-center gap-1 text-xs">
                            <input type="checkbox" wire:model="tokenAbilities" value="{{ $ability }}"> {{ $ability }}
                        </label>
                    @endforeach
                </div>
            </div>
            <button type="submit" class="rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white dark:bg-white dark:text-neutral-900">{{ __('Create token') }}</button>
            @error('tokenName') <p class="w-full text-xs text-red-600">{{ $message }}</p> @enderror
        </form>

        <ul class="mt-3 divide-y divide-neutral-100 text-sm dark:divide-neutral-800">
            @forelse ($tokens as $token)
                <li class="flex items-center justify-between py-2">
                    <span>{{ $token->name }} <span class="text-xs text-neutral-500">{{ implode(', ', $token->abilities) }}</span></span>
                    <button type="button" wire:click="revokeToken({{ $token->id }})" class="text-xs text-red-600 hover:underline">{{ __('Revoke') }}</button>
                </li>
            @empty
                <li class="py-2 text-neutral-500">{{ __('No tokens yet.') }}</li>
            @endforelse
        </ul>
    </div>

    @if ($canManageWebhooks)
        <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-800">
            <h2 class="text-sm font-semibold">{{ __('Webhook endpoints') }}</h2>
            <p class="mt-1 text-xs text-neutral-500">{{ __('We POST signed JSON (X-Webhook-Signature: sha256=…) on:') }} {{ implode(', ', $events) }}.</p>

            <form wire:submit="createWebhook" class="mt-3 flex flex-wrap items-end gap-3">
                <label class="text-sm">
                    <span class="text-neutral-600 dark:text-neutral-400">{{ __('Name') }}</span>
                    <input type="text" wire:model="webhookName" class="mt-1 w-48 rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900" />
                </label>
                <label class="text-sm">
                    <span class="text-neutral-600 dark:text-neutral-400">{{ __('URL') }}</span>
                    <input type="url" wire:model="webhookUrl" class="mt-1 w-80 rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900" />
                </label>
                <button type="submit" class="rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white dark:bg-white dark:text-neutral-900">{{ __('Add') }}</button>
                @error('webhookUrl') <p class="w-full text-xs text-red-600">{{ $message }}</p> @enderror
            </form>

            <ul class="mt-3 divide-y divide-neutral-100 text-sm dark:divide-neutral-800">
                @forelse ($webhooks as $hook)
                    <li class="flex items-center justify-between py-2">
                        <span>
                            {{ $hook->name }} — <span class="text-xs text-neutral-500">{{ $hook->url }}</span>
                            @unless ($hook->is_active) <span class="ml-1 rounded bg-red-100 px-1.5 text-xs text-red-700 dark:bg-red-950 dark:text-red-300">disabled</span> @endunless
                        </span>
                        <button type="button" wire:click="deleteWebhook('{{ $hook->uuid }}')" class="text-xs text-red-600 hover:underline">{{ __('Delete') }}</button>
                    </li>
                @empty
                    <li class="py-2 text-neutral-500">{{ __('No endpoints yet.') }}</li>
                @endforelse
            </ul>
        </div>
    @endif
</div>
