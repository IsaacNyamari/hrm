@php
    $logo = $job->tenant->branding?->logoUrl();
    $salary = collect([$job->min_salary_cents, $job->max_salary_cents])->filter()->map(fn ($c) => number_format($c / 100))->implode(' – ');
@endphp

<x-layouts::marketing :title="$job->title.' — '.$job->tenant->name" :description="\Illuminate\Support\Str::limit(strip_tags((string) $job->description), 155)">
    <x-marketing.page-header :eyebrow="$job->tenant->name" :title="$job->title">
        <div class="mt-6 flex flex-wrap justify-center gap-2 text-sm">
            @if ($job->department)
                <span class="rounded-full bg-white/10 px-3 py-1">{{ $job->department->name }}</span>
            @endif
            @if ($job->location)
                <span class="inline-flex items-center gap-1 rounded-full bg-white/10 px-3 py-1"><flux:icon.map-pin variant="micro" /> {{ $job->location }}</span>
            @endif
            @if ($job->is_remote)
                <span class="rounded-full bg-highlight/20 px-3 py-1 text-highlight">{{ __('Remote') }}</span>
            @endif
        </div>
        <a href="#apply" class="mt-8 inline-flex items-center gap-2 rounded-xl bg-highlight px-6 py-3 text-sm font-bold text-night transition hover:bg-cyan-400">
            {{ __('Apply now') }} <flux:icon.arrow-down variant="mini" />
        </a>
    </x-marketing.page-header>

    <section class="relative bg-canvas pb-24">
        <div class="relative mx-auto -mt-10 grid max-w-6xl gap-8 px-4 sm:px-6 lg:grid-cols-3 lg:px-8">
            <div class="space-y-8 lg:col-span-2">
                <article class="rounded-2xl bg-white p-6 shadow-xl shadow-brand/5 sm:p-8">
                    <h2 class="text-lg font-bold text-night">{{ __('About the role') }}</h2>
                    <div class="mt-3 text-sm leading-relaxed whitespace-pre-line text-zinc-600">{{ $job->description ?: __('The hiring team will share more details during the process.') }}</div>

                    @if ($job->requirements)
                        <h2 class="mt-8 text-lg font-bold text-night">{{ __('What we are looking for') }}</h2>
                        <div class="mt-3 text-sm leading-relaxed whitespace-pre-line text-zinc-600">{{ $job->requirements }}</div>
                    @endif
                </article>

                <div class="rounded-2xl bg-white p-6 shadow-xl shadow-brand/5 sm:p-8">
                    <h2 class="text-lg font-bold text-night">{{ __('Apply for this job') }}</h2>
                    <p class="mt-1 mb-6 text-sm text-zinc-500">{{ __('It takes about 3 minutes. Fields marked * are required.') }}</p>
                    <livewire:marketing.job-application-form :organization="$job->tenant->slug" :vacancy="$job->slug" />
                </div>
            </div>

            <aside class="space-y-4">
                <div class="rounded-2xl bg-white p-6 shadow-xl shadow-brand/5">
                    <div class="flex items-center gap-3">
                        @if ($logo)
                            <img src="{{ $logo }}" alt="" class="size-12 rounded-xl object-contain ring-1 ring-zinc-100">
                        @else
                            <span class="grid size-12 place-items-center rounded-xl bg-linear-to-br from-brand to-highlight text-sm font-bold text-white">{{ \Illuminate\Support\Str::of($job->tenant->name)->substr(0, 2)->upper() }}</span>
                        @endif
                        <div>
                            <p class="font-bold text-night">{{ $job->tenant->name }}</p>
                            <a href="{{ route('jobs.index', ['org' => $job->tenant->slug]) }}" class="text-xs font-semibold text-brand hover:underline">{{ __('More jobs here') }}</a>
                        </div>
                    </div>
                    <dl class="mt-6 space-y-3 text-sm">
                        @if ($salary !== '')
                            <div class="flex justify-between gap-4"><dt class="text-zinc-500">{{ __('Salary') }}</dt><dd class="text-right font-semibold text-night">{{ $job->salary_currency }} {{ $salary }}</dd></div>
                        @endif
                        @if ($job->headcount > 1)
                            <div class="flex justify-between gap-4"><dt class="text-zinc-500">{{ __('Openings') }}</dt><dd class="font-semibold text-night">{{ $job->remainingHeadcount() }}</dd></div>
                        @endif
                        @if ($job->published_at)
                            <div class="flex justify-between gap-4"><dt class="text-zinc-500">{{ __('Posted') }}</dt><dd class="font-semibold text-night">{{ $job->published_at->toFormattedDateString() }}</dd></div>
                        @endif
                        @if ($job->closes_on)
                            <div class="flex justify-between gap-4"><dt class="text-zinc-500">{{ __('Apply by') }}</dt><dd class="font-semibold text-night">{{ $job->closes_on->toFormattedDateString() }}</dd></div>
                        @endif
                    </dl>
                </div>

                <div class="rounded-2xl bg-night p-6 text-white">
                    <p class="font-bold">{{ __('How it works') }}</p>
                    <ol class="mt-4 space-y-3 text-sm text-zinc-300">
                        <li class="flex gap-3"><span class="grid size-6 shrink-0 place-items-center rounded-full bg-highlight text-xs font-bold text-night">1</span> {{ __('Apply online and get a tracking code.') }}</li>
                        <li class="flex gap-3"><span class="grid size-6 shrink-0 place-items-center rounded-full bg-highlight text-xs font-bold text-night">2</span> {{ __('Follow your progress on the Track application page.') }}</li>
                        <li class="flex gap-3"><span class="grid size-6 shrink-0 place-items-center rounded-full bg-highlight text-xs font-bold text-night">3</span> {{ __('Get interview invites and your decision by email.') }}</li>
                        <li class="flex gap-3"><span class="grid size-6 shrink-0 place-items-center rounded-full bg-highlight text-xs font-bold text-night">4</span> {{ __('Hired? We email a link to your staff account.') }}</li>
                    </ol>
                    <a href="{{ route('applications.track') }}" class="mt-5 inline-flex text-sm font-semibold text-highlight hover:underline">{{ __('Track an application →') }}</a>
                </div>
            </aside>
        </div>
    </section>
</x-layouts::marketing>
