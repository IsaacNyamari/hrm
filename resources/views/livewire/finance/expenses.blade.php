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
                            <td><x-status-badge :status="$claim->status" /></td>
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
                        @if ($claim->items->isNotEmpty() || $claim->status->isEditable())
                            <tr wire:key="claim-items-{{ $claim->uuid }}">
                                <td></td>
                                <td colspan="5" class="pb-3">
                                    <ul class="space-y-1 text-xs text-neutral-600 dark:text-neutral-400">
                                        @foreach ($claim->items as $lineItem)
                                            <li wire:key="item-{{ $lineItem->uuid }}" class="flex items-center justify-between gap-2">
                                                <span>{{ $lineItem->spent_on->format('d M') }} · {{ $lineItem->description }} ({{ $lineItem->category->value }}) — {{ number_format($lineItem->amount_cents / 100, 2) }}
                                                    @if ($lineItem->receipt_path) · <a class="underline" href="{{ route('expense-items.receipt', $lineItem) }}">{{ __('Receipt') }}</a> @endif
                                                </span>
                                                @if ($claim->status->isEditable())
                                                    <button type="button" class="text-red-600" wire:click="removeItem('{{ $lineItem->uuid }}')" wire:confirm="{{ __('Remove this line item?') }}">{{ __('Remove') }}</button>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                    @if ($claim->status->isEditable())
                                        <flux:button size="xs" class="mt-2" wire:click="startAddItem('{{ $claim->uuid }}')">{{ __('Add line item') }}</flux:button>
                                    @endif
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr><td colspan="6" class="py-2 text-neutral-500">{{ __('No claims yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            <flux:text size="sm" class="mt-2 text-neutral-400">{{ __('Add a line item (with its receipt) for each expense, then submit the claim.') }}</flux:text>
        </div>

        <flux:modal name="expense-item" class="max-w-md">
            <form wire:submit="saveItem" class="flex flex-col gap-4">
                <flux:heading size="lg">{{ __('Add line item') }}</flux:heading>
                <flux:input wire:model="item.description" :label="__('Description')" required />
                <flux:select wire:model.live="item.category" :label="__('Category')">
                    @foreach ($categories as $category)
                        <flux:select.option value="{{ $category->value }}">{{ ucwords(str_replace('_', ' ', $category->value)) }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input type="date" wire:model="item.spent_on" :label="__('Date spent')" required />
                @if ($item['category'] === 'mileage')
                    <flux:input type="number" step="0.1" min="0" wire:model="item.quantity" :label="__('Distance (km)')" required />
                @else
                    <flux:input type="number" step="0.01" min="0" wire:model="item.amount" :label="__('Amount')" required />
                @endif
                <flux:textarea wire:model="item.notes" :label="__('Notes')" rows="2" />
                <div>
                    <flux:input type="file" wire:model="receipt" :label="__('Receipt (PDF, JPG or PNG)')" accept=".pdf,.jpg,.jpeg,.png" />
                    @error('receipt')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                @foreach (['item.description', 'item.spent_on', 'item.amount', 'item.quantity'] as $field)
                    @error($field)<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                @endforeach
                <div class="flex justify-end gap-2">
                    <flux:modal.close><flux:button type="button" variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="primary">{{ __('Add') }}</flux:button>
                </div>
            </form>
        </flux:modal>

        @if ($toReview->isNotEmpty())
            <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-800">
                <flux:heading size="sm">{{ __('Awaiting my decision') }}</flux:heading>
                <ul class="mt-2 space-y-2 text-sm">
                    @foreach ($toReview as $claim)
                        <li wire:key="review-{{ $claim->uuid }}" class="flex items-center justify-between">
                            <span class="text-neutral-600 dark:text-neutral-400">{{ $claim->reference }} — {{ $claim->employee->fullName() }} — {{ $claim->title }}</span>
                            <span class="flex items-center gap-2">
                                <span class="font-medium">{{ number_format($claim->total_cents / 100, 2) }}</span>
                                <flux:button size="sm" variant="primary" color="green" icon="check" wire:click="approveClaim('{{ $claim->uuid }}')">{{ __('Approve') }}</flux:button>
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
