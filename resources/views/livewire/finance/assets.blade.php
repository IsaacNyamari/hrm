<div class="flex flex-col gap-6">
    <flux:heading size="xl">{{ __('Assets') }}</flux:heading>

    <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-800">
        <flux:heading size="sm">{{ __('Assigned to me') }}</flux:heading>
        <ul class="mt-2 space-y-1 text-sm">
            @forelse ($mine as $assignment)
                <li class="flex justify-between">
                    <span class="text-neutral-700 dark:text-neutral-300">{{ $assignment->asset->asset_tag }} — {{ $assignment->asset->name }}</span>
                    <span class="text-xs text-neutral-500">{{ __('since') }} {{ $assignment->assigned_on->toFormattedDateString() }}</span>
                </li>
            @empty
                <li class="text-neutral-500">{{ __('Nothing assigned to you.') }}</li>
            @endforelse
        </ul>
    </div>

    @if ($canManage)
        <div class="flex items-center justify-between">
            <flux:heading size="sm">{{ __('Asset register') }}</flux:heading>
            @if ($canEdit)
                <flux:button variant="primary" wire:click="startCreate">{{ __('Add asset') }}</flux:button>
            @endif
        </div>

        <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-800">
            <table class="mt-2 w-full text-left text-sm">
                <thead class="text-xs uppercase text-neutral-500">
                    <tr><th class="py-1">{{ __('Tag') }}</th><th>{{ __('Name') }}</th><th>{{ __('Category') }}</th><th>{{ __('Status') }}</th><th>{{ __('Holder') }}</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($register as $asset)
                        <tr wire:key="asset-{{ $asset->uuid }}" class="border-t border-neutral-100 dark:border-neutral-800">
                            <td class="py-1.5 font-mono text-xs">{{ $asset->asset_tag }}</td>
                            <td>{{ $asset->name }}</td>
                            <td class="text-neutral-500">{{ $asset->category->label() }}</td>
                            <td><flux:badge size="sm">{{ $asset->status->label() }}</flux:badge></td>
                            <td class="text-neutral-500">{{ optional($asset->activeAssignments->first())->employee?->fullName() ?? '—' }}</td>
                            @if ($canEdit)
                                <td class="text-right space-x-2 whitespace-nowrap">
                                    @if ($asset->status->isAssignable())
                                        <flux:button size="sm" variant="primary" wire:click="startAssign('{{ $asset->uuid }}')">{{ __('Assign') }}</flux:button>
                                    @elseif ($asset->activeAssignments->isNotEmpty())
                                        <flux:button size="sm" variant="primary" wire:click="returnAsset('{{ $asset->activeAssignments->first()->uuid }}')">{{ __('Return') }}</flux:button>
                                    @endif
                                    <flux:button size="sm" variant="ghost" wire:click="startEdit('{{ $asset->uuid }}')">{{ __('Edit') }}</flux:button>
                                    @if (! in_array($asset->status->value, ['retired', 'lost'], true))
                                        <flux:modal.trigger name="retire-asset-{{ $asset->uuid }}">
                                            <flux:button size="sm" variant="ghost">{{ __('Retire') }}</flux:button>
                                        </flux:modal.trigger>
                                        <x-confirm-modal
                                            name="retire-asset-{{ $asset->uuid }}"
                                            :heading="__('Retire this asset?')"
                                            :confirm-label="__('Retire')"
                                            variant="danger"
                                            confirm-wire="retireAsset('{{ $asset->uuid }}')" />
                                    @endif
                                    <flux:modal.trigger name="delete-asset-{{ $asset->uuid }}">
                                        <flux:button size="sm" variant="danger">{{ __('Delete') }}</flux:button>
                                    </flux:modal.trigger>
                                    <x-confirm-modal
                                        name="delete-asset-{{ $asset->uuid }}"
                                        :heading="__('Delete this asset?')"
                                        :confirm-label="__('Delete')"
                                        confirm-wire="remove('{{ $asset->uuid }}')" />
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-2 text-neutral-500">{{ __('No assets recorded.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <flux:modal wire:model.self="showForm" class="max-w-lg">
            <form wire:submit="save" class="flex flex-col gap-4">
                <flux:heading size="lg">{{ $editingUuid ? __('Edit asset') : __('New asset') }}</flux:heading>
                <flux:input wire:model="form.asset_tag" :label="__('Asset tag')" />
                <flux:input wire:model="form.name" :label="__('Name')" />
                <flux:select wire:model="form.category" :label="__('Category')">
                    @foreach ($categories as $c)
                        <flux:select.option value="{{ $c->value }}">{{ $c->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="form.serial_number" :label="__('Serial number')" />
                <flux:select wire:model="form.branch_id" :label="__('Branch')">
                    <flux:select.option value="">{{ __('None') }}</flux:select.option>
                    @foreach ($branches as $b)
                        <flux:select.option value="{{ $b->id }}">{{ $b->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <div class="flex justify-end gap-2">
                    <flux:button type="button" variant="ghost" wire:click="$set('showForm', false)">{{ __('Cancel') }}</flux:button>
                    <flux:button type="submit" variant="primary">{{ $editingUuid ? __('Save changes') : __('Create asset') }}</flux:button>
                </div>
            </form>
        </flux:modal>

        <flux:modal wire:model.self="showAssignForm" class="max-w-sm">
            <form wire:submit="assign" class="flex flex-col gap-4">
                <flux:heading size="lg">{{ __('Assign asset') }}</flux:heading>
                <flux:select wire:model="assignEmployeeId" :label="__('Employee')">
                    <flux:select.option value="">{{ __('Select employee') }}</flux:select.option>
                    @foreach ($employees as $emp)
                        <flux:select.option value="{{ $emp->id }}">{{ $emp->first_name }} {{ $emp->last_name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <div class="flex justify-end gap-2">
                    <flux:button type="button" variant="ghost" wire:click="$set('showAssignForm', false)">{{ __('Cancel') }}</flux:button>
                    <flux:button type="submit" variant="primary">{{ __('Confirm assignment') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endif
</div>
