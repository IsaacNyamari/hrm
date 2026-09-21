@php
    $modules = [
        ['users', __('People & org chart'), __('Employee records, documents, departments, branches and a live org chart — every profile in one place.'), 'text-brand bg-brand/10'],
        ['finger-print', __('Attendance & biometrics'), __('Web and mobile clock-in with geofencing, shifts, overtime and direct sync from ZKTeco biometric devices.'), 'text-cyan-700 bg-highlight/15'],
        ['calendar-days', __('Leave management'), __('Custom leave types, accruals, carry-forward, approval chains and a team leave calendar.'), 'text-success bg-success/10'],
        ['banknotes', __('Payroll'), __('Salary structures, allowances and deductions with PAYE, NSSF, SHIF and Housing Levy calculated for you.'), 'text-info bg-info/10'],
        ['trophy', __('Performance'), __('Review cycles, goals, KPIs and check-ins that keep growth conversations on track.'), 'text-amber-600 bg-warning/15'],
        ['user-plus', __('Recruitment'), __('Post vacancies to your own careers page, track applicants, schedule interviews and send offers.'), 'text-danger bg-danger/10'],
        ['receipt-percent', __('Expenses, loans & assets'), __('Claims with receipts, staff loans with repayments, and a register of company assets.'), 'text-brand bg-brand/10'],
        ['document-text', __('HR letters & documents'), __('Generate offer, confirmation and other letters from templates; get alerts before documents expire.'), 'text-cyan-700 bg-highlight/15'],
    ];
    $faqs = [
        [__('How do we get started?'), __('Request a quote with a few details about your organization. Once approved you receive a secure, single-use link by email to create your workspace — it takes about two minutes.')],
        [__('Does payroll handle Kenyan statutory deductions?'), __('Yes. PAYE, NSSF, SHIF and the Affordable Housing Levy are built into payroll, and payslips are generated for every employee.')],
        [__('Can we connect our biometric devices?'), __('Yes. ZKTeco devices that support the ADMS push protocol send attendance straight into the system, alongside web and mobile clock-ins.')],
        [__('Is our data kept separate from other organizations?'), __('Every organization has its own isolated workspace. Access is role-based, sensitive fields are encrypted, and every important change is recorded in an audit trail.')],
        [__('How do we pay?'), __('Subscriptions are billed monthly in KES and can be paid securely online by card, M-Pesa or bank through our payment provider.')],
    ];
@endphp

<x-layouts::marketing>
    {{-- Hero --}}
    <section class="relative overflow-hidden bg-night pt-32 pb-24 text-white sm:pt-40 lg:pb-32">
        <div class="pointer-events-none absolute inset-0">
            <div class="absolute -top-40 -left-32 size-[34rem] rounded-full bg-brand/60 blur-3xl"></div>
            <div class="absolute top-20 -right-40 size-[30rem] rounded-full bg-highlight/25 blur-3xl"></div>
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_1px_1px,rgba(255,255,255,0.07)_1px,transparent_0)] [background-size:28px_28px]"></div>
        </div>

        <div class="relative mx-auto grid max-w-7xl items-center gap-16 px-4 sm:px-6 lg:grid-cols-2 lg:px-8">
            <div>
                <span class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-3 py-1 text-xs font-medium text-highlight">
                    <span class="size-1.5 rounded-full bg-highlight"></span>
                    {{ __('Payroll with PAYE, NSSF, SHIF & Housing Levy built in') }}
                </span>
                <h1 class="mt-6 text-4xl font-extrabold leading-[1.1] tracking-tight sm:text-5xl lg:text-6xl">
                    {{ __('All of HR, in one') }}
                    <span class="bg-linear-to-r from-highlight to-cyan-200 bg-clip-text text-transparent">{{ __('calm, secure') }}</span>
                    {{ __('place.') }}
                </h1>
                <p class="mt-6 max-w-xl text-lg leading-relaxed text-zinc-300">
                    {{ __('Records, attendance, leave, payroll, performance and hiring — connected, automated and ready for your whole team from day one.') }}
                </p>
                <div class="mt-10 flex flex-wrap gap-3">
                    <a href="{{ route('quote') }}" class="inline-flex items-center gap-2 rounded-xl bg-highlight px-6 py-3 text-sm font-bold text-night shadow-lg shadow-highlight/25 transition hover:-translate-y-0.5 hover:bg-cyan-300">
                        {{ __('Request a quote') }} <flux:icon.arrow-right variant="mini" />
                    </a>
                    <a href="{{ route('features') }}" class="inline-flex items-center gap-2 rounded-xl border border-white/20 px-6 py-3 text-sm font-semibold text-white transition hover:bg-white/10">
                        {{ __('Explore features') }}
                    </a>
                </div>
                <dl class="mt-12 grid max-w-lg grid-cols-3 gap-6 border-t border-white/10 pt-8">
                    @foreach ([['12+', __('HR modules')], ['24/7', __('Self-service portal')], ['100%', __('Cloud-based')]] as [$figure, $caption])
                        <div>
                            <dt class="text-2xl font-extrabold text-white">{{ $figure }}</dt>
                            <dd class="mt-1 text-xs text-zinc-400">{{ $caption }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>

            {{-- Product preview --}}
            <div class="relative">
                <div class="absolute -inset-4 rounded-3xl bg-linear-to-tr from-brand/40 to-highlight/30 blur-2xl"></div>
                <div class="relative overflow-hidden rounded-2xl border border-white/10 bg-white text-ink shadow-2xl">
                    <div class="flex items-center gap-2 bg-night px-4 py-3">
                        <span class="size-2.5 rounded-full bg-danger"></span>
                        <span class="size-2.5 rounded-full bg-warning"></span>
                        <span class="size-2.5 rounded-full bg-success"></span>
                        <span class="ms-3 rounded-md bg-white/10 px-3 py-1 text-[11px] text-zinc-300">hrm.solforbs.com/dashboard</span>
                    </div>
                    <div class="grid grid-cols-[4.5rem_1fr]">
                        <div class="space-y-3 bg-linear-to-b from-white to-indigo-100 p-3">
                            @foreach (['home', 'users', 'clock', 'calendar-days', 'banknotes', 'chart-bar'] as $icon)
                                <span class="grid size-9 place-items-center rounded-lg {{ $loop->first ? 'bg-brand text-white shadow-md shadow-brand/30' : 'text-zinc-400' }}">
                                    <flux:icon :name="$icon" variant="mini" />
                                </span>
                            @endforeach
                        </div>
                        <div class="space-y-4 bg-canvas p-4">
                            <p class="text-sm font-bold text-night">{{ __('Hi, welcome back!') }}</p>
                            <div class="grid grid-cols-2 gap-3">
                                @foreach ([['bg-warning text-night', __('Employees'), '248'], ['bg-danger text-white', __('On leave'), '12'], ['bg-info text-white', __('Clocked in'), '231'], ['bg-success text-white', __('Payroll'), __('Ready')]] as [$tone, $label, $value])
                                    <div class="rounded-xl p-3 shadow-md {{ $tone }}">
                                        <p class="text-[10px] font-semibold opacity-90">{{ $label }}</p>
                                        <p class="mt-1 text-xl font-extrabold">{{ $value }}</p>
                                    </div>
                                @endforeach
                            </div>
                            <div class="rounded-xl bg-white p-3 shadow-sm">
                                <p class="text-[10px] font-semibold text-zinc-500">{{ __('Attendance this week') }}</p>
                                <div class="mt-2 flex h-20 items-end gap-2">
                                    @foreach ([62, 78, 70, 88, 94, 40, 30] as $h)
                                        <div class="flex-1 rounded-t bg-linear-to-t from-brand to-indigo-300" style="height: {{ $h }}%"></div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="absolute -bottom-6 -left-6 hidden items-center gap-3 rounded-xl bg-white p-3 pe-5 text-ink shadow-xl sm:flex">
                    <span class="grid size-10 place-items-center rounded-full bg-success/15 text-success"><flux:icon.check-badge variant="mini" /></span>
                    <span>
                        <span class="block text-xs font-bold text-night">{{ __('Leave approved') }}</span>
                        <span class="block text-[11px] text-zinc-500">{{ __('Notified by email & in-app') }}</span>
                    </span>
                </div>
            </div>
        </div>
    </section>

    {{-- Trust strip --}}
    <section class="border-b border-zinc-100 bg-white">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-center gap-x-10 gap-y-4 px-4 py-8 text-sm font-semibold text-zinc-500 sm:px-6 lg:px-8">
            @foreach ([['shield-check', __('Role-based access')], ['finger-print', __('ZKTeco biometric sync')], ['device-phone-mobile', __('M-Pesa & card payments')], ['document-arrow-down', __('Excel, CSV & PDF exports')], ['code-bracket', __('REST API & webhooks')]] as [$icon, $label])
                <span class="flex items-center gap-2"><flux:icon :name="$icon" variant="mini" class="text-brand" /> {{ $label }}</span>
            @endforeach
        </div>
    </section>

    {{-- Modules --}}
    <section class="bg-white py-24">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-2xl text-center">
                <p class="text-sm font-bold uppercase tracking-widest text-brand">{{ __('Everything in one platform') }}</p>
                <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-night sm:text-4xl">{{ __('Replace the spreadsheets, paperwork and five different tools') }}</h2>
                <p class="mt-4 text-lg text-zinc-500">{{ __('Each module works on its own and shares data with the rest — so an approved leave shows on the calendar, attendance and payroll automatically.') }}</p>
            </div>

            <div class="mt-16 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($modules as [$icon, $title, $text, $tone])
                    <div class="group rounded-2xl border border-zinc-100 bg-white p-6 shadow-sm transition duration-200 hover:-translate-y-1 hover:border-brand/20 hover:shadow-xl hover:shadow-brand/5">
                        <span class="grid size-12 place-items-center rounded-xl {{ $tone }} transition group-hover:scale-110">
                            <flux:icon :name="$icon" />
                        </span>
                        <h3 class="mt-5 text-base font-bold text-night">{{ $title }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-zinc-500">{{ $text }}</p>
                    </div>
                @endforeach
            </div>

            <div class="mt-12 text-center">
                <a href="{{ route('features') }}" class="inline-flex items-center gap-1.5 text-sm font-bold text-brand hover:underline">{{ __('See every feature') }} <flux:icon.arrow-right variant="micro" /></a>
            </div>
        </div>
    </section>

    {{-- Highlights --}}
    <section class="bg-canvas py-24">
        <div class="mx-auto max-w-7xl space-y-24 px-4 sm:px-6 lg:px-8">
            <div class="grid items-center gap-12 lg:grid-cols-2">
                <div>
                    <span class="grid size-12 place-items-center rounded-xl bg-info/10 text-info"><flux:icon.banknotes /></span>
                    <h2 class="mt-5 text-3xl font-extrabold tracking-tight text-night">{{ __('Payroll you can trust, every month') }}</h2>
                    <p class="mt-4 text-lg text-zinc-500">{{ __('Set up salary components once. Each run pulls attendance, leave, loans and expenses, applies statutory deductions and produces payslips your team can open from their own portal.') }}</p>
                    <ul class="mt-6 space-y-3 text-sm">
                        @foreach ([__('PAYE, NSSF, SHIF and Housing Levy calculated automatically'), __('Approval step before a run is finalised'), __('Payslips delivered to every employee\'s self-service portal'), __('Payroll reports exported to Excel, CSV or print')] as $point)
                            <li class="flex gap-3"><flux:icon.check-circle variant="mini" class="shrink-0 text-success" /> {{ $point }}</li>
                        @endforeach
                    </ul>
                </div>
                <div class="rounded-2xl bg-white p-6 shadow-xl shadow-brand/5">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-bold text-night">{{ __('Payslip preview') }}</p>
                        <span class="rounded-full bg-success/10 px-2.5 py-1 text-[11px] font-bold text-success">{{ __('Approved') }}</span>
                    </div>
                    <dl class="mt-5 divide-y divide-zinc-100 text-sm">
                        @foreach ([[__('Basic salary'), '85,000.00', ''], [__('House allowance'), '15,000.00', ''], [__('PAYE'), '− 22,137.50', 'text-danger'], [__('NSSF'), '− 2,160.00', 'text-danger'], [__('SHIF'), '− 2,750.00', 'text-danger'], [__('Housing Levy'), '− 1,500.00', 'text-danger']] as [$label, $amount, $tone])
                            <div class="flex justify-between py-2.5"><dt class="text-zinc-500">{{ $label }}</dt><dd class="font-semibold {{ $tone }}">KES {{ $amount }}</dd></div>
                        @endforeach
                        <div class="flex justify-between py-3 text-base"><dt class="font-bold text-night">{{ __('Net pay') }}</dt><dd class="font-extrabold text-brand">KES 71,452.50</dd></div>
                    </dl>
                    <p class="mt-3 text-[11px] text-zinc-400">{{ __('Illustrative figures only.') }}</p>
                </div>
            </div>

            <div class="grid items-center gap-12 lg:grid-cols-2">
                <div class="order-2 rounded-2xl bg-white p-6 shadow-xl shadow-brand/5 lg:order-1">
                    <p class="text-sm font-bold text-night">{{ __('Today at a glance') }}</p>
                    <div class="mt-5 space-y-3">
                        @foreach ([['Grace W.', '07:52', __('On time'), 'bg-success/10 text-success'], ['Brian O.', '08:14', __('Late'), 'bg-warning/20 text-amber-700'], ['Aisha M.', '—', __('On leave'), 'bg-info/10 text-info'], ['Kevin K.', '07:48', __('Biometric'), 'bg-brand/10 text-brand']] as [$person, $time, $state, $tone])
                            <div class="flex items-center gap-3 rounded-xl border border-zinc-100 p-3">
                                <span class="grid size-9 place-items-center rounded-full bg-linear-to-br from-brand to-highlight text-xs font-bold text-white">{{ collect(explode(' ', $person))->map(fn ($p) => $p[0])->implode('') }}</span>
                                <span class="flex-1 text-sm font-semibold text-night">{{ $person }}</span>
                                <span class="text-sm text-zinc-500">{{ $time }}</span>
                                <span class="rounded-full px-2.5 py-1 text-[11px] font-bold {{ $tone }}">{{ $state }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="order-1 lg:order-2">
                    <span class="grid size-12 place-items-center rounded-xl bg-highlight/15 text-cyan-700"><flux:icon.finger-print /></span>
                    <h2 class="mt-5 text-3xl font-extrabold tracking-tight text-night">{{ __('Know who is in — without chasing anyone') }}</h2>
                    <p class="mt-4 text-lg text-zinc-500">{{ __('Staff clock in from the web, their phone within a geofence, or your biometric devices. Late arrivals, absences and overtime are flagged automatically and flow into payroll.') }}</p>
                    <ul class="mt-6 space-y-3 text-sm">
                        @foreach ([__('ZKTeco devices push punches in real time'), __('Shifts, breaks and automatic clock-out'), __('Geofenced clock-in for field and branch teams')] as $point)
                            <li class="flex gap-3"><flux:icon.check-circle variant="mini" class="shrink-0 text-success" /> {{ $point }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </section>

    {{-- How it works --}}
    <section class="bg-white py-24">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-2xl text-center">
                <p class="text-sm font-bold uppercase tracking-widest text-brand">{{ __('Getting started') }}</p>
                <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-night sm:text-4xl">{{ __('Up and running in four steps') }}</h2>
            </div>
            <ol class="mt-16 grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                @foreach ([
                    ['chat-bubble-left-right', __('Request a quote'), __('Tell us about your organization and team size.')],
                    ['envelope-open', __('Get your access link'), __('We email a secure, single-use link to create your workspace.')],
                    ['cog-6-tooth', __('Set up your workspace'), __('Add departments, leave types and salary components — sensible defaults are ready.')],
                    ['user-group', __('Invite your team'), __('Import employees and invite managers. Everyone gets their own self-service portal.')],
                ] as [$icon, $title, $text])
                    <li class="relative rounded-2xl border border-zinc-100 p-6">
                        <span class="absolute -top-3 end-5 rounded-full bg-brand px-2.5 py-0.5 text-xs font-bold text-white">{{ $loop->iteration }}</span>
                        <span class="grid size-11 place-items-center rounded-xl bg-brand/10 text-brand"><flux:icon :name="$icon" variant="mini" /></span>
                        <h3 class="mt-4 font-bold text-night">{{ $title }}</h3>
                        <p class="mt-2 text-sm text-zinc-500">{{ $text }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- FAQ --}}
    <section class="bg-canvas py-24">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <h2 class="text-center text-3xl font-extrabold tracking-tight text-night">{{ __('Frequently asked questions') }}</h2>
            <div class="mt-12 space-y-3" x-data="{ open: 0 }">
                @foreach ($faqs as [$question, $answer])
                    <div class="rounded-xl bg-white shadow-sm">
                        <button type="button" @click="open = open === {{ $loop->index }} ? null : {{ $loop->index }}" class="flex w-full items-center justify-between gap-4 px-6 py-4 text-left font-semibold text-night">
                            {{ $question }}
                            <flux:icon.chevron-down variant="mini" class="shrink-0 text-brand transition" ::class="open === {{ $loop->index }} && 'rotate-180'" />
                        </button>
                        <div x-show="open === {{ $loop->index }}" x-collapse x-cloak class="px-6 pb-5 text-sm leading-relaxed text-zinc-500">{{ $answer }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <x-marketing.cta />
</x-layouts::marketing>
