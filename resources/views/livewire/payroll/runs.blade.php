<div class="flex flex-col gap-4">
    <h1 class="text-xl font-semibold text-neutral-800 dark:text-neutral-100">{{ __('Payroll') }}</h1>

    @if ($error)
        <p class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-950 dark:text-red-300">{{ $error }}</p>
    @endif

    <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-neutral-800">
        <table class="w-full text-left text-sm">
            <thead class="bg-neutral-50 text-xs uppercase text-neutral-500 dark:bg-neutral-900">
                <tr>
                    <th class="px-4 py-3">{{ __('Period') }}</th>
                    <th class="px-4 py-3">{{ __('Pay date') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Net total') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                @forelse ($periods as $period)
                    @php($run = $period->runs->sortByDesc('id')->first())
                    <tr wire:key="pp-{{ $period->uuid }}">
                        <td class="px-4 py-3 font-medium">
                            {{ $period->name }}
                            @if ($period->isFinalSettlement())
                                <span class="ml-1 rounded-full bg-danger/10 px-2 py-0.5 text-xs font-medium text-danger">{{ __('Final pay') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-neutral-500">{{ $period->pay_date->toFormattedDateString() }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full bg-neutral-100 px-2 py-0.5 text-xs text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300">{{ $period->status->label() }}</span>
                        </td>
                        <td class="px-4 py-3 text-right">{{ $run ? number_format($run->net_cents / 100, 2) : '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex justify-end gap-2">
                                @if (! $run || $run->status->isMutable())
                                    <button wire:click="process('{{ $period->uuid }}')" class="rounded-lg border border-neutral-300 px-2 py-1 text-xs dark:border-neutral-700">{{ __('Run') }}</button>
                                @endif
                                @if ($run && $run->status->value === 'processed')
                                    <button wire:click="approve('{{ $run->uuid }}')" class="rounded-lg border border-neutral-300 px-2 py-1 text-xs dark:border-neutral-700">{{ __('Approve') }}</button>
                                @endif
                                @if ($run && $run->status->value === 'approved')
                                    <button wire:click="pay('{{ $run->uuid }}')" class="rounded-lg border border-neutral-300 px-2 py-1 text-xs dark:border-neutral-700">{{ __('Mark paid') }}</button>
                                @endif
                                @if ($run && ! $run->status->isMutable())
                                    <flux:dropdown>
                                        <button type="button" class="rounded-lg border border-neutral-300 px-2 py-1 text-xs dark:border-neutral-700">{{ __('Returns') }}</button>
                                        <flux:menu>
                                            <flux:menu.item href="{{ route('payroll-runs.statutory-returns.download', [$run, 'kra']) }}">{{ __('KRA iTax (PAYE + Housing Levy)') }}</flux:menu.item>
                                            <flux:menu.item href="{{ route('payroll-runs.statutory-returns.download', [$run, 'sha']) }}">{{ __('SHA (SHIF)') }}</flux:menu.item>
                                            <flux:menu.item href="{{ route('payroll-runs.statutory-returns.download', [$run, 'nssf']) }}">{{ __('NSSF') }}</flux:menu.item>
                                            <flux:menu.item href="{{ route('payroll-runs.statutory-returns.download', [$run, 'all']) }}">{{ __('All (zip)') }}</flux:menu.item>
                                        </flux:menu>
                                    </flux:dropdown>
                                @endif
                                @if ($run && in_array($run->status->value, ['approved', 'paid'], true))
                                    <button wire:click="lock('{{ $run->uuid }}')" class="rounded-lg border border-neutral-300 px-2 py-1 text-xs dark:border-neutral-700">{{ __('Lock') }}</button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @if ($run && ! empty($unpaidByRun[$run->id]))
                        <tr wire:key="pp-warn-{{ $period->uuid }}">
                            <td colspan="5" class="bg-warning/10 px-4 py-3 text-sm text-zinc-700 dark:text-zinc-200" data-test="zero-days-warning">
                                <span class="inline-flex items-center gap-1.5 font-semibold text-amber-700 dark:text-amber-300">
                                    <flux:icon.exclamation-triangle variant="mini" />
                                    {{ trans_choice(':count employee is paid for 0 days|:count employees are paid for 0 days', count($unpaidByRun[$run->id])) }}
                                </span>
                                — {{ $unpaidByRun[$run->id]->take(8)->implode(', ') }}{{ count($unpaidByRun[$run->id]) > 8 ? '…' : '' }}.
                                {{ __('Check their attendance records (or approved leave) before approving.') }}
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-neutral-500">{{ __('No payroll periods yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
