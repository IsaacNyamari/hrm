<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Organizations') }}</flux:heading>
        <flux:text class="mt-1">{{ __(':count in total', ['count' => $tenants->total()]) }}</flux:text>
    </div>

    @if ($notice)
        <flux:callout variant="success" icon="check-circle" :heading="$notice" />
    @endif

    <div class="flex flex-wrap items-end gap-3">
        <flux:input wire:model.live.debounce.300ms="search" :label="__('Search')" placeholder="{{ __('Name, slug or email') }}" class="max-w-xs" />
        <flux:select wire:model.live="status" :label="__('Status')" class="max-w-[12rem]">
            <flux:select.option value="">{{ __('Any status') }}</flux:select.option>
            @foreach ($statuses as $s)
                <flux:select.option value="{{ $s->value }}">{{ $s->label() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-neutral-700">
        <table class="w-full text-sm">
            <thead class="bg-neutral-50 text-left text-neutral-500 dark:bg-neutral-900">
                <tr>
                    <th class="px-4 py-3 font-medium">{{ __('Organization') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Plan') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Users') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Status') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Joined') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                @forelse ($tenants as $tenant)
                    <tr wire:key="tenant-{{ $tenant->id }}" class="hover:bg-neutral-50 dark:hover:bg-neutral-900">
                        <td class="px-4 py-3">
                            <a href="{{ route('platform.tenants.show', $tenant) }}" wire:navigate class="font-medium hover:underline">{{ $tenant->name }}</a>
                            <div class="text-xs text-neutral-500">{{ $tenant->slug }} · {{ $tenant->email }}</div>
                        </td>
                        <td class="px-4 py-3">{{ $tenant->subscription?->plan?->name ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $tenant->users_count }}</td>
                        <td class="px-4 py-3"><flux:badge size="sm">{{ $tenant->status->label() }}</flux:badge></td>
                        <td class="px-4 py-3 text-neutral-500">{{ $tenant->created_at?->format('Y-m-d') }}</td>
                        <td class="px-4 py-3 text-right">
                            @if ($canManage)
                                @if ($tenant->status === \App\Enums\TenantStatus::Suspended)
                                    <flux:button size="sm" variant="primary" wire:click="activate({{ $tenant->id }})">{{ __('Activate') }}</flux:button>
                                @else
                                    <flux:button size="sm" variant="ghost"
                                        wire:click="suspend({{ $tenant->id }})"
                                        wire:confirm="{{ __('Suspend :name? Its users lose app access.', ['name' => $tenant->name]) }}">
                                        {{ __('Suspend') }}
                                    </flux:button>
                                @endif
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-neutral-500">{{ __('No organizations match.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $tenants->links() }}
</div>
