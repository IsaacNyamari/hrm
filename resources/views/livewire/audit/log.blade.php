<div class="flex flex-col gap-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-semibold text-neutral-900 dark:text-neutral-100">{{ __('Audit trail') }}</h1>
        <input type="text" wire:model.live.debounce.400ms="event" placeholder="{{ __('Filter by event, e.g. employee.') }}"
            class="w-64 rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900" />
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
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-3 py-4 text-neutral-500">{{ __('No audit entries.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $logs->links() }}</div>
</div>
