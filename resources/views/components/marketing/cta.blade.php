<section class="bg-white py-20">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="relative overflow-hidden rounded-3xl bg-linear-to-br from-brand via-indigo-700 to-night px-6 py-16 text-center text-white shadow-2xl shadow-brand/20 sm:px-16">
            <div class="pointer-events-none absolute -top-24 -right-24 size-72 rounded-full bg-highlight/30 blur-3xl"></div>
            <div class="pointer-events-none absolute -bottom-24 -left-24 size-72 rounded-full bg-highlight/20 blur-3xl"></div>
            <h2 class="relative text-3xl font-extrabold tracking-tight sm:text-4xl">{{ __('Ready to bring your HR into one place?') }}</h2>
            <p class="relative mx-auto mt-4 max-w-xl text-lg text-indigo-100">{{ __('Tell us about your team and we will send you a tailored quote and a secure link to set up your workspace.') }}</p>
            <div class="relative mt-8 flex flex-wrap justify-center gap-3">
                <a href="{{ route('quote') }}" class="inline-flex items-center gap-2 rounded-xl bg-highlight px-6 py-3 text-sm font-bold text-night shadow-lg transition hover:-translate-y-0.5 hover:bg-cyan-300">
                    {{ __('Request a quote') }} <flux:icon.arrow-right variant="mini" />
                </a>
                <a href="{{ route('contact') }}" class="inline-flex items-center rounded-xl border border-white/25 px-6 py-3 text-sm font-semibold transition hover:bg-white/10">{{ __('Talk to us') }}</a>
            </div>
        </div>
    </div>
</section>
