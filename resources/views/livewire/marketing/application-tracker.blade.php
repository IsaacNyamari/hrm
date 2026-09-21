@php
    $field = 'mt-1.5 block w-full rounded-xl border border-zinc-200 bg-white px-4 py-2.5 text-sm text-night shadow-sm transition placeholder:text-zinc-400 focus:border-brand focus:outline-hidden focus:ring-4 focus:ring-brand/10';
    $label = 'block text-sm font-semibold text-night';
    $stepLabels = [
        'applied' => __('Applied'),
        'review' => __('Under review'),
        'interview' => __('Interview'),
        'offer' => __('Offer'),
        'hired' => __('Hired'),
    ];
    $tone = [
        'hired' => 'bg-success/10 text-success',
        'accepted' => 'bg-success/10 text-success',
        'offer' => 'bg-highlight/15 text-teal-700',
        'interview' => 'bg-brand/10 text-brand',
        'review' => 'bg-brand/10 text-brand',
        'received' => 'bg-brand/10 text-brand',
        'unsuccessful' => 'bg-danger/10 text-danger',
        'declined' => 'bg-zinc-100 text-zinc-600',
        'withdrawn' => 'bg-zinc-100 text-zinc-600',
    ];
@endphp

<div>
    @if (! $application)
        <form wire:submit="lookup" class="space-y-5" data-test="track-form">
            <div>
                <label for="track-code" class="{{ $label }}">{{ __('Tracking code') }}</label>
                <input id="track-code" type="text" wire:model="code" placeholder="APP-XXXXXXXX" autocomplete="off"
                    class="{{ $field }} font-mono uppercase tracking-widest" required>
                @error('code') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="track-email" class="{{ $label }}">{{ __('Email you applied with') }}</label>
                <input id="track-email" type="email" wire:model="email" autocomplete="email" class="{{ $field }}" required>
                @error('email') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-xl bg-brand px-6 py-3.5 text-sm font-bold text-white shadow-lg shadow-brand/25 transition hover:bg-indigo-700">
                <flux:icon.magnifying-glass variant="mini" /> {{ __('Track application') }}
            </button>
            <p class="text-center text-xs text-zinc-500">{{ __('Your tracking code was shown when you applied and is in your confirmation email.') }}</p>
        </form>
    @else
        <div data-test="track-result">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-zinc-500">{{ $application->tenant->name }}</p>
                    <h2 class="text-xl font-extrabold text-night">{{ $application->vacancy->title }}</h2>
                    <p class="mt-1 text-sm text-zinc-500">{{ __('Applied :date · Code :code', ['date' => $application->applied_at->toFormattedDateString(), 'code' => $application->tracking_code]) }}</p>
                </div>
                <span class="rounded-full px-3 py-1 text-xs font-bold {{ $tone[$progress->state] ?? 'bg-zinc-100 text-zinc-600' }}" data-test="track-state">{{ $progress->headline }}</span>
            </div>

            <ol class="mt-8 grid grid-cols-5 gap-2">
                @foreach ($progress->steps as $step => $state)
                    <li class="flex flex-col items-center text-center">
                        <span @class([
                            'grid size-9 place-items-center rounded-full text-sm font-bold',
                            'bg-success text-white' => $state === 'done',
                            'bg-brand text-white ring-4 ring-brand/20' => $state === 'current',
                            'bg-danger text-white' => $state === 'failed',
                            'bg-zinc-100 text-zinc-400' => $state === 'upcoming',
                        ])>
                            @if ($state === 'done')
                                <flux:icon.check variant="mini" />
                            @elseif ($state === 'failed')
                                <flux:icon.x-mark variant="mini" />
                            @else
                                {{ $loop->iteration }}
                            @endif
                        </span>
                        <span @class(['mt-2 text-xs font-semibold', 'text-night' => $state !== 'upcoming', 'text-zinc-400' => $state === 'upcoming'])>{{ $stepLabels[$step] }}</span>
                    </li>
                @endforeach
            </ol>

            <div class="mt-8 rounded-2xl bg-zinc-50 p-5 text-sm text-zinc-700">
                <p class="font-semibold text-night">{{ $progress->headline }}</p>
                <p class="mt-1">{{ $progress->message }}</p>
            </div>

            @if ($notice)
                <p class="mt-4 rounded-xl bg-brand/10 p-4 text-sm font-medium text-brand" data-test="track-notice">{{ $notice }}</p>
            @endif

            @if ($progress->nextInterview)
                @php($interview = $progress->nextInterview)
                <div class="mt-5 rounded-2xl border border-brand/20 p-5" data-test="track-interview">
                    <p class="flex items-center gap-2 font-bold text-night"><flux:icon.calendar-days variant="mini" class="text-brand" /> {{ __('Your interview') }}</p>
                    <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-2">
                        <div><dt class="text-zinc-500">{{ __('When') }}</dt><dd class="font-semibold">{{ $interview->scheduled_at->format('D j M Y, H:i') }}</dd></div>
                        <div><dt class="text-zinc-500">{{ __('Duration') }}</dt><dd class="font-semibold">{{ __(':min minutes', ['min' => $interview->duration_minutes]) }}</dd></div>
                        <div><dt class="text-zinc-500">{{ __('Format') }}</dt><dd class="font-semibold">{{ $interview->mode->label() }}</dd></div>
                        @if ($interview->location)
                            <div><dt class="text-zinc-500">{{ __('Location') }}</dt><dd class="font-semibold">{{ $interview->location }}</dd></div>
                        @endif
                        @if ($interview->meeting_url)
                            <div class="sm:col-span-2"><dt class="text-zinc-500">{{ __('Meeting link') }}</dt><dd><a href="{{ $interview->meeting_url }}" target="_blank" rel="noopener" class="font-semibold text-brand hover:underline">{{ $interview->meeting_url }}</a></dd></div>
                        @endif
                    </dl>
                </div>
            @endif

            @if ($progress->pendingOffer)
                @php($offer = $progress->pendingOffer)
                <div class="mt-5 rounded-2xl border-2 border-highlight/50 bg-highlight/5 p-5" data-test="track-offer">
                    <p class="flex items-center gap-2 font-bold text-night"><flux:icon.gift variant="mini" class="text-teal-600" /> {{ __('Your job offer') }}</p>
                    <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-2">
                        <div><dt class="text-zinc-500">{{ __('Position') }}</dt><dd class="font-semibold">{{ $offer->job_title }}</dd></div>
                        <div><dt class="text-zinc-500">{{ __('Salary') }}</dt><dd class="font-semibold">{{ $offer->salary_currency }} {{ number_format($offer->salary_cents / 100, 2) }} {{ __('per month') }}</dd></div>
                        @if ($offer->start_date)
                            <div><dt class="text-zinc-500">{{ __('Start date') }}</dt><dd class="font-semibold">{{ $offer->start_date->toFormattedDateString() }}</dd></div>
                        @endif
                        @if ($offer->expires_on)
                            <div><dt class="text-zinc-500">{{ __('Respond by') }}</dt><dd class="font-semibold">{{ $offer->expires_on->toFormattedDateString() }}</dd></div>
                        @endif
                    </dl>
                    <div class="mt-5 flex flex-wrap gap-3">
                        <button type="button" wire:click="respond(true)" wire:confirm="{{ __('Accept this job offer?') }}" class="rounded-xl bg-success px-5 py-2.5 text-sm font-bold text-white transition hover:opacity-90">{{ __('Accept offer') }}</button>
                        <button type="button" wire:click="respond(false)" wire:confirm="{{ __('Decline this job offer? This cannot be undone.') }}" class="rounded-xl border border-zinc-300 px-5 py-2.5 text-sm font-semibold text-zinc-700 transition hover:border-danger hover:text-danger">{{ __('Decline') }}</button>
                    </div>
                </div>
            @endif

            <button type="button" wire:click="startOver" class="mt-6 text-sm font-semibold text-brand hover:underline">{{ __('Track another application') }}</button>
        </div>
    @endif
</div>
