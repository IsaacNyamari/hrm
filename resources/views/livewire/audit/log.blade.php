<div class="flex flex-col gap-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-semibold text-neutral-800 dark:text-neutral-100">{{ __('Audit trail') }}</h1>
        <div class="flex items-center gap-2">
            @if ($canExport)
                <a href="{{ $exportUrl }}" class="rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700">{{ __('Export CSV') }}</a>
            @endif
            @if ($canDelete && $logs->total() > 0)
                <flux:modal.trigger name="clear-audit-trail">
                    <flux:button size="sm" variant="danger" icon="trash">{{ __('Clear all') }}</flux:button>
                </flux:modal.trigger>
                <x-confirm-modal name="clear-audit-trail" :heading="__('Clear the entire audit trail?')" :text="__('Every audit entry for your organization is permanently deleted. This cannot be undone.')" :confirm-label="__('Clear all')" confirm-wire="clearAll" />
            @endif
        </div>
    </div>

    <div class="flex flex-wrap gap-3">
        <input type="text" wire:model.live.debounce.400ms="event" placeholder="{{ __('Event, e.g. payroll.') }}"
            class="w-56 rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900" />
        <input type="text" wire:model.live.debounce.400ms="actor" placeholder="{{ __('Who (name or email)') }}"
            class="w-56 rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900" />
        <input type="date" wire:model.live="from" aria-label="{{ __('From') }}" class="rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900" />
        <input type="date" wire:model.live="to" aria-label="{{ __('To') }}" class="rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900" />
    </div>

    <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-neutral-800">
        <table class="w-full text-sm">
            <thead class="bg-neutral-50 text-left text-xs uppercase text-neutral-500 dark:bg-neutral-900">
                <tr>
                    <th class="px-3 py-2">{{ __('When') }}</th>
                    <th class="px-3 py-2">{{ __('Event') }}</th>
                    <th class="px-3 py-2">{{ __('Actor') }}</th>
                    <th class="px-3 py-2">{{ __('Subject') }}</th>
                    <th class="px-3 py-2">{{ __('IP') }}</th>
                    @if ($canDelete)<th class="px-3 py-2"></th>@endif
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr class="border-t border-neutral-100 dark:border-neutral-800">
                        <td class="whitespace-nowrap px-3 py-1.5 text-neutral-500">{{ $log->created_at?->diffForHumans() }}</td>
                        <td class="px-3 py-1.5">
                            <span class="rounded bg-neutral-100 px-1.5 py-0.5 font-mono text-xs dark:bg-neutral-800">{{ $log->event }}</span>
                            @if ($log->via_impersonation)
                                <span class="ml-1 rounded bg-amber-100 px-1.5 text-xs text-amber-800 dark:bg-amber-950 dark:text-amber-300">impersonated</span>
                            @endif
                        </td>
                        <td class="px-3 py-1.5 text-neutral-600 dark:text-neutral-400">{{ $log->actor_label ?? '—' }}</td>
                        <td class="px-3 py-1.5 text-neutral-500">
                            {{ $log->auditable_type ? class_basename($log->auditable_type) : '' }}
                            {{ $log->auditable_id ? '#'.$log->auditable_id : '' }}
                        </td>
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
                    <tr><td colspan="{{ $canDelete ? 6 : 5 }}" class="px-3 py-4 text-neutral-500">{{ __('No audit entries.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $logs->links() }}</div>
</div>
