<x-careers.layout :title="$vacancy->title.' — '.$company" :company="$company" :description="\Illuminate\Support\Str::limit(strip_tags((string) $vacancy->description), 155)">
    @push('structured-data')
        <script type="application/ld+json">{!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'JobPosting',
            'title' => $vacancy->title,
            'description' => nl2br(e((string) $vacancy->description)),
            'datePosted' => $vacancy->published_at?->toDateString(),
            'validThrough' => $vacancy->closes_on?->toDateString(),
            'hiringOrganization' => ['@type' => 'Organization', 'name' => $company],
            'jobLocationType' => $vacancy->is_remote ? 'TELECOMMUTE' : null,
            'jobLocation' => $vacancy->location ? ['@type' => 'Place', 'address' => $vacancy->location] : null,
        ], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
    @endpush

    <h1>{{ $vacancy->title }}</h1>
    <p class="muted">{{ collect([$vacancy->department?->name, $vacancy->location, $vacancy->is_remote ? __('Remote') : null])->filter()->join(' · ') }}</p>

    <div style="white-space: pre-line">{{ $vacancy->description }}</div>

    @if ($vacancy->requirements)
        <h2>{{ __('Requirements') }}</h2>
        <div style="white-space: pre-line">{{ $vacancy->requirements }}</div>
    @endif

    <h2>{{ __('Apply') }}</h2>
    <form id="apply" method="post" action="{{ route('careers.apply', $vacancy->slug) }}" enctype="multipart/form-data">
        @csrf
        <label for="first_name">{{ __('First name') }}</label><input id="first_name" name="first_name" required maxlength="100">
        <label for="last_name">{{ __('Last name') }}</label><input id="last_name" name="last_name" required maxlength="100">
        <label for="email">{{ __('Email') }}</label><input id="email" name="email" type="email" required maxlength="150">
        <label for="phone">{{ __('Phone') }}</label><input id="phone" name="phone" maxlength="40">
        <label for="cover_letter">{{ __('Cover letter') }}</label><textarea id="cover_letter" name="cover_letter" rows="5" maxlength="20000"></textarea>
        <label for="resume">{{ __('CV (PDF or Word)') }}</label><input id="resume" name="resume" type="file" accept=".pdf,.doc,.docx">
        <button type="submit">{{ __('Submit application') }}</button>
        <div id="apply-result" class="notice" hidden role="status"></div>
    </form>

    <script>
        document.getElementById('apply').addEventListener('submit', async (event) => {
            event.preventDefault();
            const form = event.target, box = document.getElementById('apply-result');
            const response = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { 'Accept': 'application/json' } });
            const body = await response.json().catch(() => ({}));
            box.hidden = false;
            box.textContent = response.ok ? body.message : (body.message || @json(__('Something went wrong. Please check your details and try again.')));
            if (response.ok) form.reset();
        });
    </script>
</x-careers.layout>
