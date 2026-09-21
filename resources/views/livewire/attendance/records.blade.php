<div class="flex flex-col gap-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <flux:heading size="lg">{{ __('Attendance records') }}</flux:heading>
        <flux:button variant="primary" wire:click="startCreate">{{ __('Add record') }}</flux:button>
    </div>

    <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-neutral-800">
        <table class="w-full text-left text-sm">
            <thead class="bg-neutral-50 text-xs uppercase text-neutral-500 dark:bg-neutral-900">
                <tr>
                    <th class="px-4 py-3">{{ __('Date') }}</th>
                    <th class="px-4 py-3">{{ __('Employee') }}</th>
                    <th class="px-4 py-3">{{ __('Clock in') }}</th>
                    <th class="px-4 py-3">{{ __('Clock out') }}</th>
                    <th class="px-4 py-3">{{ __('Worked') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                @forelse ($records as $record)
                    <tr wire:key="att-{{ $record->uuid }}">
                        <td class="px-4 py-3">{{ $record->work_date->toDateString() }}</td>
                        <td class="px-4 py-3 font-medium">{{ $record->employee->fullName() }}</td>
                        <td class="px-4 py-3 text-neutral-500">{{ $record->clock_in_at?->format('H:i') ?? '—' }}</td>
                        <td class="px-4 py-3 text-neutral-500">{{ $record->clock_out_at?->format('H:i') ?? '—' }}</td>
                        <td class="px-4 py-3 text-neutral-500">{{ $record->worked_minutes !== null ? gmdate('H:i', $record->worked_minutes * 60) : '—' }}</td>
                        <td class="px-4 py-3"><flux:badge size="sm">{{ $record->status->label() }}</flux:badge></td>
                        <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                            <flux:button size="sm" variant="ghost" wire:click="startEdit('{{ $record->uuid }}')">{{ __('Edit') }}</flux:button>
                            <flux:modal.trigger name="delete-attendance-{{ $record->uuid }}">
                                <flux:button size="sm" variant="danger">{{ __('Delete') }}</flux:button>
                            </flux:modal.trigger>
                            <x-confirm-modal
                                name="delete-attendance-{{ $record->uuid }}"
                                :heading="__('Delete this attendance record?')"
                                :confirm-label="__('Delete')"
                                confirm-wire="remove('{{ $record->uuid }}')" />
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-neutral-500">{{ __('No attendance records yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $records->links() }}</div>

    <flux:modal wire:model.self="showForm" class="max-w-lg">
        <form wire:submit="save" class="flex flex-col gap-4">
            <flux:heading size="lg">{{ $editingUuid ? __('Edit attendance record') : __('New attendance record') }}</flux:heading>

            <flux:select wire:model="form.employee_id" :label="__('Employee')" :disabled="$editingUuid !== null">
                <flux:select.option value="">{{ __('Select…') }}</flux:select.option>
                @foreach ($employees as $emp)
                    <flux:select.option value="{{ $emp->id }}">{{ $emp->first_name }} {{ $emp->last_name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input type="date" wire:model="form.work_date" :label="__('Work date')" :disabled="$editingUuid !== null" />

            <div class="grid grid-cols-2 gap-3">
                <flux:input type="datetime-local" wire:model="form.clock_in_at" :label="__('Clock in')" />
                <flux:input type="datetime-local" wire:model="form.clock_out_at" :label="__('Clock out')" />
                <flux:input type="number" wire:model="form.break_minutes" :label="__('Break (minutes)')" />
                <flux:select wire:model="form.status" :label="__('Status')">
                    @foreach ($statuses as $s)
                        <flux:select.option value="{{ $s->value }}">{{ $s->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <flux:textarea wire:model="form.notes" :label="__('Notes')" rows="2" />

            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showForm', false)">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ $editingUuid ? __('Save changes') : __('Create record') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
