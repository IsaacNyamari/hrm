@php
    $tones = [
        'bg-warning text-night shadow-warning/30',
        'bg-danger text-white shadow-danger/30',
        'bg-info text-white shadow-info/30',
        'bg-success text-white shadow-success/30',
        'bg-brand text-white shadow-brand/30',
        'bg-highlight text-night shadow-highlight/30',
    ];
    $icons = [
        'headcount' => 'users',
        'present_today' => 'clock',
        'leave_pending' => 'calendar-days',
        'expenses_pending' => 'receipt-percent',
        'payroll_open' => 'banknotes',
        'vacancies_open' => 'briefcase',
        'applications_active' => 'user-plus',
        'hr_requests_open' => 'inbox-stack',
    ];
@endphp

<div class="grid auto-rows-min gap-4 sm:grid-cols-2 lg:grid-cols-4">
    @forelse ($cards as $card)
        <a @if (! empty($card['href'])) href="{{ $card['href'] }}" wire:navigate @endif
           class="group relative overflow-hidden rounded-xl p-5 shadow-lg transition duration-150 hover:-translate-y-0.5 {{ $tones[$loop->index % count($tones)] }}">
            <span class="pointer-events-none absolute -end-6 -bottom-8 size-28 rounded-full bg-white/10"></span>

            <div class="flex items-start justify-between gap-3">
                <p class="text-sm font-semibold">{{ $card['label'] }}</p>
                <span class="grid size-10 shrink-0 place-items-center rounded-full bg-white/20 ring-4 ring-white/10">
                    <flux:icon :name="$icons[$card['key']] ?? 'chart-bar'" variant="mini" />
                </span>
            </div>

            <p class="mt-1 text-3xl font-bold tracking-tight">{{ $card['value'] }}</p>

            <p class="mt-2 flex items-center gap-1 text-xs font-medium opacity-80">
                @if (! empty($card['href']))
                    {{ __('View details') }}
                    <flux:icon.arrow-right variant="micro" class="transition group-hover:translate-x-0.5" />
                @else
                    {{ __('As of today') }}
                @endif
            </p>
        </a>
    @empty
        <p class="text-sm text-neutral-500">{{ __('No metrics available.') }}</p>
    @endforelse
</div>
