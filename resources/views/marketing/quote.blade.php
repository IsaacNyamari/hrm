<x-layouts::marketing :title="__('Request a quote')" :description="__('Tell us about your organization and receive a tailored quote and secure access to set up your HR workspace.')">
    <x-marketing.page-header
        :eyebrow="__('Request a quote')"
        :title="__('Get access for your organization')"
        :subtitle="__('Sign-up is by invitation while we onboard organizations personally. Tell us about your team and we will send a quote and a secure link to create your workspace.')" />

    <section class="relative bg-canvas pb-24">
        <div class="mx-auto -mt-10 grid max-w-6xl gap-8 px-4 sm:px-6 lg:grid-cols-5 lg:px-8">
            <div class="rounded-2xl bg-white p-6 shadow-xl shadow-brand/5 sm:p-8 lg:col-span-3">
                <h2 class="text-lg font-bold text-night">{{ __('Your details') }}</h2>
                <livewire:marketing.lead-form kind="quote" :plan="request()->query('plan')" />
            </div>

            <div class="space-y-4 lg:col-span-2">
                <div class="rounded-2xl bg-white p-6 shadow-xl shadow-brand/5">
                    <h2 class="text-lg font-bold text-night">{{ __('What happens next') }}</h2>
                    <ol class="mt-5 space-y-5">
                        @foreach ([
                            [__('We review your request'), __('Usually within one business day.')],
                            [__('You receive a quote & access link'), __('A secure link is emailed to you. It works once — it expires as soon as it is opened.')],
                            [__('Create your workspace'), __('Name your organization, set your password and your free trial starts immediately.')],
                        ] as [$title, $text])
                            <li class="flex gap-4">
                                <span class="grid size-8 shrink-0 place-items-center rounded-full bg-brand text-sm font-bold text-white">{{ $loop->iteration }}</span>
                                <span>
                                    <span class="block font-semibold text-night">{{ $title }}</span>
                                    <span class="block text-sm text-zinc-500">{{ $text }}</span>
                                </span>
                            </li>
                        @endforeach
                    </ol>
                </div>
                <div class="rounded-2xl border border-highlight/40 bg-highlight/10 p-6 text-sm text-night">
                    <p class="flex items-center gap-2 font-bold"><flux:icon.lock-closed variant="mini" class="text-cyan-700" /> {{ __('Your information is safe') }}</p>
                    <p class="mt-2 text-zinc-600">{{ __('We only use these details to prepare your quote and set up your account.') }}</p>
                </div>
            </div>
        </div>
    </section>
</x-layouts::marketing>
