<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:link :href="route('platform.tenants.index')" wire:navigate class="text-sm">&larr; {{ __('Organizations') }}</flux:link>
            <flux:heading size="xl" class="mt-1">{{ $tenant->name }}</flux:heading>
            <flux:text class="mt-1">{{ $tenant->slug }} · {{ $tenant->email }} · <x-status-badge :status="$tenant->status" /></flux:text>
        </div>

        @if ($canManage)
            <div class="flex gap-2">
                @if ($tenant->status === \App\Enums\TenantStatus::Suspended)
                    <flux:button variant="primary" wire:click="activate">{{ __('Activate') }}</flux:button>
                @else
                    <flux:button variant="danger" wire:click="suspend"
                        wire:confirm="{{ __('Suspend this organization? Its users lose app access.') }}">
                        {{ __('Suspend') }}
                    </flux:button>
                @endif
            </div>
        @endif
    </div>

    @if ($notice)
        <flux:callout variant="success" icon="check-circle" :heading="$notice" />
    @endif

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
            <p class="text-xs uppercase tracking-wide text-neutral-500">{{ __('Plan') }}</p>
            <p class="mt-2 text-lg font-semibold">{{ $tenant->subscription?->plan?->name ?? __('None') }}</p>
            <p class="text-xs text-neutral-500">{{ $tenant->subscription?->status?->label() ?? '—' }}</p>
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

    @if ($canViewPayments)
        <div class="grid gap-6 lg:grid-cols-2">
            <div class="rounded-xl border border-neutral-200 dark:border-neutral-700">
                <div class="flex items-center justify-between border-b border-neutral-100 px-4 py-3 dark:border-neutral-800">
                    <flux:heading size="lg">{{ __('Payments') }}</flux:heading>
                    <span class="text-sm text-neutral-500">{{ __('Paid to date:') }} <x-money :cents="$paidToDateCents" :decimals="0" /></span>
                </div>
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                        @forelse ($payments as $payment)
                            <tr wire:key="pay-{{ $payment->id }}">
                                <td class="px-4 py-2">
                                    <a href="{{ route('platform.billing.payments.show', $payment) }}" wire:navigate class="font-mono text-xs hover:underline">{{ $payment->reference }}</a>
                                    <div class="text-xs text-neutral-500">{{ $payment->provider->label() }} · {{ $payment->paid_at?->format('Y-m-d') ?? __('unpaid') }}</div>
                                </td>
                                <td class="px-4 py-2 text-right">
                                    <x-money :cents="$payment->amount_cents" :currency="$payment->currency" />
                                    <div><flux:badge size="sm" :color="$payment->status === \App\Enums\PaymentStatus::Succeeded ? 'green' : ($payment->status === \App\Enums\PaymentStatus::Failed ? 'red' : 'zinc')">{{ $payment->status->label() }}</flux:badge></div>
                                </td>
                            </tr>
                        @empty
                            <tr><td class="px-4 py-4 text-neutral-500">{{ __('No payments recorded.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="rounded-xl border border-neutral-200 dark:border-neutral-700">
                <div class="border-b border-neutral-100 px-4 py-3 dark:border-neutral-800">
                    <flux:heading size="lg">{{ __('Invoices') }}</flux:heading>
                </div>
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                        @forelse ($invoices as $invoice)
                            <tr wire:key="inv-{{ $invoice->id }}">
                                <td class="px-4 py-2 font-mono text-xs">{{ $invoice->number }}
                                    <div class="text-xs text-neutral-500">{{ $invoice->due_at?->format('Y-m-d') }}</div>
                                </td>
                                <td class="px-4 py-2 text-right">
                                    <x-money :cents="$invoice->total_cents" :currency="$invoice->currency" />
                                    <div><flux:badge size="sm" :color="$invoice->isPaid() ? 'green' : 'zinc'">{{ $invoice->status->label() }}</flux:badge></div>
                                </td>
                                <td class="px-4 py-2 text-right"><flux:button size="sm" variant="ghost" icon="arrow-down-tray" :href="route('platform.billing.invoices.pdf', $invoice)">{{ __('PDF') }}</flux:button></td>
                            </tr>
                        @empty
                            <tr><td class="px-4 py-4 text-neutral-500">{{ __('No invoices issued.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="rounded-xl border border-neutral-200 dark:border-neutral-700">
        <div class="border-b border-neutral-100 px-4 py-3 dark:border-neutral-800">
            <flux:heading size="lg">{{ __('Users') }}</flux:heading>
        </div>
        <table class="w-full text-sm">
            <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                @forelse ($users as $member)
                    <tr wire:key="user-{{ $member->id }}">
                        <td class="px-4 py-2">
                            <span class="font-medium">{{ $member->name }}</span>
                            <span class="text-neutral-500">&lt;{{ $member->email }}&gt;</span>
                        </td>
                        <td class="px-4 py-2">
                            @if ($canManageUsers && ! $member->is_platform_admin)
                                <flux:select size="sm" class="max-w-[12rem]" wire:change="updateUserRole({{ $member->id }}, $event.target.value)">
                                    <flux:select.option value="" disabled :selected="$member->roles->isEmpty()">{{ __('No role') }}</flux:select.option>
                                    @foreach ($assignableRoles as $roleCase)
                                        <flux:select.option value="{{ $roleCase->value }}" :selected="$member->roles->pluck('name')->contains($roleCase->value)">
                                            {{ $roleCase->value }}
                                        </flux:select.option>
                                    @endforeach
                                </flux:select>
                            @else
                                <flux:badge size="sm">{{ $member->roles->pluck('name')->join(', ') ?: __('No role') }}</flux:badge>
                            @endif
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
                    <tr><td colspan="3" class="px-4 py-4 text-neutral-500">{{ __('No users.') }}</td></tr>
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
                <li wire:key="audit-{{ $log->id }}" class="flex items-center justify-between px-4 py-2">
                    <span><span class="font-mono text-xs">{{ $log->event }}</span> — {{ $log->actor_label }}</span>
                    <span class="text-neutral-500">{{ $log->created_at?->diffForHumans() }}</span>
                </li>
            @empty
                <li class="px-4 py-4 text-neutral-500">{{ __('Nothing recorded yet.') }}</li>
            @endforelse
        </ul>
    </div>
</div>
