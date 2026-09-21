<div class="rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-700 dark:bg-neutral-900" data-test="attendance-live-today">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="font-semibold text-zinc-900 dark:text-white">{{ __("Today's attendance") }}</h2>
            <p class="text-xs text-zinc-500">{{ now()->format('l, j F Y') }}</p>
        </div>
        <span class="inline-flex items-center gap-1.5 rounded-full bg-success/10 px-2.5 py-1 text-[11px] font-semibold text-success">
            <span class="relative flex size-2"><span class="absolute inline-flex size-full animate-ping rounded-full bg-success/60"></span><span class="relative inline-flex size-2 rounded-full bg-success"></span></span>
            {{ __('Live') }}
        </span>
    </div>

    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-5">
        @foreach ([
            ['present', __('Clocked in'), 'text-success'],
            ['late', __('Late'), 'text-amber-600'],
            ['on_break', __('On break'), 'text-brand dark:text-highlight'],
            ['clocked_out', __('Gone home'), 'text-zinc-600 dark:text-zinc-300'],
            ['not_in', __('Not in yet'), 'text-danger'],
        ] as [$key, $label, $tone])
            <div class="rounded-lg bg-neutral-50 p-3 dark:bg-neutral-800/60">
                <p class="text-2xl font-bold {{ $tone }}" data-test="today-{{ $key }}">{{ $stats[$key] }}</p>
                <p class="text-xs text-zinc-500">{{ $label }}</p>
            </div>
        @endforeach
    </div>

    @if ($lastEvent)
        <p class="mt-4 flex items-center gap-2 rounded-lg bg-highlight/10 px-3 py-2 text-sm text-teal-800 dark:text-highlight" wire:key="last-{{ md5($lastEvent) }}">
            <flux:icon.bolt variant="micro" /> {{ $lastEvent }}
        </p>
    @endif

    <table class="mt-4 w-full text-left text-sm">
        <thead class="text-xs uppercase text-neutral-500">
            <tr>
                <th class="py-2">{{ __('Employee') }}</th>
                <th class="py-2">{{ __('In') }}</th>
                <th class="py-2">{{ __('Out') }}</th>
                <th class="py-2">{{ __('Status') }}</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
            @forelse ($latest as $record)
                <tr wire:key="today-{{ $record->uuid }}" @class(['bg-highlight/5' => $loop->first && $lastEvent])>
                    <td class="py-2 font-medium text-zinc-800 dark:text-zinc-200">{{ $record->employee?->fullName() ?? '—' }}</td>
                    <td class="py-2 text-zinc-600 dark:text-zinc-300">{{ $record->clock_in_at?->format('H:i') ?? '—' }}</td>
                    <td class="py-2 text-zinc-600 dark:text-zinc-300">
                        {{ $record->clock_out_at?->format('H:i') ?? ($record->isOnBreak() ? __('On break') : '—') }}
                    </td>
                    <td class="py-2"><x-status-badge :status="$record->status" /></td>
                </tr>
            @empty
                <tr><td colspan="4" class="py-6 text-center text-neutral-500">{{ __('No one has clocked in yet today.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
    <a href="{{ route('attendance.index') }}" wire:navigate class="mt-3 inline-block text-xs font-semibold text-brand hover:underline dark:text-highlight">{{ __('All attendance records') }}</a>
</div>
