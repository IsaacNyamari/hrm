<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <flux:heading size="xl">{{ __('Loans & advances') }}</flux:heading>
        @if ($hasEmployee || $canManage)
            <flux:button variant="primary" wire:click="startRequest">{{ __('Request loan') }}</flux:button>
        @endif
    </div>

    @unless ($hasEmployee || $canManage)
        <flux:text class="rounded-xl border border-dashed border-neutral-300 p-8 text-center dark:border-neutral-700">
            {{ __('Your account is not linked to an employee record.') }}
        </flux:text>
    @else
        <flux:modal wire:model.self="showForm" class="max-w-lg">
            <form wire:submit="request" class="flex flex-col gap-4">
                <flux:heading size="lg">{{ __('Request a loan or advance') }}</flux:heading>

                @if ($canManage)
                    <flux:select wire:model="employeeId" :label="__('For')">
                        <flux:select.option value="">{{ __('Myself') }}</flux:select.option>
                        @foreach ($employees as $emp)
                            <flux:select.option value="{{ $emp->id }}">{{ $emp->first_name }} {{ $emp->last_name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @endif

                <flux:select wire:model="type" :label="__('Type')">
                    @foreach ($types as $t)
                        <flux:select.option value="{{ $t->value }}">{{ $t->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <div class="grid grid-cols-2 gap-3">
                    <flux:input type="number" step="0.01" wire:model="amount" :label="__('Amount')" />
                    <flux:input type="number" wire:model="installments" :label="__('Months')" />
                </div>

                <div class="flex justify-end gap-2">
                    <flux:button type="button" variant="ghost" wire:click="$set('showForm', false)">{{ __('Cancel') }}</flux:button>
                    <flux:button type="submit" variant="primary">{{ __('Submit request') }}</flux:button>
                </div>
            </form>
        </flux:modal>

        @if ($hasEmployee)
            <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-800">
                <flux:heading size="sm">{{ __('My loans') }}</flux:heading>
                <table class="mt-2 w-full text-left text-sm">
                    <thead class="text-xs uppercase text-neutral-500">
                        <tr><th class="py-1">{{ __('Type') }}</th><th>{{ __('Principal') }}</th><th>{{ __('Installment') }}</th><th>{{ __('Outstanding') }}</th><th>{{ __('Status') }}</th><th></th></tr>
                    </thead>
                    <tbody>
                        @forelse ($mine as $loan)
                            <tr wire:key="mine-{{ $loan->uuid }}" class="border-t border-neutral-100 dark:border-neutral-800">
                                <td class="py-1.5">{{ $loan->type->label() }}</td>
                                <td>{{ number_format($loan->principal_cents / 100, 2) }}</td>
                                <td>{{ number_format($loan->installment_cents / 100, 2) }}</td>
                                <td>{{ number_format($loan->outstanding_cents / 100, 2) }}</td>
                                <td><flux:badge size="sm">{{ $loan->status->label() }}</flux:badge></td>
                                <td class="text-right">
                                    @if ($loan->status->value === 'pending')
                                        <flux:modal.trigger name="cancel-loan-{{ $loan->uuid }}">
                                            <flux:button size="sm" variant="danger">{{ __('Cancel') }}</flux:button>
                                        </flux:modal.trigger>
                                        <x-confirm-modal
                                            name="cancel-loan-{{ $loan->uuid }}"
                                            :heading="__('Cancel this loan request?')"
                                            :confirm-label="__('Cancel request')"
                                            confirm-wire="cancel('{{ $loan->uuid }}')" />
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-2 text-neutral-500">{{ __('No loans yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif

        @if ($canOversee)
            <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-800">
                <flux:heading size="sm">{{ __('Pending approval') }}</flux:heading>
                <table class="mt-2 w-full text-left text-sm">
                    <thead class="text-xs uppercase text-neutral-500">
                        <tr><th class="py-1">{{ __('Employee') }}</th><th>{{ __('Type') }}</th><th>{{ __('Principal') }}</th><th></th></tr>
                    </thead>
                    <tbody>
                        @forelse ($pending as $loan)
                            <tr wire:key="pending-{{ $loan->uuid }}" class="border-t border-neutral-100 dark:border-neutral-800">
                                <td class="py-1.5">{{ $loan->employee->fullName() }}</td>
                                <td>{{ $loan->type->label() }}</td>
                                <td>{{ number_format($loan->principal_cents / 100, 2) }}</td>
                                <td class="text-right space-x-2">
                                    <flux:button size="sm" variant="primary" wire:click="approve('{{ $loan->uuid }}')">{{ __('Approve') }}</flux:button>
                                    <flux:modal.trigger name="reject-loan-{{ $loan->uuid }}">
                                        <flux:button size="sm" variant="danger">{{ __('Reject') }}</flux:button>
                                    </flux:modal.trigger>
                                    <x-confirm-modal
                                        name="reject-loan-{{ $loan->uuid }}"
                                        :heading="__('Reject this loan request?')"
                                        :confirm-label="__('Reject')"
                                        confirm-wire="cancel('{{ $loan->uuid }}')" />
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-2 text-neutral-500">{{ __('Nothing awaiting approval.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-800">
                <flux:heading size="sm">{{ __('All loans') }}</flux:heading>
                <table class="mt-2 w-full text-left text-sm">
                    <thead class="text-xs uppercase text-neutral-500">
                        <tr><th class="py-1">{{ __('Employee') }}</th><th>{{ __('Type') }}</th><th>{{ __('Principal') }}</th><th>{{ __('Outstanding') }}</th><th>{{ __('Status') }}</th><th></th></tr>
                    </thead>
                    <tbody>
                        @forelse ($all as $loan)
                            <tr wire:key="all-{{ $loan->uuid }}" class="border-t border-neutral-100 dark:border-neutral-800">
                                <td class="py-1.5">{{ $loan->employee->fullName() }}</td>
                                <td>{{ $loan->type->label() }}</td>
                                <td>{{ number_format($loan->principal_cents / 100, 2) }}</td>
                                <td>{{ number_format($loan->outstanding_cents / 100, 2) }}</td>
                                <td><flux:badge size="sm">{{ $loan->status->label() }}</flux:badge></td>
                                <td class="text-right">
                                    @if ($loan->status->value === 'pending' && $loan->outstanding_cents === $loan->principal_cents)
                                        <flux:modal.trigger name="delete-loan-{{ $loan->uuid }}">
                                            <flux:button size="sm" variant="danger">{{ __('Delete') }}</flux:button>
                                        </flux:modal.trigger>
                                        <x-confirm-modal
                                            name="delete-loan-{{ $loan->uuid }}"
                                            :heading="__('Delete this loan permanently?')"
                                            :text="__('This cannot be undone.')"
                                            :confirm-label="__('Delete')"
                                            confirm-wire="remove('{{ $loan->uuid }}')" />
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-2 text-neutral-500">{{ __('No loans yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    @endunless
</div>
