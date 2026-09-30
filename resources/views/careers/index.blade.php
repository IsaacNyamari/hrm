<x-careers.layout :title="__('Careers at :company', ['company' => $company])" :company="$company" :description="__('Open positions at :company', ['company' => $company])">
    <h1>{{ __('Open positions') }}</h1>

    @forelse ($vacancies as $vacancy)
        <div class="job">
            <h2 style="margin:0"><a href="{{ route('careers.show', $vacancy->slug) }}">{{ $vacancy->title }}</a></h2>
            <p class="muted" style="margin:.25rem 0 0">
                {{ collect([$vacancy->department?->name, $vacancy->location, $vacancy->is_remote ? __('Remote') : null])->filter()->join(' · ') }}
            </p>
        </div>
    @empty
        <p>{{ __('There are no open positions right now. Please check back soon.') }}</p>
    @endforelse
</x-careers.layout>
