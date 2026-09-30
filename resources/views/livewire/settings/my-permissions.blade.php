<div class="flex flex-col gap-4">
    <div>
        <flux:heading size="xl">{{ __('My permissions') }}</flux:heading>
        <flux:text class="mt-1">{{ __('What your role lets you do in this organization.') }}</flux:text>
    </div>

    <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
        <p class="text-xs uppercase tracking-wide text-neutral-500">{{ __('Your role') }}</p>
        <p class="mt-2 text-lg font-semibold">{{ $roles->isNotEmpty() ? $roles->join(', ') : __('No role assigned yet') }}</p>
    </div>

    @if ($permissions->isEmpty())
        <div class="rounded-xl border border-dashed border-neutral-300 p-8 text-center text-neutral-500 dark:border-neutral-700">
            {{ __("You don't have any permissions yet. Ask your administrator to assign you a role.") }}
        </div>
    @else
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($permissions->groupBy(fn ($p) => \Illuminate\Support\Str::before($p, '.')) as $group => $items)
                <div class="rounded-lg border border-neutral-200 p-3 dark:border-neutral-700">
                    <p class="text-sm font-semibold capitalize text-neutral-800 dark:text-neutral-100">{{ str_replace(['.', '_'], ' ', $group) }}</p>
                    <ul class="mt-2 space-y-1 text-xs text-neutral-500">
                        @foreach ($items as $permission)
                            <li>{{ str_replace(['.', '_'], ' ', $permission) }}</li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    @endif
</div>
