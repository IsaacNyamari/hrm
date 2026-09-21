<x-layouts::marketing :title="__('Track application')" :description="__('Check the progress of your job application with your tracking code.')">
    <x-marketing.page-header
        :eyebrow="__('Track application')"
        :title="__('Where is my application?')"
        :subtitle="__('Enter the tracking code you received when you applied, with the same email address, to see where your application stands.')" />

    <section class="relative bg-canvas pb-24">
        <div class="relative mx-auto -mt-10 max-w-2xl px-4 sm:px-6">
            <div class="rounded-2xl bg-white p-6 shadow-xl shadow-brand/5 sm:p-8">
                <livewire:marketing.application-tracker />
            </div>
            <p class="mt-6 text-center text-sm text-zinc-500">
                {{ __('Looking for a new role?') }} <a href="{{ route('jobs.index') }}" class="font-semibold text-brand hover:underline">{{ __('Browse open jobs') }}</a>
            </p>
        </div>
    </section>
</x-layouts::marketing>
