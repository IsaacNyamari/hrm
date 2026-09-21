@props([
    'series' => [],
    'prefix' => '',
    'divisor' => 1,
    'color' => 'var(--color-brand)',
])

{{-- Each point: ['label' => string, 'value' => int]. Values are divided by $divisor for display. --}}
@php
    $max = max(1, ...array_map(fn (array $point): int => (int) $point['value'], $series ?: [['value' => 0]]));
    $compact = function (int|float $value) use ($prefix, $divisor): string {
        $value /= $divisor;
        $formatted = match (true) {
            $value >= 1_000_000 => round($value / 1_000_000, 1).'M',
            $value >= 1_000 => round($value / 1_000, 1).'K',
            default => (string) round($value),
        };

        return trim($prefix.' '.$formatted);
    };
    $lastIndex = count($series) - 1;
@endphp

<div {{ $attributes->class('relative') }}>
    <div class="pointer-events-none absolute inset-x-0 top-0 bottom-6 flex flex-col justify-between">
        @foreach ([1, 0.5, 0] as $step)
            <div class="flex items-center gap-2">
                <span class="w-14 shrink-0 text-end text-[10px] text-zinc-400">{{ $compact($max * $step) }}</span>
                <span class="h-px flex-1 border-t border-dashed border-zinc-200 dark:border-white/10"></span>
            </div>
        @endforeach
    </div>

    <div class="relative ms-16 flex h-full items-end gap-1.5 pb-6 sm:gap-2.5">
        @foreach ($series as $point)
            @php($height = $point['value'] > 0 ? max(3, ($point['value'] / $max) * 100) : 0)
            <div class="group relative flex h-full flex-1 flex-col justify-end">
                <div class="pointer-events-none absolute left-1/2 z-10 whitespace-nowrap rounded-md bg-night px-2 py-1 text-[11px] font-semibold text-white opacity-0 shadow-lg transition group-hover:opacity-100"
                     style="bottom: calc({{ $height }}% + 0.5rem); transform: translate(-50%, 0);">
                    {{ $point['label'] }}: {{ $compact($point['value']) }}
                </div>
                <div class="w-full rounded-t-md transition duration-200 {{ $loop->index === $lastIndex ? '' : 'opacity-60 group-hover:opacity-100' }}"
                     style="height: {{ $height }}%; background: linear-gradient(to top, {{ $color }}, color-mix(in oklab, {{ $color }} 60%, white));"></div>
                <span class="absolute inset-x-0 -bottom-6 pt-1.5 text-center text-[10px] font-medium text-zinc-400">{{ $point['label'] }}</span>
            </div>
        @endforeach
    </div>
</div>
