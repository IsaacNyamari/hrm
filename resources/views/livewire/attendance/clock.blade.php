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
    <h2 class="text-xs font-semibold uppercase tracking-wider text-brand dark:text-highlight">{{ __('Time clock') }}</h2>

    @unless ($hasEmployee)
        <p class="mt-3 text-sm text-neutral-500">{{ __('Your account is not linked to an employee record.') }}</p>
    @else
        <p class="mt-2 text-2xl font-semibold text-neutral-800 dark:text-neutral-100" x-data="{ now: '' }"
           x-init="now = new Date().toLocaleTimeString(); setInterval(() => now = new Date().toLocaleTimeString(), 1000)" x-text="now"></p>

        <p class="mt-1 flex items-center gap-2 text-sm text-neutral-500">
            @if ($clockedIn && ! $clockedOut)
                <span class="relative flex size-2.5">
                    <span class="absolute inline-flex size-full animate-ping rounded-full opacity-60 {{ $onBreak ? 'bg-amber-400' : 'bg-success' }}"></span>
                    <span class="relative inline-flex size-2.5 rounded-full {{ $onBreak ? 'bg-amber-500' : 'bg-success' }}"></span>
                </span>
            @endif
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
                <button wire:click="clockIn" class="flex-1 rounded-lg bg-success px-4 py-2 text-sm font-semibold text-white shadow-sm shadow-success/20 transition hover:bg-green-600">
                    {{ __('Clock in') }}
                </button>
            @elseif ($clockedIn)
                <button wire:click="toggleBreak" class="flex-1 rounded-lg border border-neutral-300 px-4 py-2 text-sm font-medium transition hover:border-highlight hover:bg-highlight/15 dark:border-neutral-700">
                    {{ $onBreak ? __('End break') : __('Start break') }}
                </button>
                <button wire:click="clockOut" @disabled($onBreak)
                        class="flex-1 rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white shadow-sm shadow-brand/20 transition hover:bg-blue-700 disabled:opacity-40">
                    {{ __('Clock out') }}
                </button>
            @endif
        </div>
    @endunless
</div>
