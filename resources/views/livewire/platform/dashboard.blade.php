<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Platform overview') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Cross-organization figures for the whole installation.') }}</flux:text>
    </div>

    <div class="grid auto-rows-min gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($cards as $card)
            <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
                <p class="text-xs uppercase tracking-wide text-neutral-500">{{ __($card['label']) }}</p>
                <p class="mt-2 text-2xl font-semibold text-neutral-900 dark:text-neutral-100">{{ $card['value'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-700">
            <flux:heading size="lg">{{ __('Revenue by plan') }}</flux:heading>
            <table class="mt-4 w-full text-sm">
                <thead>
                    <tr class="text-left text-neutral-500">
                        <th class="pb-2 font-medium">{{ __('Plan') }}</th>
                        <th class="pb-2 font-medium">{{ __('Subs') }}</th>
                        <th class="pb-2 text-right font-medium">{{ __('MRR') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($plan_breakdown as $row)
                        <tr class="border-t border-neutral-100 dark:border-neutral-800">
                            <td class="py-2">{{ $row['plan'] }}</td>
                            <td class="py-2">{{ $row['subscriptions'] }}</td>
                            <td class="py-2 text-right">{{ $currency }} {{ number_format($row['mrr_cents'] / 100, 0) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-3 text-neutral-500">{{ __('No active subscriptions yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-700">
            <div class="flex items-center justify-between">
                <flux:heading size="lg">{{ __('Newest organizations') }}</flux:heading>
                <flux:link :href="route('platform.tenants.index')" wire:navigate>{{ __('All') }}</flux:link>
            </div>
            <ul class="mt-4 divide-y divide-neutral-100 text-sm dark:divide-neutral-800">
                @forelse ($recent_tenants as $tenant)
                    <li class="flex items-center justify-between py-2">
                        <a href="{{ route('platform.tenants.show', $tenant) }}" wire:navigate class="font-medium hover:underline">{{ $tenant->name }}</a>
                        <span class="flex items-center gap-3 text-neutral-500">
                            <span>{{ $tenant->subscription?->plan?->name ?? __('No plan') }}</span>
                            <flux:badge size="sm">{{ $tenant->status->label() }}</flux:badge>
                        </span>
                    </li>
                @empty
                    <li class="py-3 text-neutral-500">{{ __('No organizations yet.') }}</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
