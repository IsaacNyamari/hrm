<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <flux:heading size="xl">{{ __('Expense claims') }}</flux:heading>
        @if ($hasEmployee)
            <flux:button variant="primary" wire:click="startCreate">{{ __('New claim') }}</flux:button>
        @endif
    </div>

    @unless ($hasEmployee)
        <flux:text class="rounded-xl border border-dashed border-neutral-300 p-8 text-center dark:border-neutral-700">
            {{ __('Your account is not linked to an employee record.') }}
        </flux:text>
    @else
        <flux:modal wire:model.self="showForm" class="max-w-md">
            <form wire:submit="createClaim" class="flex flex-col gap-4">
                <flux:heading size="lg">{{ __('New expense claim') }}</flux:heading>
                <flux:input wire:model="title" :label="__('Title')" placeholder="{{ __('e.g. Trip to Kisumu') }}" />
                <div class="flex justify-end gap-2">
                    <flux:button type="button" variant="ghost" wire:click="$set('showForm', false)">{{ __('Cancel') }}</flux:button>
                    <flux:button type="submit" variant="primary">{{ __('Create draft') }}</flux:button>
                </div>
            </form>
        </flux:modal>

        <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-800">
            <flux:heading size="sm">{{ __('My claims') }}</flux:heading>
            <table class="mt-2 w-full text-left text-sm">
                <thead class="text-xs uppercase text-neutral-500">
                    <tr><th class="py-1">{{ __('Ref') }}</th><th>{{ __('Title') }}</th><th>{{ __('Items') }}</th><th>{{ __('Total') }}</th><th>{{ __('Status') }}</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($mine as $claim)
                        <tr wire:key="claim-{{ $claim->uuid }}" class="border-t border-neutral-100 dark:border-neutral-800">
                            <td class="py-1.5 font-mono text-xs">{{ $claim->reference }}</td>
                            <td>{{ $claim->title }}</td>
                            <td>{{ $claim->items->count() }}</td>
                            <td>{{ number_format($claim->total_cents / 100, 2) }} {{ $claim->currency }}</td>
                            <td><flux:badge size="sm">{{ $claim->status->label() }}</flux:badge></td>
                            <td class="text-right space-x-2">
                                @if ($claim->status->isEditable())
                                    <flux:button size="sm" variant="primary" wire:click="submitClaim('{{ $claim->uuid }}')">{{ __('Submit') }}</flux:button>
                                    <flux:modal.trigger name="delete-claim-{{ $claim->uuid }}">
                                        <flux:button size="sm" variant="danger">{{ __('Delete') }}</flux:button>
                                    </flux:modal.trigger>
                                    <x-confirm-modal
                                        name="delete-claim-{{ $claim->uuid }}"
                                        :heading="__('Delete this draft claim?')"
                                        :confirm-label="__('Delete')"
                                        confirm-wire="deleteClaim('{{ $claim->uuid }}')" />
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-2 text-neutral-500">{{ __('No claims yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            <flux:text size="sm" class="mt-2 text-neutral-400">{{ __('Add line items (with receipts) to a draft claim through the expense-claims API, then submit here.') }}</flux:text>
        </div>

        @if ($toReview->isNotEmpty())
            <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-800">
                <flux:heading size="sm">{{ __('Awaiting my decision') }}</flux:heading>
                <ul class="mt-2 space-y-2 text-sm">
                    @foreach ($toReview as $claim)
                        <li wire:key="review-{{ $claim->uuid }}" class="flex items-center justify-between">
                            <span class="text-neutral-600 dark:text-neutral-400">{{ $claim->reference }} — {{ $claim->employee->fullName() }} — {{ $claim->title }}</span>
                            <span class="flex items-center gap-2">
                                <span class="font-medium">{{ number_format($claim->total_cents / 100, 2) }}</span>
                                <flux:button size="sm" variant="primary" wire:click="approveClaim('{{ $claim->uuid }}')">{{ __('Approve') }}</flux:button>
                                <flux:modal.trigger name="reject-claim-{{ $claim->uuid }}">
                                    <flux:button size="sm" variant="danger">{{ __('Reject') }}</flux:button>
                                </flux:modal.trigger>
                                <x-confirm-modal
                                    name="reject-claim-{{ $claim->uuid }}"
                                    :heading="__('Reject this claim?')"
                                    :confirm-label="__('Reject')"
                                    confirm-wire="rejectClaim('{{ $claim->uuid }}')" />
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($canManage && $toReimburse->isNotEmpty())
            <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-800">
                <flux:heading size="sm">{{ __('Approved — ready to reimburse') }}</flux:heading>
                <ul class="mt-2 space-y-2 text-sm">
                    @foreach ($toReimburse as $claim)
                        <li wire:key="reimburse-{{ $claim->uuid }}" class="flex items-center justify-between">
                            <span class="text-neutral-600 dark:text-neutral-400">{{ $claim->reference }} — {{ $claim->employee->fullName() }} — {{ $claim->title }}</span>
                            <span class="flex items-center gap-2">
                                <span class="font-medium">{{ number_format($claim->total_cents / 100, 2) }}</span>
                                <flux:button size="sm" variant="primary" wire:click="reimburseClaim('{{ $claim->uuid }}')">{{ __('Mark reimbursed') }}</flux:button>
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endunless
</div>
