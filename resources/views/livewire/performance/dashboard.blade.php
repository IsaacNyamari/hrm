<div class="flex flex-col gap-6">
    <flux:heading size="xl">{{ __('Performance') }}</flux:heading>

    @unless ($hasEmployee || $canManageCycles)
        <flux:text class="rounded-xl border border-dashed border-neutral-300 p-8 text-center dark:border-neutral-700">
            {{ __('Your account is not linked to an employee record. Ask your HR team to link it.') }}
        </flux:text>
    @else
        @if ($hasEmployee)
            <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-800">
                <flux:heading size="sm">{{ __('Add a goal') }}</flux:heading>
                <form wire:submit="createGoal" class="mt-3 grid gap-3 sm:grid-cols-[1fr_12rem_12rem_auto]">
                    <flux:input wire:model="title" placeholder="{{ __('Goal title') }}" />
                    <flux:select wire:model="category">
                        @foreach ($categories as $c)
                            <flux:select.option value="{{ $c->value }}">{{ $c->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model="cycleUuid">
                        <flux:select.option value="">{{ __('No cycle') }}</flux:select.option>
                        @foreach ($cycles as $cycle)
                            <flux:select.option value="{{ $cycle->uuid }}">{{ $cycle->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:button type="submit" variant="primary">{{ __('Add') }}</flux:button>
                </form>
            </div>

            <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-800">
                <flux:heading size="sm">{{ __('My goals') }}</flux:heading>
                <table class="mt-2 w-full text-left text-sm">
                    <thead class="text-xs uppercase text-neutral-500">
                        <tr><th class="py-1">{{ __('Goal') }}</th><th>{{ __('Cycle') }}</th><th>{{ __('Category') }}</th><th>{{ __('Progress') }}</th><th>{{ __('Mgr rating') }}</th><th></th></tr>
                    </thead>
                    <tbody>
                        @forelse ($goals as $goal)
                            <tr wire:key="goal-{{ $goal->uuid }}" class="border-t border-neutral-100 dark:border-neutral-800">
                                <td class="py-1.5">{{ $goal->title }}</td>
                                <td class="text-neutral-500">{{ $goal->cycle?->name ?? '—' }}</td>
                                <td class="text-neutral-500">{{ $goal->category->label() }}</td>
                                <td>{{ $goal->progress }}%</td>
                                <td>{{ $goal->manager_rating ?? '—' }}</td>
                                <td class="text-right">
                                    <flux:modal.trigger name="delete-goal-{{ $goal->uuid }}">
                                        <flux:button size="sm" variant="danger">{{ __('Delete') }}</flux:button>
                                    </flux:modal.trigger>
                                    <x-confirm-modal
                                        name="delete-goal-{{ $goal->uuid }}"
                                        :heading="__('Delete this goal?')"
                                        :confirm-label="__('Delete')"
                                        confirm-wire="deleteGoal('{{ $goal->uuid }}')" />
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-2 text-neutral-500">{{ __('No goals yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-800">
                <div class="flex items-center justify-between">
                    <flux:heading size="sm">{{ __('My KPIs') }}</flux:heading>
                    <flux:button size="sm" variant="primary" wire:click="startCreateKpi">{{ __('Add KPI') }}</flux:button>
                </div>
                <ul class="mt-2 space-y-1 text-sm">
                    @forelse ($kpis as $kpi)
                        <li wire:key="kpi-{{ $kpi->uuid }}" class="flex items-center justify-between">
                            <span class="text-neutral-500">{{ $kpi->name }}</span>
                            <span class="flex items-center gap-2">
                                <span class="font-medium">{{ rtrim(rtrim(number_format($kpi->current_value, 2), '0'), '.') }} / {{ rtrim(rtrim(number_format($kpi->target_value, 2), '0'), '.') }} {{ $kpi->unit }}</span>
                                <flux:button size="sm" variant="ghost" wire:click="startRecordKpi('{{ $kpi->uuid }}')">{{ __('Record') }}</flux:button>
                                <flux:modal.trigger name="delete-kpi-{{ $kpi->uuid }}">
                                    <flux:button size="sm" variant="danger">{{ __('Delete') }}</flux:button>
                                </flux:modal.trigger>
                                <x-confirm-modal
                                    name="delete-kpi-{{ $kpi->uuid }}"
                                    :heading="__('Delete this KPI?')"
                                    :confirm-label="__('Delete')"
                                    confirm-wire="deleteKpi('{{ $kpi->uuid }}')" />
                            </span>
                        </li>
                    @empty
                        <li class="text-neutral-500">{{ __('No KPIs yet.') }}</li>
                    @endforelse
                </ul>
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-800">
                <flux:heading size="sm">{{ __('Reviews to complete') }}</flux:heading>
                <ul class="mt-2 space-y-2 text-sm">
                    @forelse ($toComplete as $review)
                        <li wire:key="tc-{{ $review->uuid }}" class="flex items-center justify-between">
                            <span class="text-neutral-700 dark:text-neutral-300">
                                {{ $review->reviewer_type->label() }} — {{ $review->employee->fullName() }}
                                <span class="text-neutral-500">({{ $review->cycle->name }})</span>
                            </span>
                            <span class="flex items-center gap-2">
                                <x-status-badge :status="$review->status" />
                                @if ($review->status->isEditable())
                                    <flux:button size="sm" variant="primary" wire:click="startReview('{{ $review->uuid }}')">{{ __('Fill in') }}</flux:button>
                                @endif
                            </span>
                        </li>
                    @empty
                        <li class="text-neutral-500">{{ __('Nothing assigned to you.') }}</li>
                    @endforelse
                </ul>
            </div>

            <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-800">
                <flux:heading size="sm">{{ __('My reviews') }}</flux:heading>
                <ul class="mt-2 space-y-2 text-sm">
                    @forelse ($aboutMe as $review)
                        <li wire:key="me-{{ $review->uuid }}" class="flex items-center justify-between">
                            <span class="text-neutral-700 dark:text-neutral-300">
                                {{ $review->reviewer_type->label() }}
                                <span class="text-neutral-500">({{ $review->cycle->name }})</span>
                            </span>
                            <span class="flex items-center gap-2">
                                <span class="font-medium">{{ $review->overall_rating ?? '—' }}</span>
                                @if ($review->status->value === 'submitted')
                                    <flux:button size="sm" variant="primary" wire:click="acknowledgeReview('{{ $review->uuid }}')">{{ __('Acknowledge') }}</flux:button>
                                @endif
                            </span>
                        </li>
                    @empty
                        <li class="text-neutral-500">{{ __('No reviews yet.') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>

        @if ($canManageCycles)
            <div class="flex items-center justify-between">
                <flux:heading size="sm">{{ __('Performance cycles') }}</flux:heading>
                <flux:button variant="primary" wire:click="startCreateCycle">{{ __('New cycle') }}</flux:button>
            </div>

            <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-neutral-800">
                <table class="w-full text-left text-sm">
                    <thead class="bg-neutral-50 text-xs uppercase text-neutral-500 dark:bg-neutral-900">
                        <tr>
                            <th class="px-4 py-3">{{ __('Name') }}</th>
                            <th class="px-4 py-3">{{ __('Type') }}</th>
                            <th class="px-4 py-3">{{ __('Dates') }}</th>
                            <th class="px-4 py-3">{{ __('Status') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                        @forelse ($cycles as $cycle)
                            <tr wire:key="cycle-{{ $cycle->uuid }}">
                                <td class="px-4 py-3 font-medium">{{ $cycle->name }}</td>
                                <td class="px-4 py-3 text-neutral-500">{{ $cycle->type->label() }}</td>
                                <td class="px-4 py-3 text-neutral-500">{{ $cycle->starts_on->toDateString() }} – {{ $cycle->ends_on->toDateString() }}</td>
                                <td class="px-4 py-3"><x-status-badge :status="$cycle->status" /></td>
                                <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                                    @if ($cycle->status->value === 'draft')
                                        <flux:button size="sm" variant="primary" wire:click="openCycle('{{ $cycle->uuid }}')">{{ __('Open') }}</flux:button>
                                        <flux:button size="sm" variant="ghost" wire:click="startEditCycle('{{ $cycle->uuid }}')">{{ __('Edit') }}</flux:button>
                                        <flux:modal.trigger name="delete-cycle-{{ $cycle->uuid }}">
                                            <flux:button size="sm" variant="danger">{{ __('Delete') }}</flux:button>
                                        </flux:modal.trigger>
                                        <x-confirm-modal
                                            name="delete-cycle-{{ $cycle->uuid }}"
                                            :heading="__('Delete this cycle?')"
                                            :confirm-label="__('Delete')"
                                            confirm-wire="removeCycle('{{ $cycle->uuid }}')" />
                                    @elseif ($cycle->status->value === 'active')
                                        <flux:button size="sm" variant="primary" wire:click="startReviewPhaseForCycle('{{ $cycle->uuid }}')">{{ __('Start review phase') }}</flux:button>
                                    @elseif ($cycle->status->value === 'in_review')
                                        <flux:modal.trigger name="close-cycle-{{ $cycle->uuid }}">
                                            <flux:button size="sm" variant="primary">{{ __('Close cycle') }}</flux:button>
                                        </flux:modal.trigger>
                                        <x-confirm-modal
                                            name="close-cycle-{{ $cycle->uuid }}"
                                            :heading="__('Close this cycle?')"
                                            :text="__('Unsubmitted reviews will be left as-is. This cannot be undone.')"
                                            :confirm-label="__('Close cycle')"
                                            confirm-wire="closeCycle('{{ $cycle->uuid }}')" />
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-6 text-center text-neutral-500">{{ __('No performance cycles yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <flux:modal wire:model.self="showCycleForm" class="max-w-lg">
                <form wire:submit="saveCycle" class="flex flex-col gap-4">
                    <flux:heading size="lg">{{ $editingCycleUuid ? __('Edit cycle') : __('New performance cycle') }}</flux:heading>
                    <flux:input wire:model="cycleForm.name" :label="__('Name')" />
                    <flux:select wire:model="cycleForm.type" :label="__('Type')">
                        @foreach ($cycleTypes as $t)
                            <flux:select.option value="{{ $t->value }}">{{ $t->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <div class="grid grid-cols-2 gap-3">
                        <flux:input type="date" wire:model="cycleForm.starts_on" :label="__('Starts on')" />
                        <flux:input type="date" wire:model="cycleForm.ends_on" :label="__('Ends on')" />
                        <flux:input type="number" wire:model="cycleForm.goal_weight" :label="__('Goal weight %')" />
                        <flux:input type="number" wire:model="cycleForm.competency_weight" :label="__('Competency weight %')" />
                    </div>
                    <div class="flex flex-wrap gap-4">
                        <flux:field variant="inline">
                            <flux:checkbox wire:model="cycleForm.self_review_enabled" />
                            <flux:label>{{ __('Self review enabled') }}</flux:label>
                        </flux:field>
                        <flux:field variant="inline">
                            <flux:checkbox wire:model="cycleForm.peer_review_enabled" />
                            <flux:label>{{ __('Peer review enabled') }}</flux:label>
                        </flux:field>
                    </div>
                    <div class="flex justify-end gap-2">
                        <flux:button type="button" variant="ghost" wire:click="$set('showCycleForm', false)">{{ __('Cancel') }}</flux:button>
                        <flux:button type="submit" variant="primary">{{ $editingCycleUuid ? __('Save changes') : __('Create cycle') }}</flux:button>
                    </div>
                </form>
            </flux:modal>
        @endif

        <flux:modal wire:model.self="showKpiForm" class="max-w-sm">
            <form wire:submit="saveKpi" class="flex flex-col gap-4">
                <flux:heading size="lg">{{ __('New KPI') }}</flux:heading>
                <flux:input wire:model="kpiForm.name" :label="__('Name')" />
                <flux:input wire:model="kpiForm.unit" :label="__('Unit')" placeholder="{{ __('e.g. %, $, calls') }}" />
                <flux:input type="number" step="0.01" wire:model="kpiForm.target_value" :label="__('Target value')" />
                <div class="flex justify-end gap-2">
                    <flux:button type="button" variant="ghost" wire:click="$set('showKpiForm', false)">{{ __('Cancel') }}</flux:button>
                    <flux:button type="submit" variant="primary">{{ __('Create KPI') }}</flux:button>
                </div>
            </form>
        </flux:modal>

        <flux:modal name="record-kpi" class="max-w-sm">
            <form wire:submit="recordKpi" class="flex flex-col gap-4">
                <flux:heading size="lg">{{ __('Record KPI value') }}</flux:heading>
                <flux:input type="number" step="0.01" wire:model="kpiValue" :label="__('Current value')" />
                <div class="flex justify-end gap-2">
                    <flux:modal.close><flux:button type="button" variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                </div>
            </form>
        </flux:modal>

        <flux:modal name="review-form" class="max-w-xl">
            <form wire:submit="submitReview" class="flex flex-col gap-4">
                <flux:heading size="lg">{{ __('Submit review') }}</flux:heading>

                <div class="grid grid-cols-2 gap-3">
                    @foreach ($competencyFramework as $c)
                        <flux:input type="number" min="0" max="5" step="0.5" wire:model="reviewRatings.{{ $c['code'] }}" :label="$c['name']" />
                    @endforeach
                </div>

                <flux:textarea wire:model="reviewStrengths" :label="__('Strengths')" rows="2" />
                <flux:textarea wire:model="reviewImprovements" :label="__('Areas for improvement')" rows="2" />
                <flux:textarea wire:model="reviewComments" :label="__('Comments')" rows="2" />

                <div class="flex justify-end gap-2">
                    <flux:modal.close><flux:button type="button" variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="primary">{{ __('Submit review') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endunless
</div>
