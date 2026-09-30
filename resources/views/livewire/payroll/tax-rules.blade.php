<div class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-700" x-data="{ open: {{ $alerts !== [] ? 'true' : 'false' }} }">
    <button type="button" @click="open = !open" class="flex w-full items-center justify-between gap-4 text-left">
        <span class="flex items-center gap-3">
            <span class="grid size-10 place-items-center rounded-lg bg-brand/10 text-brand dark:text-highlight"><flux:icon.scale variant="mini" /></span>
            <span>
                <span class="block font-semibold text-zinc-900 dark:text-white">{{ __('Statutory rates') }}</span>
                <span class="block text-sm text-zinc-500">{{ __('PAYE bands, NSSF, SHIF, Housing Levy and NITA, by effective date') }}</span>
            </span>
        </span>
        <flux:icon.chevron-down variant="mini" class="text-zinc-400 transition" ::class="open && 'rotate-180'" />
    </button>

    <div x-show="open" x-collapse x-cloak class="mt-6 space-y-6">
        @foreach ($alerts as $alert)
            <p class="rounded-lg px-3 py-2 text-sm {{ $alert['level'] === 'danger' ? 'bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-300' : 'bg-amber-50 text-amber-800 dark:bg-amber-950 dark:text-amber-200' }}">{{ $alert['message'] }}</p>
        @endforeach

        <p class="text-xs text-zinc-500">{{ __('Rates apply by the payroll pay date. To change a rate from a given date, end the current rule the day before and add a new rule starting on that date; earlier payroll runs keep the rates they were paid under. Confirm new figures against official KRA, SHA and NSSF publications before saving.') }}</p>

        @if ($saved)
            <p class="text-sm font-medium text-success" x-data x-init="setTimeout(() => $wire.set('saved', false), 3000)">{{ __('Saved. Re-run affected payroll to apply.') }}</p>
        @endif

        @foreach ($types as $ruleType)
            <div>
                <p class="text-sm font-semibold text-zinc-900 dark:text-white">{{ $ruleType->label() }}</p>
                <div class="mt-2 divide-y divide-neutral-100 rounded-lg border border-neutral-200 text-sm dark:divide-neutral-800 dark:border-neutral-800">
                    @forelse ($rules->get($ruleType->value, collect()) as $rule)
                        @php($c = $rule->config)
                        <div class="flex flex-wrap items-center justify-between gap-3 px-3 py-2">
                            <div>
                                <span class="font-medium">{{ $rule->effective_from->format('d M Y') }} → {{ $rule->effective_to?->format('d M Y') ?? __('open-ended') }}</span>
                                @unless ($rule->is_active)<span class="ms-2 rounded-full bg-neutral-100 px-2 py-0.5 text-xs text-neutral-600 dark:bg-neutral-800">{{ __('Inactive') }}</span>@endunless
                                <span class="block text-xs text-zinc-500">
                                    @switch($ruleType->value)
                                        @case('paye') {{ __(':n bands · personal relief KES :r', ['n' => count($c['bands'] ?? []), 'r' => number_format(($c['personal_relief_cents'] ?? 0) / 100, 2)]) }} @break
                                        @case('nssf') {{ __('Employee :e% · employer :r% · upper earnings limit KES :c', ['e' => round(($c['rate'] ?? 0) * 100, 4) + 0, 'r' => round((($c['employer_rate'] ?? $c['rate'] ?? 0)) * 100, 4) + 0, 'c' => number_format(($c['ceiling_cents'] ?? 0) / 100)]) }} @break
                                        @case('shif') {{ __(':e% of gross · minimum KES :m', ['e' => round(($c['rate'] ?? 0) * 100, 4) + 0, 'm' => number_format(($c['minimum_cents'] ?? 0) / 100, 2)]) }} @break
                                        @case('housing_levy') {{ __('Employee :e% · employer :r% of gross', ['e' => round(($c['rate'] ?? 0) * 100, 4) + 0, 'r' => round((($c['employer_rate'] ?? 0)) * 100, 4) + 0]) }} @break
                                        @case('nita') {{ __('KES :a per employee per month', ['a' => number_format(($c['amount_cents'] ?? 0) / 100, 2)]) }} @break
                                        @default {{ round(($c['rate'] ?? 0) * 100, 4) + 0 }}%
                                    @endswitch
                                </span>
                            </div>
                            @if ($canManage)
                                <div class="flex gap-2">
                                    <button type="button" wire:click="edit('{{ $rule->uuid }}')" class="rounded-lg border border-neutral-300 px-2 py-1 text-xs dark:border-neutral-700">{{ __('Edit') }}</button>
                                    <button type="button" wire:click="delete('{{ $rule->uuid }}')" wire:confirm="{{ __('Delete this rule? Payroll dated in this period will be blocked until another rule covers it.') }}" class="rounded-lg border border-red-300 px-2 py-1 text-xs text-red-700 dark:border-red-800">{{ __('Delete') }}</button>
                                </div>
                            @endif
                        </div>
                    @empty
                        <p class="px-3 py-2 text-xs text-zinc-500">{{ __('No rules yet.') }}</p>
                    @endforelse
                </div>
            </div>
        @endforeach

        @if ($canManage)
            @if (! $showForm)
                <flux:button type="button" wire:click="create">{{ __('Add rule') }}</flux:button>
            @else
                <form wire:submit="save" class="space-y-4 rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
                    <p class="font-semibold text-zinc-900 dark:text-white">{{ $editingUuid ? __('Edit rule') : __('New rule') }}</p>
                    <div class="grid gap-4 sm:grid-cols-4">
                        <flux:select wire:model.live="type" :label="__('Type')" :disabled="$editingUuid !== null">
                            @foreach ($types as $ruleType)
                                <flux:select.option value="{{ $ruleType->value }}">{{ $ruleType->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:input type="date" wire:model="effective_from" :label="__('Effective from')" required />
                        <flux:input type="date" wire:model="effective_to" :label="__('Effective to (optional)')" />
                        <flux:switch wire:model="is_active" :label="__('Active')" class="mt-7" />
                    </div>
                    @error('effective_from')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    @error('effective_to')<p class="text-sm text-red-600">{{ $message }}</p>@enderror

                    <div class="grid gap-4 sm:grid-cols-3">
                        @if (in_array($type, ['nssf', 'shif', 'housing_levy', 'pension'], true))
                            <flux:input type="number" step="0.0001" min="0" wire:model="rate" :label="in_array($type, ['nssf', 'housing_levy'], true) ? __('Employee rate (%)') : __('Rate (%)')" />
                        @endif
                        @if (in_array($type, ['nssf', 'housing_levy'], true))
                            <flux:input type="number" step="0.0001" min="0" wire:model="employer_rate" :label="__('Employer rate (%)')" />
                        @endif
                        @if ($type === 'nssf')
                            <flux:input type="number" step="0.01" min="0" wire:model="ceiling" :label="__('Upper earnings limit (KES)')" :description="__('Pensionable pay above this is not charged')" />
                        @endif
                        @if ($type === 'shif')
                            <flux:input type="number" step="0.01" min="0" wire:model="minimum" :label="__('Minimum contribution (KES)')" />
                        @endif
                        @if ($type === 'nita')
                            <flux:input type="number" step="0.01" min="0" wire:model="amount" :label="__('Per employee per month (KES)')" />
                        @endif
                        @if ($type === 'paye')
                            <flux:input type="number" step="0.01" min="0" wire:model="relief" :label="__('Monthly personal relief (KES)')" />
                        @endif
                    </div>
                    @foreach ($errors->getBag('default')->keys() as $key)
                        @if (str_starts_with($key, 'config'))
                            <p class="text-sm text-red-600">{{ $errors->first($key) }}</p>
                        @endif
                    @endforeach

                    @if ($type === 'paye')
                        <div class="space-y-2">
                            <p class="text-sm font-medium">{{ __('Monthly bands') }}</p>
                            @foreach ($bands as $i => $band)
                                <div class="flex items-end gap-3" wire:key="band-{{ $i }}">
                                    <flux:input type="number" step="0.01" min="0" wire:model="bands.{{ $i }}.upto" :label="__('Up to (KES)')" :placeholder="__('Empty for the last band')" />
                                    <flux:input type="number" step="0.0001" min="0" max="100" wire:model="bands.{{ $i }}.rate" :label="__('Rate (%)')" />
                                    <button type="button" wire:click="removeBand({{ $i }})" class="mb-1 rounded-lg border border-neutral-300 px-2 py-1 text-xs dark:border-neutral-700">{{ __('Remove') }}</button>
                                </div>
                            @endforeach
                            <button type="button" wire:click="addBand" class="rounded-lg border border-neutral-300 px-2 py-1 text-xs dark:border-neutral-700">{{ __('Add band') }}</button>
                            @error('bands')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                    @endif

                    <div class="flex justify-end gap-3">
                        <flux:button type="button" wire:click="cancel">{{ __('Cancel') }}</flux:button>
                        <flux:button type="submit" variant="primary">{{ __('Save rule') }}</flux:button>
                    </div>
                </form>
            @endif
        @endif
    </div>
</div>
