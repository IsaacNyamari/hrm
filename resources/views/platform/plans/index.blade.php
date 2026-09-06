<x-layouts::app :title="__('Plans')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <div>
            <flux:heading size="xl">{{ __('Plans') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Pricing tiers offered to organizations. Existing subscriptions keep their price until changed.') }}</flux:text>
        </div>

        @if (session('status'))
            <flux:callout variant="success" icon="check-circle" :heading="session('status')" />
        @endif

        @if ($errors->any())
            <flux:callout variant="danger" icon="exclamation-triangle" :heading="__('Please fix the errors below.')" />
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
                    @foreach ($plans as $plan)
                        <tr>
                            <td class="px-4 py-3 font-medium">{{ $plan->name }}<div class="text-xs text-neutral-500">{{ $plan->slug }}</div></td>
                            <td class="px-4 py-3">{{ $plan->currency }} {{ number_format($plan->price_cents / 100, 0) }}</td>
                            <td class="px-4 py-3">{{ $plan->billing_interval->label() }}</td>
                            <td class="px-4 py-3">{{ $plan->employee_limit ?? __('Unlimited') }}</td>
                            <td class="px-4 py-3">{{ $plan->subscriptions_count }}</td>
                            <td class="px-4 py-3">{{ $plan->is_active ? __('Yes') : __('No') }}</td>
                            <td class="px-4 py-3 text-right">
                                <flux:modal.trigger name="edit-plan-{{ $plan->id }}">
                                    <flux:button size="sm" variant="ghost">{{ __('Edit') }}</flux:button>
                                </flux:modal.trigger>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @foreach ($plans as $plan)
            <flux:modal name="edit-plan-{{ $plan->id }}" class="max-w-lg">
                <form method="POST" action="{{ route('platform.plans.update', $plan) }}" class="flex flex-col gap-4">
                    @csrf
                    @method('PUT')
                    <flux:heading size="lg">{{ __('Edit :name', ['name' => $plan->name]) }}</flux:heading>
                    <flux:input name="name" :label="__('Name')" :value="old('name', $plan->name)" required />
                    <flux:textarea name="description" :label="__('Description')">{{ old('description', $plan->description) }}</flux:textarea>
                    <div class="grid grid-cols-2 gap-3">
                        <flux:input type="number" name="price_cents" :label="__('Price (cents)')" :value="old('price_cents', $plan->price_cents)" required />
                        <flux:select name="billing_interval" :label="__('Interval')">
                            @foreach ($intervals as $interval)
                                <flux:select.option value="{{ $interval->value }}" :selected="old('billing_interval', $plan->billing_interval->value) === $interval->value">{{ $interval->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:input type="number" name="employee_limit" :label="__('Employee limit')" :value="old('employee_limit', $plan->employee_limit)" />
                        <flux:input type="number" name="storage_limit_mb" :label="__('Storage (MB)')" :value="old('storage_limit_mb', $plan->storage_limit_mb)" />
                        <flux:input type="number" name="trial_days" :label="__('Trial days')" :value="old('trial_days', $plan->trial_days)" />
                        <flux:input type="number" name="sort_order" :label="__('Sort order')" :value="old('sort_order', $plan->sort_order)" />
                    </div>
                    <flux:field variant="inline">
                        <flux:checkbox name="is_active" value="1" :checked="(bool) old('is_active', $plan->is_active)" />
                        <flux:label>{{ __('Active (offered to new organizations)') }}</flux:label>
                    </flux:field>
                    <div class="flex justify-end gap-2">
                        <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                    </div>
                </form>
            </flux:modal>
        @endforeach

        <div class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-700">
            <flux:heading size="lg">{{ __('New plan') }}</flux:heading>
            <form method="POST" action="{{ route('platform.plans.store') }}" class="mt-4 grid gap-4 sm:grid-cols-2">
                @csrf
                <flux:input name="name" :label="__('Name')" :value="old('name')" required />
                <flux:select name="billing_interval" :label="__('Interval')">
                    @foreach ($intervals as $interval)
                        <flux:select.option value="{{ $interval->value }}" :selected="old('billing_interval') === $interval->value">{{ $interval->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input type="number" name="price_cents" :label="__('Price (cents)')" :value="old('price_cents', 0)" required />
                <flux:input type="number" name="trial_days" :label="__('Trial days')" :value="old('trial_days', 14)" />
                <flux:input type="number" name="employee_limit" :label="__('Employee limit (blank = unlimited)')" :value="old('employee_limit')" />
                <flux:input type="number" name="storage_limit_mb" :label="__('Storage MB (blank = unlimited)')" :value="old('storage_limit_mb')" />
                <flux:textarea name="description" :label="__('Description')" class="sm:col-span-2">{{ old('description') }}</flux:textarea>
                <div class="sm:col-span-2">
                    <flux:button type="submit" variant="primary">{{ __('Create plan') }}</flux:button>
                </div>
            </form>
        </div>
    </div>
</x-layouts::app>
