@props([
    'series' => [],
    'color' => 'var(--color-highlight)',
])

{{-- Each point: ['label' => string, 'value' => int]. --}}
@php
    $count = count($series);
    $max = max(1, ...array_map(fn (array $point): int => (int) $point['value'], $series ?: [['value' => 0]]));
    $points = collect($series)->values()->map(fn (array $point, int $i): array => [
        'x' => $count > 1 ? round($i / ($count - 1) * 100, 2) : 50,
        'y' => round(92 - ($point['value'] / $max) * 80, 2),
        'label' => $point['label'],
        'value' => $point['value'],
    ]);
    $line = $points->map(fn (array $p, int $i): string => ($i === 0 ? 'M' : 'L').$p['x'].' '.$p['y'])->implode(' ');
    $area = $count > 0 ? $line.' L100 100 L0 100 Z' : '';
    $gradientId = 'line-fill-'.\Illuminate\Support\Str::random(6);
@endphp

<div {{ $attributes->class('relative') }}>
    <div class="relative h-full pb-6">
        <div class="relative h-full">
            <svg viewBox="0 0 100 100" preserveAspectRatio="none" class="absolute inset-0 size-full overflow-visible" aria-hidden="true">
                <defs>
                    <linearGradient id="{{ $gradientId }}" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0" stop-color="{{ $color }}" stop-opacity="0.35" />
                        <stop offset="1" stop-color="{{ $color }}" stop-opacity="0" />
                    </linearGradient>
                </defs>
                @foreach ([12, 52, 92] as $gridY)
                    <line x1="0" x2="100" y1="{{ $gridY }}" y2="{{ $gridY }}" stroke="currentColor" class="text-zinc-200 dark:text-white/10" stroke-dasharray="3 3" vector-effect="non-scaling-stroke" />
                @endforeach
                <path d="{{ $area }}" fill="url(#{{ $gradientId }})" />
                <path d="{{ $line }}" fill="none" stroke="{{ $color }}" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />
            </svg>

            @foreach ($points as $point)
                <div class="group absolute -translate-x-1/2 -translate-y-1/2" style="left: {{ $point['x'] }}%; top: {{ $point['y'] }}%;">
                    <span class="block size-2.5 rounded-full border-2 border-white shadow transition group-hover:scale-150 dark:border-zinc-900" style="background: {{ $color }}"></span>
                    <span class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-2 -translate-x-1/2 whitespace-nowrap rounded-md bg-night px-2 py-1 text-[11px] font-semibold text-white opacity-0 shadow-lg transition group-hover:opacity-100">
                        {{ $point['label'] }}: {{ number_format($point['value']) }}
                    </span>
                </div>
            @endforeach
        </div>

        <div class="absolute inset-x-0 bottom-0 h-6">
            @foreach ($points as $point)
                <span class="absolute -translate-x-1/2 pt-1.5 text-[10px] font-medium text-zinc-400" style="left: {{ $point['x'] }}%">{{ $point['label'] }}</span>
            @endforeach
        </div>
    </div>
</div>
