<x-layouts::app :title="__('Training')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <div>
            <flux:heading size="xl">{{ __('Training & help centre') }}</flux:heading>
            <flux:text class="mt-1">{{ __('A walkthrough of every part of the system — what it does, who can use it, and how.') }}</flux:text>
        </div>

        <div class="grid gap-8 lg:grid-cols-[240px_1fr] lg:items-start">
            <nav class="scrollbar-none order-2 -mx-1 flex gap-1 overflow-x-auto px-1 pb-1 lg:sticky lg:top-6 lg:order-1 lg:flex-col lg:overflow-visible lg:pb-0">
                @foreach ([
                    'roles' => __('Roles & permissions'),
                    'employees' => __('Employees & org chart'),
                    'attendance' => __('Attendance & biometric devices'),
                    'leave' => __('Leave'),
                    'tasks' => __('Tasks'),
                    'payroll' => __('Payroll'),
                    'performance' => __('Performance'),
                    'recruitment' => __('Recruitment'),
                    'hr-letters' => __('HR letters'),
                    'finance' => __('Expenses, loans & assets'),
                    'reports' => __('Reports'),
                    'notifications' => __('Notifications'),
                    'developers' => __('Developers & API'),
                    'audit' => __('Audit trail'),
                    'billing' => __('Billing & branding'),
                ] as $anchor => $label)
                    <a href="#{{ $anchor }}" class="whitespace-nowrap rounded-lg px-3 py-1.5 text-sm text-neutral-600 hover:bg-neutral-100 dark:text-neutral-400 dark:hover:bg-neutral-800">
                        {{ $label }}
                    </a>
                @endforeach
            </nav>

            <div class="order-1 flex flex-col gap-10 lg:order-2 [&_section]:scroll-mt-6">

                <section id="roles">
                    <flux:heading size="lg">{{ __('Roles & permissions') }}</flux:heading>
                    <flux:text class="mt-2">
                        {{ __('Everything in the system is gated by role. You only see the modules and actions your role grants — if a button or page described here is missing, it\'s a permissions issue, not a bug.') }}
                    </flux:text>
                    <div class="mt-4 overflow-x-auto rounded-xl border border-neutral-200 dark:border-neutral-800">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-neutral-50 text-xs uppercase text-neutral-500 dark:bg-neutral-900">
                                <tr>
                                    <th class="px-4 py-3">{{ __('Role') }}</th>
                                    <th class="px-4 py-3">{{ __('Typical access') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                                <tr>
                                    <td class="px-4 py-3 font-medium">{{ __('Tenant Owner / HR Administrator') }}</td>
                                    <td class="px-4 py-3 text-neutral-600 dark:text-neutral-400">{{ __('Full access to every module, including billing, branding, and role assignment.') }}</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 font-medium">{{ __('HR Manager') }}</td>
                                    <td class="px-4 py-3 text-neutral-600 dark:text-neutral-400">{{ __('Employees, attendance, leave, performance, recruitment, tasks, expenses (view/approve), assets, reports.') }}</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 font-medium">{{ __('Payroll Manager') }}</td>
                                    <td class="px-4 py-3 text-neutral-600 dark:text-neutral-400">{{ __('Payroll runs and processing, expense approval, reports.') }}</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 font-medium">{{ __('Finance Manager') }}</td>
                                    <td class="px-4 py-3 text-neutral-600 dark:text-neutral-400">{{ __('Payroll approval, expenses, assets, billing visibility, reports.') }}</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 font-medium">{{ __('Department Manager / Supervisor') }}</td>
                                    <td class="px-4 py-3 text-neutral-600 dark:text-neutral-400">{{ __('Their team\'s attendance and leave approval, performance reviews, task assignment, interview feedback.') }}</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 font-medium">{{ __('Employee') }}</td>
                                    <td class="px-4 py-3 text-neutral-600 dark:text-neutral-400">{{ __('My HR portal, clocking in/out, requesting leave, their own tasks, expense claims, payslips.') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section id="employees">
                    <flux:heading size="lg">{{ __('Employees & org chart') }}</flux:heading>
                    <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-neutral-700 dark:text-neutral-300">
                        <li>{{ __('People → Employees lists everyone at the company. Use "Add employee" to create a record, or Import/Export from the toolbar for bulk changes via CSV.') }}</li>
                        <li>{{ __('Click an employee to edit their personal, job, and compensation details, upload documents, or set their manager and department — this drives the org chart and approval routing.') }}</li>
                        <li>{{ __('People → Org chart renders the reporting structure automatically from each employee\'s manager field.') }}</li>
                        <li>{{ __('An employee only becomes a system user (able to log in) once invited — invite from the employee\'s profile or via Users/Invitations.') }}</li>
                    </ul>
                </section>

                <section id="attendance">
                    <flux:heading size="lg">{{ __('Attendance & biometric devices') }}</flux:heading>
                    <flux:text class="mt-2">{{ __('Time & leave → Attendance covers both self-service clocking and company-wide records.') }}</flux:text>
                    <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-neutral-700 dark:text-neutral-300">
                        <li>{{ __('Every employee sees a Clock in / Clock out / Start break / End break panel at the top — this is how attendance is recorded day to day.') }}</li>
                        <li>{{ __('HR/managers see an admin table of every punch, with the ability to add or correct a record manually when someone forgets to clock in.') }}</li>
                        <li>{{ __('Biometric terminals (ZKTeco): "Register device" adds a terminal by its serial number — point the terminal\'s ADMS server setting at this system first. Once online, use "Employees" on that device to push a PIN, keypad password, and (if set) a card number to the terminal with "Add" — this lets someone verify by PIN+password or card immediately.') }}</li>
                        <li>{{ __('Fingerprint enrolment cannot be done remotely — it must happen at the terminal itself: User Mgt → the employee\'s PIN → Edit → Fingerprint. Once captured, the "Fingerprints" button lets you push that same fingerprint to the company\'s other terminals so the employee doesn\'t have to re-enrol at each gate, or delete it everywhere.') }}</li>
                        <li>{{ __('"Test connection", "List enrolled users" and "Reboot" on a device are diagnostic tools — enrolled users fill in automatically as the terminal replies, no page refresh needed.') }}</li>
                        <li>{{ __('One company can register multiple terminals, and any employee synced to a terminal can use it — there\'s no one-device-per-employee restriction.') }}</li>
                    </ul>
                </section>

                <section id="leave">
                    <flux:heading size="lg">{{ __('Leave') }}</flux:heading>
                    <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-neutral-700 dark:text-neutral-300">
                        <li>{{ __('Time & leave → Leave shows an employee\'s balances per leave type, a request form, and their request history.') }}</li>
                        <li>{{ __('Submitting a request routes it through the configured approval chain (usually the employee\'s manager, then HR) — approvers see it under "Awaiting my decision" on the same page.') }}</li>
                        <li>{{ __('A pending request, or an approved one that hasn\'t started yet, can be cancelled by the requester or an approver.') }}</li>
                        <li>{{ __('HR can manage leave types (annual, sick, etc.), their yearly allocation, accrual rules, and whether they require approval or a supporting document.') }}</li>
                    </ul>
                </section>

                <section id="tasks">
                    <flux:heading size="lg">{{ __('Tasks') }}</flux:heading>
                    <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-neutral-700 dark:text-neutral-300">
                        <li>{{ __('Work → Tasks lets a manager assign a task to any employee with a title, description, priority, and due date via "Assign task".') }}</li>
                        <li>{{ __('The employee sees it under "My tasks" and can "Start" it, then "Mark complete" (optionally leaving a remarks note) — both the assignment and the completion send a notification.') }}</li>
                        <li>{{ __('Managers see every task company-wide under "All tasks", including an "Overdue" flag, and can edit, reassign, cancel, or delete a task.') }}</li>
                    </ul>
                </section>

                <section id="payroll">
                    <flux:heading size="lg">{{ __('Payroll') }}</flux:heading>
                    <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-neutral-700 dark:text-neutral-300">
                        <li>{{ __('Work → Payroll is where salary components (allowances, deductions) and statutory tax rules are configured, and where an employee\'s individual salary structure is set from their profile.') }}</li>
                        <li>{{ __('Create a payroll period, then "Run" it to generate every employee\'s payslip for that period.') }}</li>
                        <li>{{ __('A run moves through Approve → Pay → Lock — locking prevents further edits once salaries have gone out.') }}</li>
                        <li>{{ __('Employees view and download their own payslips from My HR.') }}</li>
                    </ul>
                </section>

                <section id="performance">
                    <flux:heading size="lg">{{ __('Performance') }}</flux:heading>
                    <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-neutral-700 dark:text-neutral-300">
                        <li>{{ __('Work → Performance is organised around cycles (e.g. "2026 H1 Review") — open a cycle, then start its review phase when it\'s time for write-ups.') }}</li>
                        <li>{{ __('Within a cycle, employees and managers set goals with check-ins, and can track KPIs with recorded values over time.') }}</li>
                        <li>{{ __('A review is written by the employee and/or manager, submitted, then acknowledged to close it out.') }}</li>
                    </ul>
                </section>

                <section id="recruitment">
                    <flux:heading size="lg">{{ __('Recruitment') }}</flux:heading>
                    <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-neutral-700 dark:text-neutral-300">
                        <li>{{ __('Work → Recruitment is a hiring pipeline: create a job position, open a vacancy against it, then "Publish" the vacancy to list it on the company\'s public careers page.') }}</li>
                        <li>{{ __('Candidates who apply (or are added manually) move across the board through Applied → Screening → Interview → Offer as you drag/click them forward.') }}</li>
                        <li>{{ __('Schedule interviews and leave feedback from the applicant\'s card; a candidate can be rejected at any open stage.') }}</li>
                        <li>{{ __('At the Offer stage, "Make offer" then "Send" puts it in front of the candidate; once accepted, "Convert" turns them into a full employee record.') }}</li>
                    </ul>
                </section>

                <section id="hr-letters">
                    <flux:heading size="lg">{{ __('HR letters') }}</flux:heading>
                    <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-neutral-700 dark:text-neutral-300">
                        <li>{{ __('Work → HR letters holds reusable letter templates (offer letters, confirmation letters, etc.) with placeholders like an employee\'s name that get filled in automatically.') }}</li>
                        <li>{{ __('Pick a template and an employee, preview the merged letter, then generate and download it.') }}</li>
                    </ul>
                </section>

                <section id="finance">
                    <flux:heading size="lg">{{ __('Expenses, loans & assets') }}</flux:heading>
                    <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-neutral-700 dark:text-neutral-300">
                        <li>{{ __('Finance → Expenses: an employee starts a claim and adds line items with receipts; it moves through Submit → Approve → Reimburse (or Reject at any point).') }}</li>
                        <li>{{ __('Finance → Loans: an employee (or HR, on someone\'s behalf) requests a salary advance or loan with a number of instalments; once approved it\'s repaid automatically through payroll.') }}</li>
                        <li>{{ __('Finance → Assets is the company equipment register — add an asset, "Assign" it to an employee, and "Return" or "Retire" it when it comes back or reaches end of life.') }}</li>
                    </ul>
                </section>

                <section id="reports">
                    <flux:heading size="lg">{{ __('Reports') }}</flux:heading>
                    <flux:text class="mt-2 text-sm text-neutral-700 dark:text-neutral-300">
                        {{ __('Reports gathers a catalogue of pre-built reports across headcount, attendance, leave, and payroll. Open one to view it on screen, export it, or print it.') }}
                    </flux:text>
                </section>

                <section id="notifications">
                    <flux:heading size="lg">{{ __('Notifications') }}</flux:heading>
                    <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-neutral-700 dark:text-neutral-300">
                        <li>{{ __('The bell icon and the Notifications page show everything that needs your attention — a leave request awaiting approval, a task assigned to you, and similar events.') }}</li>
                        <li>{{ __('Each person can turn off e-mail for a category they don\'t want to be e-mailed about (in-app notifications are always kept) from their notification preferences.') }}</li>
                    </ul>
                </section>

                <section id="developers">
                    <flux:heading size="lg">{{ __('Developers & API') }}</flux:heading>
                    <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-neutral-700 dark:text-neutral-300">
                        <li>{{ __('Developers → API tokens lets you generate a personal access token for scripts or external integrations, scoped to specific abilities (e.g. read-only leave data, or write access to tasks).') }}</li>
                        <li>{{ __('Webhook endpoints let the system push events (like a new attendance punch) to a URL you control, with delivery history and a "Ping" test button.') }}</li>
                        <li>{{ __('Every module documented above is also reachable over the API — the dashboard and the API always operate on the same data.') }}</li>
                    </ul>
                </section>

                <section id="audit">
                    <flux:heading size="lg">{{ __('Audit trail') }}</flux:heading>
                    <flux:text class="mt-2 text-sm text-neutral-700 dark:text-neutral-300">
                        {{ __('Audit trail (visible to those with audit access) is a chronological log of meaningful changes across the system — who did what, and when — useful for compliance and investigating a support issue.') }}
                    </flux:text>
                </section>

                <section id="billing">
                    <flux:heading size="lg">{{ __('Billing & branding') }}</flux:heading>
                    <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-neutral-700 dark:text-neutral-300">
                        <li>{{ __('The owner manages the company\'s subscription plan and views past invoices/payments from Billing.') }}</li>
                        <li>{{ __('Settings → Branding lets you set the company logo and colours used on letters, payslips, and the careers page.') }}</li>
                    </ul>
                </section>

                <flux:callout icon="information-circle">
                    <flux:callout.heading>{{ __('Still stuck?') }}</flux:callout.heading>
                    <flux:callout.text>{{ __('Ask your HR Administrator, or raise it as an HR request if that module is enabled for your company.') }}</flux:callout.text>
                </flux:callout>
            </div>
        </div>
    </div>
</x-layouts::app>
