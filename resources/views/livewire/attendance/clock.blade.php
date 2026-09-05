<div class="max-w-sm rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-800 dark:bg-neutral-900"
     x-data="{
        async locate() {
            if (!navigator.geolocation) return;
            navigator.geolocation.getCurrentPosition((p) => {
                $wire.set('latitude', p.coords.latitude);
                $wire.set('longitude', p.coords.longitude);
            });
        }
     }"
     x-init="locate()">
    <h2 class="text-sm font-semibold uppercase tracking-wide text-neutral-500">{{ __('Time clock') }}</h2>

    @unless ($hasEmployee)
        <p class="mt-3 text-sm text-neutral-500">{{ __('Your account is not linked to an employee record.') }}</p>
    @else
        <p class="mt-2 text-2xl font-semibold text-neutral-900 dark:text-neutral-100" x-data="{ now: '' }"
           x-init="now = new Date().toLocaleTimeString(); setInterval(() => now = new Date().toLocaleTimeString(), 1000)" x-text="now"></p>

        <p class="mt-1 text-sm text-neutral-500">
            @if ($clockedOut)
                {{ __('Clocked out') }} · {{ $attendance->worked_minutes }} {{ __('min worked') }}
            @elseif ($onBreak)
                {{ __('On break since') }} {{ $attendance->break_started_at?->format('H:i') }}
            @elseif ($clockedIn)
                {{ __('Clocked in at') }} {{ $attendance->clock_in_at?->format('H:i') }}
            @else
                {{ __('Not clocked in') }}
            @endif
        </p>

        @if ($error)
            <p class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-950 dark:text-red-300">{{ $error }}</p>
        @endif

        <div class="mt-4 flex gap-2">
            @if (! $clockedIn && ! $clockedOut)
                <button wire:click="clockIn" class="flex-1 rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white dark:bg-white dark:text-neutral-900">
                    {{ __('Clock in') }}
                </button>
            @elseif ($clockedIn)
                <button wire:click="toggleBreak" class="flex-1 rounded-lg border border-neutral-300 px-4 py-2 text-sm font-medium dark:border-neutral-700">
                    {{ $onBreak ? __('End break') : __('Start break') }}
                </button>
                <button wire:click="clockOut" @disabled($onBreak)
                        class="flex-1 rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-40 dark:bg-white dark:text-neutral-900">
                    {{ __('Clock out') }}
                </button>
            @endif
        </div>
    @endunless
</div>
