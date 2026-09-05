<div class="grid auto-rows-min gap-4 sm:grid-cols-2 lg:grid-cols-4">
    @forelse ($cards as $card)
        <a @if (! empty($card['href'])) href="{{ $card['href'] }}" wire:navigate @endif
           class="rounded-xl border border-neutral-200 p-4 transition hover:border-neutral-300 dark:border-neutral-700 dark:hover:border-neutral-600">
            <p class="text-xs uppercase tracking-wide text-neutral-500">{{ $card['label'] }}</p>
            <p class="mt-2 text-2xl font-semibold text-neutral-900 dark:text-neutral-100">{{ $card['value'] }}</p>
        </a>
    @empty
        <p class="text-sm text-neutral-500">{{ __('No metrics available.') }}</p>
    @endforelse
</div>
