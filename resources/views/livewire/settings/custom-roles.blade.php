<div class="flex flex-col gap-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <flux:heading size="lg">{{ __('Custom roles') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Build a role for jobs the standard roles do not fit, for example a read-only auditor. You can only include permissions you hold yourself.') }}</flux:text>
        </div>
        @if ($canManage)
            <flux:button variant="primary" wire:click="startCreate">{{ __('New role') }}</flux:button>
        @endif
    </div>

    <div class="divide-y divide-neutral-100 rounded-xl border border-neutral-200 text-sm dark:divide-neutral-800 dark:border-neutral-800">
        @forelse ($roles as $role)
            <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3" wire:key="role-{{ $role->id }}">
                <div>
                    <p class="font-medium text-neutral-800 dark:text-neutral-100">{{ $role->name }}</p>
                    <p class="text-xs text-neutral-500">{{ trans_choice(':count permission|:count permissions', $role->permissions->count(), ['count' => $role->permissions->count()]) }} · {{ trans_choice(':count person|:count people', $role->users_count, ['count' => $role->users_count]) }}</p>
                </div>
                @if ($canManage)
                    <div class="flex gap-2">
                        <flux:button size="sm" wire:click="startEdit({{ $role->id }})">{{ __('Edit') }}</flux:button>
                        <flux:button size="sm" variant="danger" wire:click="delete({{ $role->id }})" wire:confirm="{{ __('Delete this role?') }}">{{ __('Delete') }}</flux:button>
                    </div>
                @endif
            </div>
        @empty
            <p class="px-4 py-6 text-neutral-500">{{ __('No custom roles yet.') }}</p>
        @endforelse
    </div>

    <flux:modal wire:model.self="showForm" class="max-w-2xl">
        <form wire:submit="save" class="flex flex-col gap-4">
            <flux:heading size="lg">{{ $editingId ? __('Edit role') : __('New role') }}</flux:heading>
            <flux:input wire:model="name" :label="__('Role name')" required />
            @error('name')<p class="text-sm text-red-600">{{ $message }}</p>@enderror

            <div>
                <p class="mb-2 text-sm font-medium">{{ __('Permissions') }}</p>
                <div class="grid max-h-72 gap-1 overflow-y-auto rounded-lg border border-neutral-200 p-3 text-sm sm:grid-cols-2 dark:border-neutral-700">
                    @foreach ($grantable as $permission)
                        <label class="flex items-center gap-2" wire:key="perm-{{ $permission }}">
                            <input type="checkbox" value="{{ $permission }}" wire:model="selected" class="accent-brand">
                            <span class="font-mono text-xs">{{ $permission }}</span>
                        </label>
                    @endforeach
                </div>
                @error('selected')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showForm', false)">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Save role') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
