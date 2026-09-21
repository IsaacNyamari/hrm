<div class="flex max-w-2xl flex-col gap-5">
    <div>
        <h1 class="text-xl font-semibold text-neutral-800 dark:text-neutral-100">{{ __('Branding') }}</h1>
        <p class="mt-1 text-sm text-neutral-500">{{ __('Applied to the app header and every e-mail your team sends.') }}</p>
    </div>

    @if ($notice)
        <p class="rounded-lg bg-green-50 px-3 py-2 text-sm text-green-700 dark:bg-green-950 dark:text-green-300">{{ $notice }}</p>
    @endif

    <form wire:submit="save" class="flex flex-col gap-4">
        <label class="text-sm">
            <span class="text-neutral-600 dark:text-neutral-400">{{ __('Brand name') }}</span>
            <input type="text" wire:model="brand_name" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900" />
        </label>

        <label class="text-sm">
            <span class="text-neutral-600 dark:text-neutral-400">{{ __('Primary colour') }}</span>
            <input type="color" wire:model="primary_color" class="mt-1 h-9 w-16 rounded border border-neutral-300 dark:border-neutral-700" />
            @error('primary_color') <span class="ml-2 text-xs text-red-600">{{ $message }}</span> @enderror
        </label>

        <div class="grid gap-4 sm:grid-cols-2">
            <label class="text-sm">
                <span class="text-neutral-600 dark:text-neutral-400">{{ __('From name') }}</span>
                <input type="text" wire:model="from_name" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900" />
            </label>
            <label class="text-sm">
                <span class="text-neutral-600 dark:text-neutral-400">{{ __('From email') }}</span>
                <input type="email" wire:model="from_email" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900" />
                @error('from_email') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </label>
        </div>

        <label class="text-sm">
            <span class="text-neutral-600 dark:text-neutral-400">{{ __('Reply-to email') }}</span>
            <input type="email" wire:model="reply_to_email" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900" />
        </label>

        <label class="text-sm">
            <span class="text-neutral-600 dark:text-neutral-400">{{ __('Email footer') }}</span>
            <textarea wire:model="email_footer" rows="2" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900"></textarea>
        </label>

        <label class="text-sm">
            <span class="text-neutral-600 dark:text-neutral-400">{{ __('Logo') }}</span>
            @if ($currentLogo)
                <img src="{{ $currentLogo }}" alt="logo" class="mt-1 h-10" />
            @endif
            <input type="file" wire:model="logo" accept="image/*" class="mt-1 block text-sm" />
            @error('logo') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
        </label>

        <div>
            <button type="submit" class="rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white dark:bg-white dark:text-neutral-900">{{ __('Save branding') }}</button>
        </div>
    </form>
</div>
