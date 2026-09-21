<div id="final-settlements" class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-700" data-test="final-settlements">
    <div class="flex items-center gap-3">
        <span class="grid size-10 place-items-center rounded-lg bg-danger/10 text-danger"><flux:icon.user-minus variant="mini" /></span>
        <div>
            <h2 class="font-semibold text-zinc-900 dark:text-white">{{ __('Final settlements') }}</h2>
            <p class="text-sm text-zinc-500">{{ __('Pay leavers what they have earned up to their exit date, plus untaken annual leave, less tax and outstanding loans.') }}</p>
        </div>
    </div>

    @if ($error)
        <p class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-950 dark:text-red-300">{{ $error }}</p>
    @endif

    <div class="mt-4 overflow-x-auto rounded-lg border border-neutral-200 dark:border-neutral-800">
        <table class="w-full text-left text-sm">
            <thead class="bg-neutral-50 text-xs uppercase text-neutral-500 dark:bg-neutral-900">
                <tr>
                    <th class="px-4 py-3">{{ __('Employee') }}</th>
                    <th class="px-4 py-3">{{ __('Reason') }}</th>
                    <th class="px-4 py-3">{{ __('Exit date') }}</th>
                    <th class="px-4 py-3">{{ __('Settlement') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Net pay') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                @forelse ($exited as $employee)
                    @php($period = $settlements->get($employee->id))
                    @php($run = $period?->runs->first())
                    @php($payslip = $run?->payslips->firstWhere('employee_id', $employee->id))
                    <tr wire:key="settle-{{ $employee->uuid }}">
                        <td class="px-4 py-3 font-medium text-zinc-900 dark:text-white">{{ $employee->fullName() }}</td>
                        <td class="px-4 py-3"><x-status-badge :status="$employee->status" /></td>
                        <td class="px-4 py-3 text-neutral-500">{{ $employee->exit_date->toFormattedDateString() }}</td>
                        <td class="px-4 py-3">
                            @if ($run)
                                <span class="rounded-full bg-neutral-100 px-2 py-0.5 text-xs text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300">{{ $run->status->label() }}</span>
                            @else
                                <span class="text-xs text-amber-700 dark:text-amber-300">{{ __('Not prepared') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right font-semibold">{{ $payslip ? number_format($payslip->net_pay_cents / 100, 2) : '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex justify-end gap-2">
                                @if ($payslip)
                                    <a href="{{ route('payslips.pdf', $payslip) }}" target="_blank" class="rounded-lg border border-neutral-300 px-2 py-1 text-xs dark:border-neutral-700">{{ __('Payslip') }}</a>
                                @endif
                                @if ($canPrepare && (! $run || $run->status->isMutable()))
                                    <button wire:click="prepare('{{ $employee->uuid }}')" wire:loading.attr="disabled" class="rounded-lg bg-brand px-2 py-1 text-xs font-medium text-white">
                                        {{ $run ? __('Recalculate') : __('Prepare final pay') }}
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-neutral-500">{{ __('No one has left in the past year. Set an employee to terminated, resigned or retired with an exit date to settle their pay.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
