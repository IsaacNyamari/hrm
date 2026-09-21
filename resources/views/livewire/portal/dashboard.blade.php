@php
    $money = fn (int $cents) => number_format($cents / 100, 2);
    $num = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    $statusTone = [
        'approved' => 'bg-success/10 text-success',
        'pending' => 'bg-warning/15 text-amber-700 dark:text-amber-300',
        'rejected' => 'bg-danger/10 text-danger',
        'cancelled' => 'bg-zinc-100 text-zinc-500 dark:bg-zinc-800',
        'paid' => 'bg-success/10 text-success',
        'locked' => 'bg-success/10 text-success',
    ];
    $card = 'rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-700 dark:bg-neutral-900';
@endphp

<div class="flex flex-col gap-6">
    @unless ($hasEmployee)
        <h1 class="text-xl font-semibold text-neutral-800 dark:text-neutral-100">{{ __('My HR') }}</h1>
        <p class="rounded-xl border border-dashed border-neutral-300 p-8 text-center text-neutral-500 dark:border-neutral-700">
            {{ __('Your account is not linked to an employee record. Ask your HR team to link it.') }}
        </p>
    @else
        @php
            $job = $data['job'];
            $earned = $data['earnings_to_date'];
            $salary = $data['salary'];
            $tasks = $data['tasks'];
            $leave = $data['leave'];
            $month = $data['attendance_month'];
            $share = $earned['full_basic_cents'] > 0 ? min(100, round($earned['gross_cents'] / $earned['full_basic_cents'] * 100)) : 0;
        @endphp

        {{-- Welcome --}}
        <div class="relative overflow-hidden rounded-2xl bg-linear-to-br from-brand to-indigo-900 p-6 text-white">
            <div class="pointer-events-none absolute -top-16 -right-10 size-56 rounded-full bg-highlight/20 blur-3xl"></div>
            <div class="relative flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-sm text-white/70">{{ __('Welcome back') }}</p>
                    <h1 class="text-2xl font-bold">{{ $data['employee']['name'] }}</h1>
                    <p class="mt-1 text-sm text-white/80">
                        {{ $data['employee']['job_title'] ?: __('Employee') }}{{ $job['department'] ? ' · '.$job['department'] : '' }}
                        · {{ __('No. :number', ['number' => $data['employee']['number']]) }}
                    </p>
                    <p class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-white/70">
                        @if ($job['manager']) <span>{{ __('Reports to :name', ['name' => $job['manager']]) }}</span> @endif
                        @if ($job['hire_date']) <span>{{ __('Joined :date', ['date' => \Carbon\CarbonImmutable::parse($job['hire_date'])->toFormattedDateString()]) }}</span> @endif
                        @if ($job['branch']) <span>{{ $job['branch'] }}</span> @endif
                    </p>
                </div>
                <div class="flex flex-col items-end gap-2">
                    <span class="rounded-full bg-white/15 px-3 py-1 text-xs font-semibold">{{ $job['on_probation'] ? __('On probation') : $job['status'] }}</span>
                    <a href="{{ route('attendance.index') }}" wire:navigate class="inline-flex items-center gap-1.5 rounded-lg bg-highlight px-3 py-2 text-sm font-semibold text-night transition hover:bg-cyan-400">
                        <flux:icon.clock variant="micro" />
                        {{ $data['attendance_today']['clocked_in'] ? __('Clocked in — open time clock') : __('Clock in') }}
                    </a>
                </div>
            </div>
        </div>

        {{-- Headline figures --}}
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="{{ $card }}" data-test="portal-earned">
                <p class="text-xs font-medium uppercase text-neutral-500">{{ __('Earned so far · :period', ['period' => $earned['period']]) }}</p>
                <p class="mt-1 text-2xl font-bold text-zinc-900 dark:text-white">{{ $earned['currency'] }} {{ $money($earned['gross_cents']) }}</p>
                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-neutral-100 dark:bg-neutral-800"><div class="h-full rounded-full bg-success" style="width: {{ $share }}%"></div></div>
                <p class="mt-1.5 text-xs text-neutral-500">{{ __(':days days paid', ['days' => $num($earned['payable_days'])]) }}{{ $earned['overtime_hours'] > 0 ? ' · '.__(':h h overtime', ['h' => $num($earned['overtime_hours'])]) : '' }}</p>
            </div>
            <div class="{{ $card }}" data-test="portal-salary">
                <p class="text-xs font-medium uppercase text-neutral-500">{{ __('Salary received this year') }}</p>
                <p class="mt-1 text-2xl font-bold text-zinc-900 dark:text-white">{{ $money($salary['ytd_net_cents']) }}</p>
                <p class="mt-1.5 text-xs text-neutral-500">{{ __('Gross :gross · deductions :ded', ['gross' => $money($salary['ytd_gross_cents']), 'ded' => $money($salary['ytd_deductions_cents'])]) }}</p>
            </div>
            <a href="{{ route('tasks') }}" wire:navigate class="{{ $card }} transition hover:border-brand/40" data-test="portal-tasks">
                <p class="text-xs font-medium uppercase text-neutral-500">{{ __('My tasks') }}</p>
                <p class="mt-1 text-2xl font-bold text-zinc-900 dark:text-white">{{ $tasks['open'] }} <span class="text-sm font-medium text-neutral-500">{{ __('open') }}</span></p>
                <p class="mt-1.5 text-xs {{ $tasks['overdue'] > 0 ? 'font-semibold text-danger' : 'text-neutral-500' }}">
                    {{ $tasks['overdue'] > 0 ? trans_choice(':count overdue|:count overdue', $tasks['overdue']) : __(':count completed this month', ['count' => $tasks['completed_this_month']]) }}
                </p>
            </a>
            <a href="{{ route('leave') }}" wire:navigate class="{{ $card }} transition hover:border-brand/40">
                @php($annual = collect($leave['balances'])->firstWhere('type', 'Annual Leave') ?? collect($leave['balances'])->first())
                <p class="text-xs font-medium uppercase text-neutral-500">{{ $annual ? __(':type left', ['type' => $annual['type']]) : __('Leave') }}</p>
                <p class="mt-1 text-2xl font-bold text-zinc-900 dark:text-white">{{ $annual ? $num($annual['remaining']) : '—' }} <span class="text-sm font-medium text-neutral-500">{{ __('days') }}</span></p>
                <p class="mt-1.5 text-xs text-neutral-500">{{ trans_choice(':count request pending|:count requests pending', $data['pending_leave']) }}</p>
            </a>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            {{-- Tasks --}}
            <div class="{{ $card }} lg:col-span-2">
                <div class="flex items-center justify-between">
                    <h2 class="font-semibold text-zinc-900 dark:text-white">{{ __('Tasks assigned to me') }}</h2>
                    <a href="{{ route('tasks') }}" wire:navigate class="text-xs font-semibold text-brand hover:underline dark:text-highlight">{{ __('Open task board') }}</a>
                </div>
                <ul class="mt-3 divide-y divide-neutral-100 text-sm dark:divide-neutral-800">
                    @forelse ($tasks['items'] as $task)
                        <li class="flex items-center justify-between gap-3 py-2.5">
                            <span class="flex min-w-0 items-center gap-2">
                                <span @class(['size-2 shrink-0 rounded-full', 'bg-danger' => $task['priority'] === 'high', 'bg-warning' => $task['priority'] === 'medium', 'bg-success' => $task['priority'] === 'low'])></span>
                                <span class="truncate text-zinc-800 dark:text-zinc-200">{{ $task['title'] }}</span>
                            </span>
                            <span class="flex shrink-0 items-center gap-2 text-xs">
                                <span class="rounded-full bg-neutral-100 px-2 py-0.5 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300">{{ \Illuminate\Support\Str::headline($task['status']) }}</span>
                                @if ($task['due_date'])
                                    <span class="{{ $task['overdue'] ? 'font-semibold text-danger' : 'text-neutral-500' }}">{{ __('Due :date', ['date' => \Carbon\CarbonImmutable::parse($task['due_date'])->format('j M')]) }}</span>
                                @endif
                            </span>
                        </li>
                    @empty
                        <li class="py-6 text-center text-neutral-500">{{ __('No open tasks. Nice work!') }}</li>
                    @endforelse
                </ul>
            </div>

            {{-- Attendance --}}
            <div class="{{ $card }}">
                <h2 class="font-semibold text-zinc-900 dark:text-white">{{ __('Attendance this month') }}</h2>
                <dl class="mt-3 space-y-2.5 text-sm">
                    <div class="flex justify-between"><dt class="text-neutral-500">{{ __('Days present') }}</dt><dd class="font-semibold">{{ $num($month['present_days']) }} / {{ $num($month['working_days_so_far']) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-neutral-500">{{ __('Paid leave days') }}</dt><dd class="font-semibold">{{ $num($month['paid_leave_days']) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-neutral-500">{{ __('Hours worked') }}</dt><dd class="font-semibold">{{ $num($month['worked_hours']) }} h</dd></div>
                    <div class="flex justify-between"><dt class="text-neutral-500">{{ __('Overtime') }}</dt><dd class="font-semibold">{{ $num($month['overtime_hours']) }} h</dd></div>
                    <div class="flex justify-between"><dt class="text-neutral-500">{{ __('Today') }}</dt>
                        <dd class="font-semibold">
                            @if ($data['attendance_today']['clocked_in'])
                                {{ $data['attendance_today']['on_break'] ? __('On break') : __('Clocked in') }}
                            @elseif ($data['attendance_today']['status'])
                                {{ \Illuminate\Support\Str::headline($data['attendance_today']['status']) }}
                            @else
                                {{ __('Not clocked in') }}
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        {{-- Leave --}}
        <div class="{{ $card }}" data-test="portal-leave">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-zinc-900 dark:text-white">{{ __('My leave') }}</h2>
                <a href="{{ route('leave') }}" wire:navigate class="inline-flex items-center gap-1 rounded-lg bg-brand px-3 py-1.5 text-xs font-semibold text-white"><flux:icon.plus variant="micro" /> {{ __('Request leave') }}</a>
            </div>
            <div class="mt-3 grid gap-6 lg:grid-cols-2">
                <table class="w-full text-left text-sm">
                    <thead class="text-xs uppercase text-neutral-500">
                        <tr><th class="py-2">{{ __('Type') }}</th><th class="py-2 text-right">{{ __('Entitled') }}</th><th class="py-2 text-right">{{ __('Used') }}</th><th class="py-2 text-right">{{ __('Pending') }}</th><th class="py-2 text-right">{{ __('Left') }}</th></tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                        @foreach ($leave['balances'] as $b)
                            <tr>
                                <td class="py-2 text-zinc-700 dark:text-zinc-300">{{ $b['type'] }}</td>
                                <td class="py-2 text-right text-neutral-500">{{ $num($b['entitled']) }}</td>
                                <td class="py-2 text-right text-neutral-500">{{ $num($b['used']) }}</td>
                                <td class="py-2 text-right text-neutral-500">{{ $num($b['pending']) }}</td>
                                <td class="py-2 text-right font-semibold">{{ $num($b['remaining']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div>
                    <p class="text-xs font-semibold uppercase text-neutral-500">{{ __('Recent requests') }}</p>
                    <ul class="mt-2 space-y-2 text-sm">
                        @forelse ($leave['requests'] as $r)
                            <li class="flex items-center justify-between gap-3 rounded-lg bg-neutral-50 px-3 py-2 dark:bg-neutral-800/50">
                                <span>
                                    <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $r['type'] }}</span>
                                    <span class="block text-xs text-neutral-500">{{ \Carbon\CarbonImmutable::parse($r['start_date'])->format('j M') }} – {{ \Carbon\CarbonImmutable::parse($r['end_date'])->format('j M Y') }} · {{ trans_choice(':count day|:count days', (int) ceil($r['days']), ['count' => $num($r['days'])]) }}</span>
                                </span>
                                <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $statusTone[$r['status']] ?? 'bg-neutral-100 text-neutral-600' }}">{{ \Illuminate\Support\Str::headline($r['status']) }}</span>
                            </li>
                        @empty
                            <li class="text-neutral-500">{{ __('No leave requests yet.') }}</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>

        {{-- Payslips --}}
        <div class="{{ $card }}" data-test="portal-payslips">
            <h2 class="font-semibold text-zinc-900 dark:text-white">{{ __('My payslips') }}</h2>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-xs uppercase text-neutral-500">
                        <tr><th class="py-2">{{ __('Period') }}</th><th class="py-2">{{ __('Pay date') }}</th><th class="py-2 text-right">{{ __('Gross') }}</th><th class="py-2 text-right">{{ __('Deductions') }}</th><th class="py-2 text-right">{{ __('Net pay') }}</th><th class="py-2"></th></tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                        @forelse ($salary['payslips'] as $p)
                            <tr>
                                <td class="py-2 font-medium text-zinc-800 dark:text-zinc-200">{{ $p['period'] }}</td>
                                <td class="py-2 text-neutral-500">{{ \Carbon\CarbonImmutable::parse($p['pay_date'])->toFormattedDateString() }}</td>
                                <td class="py-2 text-right text-neutral-500">{{ $money($p['gross_cents']) }}</td>
                                <td class="py-2 text-right text-neutral-500">{{ $money($p['deductions_cents']) }}</td>
                                <td class="py-2 text-right font-semibold">{{ $p['currency'] }} {{ $money($p['net_pay_cents']) }}</td>
                                <td class="py-2 text-right"><a href="{{ route('payslips.pdf', $p['uuid']) }}" class="text-xs font-semibold text-brand hover:underline dark:text-highlight">{{ __('Download') }}</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-6 text-center text-neutral-500">{{ __('No payslips yet. They appear here once payroll is approved.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="{{ $card }}">
                <h2 class="font-semibold text-zinc-900 dark:text-white">{{ __('Announcements') }}</h2>
                <ul class="mt-3 space-y-2 text-sm">
                    @forelse ($data['announcements'] as $a)
                        <li class="flex items-start gap-2 text-zinc-700 dark:text-zinc-300"><flux:icon.megaphone variant="micro" class="mt-0.5 text-brand dark:text-highlight" /> {{ $a['title'] }}</li>
                    @empty
                        <li class="text-neutral-500">{{ __('Nothing new.') }}</li>
                    @endforelse
                </ul>
            </div>
            <div class="{{ $card }}">
                <h2 class="font-semibold text-zinc-900 dark:text-white">{{ __('Requests & documents') }}</h2>
                <dl class="mt-3 space-y-2.5 text-sm">
                    <div class="flex justify-between"><dt class="text-neutral-500">{{ __('Open HR requests') }}</dt><dd class="font-semibold">{{ $data['open_hr_requests'] }}</dd></div>
                    <div class="flex justify-between"><dt class="text-neutral-500">{{ __('Documents expiring in 30 days') }}</dt><dd class="font-semibold">{{ $data['documents_expiring_30d'] }}</dd></div>
                </dl>
            </div>
        </div>
    @endunless
</div>
