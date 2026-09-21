<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <flux:heading size="xl">{{ __('Tasks') }}</flux:heading>
        @if ($canAssign)
            <flux:button variant="primary" wire:click="startCreate">{{ __('Assign task') }}</flux:button>
        @endif
    </div>

    @unless ($hasEmployee || $canOversee)
        <flux:text class="rounded-xl border border-dashed border-neutral-300 p-8 text-center dark:border-neutral-700">
            {{ __('Your account is not linked to an employee record.') }}
        </flux:text>
    @else
        @if ($hasEmployee)
            <div>
                <flux:heading size="sm" class="mb-2">{{ __('My tasks') }}</flux:heading>
                <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-neutral-800">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-neutral-50 text-xs uppercase text-neutral-500 dark:bg-neutral-900">
                            <tr>
                                <th class="px-4 py-3">{{ __('Title') }}</th>
                                <th class="px-4 py-3">{{ __('Priority') }}</th>
                                <th class="px-4 py-3">{{ __('Due') }}</th>
                                <th class="px-4 py-3">{{ __('Status') }}</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                            @forelse ($myTasks as $t)
                                <tr wire:key="my-task-{{ $t->uuid }}">
                                    <td class="px-4 py-3">
                                        <p class="font-medium">{{ $t->title }}</p>
                                        @if ($t->description)
                                            <p class="text-xs text-neutral-500">{{ \Illuminate\Support\Str::limit($t->description, 80) }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">{{ $t->priority->label() }}</td>
                                    <td class="px-4 py-3 text-neutral-500">
                                        {{ $t->due_date?->toFormattedDateString() ?? __('—') }}
                                        @if ($t->isOverdue())
                                            <flux:badge size="sm" color="red">{{ __('Overdue') }}</flux:badge>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3"><x-status-badge :status="$t->status" /></td>
                                    <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                                        @if ($t->status->value === 'pending')
                                            <flux:button size="sm" variant="primary" wire:click="startTask('{{ $t->uuid }}')">{{ __('Start') }}</flux:button>
                                        @endif
                                        @if ($t->status->isOpen())
                                            <flux:button size="sm" variant="primary" wire:click="startComplete('{{ $t->uuid }}')">{{ __('Mark complete') }}</flux:button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-4 py-10 text-center text-neutral-500">{{ __('No tasks assigned to you yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if ($canOversee)
            <div>
                <flux:heading size="sm" class="mb-2">{{ __('All tasks') }}</flux:heading>
                <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-neutral-800">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-neutral-50 text-xs uppercase text-neutral-500 dark:bg-neutral-900">
                            <tr>
                                <th class="px-4 py-3">{{ __('Title') }}</th>
                                <th class="px-4 py-3">{{ __('Employee') }}</th>
                                <th class="px-4 py-3">{{ __('Priority') }}</th>
                                <th class="px-4 py-3">{{ __('Due') }}</th>
                                <th class="px-4 py-3">{{ __('Status') }}</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                            @forelse ($allTasks as $t)
                                <tr wire:key="all-task-{{ $t->uuid }}">
                                    <td class="px-4 py-3 font-medium">{{ $t->title }}</td>
                                    <td class="px-4 py-3 text-neutral-500">{{ $t->employee->fullName() }}</td>
                                    <td class="px-4 py-3">{{ $t->priority->label() }}</td>
                                    <td class="px-4 py-3 text-neutral-500">
                                        {{ $t->due_date?->toFormattedDateString() ?? __('—') }}
                                        @if ($t->isOverdue())
                                            <flux:badge size="sm" color="red">{{ __('Overdue') }}</flux:badge>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3"><x-status-badge :status="$t->status" /></td>
                                    <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                                        @if ($canManage || $t->employee->user_id === auth()->id())
                                            @if ($t->status->value === 'pending')
                                                <flux:button size="sm" variant="primary" wire:click="startTask('{{ $t->uuid }}')">{{ __('Start') }}</flux:button>
                                            @endif
                                            @if ($t->status->isOpen())
                                                <flux:button size="sm" variant="primary" wire:click="startComplete('{{ $t->uuid }}')">{{ __('Complete') }}</flux:button>
                                            @endif
                                        @endif
                                        @if ($canManage)
                                            <flux:button size="sm" variant="ghost" wire:click="startEdit('{{ $t->uuid }}')">{{ __('Edit') }}</flux:button>
                                            @if ($t->status->isOpen())
                                                <flux:modal.trigger name="cancel-task-{{ $t->uuid }}">
                                                    <flux:button size="sm" variant="danger">{{ __('Cancel') }}</flux:button>
                                                </flux:modal.trigger>
                                                <x-confirm-modal
                                                    name="cancel-task-{{ $t->uuid }}"
                                                    :heading="__('Cancel this task?')"
                                                    :confirm-label="__('Cancel task')"
                                                    confirm-wire="cancelTask('{{ $t->uuid }}')" />
                                            @endif
                                            <flux:modal.trigger name="delete-task-{{ $t->uuid }}">
                                                <flux:button size="sm" variant="danger">{{ __('Delete') }}</flux:button>
                                            </flux:modal.trigger>
                                            <x-confirm-modal
                                                name="delete-task-{{ $t->uuid }}"
                                                :heading="__('Delete this task?')"
                                                :confirm-label="__('Delete')"
                                                confirm-wire="removeTask('{{ $t->uuid }}')" />
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-4 py-10 text-center text-neutral-500">{{ __('No tasks yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endunless

    <flux:modal wire:model.self="showForm" class="max-w-lg">
        <form wire:submit="save" class="flex flex-col gap-4">
            <flux:heading size="lg">{{ $editingUuid ? __('Edit task') : __('Assign task') }}</flux:heading>

            <flux:select wire:model="form.employee_id" :label="__('Employee')">
                <flux:select.option value="">{{ __('Select…') }}</flux:select.option>
                @foreach ($employees as $emp)
                    <flux:select.option value="{{ $emp->id }}">{{ $emp->first_name }} {{ $emp->last_name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model="form.title" :label="__('Title')" />
            <flux:textarea wire:model="form.description" :label="__('Description')" rows="3" />

            <div class="grid grid-cols-2 gap-4">
                <flux:select wire:model="form.priority" :label="__('Priority')">
                    @foreach ($priorities as $p)
                        <flux:select.option value="{{ $p->value }}">{{ $p->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input type="date" wire:model="form.due_date" :label="__('Due date')" />
            </div>

            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showForm', false)">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ $editingUuid ? __('Save changes') : __('Assign task') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="complete-task" class="max-w-sm">
        <form wire:submit="completeTask" class="flex flex-col gap-4">
            <flux:heading size="lg">{{ __('Mark task complete') }}</flux:heading>
            <flux:textarea wire:model="completingRemarks" :label="__('Remarks (optional)')" rows="3" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button type="button" variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Mark complete') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
