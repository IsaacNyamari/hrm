<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Plans') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Pricing tiers offered to organizations. Existing subscriptions keep their price until changed.') }}</flux:text>
        </div>
        <flux:button variant="primary" wire:click="create">{{ __('New plan') }}</flux:button>
    </div>

    @if ($notice)
        <flux:callout variant="success" icon="check-circle" :heading="$notice" />
    @endif

    <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-neutral-700">
        <table class="w-full text-sm">
            <thead class="bg-neutral-50 text-left text-neutral-500 dark:bg-neutral-900">
                <tr>
                    <th class="px-4 py-3 font-medium">{{ __('Plan') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Price') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Interval') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Employee limit') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Subs') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Active') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                @forelse ($plans as $plan)
                    <tr wire:key="plan-{{ $plan->id }}">
                        <td class="px-4 py-3 font-medium">{{ $plan->name }}<div class="text-xs text-neutral-500">{{ $plan->slug }}</div></td>
                        <td class="px-4 py-3"><x-money :cents="$plan->price_cents" :currency="$plan->currency" :decimals="0" /></td>
                        <td class="px-4 py-3">{{ $plan->billing_interval->label() }}</td>
                        <td class="px-4 py-3">{{ $plan->employee_limit ?? __('Unlimited') }}</td>
                        <td class="px-4 py-3">{{ $plan->subscriptions_count }}</td>
                        <td class="px-4 py-3">{{ $plan->is_active ? __('Yes') : __('No') }}</td>
                        <td class="px-4 py-3 text-right">
                            <flux:button size="sm" variant="primary" wire:click="edit({{ $plan->id }})">{{ __('Edit') }}</flux:button>
                            <flux:modal.trigger name="delete-plan-{{ $plan->id }}">
                                <flux:button size="sm" variant="primary" color="red" icon="trash">{{ __('Delete') }}</flux:button>
                            </flux:modal.trigger>
                            <x-confirm-modal
                                name="delete-plan-{{ $plan->id }}"
                                :heading="__('Delete the “:name” plan?', ['name' => $plan->name])"
                                :text="$plan->subscriptions_count > 0
                                    ? trans_choice('This plan has :count subscription. This cannot be undone.|This plan has :count subscriptions. This cannot be undone.', $plan->subscriptions_count)
                                    : __('This cannot be undone.')"
                                :confirm-label="__('Delete plan')"
                                confirm-wire="delete({{ $plan->id }})" />
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-6 text-center text-neutral-500">{{ __('No plans yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <flux:modal wire:model.self="showForm" class="max-w-lg">
        <form wire:submit="save" class="flex flex-col gap-4">
            <flux:heading size="lg">{{ $editingId ? __('Edit plan') : __('New plan') }}</flux:heading>

            <flux:input wire:model="form.name" :label="__('Name')" required />
            <flux:textarea wire:model="form.description" :label="__('Description')" rows="2" />

            <div class="grid grid-cols-2 gap-3">
                <flux:input type="number" wire:model="form.price_cents" :label="__('Price (cents)')" required />
                <flux:select wire:model="form.billing_interval" :label="__('Interval')">
                    @foreach ($intervals as $interval)
                        <flux:select.option value="{{ $interval->value }}">{{ $interval->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input type="number" wire:model="form.employee_limit" :label="__('Employee limit')" placeholder="{{ __('Unlimited') }}" />
                <flux:input type="number" wire:model="form.storage_limit_mb" :label="__('Storage (MB)')" placeholder="{{ __('Unlimited') }}" />
                <flux:input type="number" wire:model="form.trial_days" :label="__('Trial days')" />
                <flux:input type="number" wire:model="form.sort_order" :label="__('Sort order')" />
            </div>

            <div>
                <flux:label>{{ __('Features included') }}</flux:label>
                <div class="mt-2 grid grid-cols-2 gap-2">
                    @foreach (config('billing.feature_flags') as $flag)
                        <flux:field variant="inline">
                            <flux:checkbox wire:model="form.flags" value="{{ $flag }}" />
                            <flux:label>{{ str($flag)->replace('_', ' ')->headline() }}</flux:label>
                        </flux:field>
                    @endforeach
                </div>
            </div>

            <flux:field variant="inline">
                <flux:checkbox wire:model="form.is_active" />
                <flux:label>{{ __('Active (offered to new organizations)') }}</flux:label>
            </flux:field>

            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showForm', false)">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
