@php($money = fn (int $cents) => number_format($cents / 100, 2))
<div class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-700" data-test="earnings-to-date">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="flex items-center gap-3">
            <span class="grid size-10 place-items-center rounded-lg bg-success/10 text-success"><flux:icon.arrow-trending-up variant="mini" /></span>
            <div>
                <h2 class="font-semibold text-zinc-900 dark:text-white">{{ __('Earned so far') }}</h2>
                <p class="text-sm text-zinc-500">
                    {{ $period->name }} · {{ __('day :elapsed of :total', ['elapsed' => $daysElapsed, 'total' => $daysInCycle]) }} · {{ __('from attendance up to today') }}
                </p>
            </div>
        </div>
        <div class="text-right">
            <p class="text-xs uppercase tracking-wide text-zinc-500">{{ __('Total accrued') }}</p>
            <p class="text-2xl font-bold text-zinc-900 dark:text-white" data-test="accrued-total">{{ $currency }} {{ $money($totalEarned) }}</p>
            @if ($totalOvertime > 0)
                <p class="text-xs text-zinc-500">{{ __('incl. :amount overtime', ['amount' => $currency.' '.$money($totalOvertime)]) }}</p>
            @endif
        </div>
    </div>

    <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-neutral-100 dark:bg-neutral-800">
        <div class="h-full rounded-full bg-brand" style="width: {{ $daysInCycle > 0 ? round($daysElapsed / $daysInCycle * 100) : 0 }}%"></div>
    </div>

    <div class="mt-5 flex items-center justify-between gap-4">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search employees')" class="max-w-xs" size="sm" />
        <p class="hidden text-xs text-zinc-500 sm:block">{{ __('Gross before deductions. The payroll run works out tax and net pay.') }}</p>
    </div>

    <div class="mt-3 max-h-[28rem] overflow-y-auto rounded-lg border border-neutral-200 dark:border-neutral-800">
        <table class="w-full text-left text-sm">
            <thead class="sticky top-0 bg-neutral-50 text-xs uppercase text-neutral-500 dark:bg-neutral-900">
                <tr>
                    <th class="px-4 py-3">{{ __('Employee') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Days paid') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Overtime') }}</th>
                    <th class="px-4 py-3">{{ __('Of monthly basic') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Earned so far') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                @forelse ($rows as $row)
                    @php($share = $row['full'] > 0 ? min(100, round($row['earned'] / $row['full'] * 100)) : 0)
                    <tr wire:key="accrual-{{ $row['employee']->uuid }}">
                        <td class="px-4 py-3 font-medium text-zinc-900 dark:text-white">{{ $row['employee']->fullName() }}</td>
                        <td class="px-4 py-3 text-right text-zinc-600 dark:text-zinc-300">{{ rtrim(rtrim(number_format($row['payable_days'], 1), '0'), '.') }}</td>
                        <td class="px-4 py-3 text-right text-zinc-600 dark:text-zinc-300">{{ $row['overtime_hours'] > 0 ? rtrim(rtrim(number_format($row['overtime_hours'], 1), '0'), '.').' h' : '—' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <div class="h-1.5 w-24 overflow-hidden rounded-full bg-neutral-100 dark:bg-neutral-800">
                                    <div class="h-full rounded-full bg-success" style="width: {{ $share }}%"></div>
                                </div>
                                <span class="text-xs text-zinc-500">{{ $share }}%</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right font-semibold text-zinc-900 dark:text-white">{{ $money($row['earned']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-neutral-500">{{ __('No active employees.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
