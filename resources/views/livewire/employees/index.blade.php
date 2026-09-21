<div class="flex flex-col gap-4">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <flux:heading size="xl">{{ __('Employees') }}</flux:heading>
            <flux:text class="mt-1">{{ $employees->total() }} {{ Str::plural('person', $employees->total()) }}</flux:text>
        </div>
        <div class="flex items-center gap-2">
            @if ($canCreate)
                <flux:button variant="primary" wire:click="startCreate">{{ __('Add employee') }}</flux:button>
            @endif
            <flux:button variant="ghost" :href="route('employees.export')">{{ __('Export') }}</flux:button>
        </div>
    </div>

    <div class="flex flex-wrap items-end gap-3">
        <flux:input wire:model.live.debounce.300ms="search" :label="__('Search')" placeholder="{{ __('Search name, number, email…') }}" class="max-w-xs" />

        <flux:select wire:model.live="status" :label="__('Status')" class="max-w-[12rem]">
            <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
            @foreach ($statuses as $case)
                <flux:select.option value="{{ $case->value }}">{{ $case->label() }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="department" :label="__('Department')" class="max-w-[12rem]">
            <flux:select.option value="">{{ __('All departments') }}</flux:select.option>
            @foreach ($departments as $dept)
                <flux:select.option value="{{ $dept->uuid }}">{{ $dept->name }}</flux:select.option>
            @endforeach
        </flux:select>

        @if ($search !== '' || $status !== '' || $department !== '')
            <flux:button variant="ghost" size="sm" wire:click="clearFilters">{{ __('Clear') }}</flux:button>
        @endif
    </div>

    <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-neutral-800">
        <table class="w-full text-left text-sm">
            <thead class="bg-neutral-50 text-xs uppercase text-neutral-500 dark:bg-neutral-900">
                <tr>
                    <th class="px-4 py-3">{{ __('Number') }}</th>
                    <th class="px-4 py-3">{{ __('Name') }}</th>
                    <th class="px-4 py-3">{{ __('Job title') }}</th>
                    <th class="px-4 py-3">{{ __('Department') }}</th>
                    <th class="px-4 py-3">{{ __('Branch') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                    @if ($canUpdate || $canDelete)
                        <th class="px-4 py-3"></th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                @forelse ($employees as $employee)
                    <tr wire:key="emp-{{ $employee->uuid }}" class="hover:bg-neutral-50 dark:hover:bg-neutral-900">
                        <td class="px-4 py-3 font-mono text-xs">{{ $employee->employee_number }}</td>
                        <td class="px-4 py-3 font-medium text-neutral-800 dark:text-neutral-100">{{ $employee->fullName() }}</td>
                        <td class="px-4 py-3 text-neutral-500">{{ $employee->job_title ?? '—' }}</td>
                        <td class="px-4 py-3 text-neutral-500">{{ $employee->department?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-neutral-500">{{ $employee->branch?->name ?? '—' }}</td>
                        <td class="px-4 py-3"><x-status-badge :status="$employee->status" /></td>
                        @if ($canUpdate || $canDelete)
                            <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                                @if ($canUpdate)
                                    <flux:button size="sm" variant="ghost" wire:click="startEdit('{{ $employee->uuid }}')">{{ __('Edit') }}</flux:button>
                                @endif
                                @if (! $employee->status->isCurrentlyEmployed() && $canSettle)
                                    <flux:button size="sm" variant="ghost" icon="banknotes" :href="route('payroll').'#final-settlements'" wire:navigate>{{ __('Final pay') }}</flux:button>
                                @endif
                                @if ($canDelete)
                                    <flux:modal.trigger name="delete-employee-{{ $employee->uuid }}">
                                        <flux:button size="sm" variant="danger">{{ __('Delete') }}</flux:button>
                                    </flux:modal.trigger>
                                    <x-confirm-modal
                                        name="delete-employee-{{ $employee->uuid }}"
                                        :heading="__('Remove :name?', ['name' => $employee->fullName()])"
                                        :text="__('This cannot be undone.')"
                                        :confirm-label="__('Delete')"
                                        confirm-wire="remove('{{ $employee->uuid }}')" />
                                @endif
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center text-neutral-500">{{ __('No employees match your filters.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $employees->links() }}</div>

    <flux:modal wire:model.self="showForm" class="max-w-2xl">
        <form wire:submit="save" class="flex flex-col gap-4">
            <flux:heading size="lg">{{ $editingUuid ? __('Edit employee') : __('New employee') }}</flux:heading>

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="form.first_name" :label="__('First name')" />
                <flux:input wire:model="form.last_name" :label="__('Last name')" />
                <flux:input type="email" wire:model="form.email" :label="__('Email')" />
                <flux:input wire:model="form.phone" :label="__('Phone')" />
                <flux:input wire:model="form.job_title" :label="__('Job title')" />
                <flux:input type="date" wire:model="form.hire_date" :label="__('Hire date')" />

                <flux:select wire:model.live="form.status" :label="__('Status')">
                    @foreach ($statuses as $case)
                        <flux:select.option value="{{ $case->value }}">{{ $case->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                @if (\App\Enums\EmploymentStatus::tryFrom($form['status'] ?? '')?->isCurrentlyEmployed() === false)
                    <flux:input type="date" wire:model="form.exit_date" :label="__('Exit date')" :description="__('Last day worked. Defaults to today.')" />
                @endif

                <flux:select wire:model="form.department_id" :label="__('Department')">
                    <flux:select.option value="">{{ __('None') }}</flux:select.option>
                    @foreach ($departments as $dept)
                        <flux:select.option value="{{ $dept->id }}">{{ $dept->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="form.designation_id" :label="__('Designation')">
                    <flux:select.option value="">{{ __('None') }}</flux:select.option>
                    @foreach ($designations as $d)
                        <flux:select.option value="{{ $d->id }}">{{ $d->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="form.branch_id" :label="__('Branch')">
                    <flux:select.option value="">{{ __('None') }}</flux:select.option>
                    @foreach ($branches as $b)
                        <flux:select.option value="{{ $b->id }}">{{ $b->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="form.employment_type_id" :label="__('Employment type')">
                    <flux:select.option value="">{{ __('None') }}</flux:select.option>
                    @foreach ($employmentTypes as $t)
                        <flux:select.option value="{{ $t->id }}">{{ $t->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="form.manager_id" :label="__('Manager')">
                    <flux:select.option value="">{{ __('None') }}</flux:select.option>
                    @foreach ($managers as $m)
                        <flux:select.option value="{{ $m->id }}">{{ $m->first_name }} {{ $m->last_name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showForm', false)">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ $editingUuid ? __('Save changes') : __('Create employee') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
