@props([
    'segments' => [],
    'caption' => '',
])

{{-- Each segment: ['label' => string, 'value' => int, 'color' => css colour, 'href' => ?string]. --}}
@php
    $total = array_sum(array_column($segments, 'value'));
    $radius = 15.9155;
    $offset = 25;
@endphp

<div {{ $attributes->class('flex flex-col items-center gap-5 sm:flex-row lg:flex-col 2xl:flex-row') }}>
    <div class="relative size-40 shrink-0">
        <svg viewBox="0 0 42 42" class="size-full" aria-hidden="true">
            <circle cx="21" cy="21" r="{{ $radius }}" fill="none" stroke="currentColor" stroke-width="5" class="text-zinc-100 dark:text-white/5" />
            @foreach ($segments as $segment)
                @continue($total === 0 || $segment['value'] === 0)
                @php($share = $segment['value'] / $total * 100)
                <circle cx="21" cy="21" r="{{ $radius }}" fill="none" stroke="{{ $segment['color'] }}" stroke-width="5"
                        stroke-dasharray="{{ round($share, 3) }} {{ round(100 - $share, 3) }}" stroke-dashoffset="{{ round($offset, 3) }}">
                    <title>{{ $segment['label'] }}: {{ $segment['value'] }}</title>
                </circle>
                @php($offset -= $share)
            @endforeach
        </svg>
        <div class="absolute inset-0 grid place-content-center text-center">
            <span class="text-2xl font-bold text-zinc-900 dark:text-white">{{ number_format($total) }}</span>
            <span class="text-[11px] text-zinc-500">{{ $caption }}</span>
        </div>
    </div>

    <ul class="w-full space-y-1 text-sm">
        @forelse ($segments as $segment)
            <li>
                <a @if (! empty($segment['href'])) href="{{ $segment['href'] }}" wire:navigate @endif
                   class="flex items-center gap-2 rounded-md px-2 py-1.5 transition hover:bg-zinc-50 dark:hover:bg-white/5">
                    <span class="size-2.5 shrink-0 rounded-full" style="background: {{ $segment['color'] }}"></span>
                    <span class="flex-1 truncate text-zinc-600 dark:text-zinc-300">{{ $segment['label'] }}</span>
                    <span class="font-semibold text-zinc-900 dark:text-white">{{ $segment['value'] }}</span>
                    <span class="w-10 text-end text-xs text-zinc-400">{{ $total > 0 ? round($segment['value'] / $total * 100) : 0 }}%</span>
                </a>
            </li>
        @empty
            <li class="px-2 text-zinc-500">{{ __('No data yet.') }}</li>
        @endforelse
    </ul>
</div>
