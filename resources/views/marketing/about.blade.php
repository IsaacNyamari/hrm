<x-layouts::marketing :title="__('About')" :description="__('We build HR software that gives growing organizations the same clarity and control as the largest enterprises — without the complexity.')"
    :keywords="['HR software company Kenya', 'HRM software provider Kenya', 'Kenyan HR technology company', 'about our HR software']">
    <x-marketing.page-header
        :eyebrow="__('About us')"
        :title="__('HR software that respects people\'s time')"
        :subtitle="__('We believe great organizations are built on fair pay, clear expectations and trust. Our job is to make the admin behind that effortless.')" />

    <section class="bg-white py-24">
        <div class="mx-auto grid max-w-7xl items-center gap-16 px-4 sm:px-6 lg:grid-cols-2 lg:px-8">
            <div>
                <p class="text-sm font-bold uppercase tracking-widest text-brand">{{ __('Our story') }}</p>
                <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-night">{{ __('Built for how organizations in Kenya actually work') }}</h2>
                <div class="mt-6 space-y-4 text-lg leading-relaxed text-zinc-500">
                    <p>{{ __('Too many HR teams still run on spreadsheets, paper leave forms and payroll done late at night. International tools rarely understand local statutory rules, M-Pesa, or the biometric devices already on the wall.') }}</p>
                    <p>{{ __('So we built one platform that does: people records, attendance from any device, leave, payroll with PAYE, NSSF, SHIF and the Housing Levy, performance, recruitment and more — secure, connected and simple enough for everyone on the team to use.') }}</p>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                @foreach ([
                    ['bg-brand text-white', 'bolt', __('Fast to adopt'), __('Sensible defaults mean you are productive on day one.')],
                    ['bg-highlight text-night', 'shield-check', __('Secure by design'), __('Isolated workspaces, roles, encryption and audit logs.')],
                    ['bg-warning text-night', 'map-pin', __('Locally relevant'), __('Kenyan statutory deductions, KES billing and M-Pesa.')],
                    ['bg-success text-white', 'heart', __('People first'), __('Self-service for every employee, not just HR.')],
                ] as [$tone, $icon, $title, $text])
                    <div class="rounded-2xl p-6 shadow-lg {{ $tone }} {{ $loop->even ? 'translate-y-6' : '' }}">
                        <flux:icon :name="$icon" />
                        <h3 class="mt-4 font-bold">{{ $title }}</h3>
                        <p class="mt-2 text-sm opacity-90">{{ $text }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="bg-canvas py-24">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-2xl text-center">
                <p class="text-sm font-bold uppercase tracking-widest text-brand">{{ __('What we value') }}</p>
                <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-night">{{ __('Principles behind every feature') }}</h2>
            </div>
            <div class="mt-14 grid gap-6 md:grid-cols-3">
                @foreach ([
                    ['1', __('Accuracy over everything'), __('Pay and leave affect people\'s lives. Calculations are tested, approvals are explicit and every change is traceable.')],
                    ['2', __('Privacy is not optional'), __('People data is sensitive. Each organization is isolated, access follows roles, and sensitive fields are encrypted.')],
                    ['3', __('Simple beats clever'), __('If a manager cannot approve leave from their phone in ten seconds, we have more work to do.')],
                ] as [$number, $title, $text])
                    <div class="rounded-2xl bg-white p-8 shadow-sm">
                        <span class="text-5xl font-extrabold text-brand/15">0{{ $number }}</span>
                        <h3 class="mt-2 text-lg font-bold text-night">{{ $title }}</h3>
                        <p class="mt-3 text-sm leading-relaxed text-zinc-500">{{ $text }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <x-marketing.cta />
</x-layouts::marketing>
