@php($contact = config('platform.contact'))

<x-layouts::marketing :title="__('Contact')" :description="__('Questions about HR, payroll or attendance software for your organization? Send us a message.')"
    :keywords="['contact HR software provider Kenya', 'HR software support Kenya', 'talk to HRM software sales Kenya']">
    <x-marketing.page-header
        :eyebrow="__('Contact')"
        :title="__('Let\'s talk about your team')"
        :subtitle="__('Questions, a demo request or help choosing a plan — send us a message and we will get back to you within one business day.')" />

    <section class="relative bg-canvas pb-24">
        <div class="mx-auto -mt-10 grid max-w-6xl gap-8 px-4 sm:px-6 lg:grid-cols-5 lg:px-8">
            <div class="space-y-4 lg:col-span-2">
                <div class="rounded-2xl bg-white p-6 shadow-xl shadow-brand/5">
                    <h2 class="text-lg font-bold text-night">{{ __('Get in touch') }}</h2>
                    <ul class="mt-5 space-y-4 text-sm">
                        @if ($contact['email'])
                            <li class="flex gap-3">
                                <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-brand/10 text-brand"><flux:icon.envelope variant="mini" /></span>
                                <span><span class="block text-zinc-500">{{ __('Email') }}</span><a href="mailto:{{ $contact['email'] }}" class="font-semibold text-night hover:text-brand">{{ $contact['email'] }}</a></span>
                            </li>
                        @endif
                        @if ($contact['phone'])
                            <li class="flex gap-3">
                                <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-highlight/15 text-cyan-700"><flux:icon.phone variant="mini" /></span>
                                <span><span class="block text-zinc-500">{{ __('Phone') }}</span><a href="tel:{{ preg_replace('/\s+/', '', $contact['phone']) }}" class="font-semibold text-night hover:text-brand">{{ $contact['phone'] }}</a></span>
                            </li>
                        @endif
                        @if ($contact['location'])
                            <li class="flex gap-3">
                                <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-success/10 text-success"><flux:icon.map-pin variant="mini" /></span>
                                <span><span class="block text-zinc-500">{{ __('Location') }}</span><span class="font-semibold text-night">{{ $contact['location'] }}</span></span>
                            </li>
                        @endif
                        <li class="flex gap-3">
                            <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-warning/15 text-amber-600"><flux:icon.clock variant="mini" /></span>
                            <span><span class="block text-zinc-500">{{ __('Hours') }}</span><span class="font-semibold text-night">{{ __('Mon – Fri, 8:00 – 17:00 EAT') }}</span></span>
                        </li>
                    </ul>
                </div>
                <a href="{{ route('quote') }}" class="group block rounded-2xl bg-linear-to-br from-brand to-night p-6 text-white shadow-xl shadow-brand/20">
                    <p class="font-bold">{{ __('Ready to get started?') }}</p>
                    <p class="mt-1 text-sm text-indigo-100">{{ __('Request a quote and receive your secure access link.') }}</p>
                    <span class="mt-4 inline-flex items-center gap-1.5 text-sm font-bold text-highlight">{{ __('Request a quote') }} <flux:icon.arrow-right variant="micro" class="transition group-hover:translate-x-0.5" /></span>
                </a>
            </div>

            <div class="rounded-2xl bg-white p-6 shadow-xl shadow-brand/5 sm:p-8 lg:col-span-3">
                <h2 class="text-lg font-bold text-night">{{ __('Send us a message') }}</h2>
                <livewire:marketing.lead-form kind="contact" />
            </div>
        </div>
    </section>
</x-layouts::marketing>
