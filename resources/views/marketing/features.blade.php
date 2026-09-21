@php
    $groups = [
        [
            'icon' => 'users', 'tone' => 'text-brand bg-brand/10',
            'title' => __('People & organization'),
            'intro' => __('A single source of truth for every employee and how your organization fits together.'),
            'items' => [
                __('Complete employee profiles with bank, statutory and emergency-contact details'),
                __('Branches, departments, designations and employment types'),
                __('Interactive org chart and department tree'),
                __('Bulk employee import from spreadsheets with a preview step'),
                __('Document storage with expiry reminders'),
                __('Team invitations with role-based access'),
            ],
        ],
        [
            'icon' => 'finger-print', 'tone' => 'text-cyan-700 bg-highlight/15',
            'title' => __('Time & attendance'),
            'intro' => __('Accurate time data from wherever your people work.'),
            'items' => [
                __('Web clock-in with breaks and automatic clock-out'),
                __('Geofenced clock-in for branch and field staff'),
                __('ZKTeco biometric devices via the ADMS push protocol'),
                __('Shifts and shift assignments'),
                __('Late, absent and half-day detection'),
                __('Attendance records and summaries by day or employee'),
            ],
        ],
        [
            'icon' => 'calendar-days', 'tone' => 'text-success bg-success/10',
            'title' => __('Leave'),
            'intro' => __('Fair, transparent time off without the email chains.'),
            'items' => [
                __('Custom leave types with monthly accrual'),
                __('Carry-forward rules at year end'),
                __('Manager approval workflow with notifications'),
                __('Team leave calendar and public holidays'),
                __('Live balances on every employee\'s portal'),
            ],
        ],
        [
            'icon' => 'banknotes', 'tone' => 'text-info bg-info/10',
            'title' => __('Payroll & finance'),
            'intro' => __('Monthly payroll with Kenyan statutory deductions handled for you.'),
            'items' => [
                __('Salary structures, allowances and deductions'),
                __('PAYE, NSSF, SHIF and Affordable Housing Levy'),
                __('Payroll periods and runs with an approval step'),
                __('Payslips for every employee'),
                __('Expense claims with receipts and approvals'),
                __('Staff loans with scheduled repayments'),
                __('Company asset register and assignments'),
            ],
        ],
        [
            'icon' => 'trophy', 'tone' => 'text-amber-600 bg-warning/15',
            'title' => __('Performance & growth'),
            'intro' => __('Keep goals visible and reviews on schedule.'),
            'items' => [
                __('Performance cycles and reviews'),
                __('Goals with progress check-ins'),
                __('KPIs and ratings'),
                __('Tasks assigned across the team'),
            ],
        ],
        [
            'icon' => 'user-plus', 'tone' => 'text-danger bg-danger/10',
            'title' => __('Recruitment'),
            'intro' => __('From job post to first day in one flow.'),
            'items' => [
                __('Vacancies published on your own careers page'),
                __('Applicant tracking with activity history'),
                __('Interview scheduling'),
                __('Offers that convert straight into employee records'),
            ],
        ],
        [
            'icon' => 'document-text', 'tone' => 'text-brand bg-brand/10',
            'title' => __('Documents & communication'),
            'intro' => __('Paperwork, generated in seconds.'),
            'items' => [
                __('HR letter templates (offers, confirmations and more)'),
                __('Company announcements with read tracking'),
                __('HR request desk for employee queries'),
                __('Email and in-app notifications with preferences'),
            ],
        ],
        [
            'icon' => 'chart-bar', 'tone' => 'text-cyan-700 bg-highlight/15',
            'title' => __('Reports & integrations'),
            'intro' => __('Your data, available where you need it.'),
            'items' => [
                __('Headcount, attendance, leave, payroll, expense and recruitment reports'),
                __('Export to Excel, CSV or print'),
                __('REST API with personal access tokens and scoped abilities'),
                __('Outgoing webhooks for your other systems'),
            ],
        ],
    ];
@endphp

<x-layouts::marketing :title="__('Features')" :description="__('Every HR module you need: people, attendance, leave, payroll with Kenyan statutory deductions, performance, recruitment, documents and reports.')">
    <x-marketing.page-header
        :eyebrow="__('Features')"
        :title="__('Everything your HR team does — in one system')"
        :subtitle="__('Twelve connected modules that share data automatically, so work done once shows up everywhere it matters.')" />

    <section class="bg-white py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-6 md:grid-cols-2">
                @foreach ($groups as $group)
                    <div class="rounded-2xl border border-zinc-100 p-8 shadow-sm transition hover:shadow-xl hover:shadow-brand/5">
                        <div class="flex items-center gap-4">
                            <span class="grid size-12 shrink-0 place-items-center rounded-xl {{ $group['tone'] }}"><flux:icon :name="$group['icon']" /></span>
                            <div>
                                <h2 class="text-xl font-bold text-night">{{ $group['title'] }}</h2>
                                <p class="text-sm text-zinc-500">{{ $group['intro'] }}</p>
                            </div>
                        </div>
                        <ul class="mt-6 grid gap-3 text-sm sm:grid-cols-2">
                            @foreach ($group['items'] as $item)
                                <li class="flex gap-2.5"><flux:icon.check-circle variant="mini" class="mt-0.5 size-4 shrink-0 text-success" /> <span>{{ $item }}</span></li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="bg-canvas py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-2xl text-center">
                <p class="text-sm font-bold uppercase tracking-widest text-brand">{{ __('Security & control') }}</p>
                <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-night">{{ __('Built to protect sensitive people data') }}</h2>
            </div>
            <div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ([
                    ['building-office-2', __('Isolated workspaces'), __('Each organization\'s data is kept separate from every other organization.')],
                    ['key', __('Roles & permissions'), __('Owners, HR, finance, managers and employees only see what their role allows.')],
                    ['lock-closed', __('Encrypted sensitive fields'), __('Bank details, statutory numbers and secrets are encrypted in the database.')],
                    ['device-phone-mobile', __('Two-factor & passkeys'), __('Protect accounts with an authenticator app or passwordless passkeys.')],
                    ['shield-check', __('Audit trail'), __('Important changes are logged with who made them and when.')],
                    ['globe-alt', __('HTTPS everywhere'), __('All traffic is served over TLS with modern security headers.')],
                ] as [$icon, $title, $text])
                    <div class="rounded-2xl bg-white p-6 shadow-sm">
                        <span class="grid size-11 place-items-center rounded-xl bg-brand/10 text-brand"><flux:icon :name="$icon" variant="mini" /></span>
                        <h3 class="mt-4 font-bold text-night">{{ $title }}</h3>
                        <p class="mt-2 text-sm text-zinc-500">{{ $text }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <x-marketing.cta />
</x-layouts::marketing>
