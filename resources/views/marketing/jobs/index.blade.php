<x-layouts::marketing :title="__('Jobs in Kenya')" :description="__('Browse open positions at organizations hiring through our platform and apply online in minutes.')"
    :keywords="['jobs in Kenya', 'job vacancies Kenya', 'jobs in Nairobi', 'latest jobs Kenya', 'apply for jobs online Kenya', 'job board Kenya']">
    <x-marketing.page-header
        :eyebrow="__('Careers')"
        :title="__('Find your next job')"
        :subtitle="__('Open positions at organizations across the platform. Apply online in a few minutes and track your application with a code — no account needed.')">
        <a href="{{ route('applications.track') }}" class="mt-8 inline-flex items-center gap-2 rounded-xl border border-white/20 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-white/10">
            <flux:icon.magnifying-glass variant="mini" /> {{ __('Already applied? Track your application') }}
        </a>
    </x-marketing.page-header>

    <section class="relative bg-canvas pb-24">
        <div class="relative mx-auto -mt-10 max-w-6xl px-4 sm:px-6 lg:px-8">
            <livewire:marketing.job-board />
        </div>
    </section>
</x-layouts::marketing>
