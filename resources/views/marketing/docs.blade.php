@php
    $base = rtrim(config('app.url'), '/');
    $sections = [
        'getting-started' => __('Getting started'),
        'roles' => __('Roles & access'),
        'employees' => __('Employees'),
        'attendance' => __('Attendance'),
        'biometrics' => __('Biometric devices'),
        'leave' => __('Leave'),
        'payroll' => __('Payroll'),
        'performance' => __('Performance & tasks'),
        'recruitment' => __('Recruitment'),
        'finance' => __('Expenses, loans & assets'),
        'documents' => __('Documents & letters'),
        'reports' => __('Reports'),
        'billing' => __('Billing & subscription'),
        'security' => __('Account security'),
        'api' => __('REST API'),
        'webhooks' => __('Webhooks'),
        'support' => __('Support'),
    ];
    $endpoints = [
        ['GET', '/api/v1/me', '—', __('The token owner and their organization')],
        ['GET', '/api/v1/employees', 'employees:read', __('List employees (paginated, filterable)')],
        ['GET', '/api/v1/employees/{uuid}', 'employees:read', __('One employee')],
        ['GET', '/api/v1/departments', 'departments:read', __('List departments')],
        ['GET', '/api/v1/leave-requests', 'leave:read', __('List leave requests')],
        ['POST', '/api/v1/leave-requests', 'leave:write', __('Submit a leave request')],
        ['GET', '/api/v1/payslips', 'payroll:read', __('List payslips')],
        ['GET', '/api/v1/payslips/{uuid}', 'payroll:read', __('One payslip')],
        ['GET', '/api/v1/tasks', 'tasks:read', __('List tasks')],
        ['POST', '/api/v1/tasks', 'tasks:write', __('Create a task')],
    ];
    $h2 = 'scroll-mt-28 text-2xl font-extrabold tracking-tight text-night';
    $p = 'mt-3 leading-relaxed text-zinc-600';
    $li = 'flex gap-2.5';
@endphp

<x-layouts::marketing :title="__('Documentation')" :description="__('How to set up and use the HR platform: employees, attendance, biometric devices, leave, payroll, recruitment, billing, security and the REST API.')">
    <x-marketing.page-header
        :eyebrow="__('Documentation')"
        :title="__('Everything you need to run your workspace')"
        :subtitle="__('Guides for administrators, managers and developers — from your first login to connecting other systems through the API.')" />

    <section class="bg-white py-16" x-data="{ active: 'getting-started' }">
        <div class="mx-auto grid max-w-7xl gap-12 px-4 sm:px-6 lg:grid-cols-[15rem_1fr] lg:px-8">
            {{-- Sidebar --}}
            <aside class="hidden lg:block">
                <nav class="sticky top-24 space-y-0.5 border-s border-zinc-100 text-sm">
                    @foreach ($sections as $id => $title)
                        <a href="#{{ $id }}" @click="active = '{{ $id }}'"
                           :class="active === '{{ $id }}' ? 'border-brand text-brand font-semibold' : 'border-transparent text-zinc-500 hover:text-night'"
                           class="-ms-px block border-s-2 py-1.5 ps-4 transition">{{ $title }}</a>
                    @endforeach
                </nav>
            </aside>

            {{-- Mobile jump menu --}}
            <div class="lg:hidden">
                <select onchange="location.hash = this.value" class="w-full rounded-xl border border-zinc-200 px-4 py-2.5 text-sm">
                    @foreach ($sections as $id => $title)
                        <option value="{{ $id }}">{{ $title }}</option>
                    @endforeach
                </select>
            </div>

            <article class="min-w-0 max-w-3xl space-y-16">
                <div id="getting-started" x-intersect.margin.-40%="active = 'getting-started'">
                    <h2 class="{{ $h2 }}">{{ __('Getting started') }}</h2>
                    <p class="{{ $p }}">{{ __('Sign-up is by invitation. Request a quote on the website; once approved you receive an email with a secure access link.') }}</p>
                    <ol class="mt-5 space-y-4">
                        @foreach ([
                            [__('Open your access link'), __('The link works once. When you press “Create my workspace” it is used up, and you have one hour in that browser to finish. If it expires, ask us for a new one.')],
                            [__('Create your organization'), __('Enter your organization name, your name and a strong password. Your free trial starts immediately and you become the Tenant Owner.')],
                            [__('Review the defaults'), __('Leave types, employment types, salary components and letter templates are pre-filled. Adjust them under each module\'s settings.')],
                            [__('Add structure'), __('Create branches, departments and designations, then add employees one by one or import them from a spreadsheet.')],
                            [__('Invite your team'), __('Invite HR staff and managers by email and choose each person\'s role. Employees get their own “My HR” self-service portal.')],
                        ] as [$title, $text])
                            <li class="flex gap-4">
                                <span class="grid size-7 shrink-0 place-items-center rounded-full bg-brand text-xs font-bold text-white">{{ $loop->iteration }}</span>
                                <span><span class="font-semibold text-night">{{ $title }}.</span> <span class="text-zinc-600">{{ $text }}</span></span>
                            </li>
                        @endforeach
                    </ol>
                </div>

                <div id="roles" x-intersect.margin.-40%="active = 'roles'">
                    <h2 class="{{ $h2 }}">{{ __('Roles & access') }}</h2>
                    <p class="{{ $p }}">{{ __('Every user has a role inside your organization. Roles decide which menus, records and actions they can see.') }}</p>
                    <div class="mt-5 overflow-hidden rounded-xl border border-zinc-100">
                        <table class="w-full text-sm">
                            <tbody class="divide-y divide-zinc-100">
                                @foreach ([
                                    [__('Tenant Owner / HR Administrator'), __('Full access, including billing, settings and the audit trail.')],
                                    [__('HR Manager'), __('People, attendance, leave, documents and recruitment.')],
                                    [__('Payroll Manager / Finance Manager'), __('Payroll, expenses, loans and assets.')],
                                    [__('Department Manager / Supervisor'),__('Their team\'s attendance, leave approvals, tasks and reviews.')],
                                    [__('Employee'), __('Their own profile, payslips, leave, attendance and requests via My HR.')],
                                ] as [$role, $scope])
                                    <tr><td class="w-1/3 bg-canvas px-4 py-3 font-semibold text-night">{{ $role }}</td><td class="px-4 py-3 text-zinc-600">{{ $scope }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="employees" x-intersect.margin.-40%="active = 'employees'">
                    <h2 class="{{ $h2 }}">{{ __('Employees') }}</h2>
                    <p class="{{ $p }}">{{ __('Go to People → Employees. Each profile holds personal and job details, bank and statutory information (encrypted), emergency contacts, salary components and documents.') }}</p>
                    <ul class="mt-4 space-y-2 text-sm text-zinc-600">
                        <li class="{{ $li }}"><flux:icon.check-circle variant="micro" class="mt-1 text-success" /> {{ __('Import: upload a spreadsheet, check the preview, then confirm — nothing is saved until you confirm.') }}</li>
                        <li class="{{ $li }}"><flux:icon.check-circle variant="micro" class="mt-1 text-success" /> {{ __('Export the employee list to Excel or CSV at any time.') }}</li>
                        <li class="{{ $li }}"><flux:icon.check-circle variant="micro" class="mt-1 text-success" /> {{ __('The Org chart page shows reporting lines and the department tree.') }}</li>
                        <li class="{{ $li }}"><flux:icon.check-circle variant="micro" class="mt-1 text-success" /> {{ __('Documents with an expiry date trigger reminders before they lapse.') }}</li>
                    </ul>
                </div>

                <div id="attendance" x-intersect.margin.-40%="active = 'attendance'">
                    <h2 class="{{ $h2 }}">{{ __('Attendance') }}</h2>
                    <p class="{{ $p }}">{{ __('Employees clock in and out from the dashboard or their phone, including breaks. Administrators can require clock-ins to happen inside a geofence around a branch, and staff who forget to clock out are clocked out automatically after a set number of hours.') }}</p>
                    <p class="{{ $p }}">{{ __('Define shifts and assign them to employees; late arrivals, early departures, half days and absences are detected against the shift and appear in attendance records and payroll.') }}</p>
                </div>

                <div id="biometrics" x-intersect.margin.-40%="active = 'biometrics'">
                    <h2 class="{{ $h2 }}">{{ __('Biometric devices (ZKTeco)') }}</h2>
                    <p class="{{ $p }}">{{ __('Devices that support the ADMS / Push protocol send punches straight to your workspace.') }}</p>
                    <ol class="mt-4 list-decimal space-y-2 ps-5 text-sm text-zinc-600">
                        <li>{{ __('Register the device under Attendance → Devices using its serial number.') }}</li>
                        <li>{{ __('On the device, open Comm → Cloud Server Setting and enter:') }}
                            <code class="mt-2 block rounded-lg bg-night px-4 py-3 text-xs text-highlight">{{ __('Server address') }}: {{ parse_url($base, PHP_URL_HOST) }} &nbsp;·&nbsp; {{ __('Port') }}: 443 &nbsp;·&nbsp; HTTPS: {{ __('on') }}</code>
                        </li>
                        <li>{{ __('Match each device user to an employee. Punches then appear as attendance within a minute.') }}</li>
                    </ol>
                </div>

                <div id="leave" x-intersect.margin.-40%="active = 'leave'">
                    <h2 class="{{ $h2 }}">{{ __('Leave') }}</h2>
                    <p class="{{ $p }}">{{ __('Create leave types (annual, sick, maternity…) with their yearly entitlement. Balances accrue monthly and unused days carry forward according to each type\'s rules. Employees apply from My HR; the request goes to their manager, and both are notified of the decision. Approved leave shows on the team calendar and in attendance.') }}</p>
                </div>

                <div id="payroll" x-intersect.margin.-40%="active = 'payroll'">
                    <h2 class="{{ $h2 }}">{{ __('Payroll') }}</h2>
                    <ol class="mt-4 list-decimal space-y-2 ps-5 text-zinc-600">
                        <li>{{ __('Set up salary components (earnings and deductions) and assign them to employees.') }}</li>
                        <li>{{ __('Open a payroll period and create a run. The run calculates gross pay, PAYE, NSSF, SHIF and the Affordable Housing Levy using the tax rules in your workspace.') }}</li>
                        <li>{{ __('Review, approve, then mark the run paid. Payslips become available to each employee in My HR and through the API.') }}</li>
                    </ol>
                    <h3 class="mt-6 font-bold text-night">{{ __('Attendance-based pay & overtime') }}</h3>
                    <p class="{{ $p }}">{{ __('Payroll reads attendance automatically. Under Payroll → Pay rules choose how salary is calculated:') }}</p>
                    <ul class="mt-3 space-y-2 text-sm text-zinc-600">
                        <li class="{{ $li }}"><flux:icon.check-circle variant="micro" class="mt-1 text-success" /> <span><strong>{{ __('Attendance-based (default):') }}</strong> {{ __('basic salary × payable days ÷ working days. Days present count fully, half days count ½, and approved paid leave counts as worked. Weekends and public holidays are not working days, so they never reduce pay.') }}</span></li>
                        <li class="{{ $li }}"><flux:icon.check-circle variant="micro" class="mt-1 text-success" /> <span><strong>{{ __('Fixed:') }}</strong> {{ __('the full monthly basic is paid regardless of attendance.') }}</span></li>
                        <li class="{{ $li }}"><flux:icon.check-circle variant="micro" class="mt-1 text-success" /> <span><strong>{{ __('Hourly staff') }}</strong> {{ __('(pay frequency “hourly”) earn their hourly rate × regular hours worked.') }}</span></li>
                        <li class="{{ $li }}"><flux:icon.check-circle variant="micro" class="mt-1 text-success" /> <span><strong>{{ __('Overtime') }}</strong> {{ __('is added as a taxable earning: hours beyond the shift (after its overtime threshold) at 1.5× the hourly rate, and every hour worked on a rest day or public holiday at 2×. Both rates are configurable. Hourly rate = basic ÷ (working days × standard daily hours).') }}</span></li>
                    </ul>
                    <p class="{{ $p }}">{{ __('Each payslip shows the days paid, hours worked and overtime, and the payroll run warns you about anyone paid for zero days before you approve it.') }}</p>

                    <h3 class="mt-6 font-bold text-night">{{ __('Earnings to date & final pay') }}</h3>
                    <p class="{{ $p }}">{{ __('The Payroll page shows what each employee has earned so far in the current pay cycle. It is worked out from attendance up to today, so the figure grows with every day worked.') }}</p>
                    <p class="{{ $p }}">{{ __('When someone leaves, set their status to terminated, resigned or retired and enter an exit date. Under Payroll → Final settlements, click “Prepare final pay”. This works out everything they are owed:') }}</p>
                    <ul class="mt-3 space-y-2 text-sm text-zinc-600">
                        <li class="{{ $li }}"><flux:icon.check-circle variant="micro" class="mt-1 text-success" /> <span>{{ __('Pay from the day after their last payroll up to the exit date, including overtime.') }}</span></li>
                        <li class="{{ $li }}"><flux:icon.check-circle variant="micro" class="mt-1 text-success" /> <span>{{ __('Their untaken annual leave, paid out at their daily rate.') }}</span></li>
                        <li class="{{ $li }}"><flux:icon.check-circle variant="micro" class="mt-1 text-success" /> <span>{{ __('Less tax and statutory deductions, with any outstanding loan recovered in full as far as their pay covers it.') }}</span></li>
                    </ul>
                    <p class="{{ $p }}">{{ __('The settlement then appears in the payroll runs list, where you approve it and mark it paid like any other run.') }}</p>
                    <p class="mt-4 rounded-xl border border-warning/40 bg-warning/10 p-4 text-sm text-zinc-700">{{ __('Statutory rates change from time to time. Check the tax rules under Payroll settings before your first run and after any government announcement.') }}</p>
                </div>

                <div id="performance" x-intersect.margin.-40%="active = 'performance'">
                    <h2 class="{{ $h2 }}">{{ __('Performance & tasks') }}</h2>
                    <p class="{{ $p }}">{{ __('Start a performance cycle, set goals and KPIs, and assign reviews to managers. Employees record check-ins against their goals. Tasks can be assigned to anyone and tracked to completion, with notifications on assignment and completion.') }}</p>
                </div>

                <div id="recruitment" x-intersect.margin.-40%="active = 'recruitment'">
                    <h2 class="{{ $h2 }}">{{ __('Recruitment') }}</h2>
                    <p class="{{ $p }}">{{ __('Publish a vacancy and it appears on the public Jobs page, where candidates apply online with their CV. No account is needed. Each applicant gets a tracking code by email and can follow their progress on the Track application page.') }}</p>
                    <p class="{{ $p }}">{{ __('Move applications through the stages, schedule interviews and send offers. Candidates are emailed at each step: when their application is received, when they are invited to an interview, when they get an offer, and when they are unsuccessful. They can accept or decline an offer on the tracking page.') }}</p>
                    <p class="{{ $p }}">{{ __('On the recruitment board, click “Hire & create account” on an accepted offer. This creates the employee record and a staff login, and emails the new hire a one-time link to set their password. The link is valid for 7 days.') }}</p>
                    <p class="{{ $p }}">{{ __('New hires sign in to My HR. There they see what they have earned so far this month, salary received this year, and their payslips. They can also see their tasks, leave balances and requests, attendance, and company announcements.') }}</p>
                </div>

                <div id="finance" x-intersect.margin.-40%="active = 'finance'">
                    <h2 class="{{ $h2 }}">{{ __('Expenses, loans & assets') }}</h2>
                    <p class="{{ $p }}">{{ __('Employees submit expense claims with receipts for approval and reimbursement. Staff loans are recorded with a repayment schedule. The asset register tracks company equipment and who it is assigned to.') }}</p>
                </div>

                <div id="documents" x-intersect.margin.-40%="active = 'documents'">
                    <h2 class="{{ $h2 }}">{{ __('Documents & HR letters') }}</h2>
                    <p class="{{ $p }}">{{ __('Letter templates use placeholders such as the employee\'s name, job title and salary. Generate a letter for any employee and it is saved to their file. Announcements reach everyone with read tracking, and the HR request desk collects employee queries in one queue.') }}</p>
                </div>

                <div id="reports" x-intersect.margin.-40%="active = 'reports'">
                    <h2 class="{{ $h2 }}">{{ __('Reports') }}</h2>
                    <p class="{{ $p }}">{{ __('Headcount, attendance, leave, payroll, expense and recruitment reports can be filtered, printed, or exported to Excel and CSV from the Reports page.') }}</p>
                </div>

                <div id="billing" x-intersect.margin.-40%="active = 'billing'">
                    <h2 class="{{ $h2 }}">{{ __('Billing & subscription') }}</h2>
                    <p class="{{ $p }}">{{ __('Your trial and subscription are managed under Billing. Choose a plan and pay online by card, M-Pesa or bank; invoices can be downloaded as PDF. If a trial ends or a payment lapses, only the Billing page stays available until the subscription is renewed — no data is deleted.') }}</p>
                </div>

                <div id="security" x-intersect.margin.-40%="active = 'security'">
                    <h2 class="{{ $h2 }}">{{ __('Account security') }}</h2>
                    <p class="{{ $p }}">{{ __('Under Settings every user can change their password, enable two-factor authentication with an authenticator app, and register passkeys for passwordless sign-in. Administrators can review important changes in the audit trail.') }}</p>
                </div>

                <div id="api" x-intersect.margin.-40%="active = 'api'">
                    <h2 class="{{ $h2 }}">{{ __('REST API') }}</h2>
                    <p class="{{ $p }}">{{ __('Create a personal access token under Developers and choose its abilities (read-only by default). Send it as a Bearer token. Requests are limited to 120 per minute per token and always act on the token owner\'s organization.') }}</p>
                    <pre class="mt-5 overflow-x-auto rounded-xl bg-night p-5 text-xs leading-relaxed text-zinc-200"><code><span class="text-highlight">curl</span> {{ $base }}/api/v1/employees \
  -H <span class="text-amber-300">"Authorization: Bearer YOUR_TOKEN"</span> \
  -H <span class="text-amber-300">"Accept: application/json"</span></code></pre>
                    <div class="mt-5 overflow-x-auto rounded-xl border border-zinc-100">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-canvas text-xs uppercase tracking-wider text-zinc-500">
                                <tr><th class="px-4 py-3">{{ __('Method') }}</th><th class="px-4 py-3">{{ __('Endpoint') }}</th><th class="px-4 py-3">{{ __('Ability') }}</th><th class="px-4 py-3">{{ __('Description') }}</th></tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100">
                                @foreach ($endpoints as [$method, $path, $ability, $text])
                                    <tr>
                                        <td class="px-4 py-2.5"><span class="rounded px-2 py-0.5 text-[11px] font-bold {{ $method === 'GET' ? 'bg-info/10 text-info' : 'bg-success/10 text-success' }}">{{ $method }}</span></td>
                                        <td class="px-4 py-2.5 font-mono text-xs text-night">{{ $path }}</td>
                                        <td class="px-4 py-2.5 font-mono text-xs text-zinc-500">{{ $ability }}</td>
                                        <td class="px-4 py-2.5 text-zinc-600">{{ $text }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="webhooks" x-intersect.margin.-40%="active = 'webhooks'">
                    <h2 class="{{ $h2 }}">{{ __('Webhooks') }}</h2>
                    <p class="{{ $p }}">{{ __('Under Developers, add an endpoint URL and choose the events to receive. Each delivery is signed with the endpoint\'s secret so you can verify it came from us. Failed deliveries are retried with back-off, and an endpoint is paused after repeated failures.') }}</p>
                </div>

                <div id="support" x-intersect.margin.-40%="active = 'support'">
                    <h2 class="{{ $h2 }}">{{ __('Support') }}</h2>
                    <p class="{{ $p }}">{{ __('Can\'t find what you need?') }} <a href="{{ route('contact') }}" class="font-semibold text-brand hover:underline">{{ __('Contact us') }}</a> {{ __('and we will help you get set up.') }}</p>
                </div>
            </article>
        </div>
    </section>
</x-layouts::marketing>
