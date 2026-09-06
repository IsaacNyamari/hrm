<x-layouts::app :title="$tenant->name">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <flux:link :href="route('platform.tenants.index')" wire:navigate class="text-sm">&larr; {{ __('Organizations') }}</flux:link>
                <flux:heading size="xl" class="mt-1">{{ $tenant->name }}</flux:heading>
                <flux:text class="mt-1">{{ $tenant->slug }} · {{ $tenant->email }} · <flux:badge size="sm">{{ $tenant->status->label() }}</flux:badge></flux:text>
            </div>

            @if ($canManage)
                <div class="flex gap-2">
                    @if ($tenant->status === \App\Enums\TenantStatus::Suspended)
                        <form method="POST" action="{{ route('platform.tenants.activate', $tenant) }}">
                            @csrf
                            <flux:button type="submit" variant="primary">{{ __('Activate') }}</flux:button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('platform.tenants.suspend', $tenant) }}"
                              onsubmit="return confirm('{{ __('Suspend this organization? Its users lose app access.') }}')">
                            @csrf
                            <flux:button type="submit" variant="danger">{{ __('Suspend') }}</flux:button>
                        </form>
                    @endif
                </div>
            @endif
        </div>

        @if (session('status'))
            <flux:callout variant="success" icon="check-circle" :heading="session('status')" />
        @endif

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
                <p class="text-xs uppercase tracking-wide text-neutral-500">{{ __('Plan') }}</p>
                <p class="mt-2 text-lg font-semibold">{{ $tenant->subscription?->plan?->name ?? __('None') }}</p>
                <p class="text-xs text-neutral-500">{{ $tenant->subscription?->status?->value ?? '—' }}</p>
            </div>
            <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
                <p class="text-xs uppercase tracking-wide text-neutral-500">{{ __('Employees') }}</p>
                <p class="mt-2 text-lg font-semibold">{{ $employeeCount }}</p>
            </div>
            <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
                <p class="text-xs uppercase tracking-wide text-neutral-500">{{ __('Created') }}</p>
                <p class="mt-2 text-lg font-semibold">{{ $tenant->created_at?->format('Y-m-d') }}</p>
                <p class="text-xs text-neutral-500">{{ $tenant->country }} · {{ $tenant->currency }}</p>
            </div>
        </div>

        <div class="rounded-xl border border-neutral-200 dark:border-neutral-700">
            <div class="border-b border-neutral-100 px-4 py-3 dark:border-neutral-800">
                <flux:heading size="lg">{{ __('Users') }}</flux:heading>
            </div>
            <table class="w-full text-sm">
                <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                    @forelse ($users as $member)
                        <tr>
                            <td class="px-4 py-2">
                                <span class="font-medium">{{ $member->name }}</span>
                                <span class="text-neutral-500">&lt;{{ $member->email }}&gt;</span>
                            </td>
                            <td class="px-4 py-2 text-right">
                                @if ($canImpersonate && ! $member->is_platform_admin)
                                    <form method="POST" action="{{ route('impersonate.start', $member) }}"
                                          onsubmit="return confirm('{{ __('Sign in as this user? The session is audited.') }}')">
                                        @csrf
                                        <flux:button type="submit" size="sm" variant="ghost">{{ __('Impersonate') }}</flux:button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td class="px-4 py-4 text-neutral-500">{{ __('No users.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="rounded-xl border border-neutral-200 dark:border-neutral-700">
            <div class="border-b border-neutral-100 px-4 py-3 dark:border-neutral-800">
                <flux:heading size="lg">{{ __('Recent activity') }}</flux:heading>
            </div>
            <ul class="divide-y divide-neutral-100 text-sm dark:divide-neutral-800">
                @forelse ($recentAudit as $log)
                    <li class="flex items-center justify-between px-4 py-2">
                        <span><span class="font-mono text-xs">{{ $log->event }}</span> — {{ $log->actor_label }}</span>
                        <span class="text-neutral-500">{{ $log->created_at?->diffForHumans() }}</span>
                    </li>
                @empty
                    <li class="px-4 py-4 text-neutral-500">{{ __('Nothing recorded yet.') }}</li>
                @endforelse
            </ul>
        </div>
    </div>
</x-layouts::app>
