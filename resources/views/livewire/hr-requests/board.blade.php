<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <flux:heading size="xl">{{ __('HR requests') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Salary certificates, employment letters, and other HR tickets.') }}</flux:text>
        </div>
        @if ($hasEmployee)
            <flux:button variant="primary" wire:click="startCreate">{{ __('New request') }}</flux:button>
        @endif
    </div>

    @if ($hasEmployee)
        <div>
            <flux:heading size="sm" class="mb-2">{{ __('My requests') }}</flux:heading>
            <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-neutral-800">
                <table class="w-full text-left text-sm">
                    <thead class="bg-neutral-50 text-xs uppercase text-neutral-500 dark:bg-neutral-900">
                        <tr>
                            <th class="px-4 py-3">{{ __('Reference') }}</th>
                            <th class="px-4 py-3">{{ __('Type') }}</th>
                            <th class="px-4 py-3">{{ __('Subject') }}</th>
                            <th class="px-4 py-3">{{ __('Priority') }}</th>
                            <th class="px-4 py-3">{{ __('Status') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                        @forelse ($myRequests as $r)
                            <tr wire:key="my-hr-{{ $r->uuid }}">
                                <td class="px-4 py-3 font-mono text-xs">{{ $r->reference }}</td>
                                <td class="px-4 py-3">{{ $r->type->label() }}</td>
                                <td class="px-4 py-3">{{ $r->subject }}</td>
                                <td class="px-4 py-3 capitalize">{{ $r->priority }}</td>
                                <td class="px-4 py-3"><flux:badge size="sm">{{ $r->status->label() }}</flux:badge></td>
                                <td class="px-4 py-3 text-right">
                                    <flux:button size="sm" variant="ghost" wire:click="view('{{ $r->uuid }}')">{{ __('View') }}</flux:button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-neutral-500">{{ __('You haven\'t raised any requests yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if (! $isHr && $assignedToMe->isNotEmpty())
        <div>
            <flux:heading size="sm" class="mb-2">{{ __('Assigned to me') }}</flux:heading>
            <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-neutral-800">
                <table class="w-full text-left text-sm">
                    <thead class="bg-neutral-50 text-xs uppercase text-neutral-500 dark:bg-neutral-900">
                        <tr>
                            <th class="px-4 py-3">{{ __('Reference') }}</th>
                            <th class="px-4 py-3">{{ __('Employee') }}</th>
                            <th class="px-4 py-3">{{ __('Subject') }}</th>
                            <th class="px-4 py-3">{{ __('Status') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                        @foreach ($assignedToMe as $r)
                            <tr wire:key="assigned-hr-{{ $r->uuid }}">
                                <td class="px-4 py-3 font-mono text-xs">{{ $r->reference }}</td>
                                <td class="px-4 py-3">{{ $r->employee->fullName() }}</td>
                                <td class="px-4 py-3">{{ $r->subject }}</td>
                                <td class="px-4 py-3"><flux:badge size="sm">{{ $r->status->label() }}</flux:badge></td>
                                <td class="px-4 py-3 text-right">
                                    <flux:button size="sm" variant="ghost" wire:click="view('{{ $r->uuid }}')">{{ __('View') }}</flux:button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($isHr)
        <div>
            <flux:heading size="sm" class="mb-2">{{ __('All requests') }}</flux:heading>
            <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-neutral-800">
                <table class="w-full text-left text-sm">
                    <thead class="bg-neutral-50 text-xs uppercase text-neutral-500 dark:bg-neutral-900">
                        <tr>
                            <th class="px-4 py-3">{{ __('Reference') }}</th>
                            <th class="px-4 py-3">{{ __('Employee') }}</th>
                            <th class="px-4 py-3">{{ __('Type') }}</th>
                            <th class="px-4 py-3">{{ __('Subject') }}</th>
                            <th class="px-4 py-3">{{ __('Priority') }}</th>
                            <th class="px-4 py-3">{{ __('Status') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                        @forelse ($allRequests as $r)
                            <tr wire:key="all-hr-{{ $r->uuid }}">
                                <td class="px-4 py-3 font-mono text-xs">{{ $r->reference }}</td>
                                <td class="px-4 py-3">{{ $r->employee->fullName() }}</td>
                                <td class="px-4 py-3">{{ $r->type->label() }}</td>
                                <td class="px-4 py-3">{{ $r->subject }}</td>
                                <td class="px-4 py-3 capitalize">{{ $r->priority }}</td>
                                <td class="px-4 py-3"><flux:badge size="sm">{{ $r->status->label() }}</flux:badge></td>
                                <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                                    @if ($r->assigned_to === null)
                                        <flux:button size="sm" variant="ghost" wire:click="assignToMe('{{ $r->uuid }}')">{{ __('Assign to me') }}</flux:button>
                                    @endif
                                    <flux:button size="sm" variant="ghost" wire:click="view('{{ $r->uuid }}')">{{ __('View') }}</flux:button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-10 text-center text-neutral-500">{{ __('No HR requests yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <flux:modal wire:model.self="showForm" class="max-w-lg">
        <form wire:submit="submit" class="flex flex-col gap-4">
            <flux:heading size="lg">{{ __('New HR request') }}</flux:heading>

            <flux:select wire:model="form.type" :label="__('Type')">
                @foreach ($types as $type)
                    <flux:select.option value="{{ $type->value }}">{{ $type->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model="form.subject" :label="__('Subject')" />
            <flux:textarea wire:model="form.body" :label="__('Details (optional)')" rows="4" />

            <flux:select wire:model="form.priority" :label="__('Priority')">
                <flux:select.option value="low">{{ __('Low') }}</flux:select.option>
                <flux:select.option value="normal">{{ __('Normal') }}</flux:select.option>
                <flux:select.option value="high">{{ __('High') }}</flux:select.option>
            </flux:select>

            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showForm', false)">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Submit request') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="view-request" class="max-w-2xl">
        @if ($viewing)
            <div class="flex flex-col gap-4">
                <div>
                    <flux:heading size="lg">{{ $viewing->subject }}</flux:heading>
                    <flux:text class="mt-1">
                        {{ $viewing->reference }} · {{ $viewing->type->label() }} · {{ __('Raised by') }} {{ $viewing->employee->fullName() }}
                    </flux:text>
                </div>

                @if ($viewing->body)
                    <flux:text class="rounded-lg border border-neutral-200 p-3 dark:border-neutral-800">{{ $viewing->body }}</flux:text>
                @endif

                <div class="flex flex-wrap items-center gap-3">
                    <flux:badge size="sm">{{ $viewing->status->label() }}</flux:badge>
                    <span class="text-sm capitalize text-neutral-500">{{ __('Priority') }}: {{ $viewing->priority }}</span>
                    <span class="text-sm text-neutral-500">{{ __('Assigned to') }}: {{ $viewing->assignee?->name ?? __('Unassigned') }}</span>
                </div>

                @if ($canManageViewing)
                    <div class="grid gap-3 rounded-lg border border-neutral-200 p-3 dark:border-neutral-800 sm:grid-cols-2">
                        <div class="flex items-end gap-2">
                            <flux:select wire:model="statusValue" :label="__('Status')" class="flex-1">
                                @foreach ($statuses as $s)
                                    <flux:select.option value="{{ $s->value }}">{{ $s->label() }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:button size="sm" wire:click="saveStatus">{{ __('Save') }}</flux:button>
                        </div>
                        <div class="flex items-end gap-2">
                            <flux:select wire:model="assigneeValue" :label="__('Assigned to')" class="flex-1">
                                <flux:select.option value="">{{ __('Unassigned') }}</flux:select.option>
                                @foreach ($hrStaff as $staff)
                                    <flux:select.option value="{{ $staff->id }}">{{ $staff->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:button size="sm" wire:click="saveAssignment">{{ __('Save') }}</flux:button>
                        </div>
                    </div>
                @endif

                <div class="flex flex-col gap-3">
                    <flux:heading size="sm">{{ __('Comments') }}</flux:heading>
                    <div class="flex max-h-64 flex-col gap-2 overflow-y-auto">
                        @forelse ($visibleComments as $c)
                            <div class="rounded-lg border border-neutral-200 p-2 text-sm dark:border-neutral-800 {{ $c->is_internal ? 'bg-amber-50 dark:bg-amber-950/30' : '' }}">
                                <p class="flex items-center gap-2 text-xs text-neutral-500">
                                    <span class="font-medium text-neutral-700 dark:text-neutral-300">{{ $c->user?->name ?? __('System') }}</span>
                                    @if ($c->is_internal)
                                        <flux:badge size="sm" color="amber">{{ __('Internal') }}</flux:badge>
                                    @endif
                                </p>
                                <p class="mt-1">{{ $c->body }}</p>
                            </div>
                        @empty
                            <p class="text-sm text-neutral-500">{{ __('No comments yet.') }}</p>
                        @endforelse
                    </div>

                    <form wire:submit="addComment" class="flex flex-col gap-2">
                        <flux:textarea wire:model="newComment" :label="__('Add a comment')" rows="2" />
                        @if ($showInternal)
                            <flux:field variant="inline">
                                <flux:checkbox wire:model="newCommentInternal" />
                                <flux:label>{{ __('Internal note (not visible to the employee)') }}</flux:label>
                            </flux:field>
                        @endif
                        <div class="flex justify-end">
                            <flux:button type="submit" variant="primary" size="sm">{{ __('Post comment') }}</flux:button>
                        </div>
                    </form>
                </div>

                <div class="flex justify-end">
                    <flux:modal.close><flux:button type="button" variant="ghost">{{ __('Close') }}</flux:button></flux:modal.close>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
