<div class="flex flex-col gap-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-semibold text-neutral-800 dark:text-neutral-100">{{ __('Platform audit trail') }}</h1>
        @if ($canDelete && $logs->total() > 0)
            <flux:modal.trigger name="clear-platform-audit">
                <flux:button size="sm" variant="danger" icon="trash">{{ __('Clear all') }}</flux:button>
            </flux:modal.trigger>
            <x-confirm-modal name="clear-platform-audit" :heading="__('Clear the platform audit trail?')" :text="__('Every platform audit entry is permanently deleted. This cannot be undone.')" :confirm-label="__('Clear all')" confirm-wire="clearAll" />
        @endif
    </div>

    <div class="flex flex-wrap gap-3">
        <input type="text" wire:model.live.debounce.400ms="event" placeholder="{{ __('Event, e.g. platform.tenant') }}"
            class="w-56 rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900" />
        <input type="text" wire:model.live.debounce.400ms="actor" placeholder="{{ __('Who (name or email)') }}"
            class="w-56 rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900" />
    </div>

    <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-neutral-800">
        <table class="w-full text-sm">
            <thead class="bg-neutral-50 text-left text-xs uppercase text-neutral-500 dark:bg-neutral-900">
                <tr>
                    <th class="px-3 py-2">{{ __('When') }}</th>
                    <th class="px-3 py-2">{{ __('Event') }}</th>
                    <th class="px-3 py-2">{{ __('Operator') }}</th>
                    <th class="px-3 py-2">{{ __('Organization') }}</th>
                    <th class="px-3 py-2">{{ __('IP') }}</th>
                    @if ($canDelete)<th class="px-3 py-2"></th>@endif
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr class="border-t border-neutral-100 dark:border-neutral-800">
                        <td class="whitespace-nowrap px-3 py-1.5 text-neutral-500">{{ $log->created_at?->format('d M Y H:i') }}</td>
                        <td class="px-3 py-1.5"><span class="rounded bg-neutral-100 px-1.5 py-0.5 font-mono text-xs dark:bg-neutral-800">{{ $log->event }}</span></td>
                        <td class="px-3 py-1.5 text-neutral-600 dark:text-neutral-400">{{ $log->actor_label ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-neutral-500">{{ $log->tenant_id ? '#'.$log->tenant_id : '—' }}</td>
                        <td class="px-3 py-1.5 text-neutral-400">{{ $log->ip_address }}</td>
                        @if ($canDelete)
                            <td class="px-3 py-1.5 text-right">
                                <flux:modal.trigger name="delete-audit-{{ $log->uuid }}">
                                    <button type="button" class="text-xs text-red-600 hover:underline">{{ __('Delete') }}</button>
                                </flux:modal.trigger>
                                <x-confirm-modal name="delete-audit-{{ $log->uuid }}" :heading="__('Delete this audit entry?')" :text="__('This cannot be undone.')" :confirm-label="__('Delete')" confirm-wire="deleteEntry('{{ $log->uuid }}')" />
                            </td>
                        @endif
                    </tr>
                    @if ($log->properties)
                        <tr class="border-t border-neutral-50 dark:border-neutral-900">
                            <td colspan="{{ $canDelete ? 6 : 5 }}" class="px-3 pb-2">
                                <details>
                                    <summary class="cursor-pointer text-xs text-neutral-500">{{ __('Details') }}</summary>
                                    <pre class="mt-1 overflow-x-auto rounded bg-neutral-50 p-2 text-xs dark:bg-neutral-900">{{ json_encode($log->properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                </details>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="{{ $canDelete ? 6 : 5 }}" class="px-3 py-4 text-neutral-500">{{ __('No platform activity yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $logs->links() }}</div>
</div>
