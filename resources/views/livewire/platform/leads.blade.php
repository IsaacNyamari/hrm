<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Leads') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Quote requests and messages from the website. Registration is invite-only: send an access link to let an organization sign up.') }}</flux:text>
        </div>
        <div class="flex gap-3">
            <div class="rounded-xl border border-neutral-200 px-4 py-2 text-center dark:border-neutral-700">
                <p class="text-xl font-bold text-brand dark:text-highlight">{{ $newCount }}</p>
                <p class="text-xs text-zinc-500">{{ __('New') }}</p>
            </div>
            <div class="rounded-xl border border-neutral-200 px-4 py-2 text-center dark:border-neutral-700">
                <p class="text-xl font-bold text-brand dark:text-highlight">{{ $openLinks }}</p>
                <p class="text-xs text-zinc-500">{{ __('Unopened links') }}</p>
            </div>
        </div>
    </div>

    @if ($notice)
        <flux:callout variant="success" icon="check-circle" :heading="$notice" />
    @endif

    <div class="flex flex-wrap gap-3">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search name, email or organization…')" class="max-w-sm" />
        <flux:select wire:model.live="status" class="max-w-48">
            <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
            @foreach ($statuses as $option)
                <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-neutral-700">
        <table class="w-full text-sm">
            <thead class="bg-neutral-50 text-left text-neutral-500 dark:bg-neutral-900">
                <tr>
                    <th class="px-4 py-3 font-medium">{{ __('Contact') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Organization') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Type') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Received') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Status') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                @forelse ($leads as $lead)
                    <tr wire:key="lead-{{ $lead->uuid }}">
                        <td class="px-4 py-3">
                            <p class="font-medium text-zinc-900 dark:text-white">{{ $lead->name }}</p>
                            <p class="text-xs text-zinc-500">{{ $lead->email }}{{ $lead->phone ? ' · '.$lead->phone : '' }}</p>
                        </td>
                        <td class="px-4 py-3">
                            {{ $lead->company ?? '—' }}
                            @if ($lead->employee_count)
                                <p class="text-xs text-zinc-500">{{ trans_choice(':count employee|:count employees', $lead->employee_count) }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $lead->kind->label() }}</td>
                        <td class="px-4 py-3 text-zinc-500">{{ $lead->created_at->diffForHumans() }}</td>
                        <td class="px-4 py-3"><x-status-badge :status="$lead->status" /></td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <flux:button size="sm" variant="ghost" icon="eye" wire:click="view('{{ $lead->uuid }}')">{{ __('View') }}</flux:button>
                            <flux:button size="sm" variant="primary" icon="paper-airplane" wire:click="sendAccess('{{ $lead->uuid }}')"
                                         wire:confirm="{{ __('Email a single-use registration link to :email?', ['email' => $lead->email]) }}">
                                {{ $lead->status === \App\Enums\LeadStatus::Invited ? __('Resend link') : __('Send access link') }}
                            </flux:button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-12 text-center text-neutral-500">{{ __('No leads yet. Quote requests from the website appear here.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $leads->links() }}

    <flux:modal :show="$viewing !== null" wire:close="closeView" class="max-w-xl">
        @if ($viewing)
            <div class="space-y-5">
                <div>
                    <flux:heading size="lg">{{ $viewing->name }}</flux:heading>
                    <flux:text class="mt-1">{{ $viewing->kind->label() }} · {{ $viewing->created_at->toDayDateTimeString() }}</flux:text>
                </div>

                <dl class="grid grid-cols-2 gap-4 text-sm">
                    <div><dt class="text-zinc-500">{{ __('Email') }}</dt><dd class="font-medium">{{ $viewing->email }}</dd></div>
                    <div><dt class="text-zinc-500">{{ __('Phone') }}</dt><dd class="font-medium">{{ $viewing->phone ?? '—' }}</dd></div>
                    <div><dt class="text-zinc-500">{{ __('Organization') }}</dt><dd class="font-medium">{{ $viewing->company ?? '—' }}</dd></div>
                    <div><dt class="text-zinc-500">{{ __('Employees') }}</dt><dd class="font-medium">{{ $viewing->employee_count ?? '—' }}</dd></div>
                    <div><dt class="text-zinc-500">{{ __('Plan interest') }}</dt><dd class="font-medium">{{ $viewing->plan_interest ? str($viewing->plan_interest)->headline() : '—' }}</dd></div>
                    <div><dt class="text-zinc-500">{{ __('Status') }}</dt><dd><x-status-badge :status="$viewing->status" /></dd></div>
                </dl>

                @if ($viewing->message)
                    <div class="rounded-lg bg-zinc-50 p-4 text-sm whitespace-pre-line dark:bg-white/5">{{ $viewing->message }}</div>
                @endif

                @if ($viewing->invitations->isNotEmpty())
                    <div>
                        <p class="mb-2 text-sm font-semibold">{{ __('Access links') }}</p>
                        <ul class="space-y-1 text-xs text-zinc-500">
                            @foreach ($viewing->invitations as $invitation)
                                <li>
                                    {{ __('Sent') }} {{ $invitation->created_at->diffForHumans() }} —
                                    @if ($invitation->used_at)
                                        <span class="font-semibold text-success">{{ __('registered') }}</span>
                                    @elseif ($invitation->opened_at)
                                        {{ __('opened') }} {{ $invitation->opened_at->diffForHumans() }}
                                    @elseif ($invitation->expires_at->isPast())
                                        {{ __('expired / revoked') }}
                                    @else
                                        {{ __('not opened yet, expires') }} {{ $invitation->expires_at->diffForHumans() }}
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="flex flex-wrap justify-end gap-2">
                    <flux:button size="sm" variant="ghost" wire:click="setStatus('{{ $viewing->uuid }}', 'contacted')">{{ __('Mark contacted') }}</flux:button>
                    <flux:button size="sm" variant="ghost" wire:click="setStatus('{{ $viewing->uuid }}', 'declined')">{{ __('Decline') }}</flux:button>
                    <flux:button size="sm" variant="primary" icon="paper-airplane" wire:click="sendAccess('{{ $viewing->uuid }}')">{{ __('Send access link') }}</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
