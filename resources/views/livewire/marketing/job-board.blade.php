@php($field = 'block w-full rounded-xl border border-zinc-200 bg-white px-4 py-2.5 text-sm text-night shadow-sm transition placeholder:text-zinc-400 focus:border-brand focus:outline-hidden focus:ring-4 focus:ring-brand/10')

<div>
    <div class="grid gap-3 rounded-2xl bg-white p-4 shadow-xl shadow-brand/5 sm:grid-cols-[1fr_14rem_auto]">
        <div class="relative">
            <flux:icon.magnifying-glass variant="mini" class="pointer-events-none absolute top-3 left-3.5 text-zinc-400" />
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Job title, keyword or location') }}" class="{{ $field }} pl-10" aria-label="{{ __('Search jobs') }}">
        </div>
        <select wire:model.live="organization" class="{{ $field }}" aria-label="{{ __('Organization') }}">
            <option value="">{{ __('All organizations') }}</option>
            @foreach ($organizations as $org)
                <option value="{{ $org->slug }}">{{ $org->name }}</option>
            @endforeach
        </select>
        <label class="flex items-center gap-2 rounded-xl border border-zinc-200 px-4 py-2.5 text-sm font-medium text-zinc-700">
            <input type="checkbox" wire:model.live="remote" class="accent-brand"> {{ __('Remote only') }}
        </label>
    </div>

    <p class="mt-6 text-sm text-zinc-500">{{ trans_choice(':count open position|:count open positions', $jobs->count()) }}</p>

    <div class="mt-4 grid gap-4 md:grid-cols-2" data-test="job-list">
        @forelse ($jobs as $job)
            @php($logo = $job->tenant->branding?->logoUrl())
            <a href="{{ route('jobs.show', [$job->tenant->slug, $job->slug]) }}" wire:key="job-{{ $job->uuid }}"
                class="group flex flex-col rounded-2xl border border-zinc-200 bg-white p-6 transition hover:-translate-y-0.5 hover:border-brand/40 hover:shadow-xl hover:shadow-brand/10">
                <div class="flex items-start gap-4">
                    @if ($logo)
                        <img src="{{ $logo }}" alt="" class="size-12 shrink-0 rounded-xl object-contain ring-1 ring-zinc-100">
                    @else
                        <span class="grid size-12 shrink-0 place-items-center rounded-xl bg-linear-to-br from-brand to-highlight text-sm font-bold text-white">{{ \Illuminate\Support\Str::of($job->tenant->name)->substr(0, 2)->upper() }}</span>
                    @endif
                    <div class="min-w-0">
                        <h3 class="text-lg font-bold text-night group-hover:text-brand">{{ $job->title }}</h3>
                        <p class="text-sm text-zinc-500">{{ $job->tenant->name }}{{ $job->department ? ' · '.$job->department->name : '' }}</p>
                    </div>
                </div>
                <div class="mt-4 flex flex-wrap gap-2 text-xs font-medium">
                    @if ($job->location)
                        <span class="inline-flex items-center gap-1 rounded-full bg-zinc-100 px-2.5 py-1 text-zinc-600"><flux:icon.map-pin variant="micro" /> {{ $job->location }}</span>
                    @endif
                    @if ($job->is_remote)
                        <span class="rounded-full bg-highlight/15 px-2.5 py-1 text-teal-700">{{ __('Remote') }}</span>
                    @endif
                    @if ($job->min_salary_cents || $job->max_salary_cents)
                        <span class="rounded-full bg-success/10 px-2.5 py-1 text-success">
                            {{ $job->salary_currency }}
                            {{ collect([$job->min_salary_cents, $job->max_salary_cents])->filter()->map(fn ($c) => number_format($c / 100))->implode(' – ') }}
                        </span>
                    @endif
                </div>
                <div class="mt-auto flex items-center justify-between pt-5 text-xs text-zinc-500">
                    <span>{{ $job->published_at ? __('Posted :ago', ['ago' => $job->published_at->diffForHumans()]) : '' }}</span>
                    <span>{{ $job->closes_on ? __('Closes :date', ['date' => $job->closes_on->toFormattedDateString()]) : '' }}</span>
                </div>
            </a>
        @empty
            <div class="rounded-2xl border border-dashed border-zinc-300 bg-white p-12 text-center md:col-span-2">
                <flux:icon.briefcase class="mx-auto size-10 text-zinc-300" />
                <p class="mt-3 font-semibold text-night">{{ __('No open positions match your search') }}</p>
                @if ($search !== '' || $organization !== '' || $remote)
                    <button type="button" wire:click="clearFilters" class="mt-3 text-sm font-semibold text-brand hover:underline">{{ __('Clear filters') }}</button>
                @else
                    <p class="mt-1 text-sm text-zinc-500">{{ __('New jobs are posted regularly — check back soon.') }}</p>
                @endif
            </div>
        @endforelse
    </div>
</div>
