<div class="flex flex-col gap-6">
    <flux:heading size="xl">{{ __('Leave') }}</flux:heading>

    @unless ($hasEmployee || $canManageTypes)
        <flux:text class="rounded-xl border border-dashed border-neutral-300 p-8 text-center dark:border-neutral-700">
            {{ __('Your account is not linked to an employee record.') }}
        </flux:text>
    @else
        @if ($hasEmployee)
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($balances as $b)
                    <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-800">
                        <p class="text-xs uppercase text-neutral-500">{{ $b['name'] }}</p>
                        <p class="mt-1 text-2xl font-semibold text-neutral-800 dark:text-neutral-100">{{ $b['remaining'] }}</p>
                        <p class="text-xs text-neutral-500">{{ $b['used'] }} {{ __('used') }} · {{ $b['pending'] }} {{ __('pending') }}</p>
                    </div>
                @endforeach
            </div>

            <form wire:submit="submit" class="grid gap-3 rounded-xl border border-neutral-200 p-4 dark:border-neutral-800 md:grid-cols-2">
                <flux:heading size="sm" class="md:col-span-2">{{ __('Request leave') }}</flux:heading>

                <flux:select wire:model="leaveTypeUuid" :label="__('Leave type')">
                    <flux:select.option value="">{{ __('Select…') }}</flux:select.option>
                    @foreach ($leaveTypes as $type)
                        <flux:select.option value="{{ $type->uuid }}">{{ $type->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="reason" :label="__('Reason')" />
                <flux:input type="date" wire:model="startDate" :label="__('Start date')" />
                <flux:input type="date" wire:model="endDate" :label="__('End date')" />

                <div class="flex items-center gap-4 text-sm md:col-span-2">
                    <flux:field variant="inline">
                        <flux:checkbox wire:model="halfDayStart" />
                        <flux:label>{{ __('Half day (start)') }}</flux:label>
                    </flux:field>
                    <flux:field variant="inline">
                        <flux:checkbox wire:model="halfDayEnd" />
                        <flux:label>{{ __('Half day (end)') }}</flux:label>
                    </flux:field>
                </div>

                <div class="md:col-span-2">
                    <flux:button type="submit" variant="primary">{{ __('Submit request') }}</flux:button>
                </div>
            </form>

            <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-neutral-800">
                <table class="w-full text-left text-sm">
                    <thead class="bg-neutral-50 text-xs uppercase text-neutral-500 dark:bg-neutral-900">
                        <tr>
                            <th class="px-4 py-3">{{ __('Type') }}</th>
                            <th class="px-4 py-3">{{ __('Dates') }}</th>
                            <th class="px-4 py-3">{{ __('Days') }}</th>
                            <th class="px-4 py-3">{{ __('Status') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                        @forelse ($requests as $r)
                            <tr wire:key="lr-{{ $r->uuid }}">
                                <td class="px-4 py-3">{{ $r->leaveType->name }}</td>
                                <td class="px-4 py-3 text-neutral-500">{{ $r->start_date->toFormattedDateString() }} – {{ $r->end_date->toFormattedDateString() }}</td>
                                <td class="px-4 py-3">{{ $r->days }}</td>
                                <td class="px-4 py-3"><x-status-badge :status="$r->status" /></td>
                                <td class="px-4 py-3 text-right">
                                    @if ($r->status->value === 'pending' || ($r->status->value === 'approved' && $r->start_date->isFuture()))
                                        <flux:modal.trigger name="cancel-leave-{{ $r->uuid }}">
                                            <flux:button size="sm" variant="danger">{{ __('Cancel') }}</flux:button>
                                        </flux:modal.trigger>
                                        <x-confirm-modal
                                            name="cancel-leave-{{ $r->uuid }}"
                                            :heading="__('Cancel this leave request?')"
                                            :confirm-label="__('Cancel request')"
                                            confirm-wire="cancelRequest('{{ $r->uuid }}')" />
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-10 text-center text-neutral-500">{{ __('No leave requests yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif

        @if ($canOversee)
            <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-800">
                <flux:heading size="sm">{{ __('Awaiting my decision') }}</flux:heading>
                <ul class="mt-2 space-y-2 text-sm">
                    @forelse ($toReview as $r)
                        <li wire:key="review-{{ $r->uuid }}" class="flex items-center justify-between">
                            <span class="text-neutral-600 dark:text-neutral-400">
                                {{ $r->employee->fullName() }} — {{ $r->leaveType->name }} — {{ $r->start_date->toFormattedDateString() }} – {{ $r->end_date->toFormattedDateString() }} ({{ $r->days }} {{ __('days') }})
                            </span>
                            <span class="flex items-center gap-2">
                                <flux:button size="sm" variant="primary" color="green" icon="check" wire:click="approve('{{ $r->uuid }}')">{{ __('Approve') }}</flux:button>
                                <flux:modal.trigger name="reject-leave-{{ $r->uuid }}">
                                    <flux:button size="sm" variant="danger">{{ __('Reject') }}</flux:button>
                                </flux:modal.trigger>
                                <x-confirm-modal
                                    name="reject-leave-{{ $r->uuid }}"
                                    :heading="__('Reject this leave request?')"
                                    :confirm-label="__('Reject')"
                                    confirm-wire="reject('{{ $r->uuid }}')" />
                            </span>
                        </li>
                    @empty
                        <li class="text-neutral-500">{{ __('Nothing awaiting your decision.') }}</li>
                    @endforelse
                </ul>
            </div>
        @endif

        @if ($canManageTypes)
            <div class="flex items-center justify-between">
                <flux:heading size="sm">{{ __('Leave types') }}</flux:heading>
                <flux:button variant="primary" wire:click="startCreateType">{{ __('Add leave type') }}</flux:button>
            </div>

            <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-neutral-800">
                <table class="w-full text-left text-sm">
                    <thead class="bg-neutral-50 text-xs uppercase text-neutral-500 dark:bg-neutral-900">
                        <tr>
                            <th class="px-4 py-3">{{ __('Name') }}</th>
                            <th class="px-4 py-3">{{ __('Code') }}</th>
                            <th class="px-4 py-3">{{ __('Days/year') }}</th>
                            <th class="px-4 py-3">{{ __('Accrual') }}</th>
                            <th class="px-4 py-3">{{ __('Active') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                        @forelse ($allTypes as $type)
                            <tr wire:key="type-{{ $type->uuid }}">
                                <td class="px-4 py-3 font-medium">{{ $type->name }}</td>
                                <td class="px-4 py-3 font-mono text-xs">{{ $type->code }}</td>
                                <td class="px-4 py-3">{{ $type->default_days_per_year }}</td>
                                <td class="px-4 py-3 text-neutral-500">{{ $type->accrual->label() }}</td>
                                <td class="px-4 py-3">{{ $type->is_active ? __('Yes') : __('No') }}</td>
                                <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                                    <flux:button size="sm" variant="ghost" wire:click="startEditType('{{ $type->uuid }}')">{{ __('Edit') }}</flux:button>
                                    <flux:modal.trigger name="delete-leave-type-{{ $type->uuid }}">
                                        <flux:button size="sm" variant="danger">{{ __('Delete') }}</flux:button>
                                    </flux:modal.trigger>
                                    <x-confirm-modal
                                        name="delete-leave-type-{{ $type->uuid }}"
                                        :heading="__('Delete :name?', ['name' => $type->name])"
                                        :confirm-label="__('Delete')"
                                        confirm-wire="removeType('{{ $type->uuid }}')" />
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-6 text-center text-neutral-500">{{ __('No leave types yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <flux:modal wire:model.self="showTypeForm" class="max-w-lg">
                <form wire:submit="saveType" class="flex flex-col gap-4">
                    <flux:heading size="lg">{{ $editingTypeUuid ? __('Edit leave type') : __('New leave type') }}</flux:heading>
                    <div class="grid grid-cols-2 gap-4">
                        <flux:input wire:model="typeForm.name" :label="__('Name')" />
                        <flux:input wire:model="typeForm.code" :label="__('Code')" />
                        <flux:input type="number" step="0.5" wire:model="typeForm.default_days_per_year" :label="__('Days per year')" />
                        <flux:select wire:model="typeForm.accrual" :label="__('Accrual')">
                            @foreach ($accruals as $a)
                                <flux:select.option value="{{ $a->value }}">{{ $a->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                    <div class="flex flex-wrap gap-4">
                        <flux:field variant="inline">
                            <flux:checkbox wire:model="typeForm.is_paid" />
                            <flux:label>{{ __('Paid') }}</flux:label>
                        </flux:field>
                        <flux:field variant="inline">
                            <flux:checkbox wire:model="typeForm.requires_approval" />
                            <flux:label>{{ __('Requires approval') }}</flux:label>
                        </flux:field>
                        <flux:field variant="inline">
                            <flux:checkbox wire:model="typeForm.is_active" />
                            <flux:label>{{ __('Active') }}</flux:label>
                        </flux:field>
                    </div>
                    <div class="flex justify-end gap-2">
                        <flux:button type="button" variant="ghost" wire:click="$set('showTypeForm', false)">{{ __('Cancel') }}</flux:button>
                        <flux:button type="submit" variant="primary">{{ $editingTypeUuid ? __('Save changes') : __('Create leave type') }}</flux:button>
                    </div>
                </form>
            </flux:modal>
        @endif
    @endunless
</div>
