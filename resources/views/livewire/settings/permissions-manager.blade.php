<div class="flex flex-col gap-4">
    <div>
        <flux:heading size="xl">{{ __('Roles & permissions') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Assign a role to the people in your organization. Each role grants a fixed set of permissions.') }}</flux:text>
    </div>

    <flux:input wire:model.live.debounce.300ms="search" :label="__('Search')" placeholder="{{ __('Search name or email…') }}" class="max-w-xs" />

    <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-neutral-800">
        <table class="w-full text-left text-sm">
            <thead class="bg-neutral-50 text-xs uppercase text-neutral-500 dark:bg-neutral-900">
                <tr>
                    <th class="px-4 py-3">{{ __('Name') }}</th>
                    <th class="px-4 py-3">{{ __('Email') }}</th>
                    <th class="px-4 py-3">{{ __('Role') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                @forelse ($users as $user)
                    <tr wire:key="user-{{ $user->id }}" class="hover:bg-neutral-50 dark:hover:bg-neutral-900">
                        <td class="px-4 py-3 font-medium text-neutral-800 dark:text-neutral-100">{{ $user->name }}</td>
                        <td class="px-4 py-3 text-neutral-500">{{ $user->email }}</td>
                        <td class="px-4 py-3">
                            @if ($user->id === $authUserId)
                                <flux:badge size="sm">{{ $user->roles->pluck('name')->join(', ') ?: __('No role') }}</flux:badge>
                                <span class="ms-1 text-xs text-neutral-400">{{ __('(you)') }}</span>
                            @elseif ($user->roles->contains('name', \App\Enums\Role::TenantOwner->value))
                                <flux:badge size="sm" color="indigo">{{ __('Tenant Owner') }}</flux:badge>
                                <span class="ms-1 text-xs text-neutral-400">{{ __('Set by platform admin') }}</span>
                            @else
                                <flux:select size="sm" class="max-w-[14rem]" wire:change="updateRole({{ $user->id }}, $event.target.value)">
                                    <flux:select.option value="" disabled :selected="$user->roles->isEmpty()">{{ __('Choose a role…') }}</flux:select.option>
                                    @foreach ($assignableRoles as $roleCase)
                                        <flux:select.option value="{{ $roleCase->value }}" :selected="$user->roles->pluck('name')->contains($roleCase->value)">
                                            {{ $roleCase->value }}
                                        </flux:select.option>
                                    @endforeach
                                    @foreach ($customRoleNames as $customRole)
                                        <flux:select.option value="{{ $customRole }}" :selected="$user->roles->pluck('name')->contains($customRole)">{{ $customRole }} ({{ __('custom') }})</flux:select.option>
                                    @endforeach
                                </flux:select>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-4 py-12 text-center text-neutral-500">{{ __('No one matches your search.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $users->links() }}</div>

    <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
        <flux:heading size="lg">{{ __('What each role can do') }}</flux:heading>
        <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($assignableRoles as $roleCase)
                <div class="rounded-lg border border-neutral-200 p-3 text-sm dark:border-neutral-700">
                    <p class="font-semibold text-neutral-800 dark:text-neutral-100">{{ $roleCase->value }}</p>
                    <p class="mt-1 text-xs text-neutral-500">
                        @if ($roleCase->permissions() === \App\Enums\Permission::values())
                            {{ __('Full access.') }}
                        @else
                            {{ trans_choice(':count permission|:count permissions', count($roleCase->permissions()), ['count' => count($roleCase->permissions())]) }}
                        @endif
                    </p>
                </div>
            @endforeach
        </div>
    </div>
</div>
