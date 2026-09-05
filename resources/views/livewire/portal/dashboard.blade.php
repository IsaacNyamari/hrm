<div class="flex flex-col gap-6">
    <h1 class="text-xl font-semibold text-neutral-900 dark:text-neutral-100">{{ __('My HR') }}</h1>

    @unless ($hasEmployee)
        <p class="rounded-xl border border-dashed border-neutral-300 p-8 text-center text-neutral-500 dark:border-neutral-700">
            {{ __('Your account is not linked to an employee record. Ask your HR team to link it.') }}
        </p>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-800">
                <p class="text-xs uppercase text-neutral-500">{{ __('Today') }}</p>
                <p class="mt-1 text-lg font-semibold text-neutral-900 dark:text-neutral-100">
                    @if ($data['attendance_today']['clocked_in'])
                        {{ __('Clocked in') }}
                    @elseif ($data['attendance_today']['status'])
                        {{ \Illuminate\Support\Str::headline($data['attendance_today']['status']) }}
                    @else
                        {{ __('Not clocked in') }}
                    @endif
                </p>
                <a href="{{ route('attendance.index') }}" class="text-xs text-neutral-500 underline">{{ __('Time clock') }}</a>
            </div>

            <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-800">
                <p class="text-xs uppercase text-neutral-500">{{ __('Pending leave') }}</p>
                <p class="mt-1 text-2xl font-semibold text-neutral-900 dark:text-neutral-100">{{ $data['pending_leave'] }}</p>
                <a href="{{ route('leave') }}" class="text-xs text-neutral-500 underline">{{ __('Leave') }}</a>
            </div>

            <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-800">
                <p class="text-xs uppercase text-neutral-500">{{ __('Open HR requests') }}</p>
                <p class="mt-1 text-2xl font-semibold text-neutral-900 dark:text-neutral-100">{{ $data['open_hr_requests'] }}</p>
            </div>

            <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-800">
                <p class="text-xs uppercase text-neutral-500">{{ __('Documents expiring') }}</p>
                <p class="mt-1 text-2xl font-semibold text-neutral-900 dark:text-neutral-100">{{ $data['documents_expiring_30d'] }}</p>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-800">
                <h2 class="text-sm font-semibold">{{ __('Leave balances') }}</h2>
                <ul class="mt-2 space-y-1 text-sm">
                    @foreach ($data['leave_balances'] as $b)
                        <li class="flex justify-between"><span class="text-neutral-500">{{ $b['type'] }}</span><span class="font-medium">{{ $b['remaining'] }}</span></li>
                    @endforeach
                </ul>
            </div>

            <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-800">
                <h2 class="text-sm font-semibold">{{ __('Recent payslips') }}</h2>
                <ul class="mt-2 space-y-1 text-sm">
                    @forelse ($data['recent_payslips'] as $p)
                        <li class="flex justify-between">
                            <span class="text-neutral-500">{{ $p['period'] }}</span>
                            <span class="font-medium">{{ number_format($p['net_pay_cents'] / 100, 2) }}</span>
                        </li>
                    @empty
                        <li class="text-neutral-500">{{ __('No payslips yet.') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-800">
            <h2 class="text-sm font-semibold">{{ __('Announcements') }}</h2>
            <ul class="mt-2 space-y-2 text-sm">
                @forelse ($data['announcements'] as $a)
                    <li class="text-neutral-700 dark:text-neutral-300">{{ $a['title'] }}</li>
                @empty
                    <li class="text-neutral-500">{{ __('Nothing new.') }}</li>
                @endforelse
            </ul>
        </div>
    @endunless
</div>
