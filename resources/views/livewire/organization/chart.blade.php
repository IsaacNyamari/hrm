<div class="flex flex-col gap-4" x-data="{ zoom: 1, q: '' }">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-neutral-800 dark:text-neutral-100">{{ __('Organogram') }}</h1>
            <p class="text-sm text-zinc-500">
                {{ $view === 'departments' ? __('How departments nest, with their heads and headcount.') : __('Who reports to whom, from the top down.') }}
            </p>
        </div>
        <div class="inline-flex rounded-lg border border-neutral-300 p-0.5 text-sm dark:border-neutral-700">
            <button type="button" wire:click="$set('view', 'reporting')"
                @class(['rounded-md px-3 py-1', 'bg-brand text-white' => $view === 'reporting'])>
                {{ __('Reporting lines') }}
            </button>
            <button type="button" wire:click="$set('view', 'departments')"
                @class(['rounded-md px-3 py-1', 'bg-brand text-white' => $view === 'departments'])>
                {{ __('Departments') }}
            </button>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
        @foreach ($stats as [$label, $value, $icon])
            <div class="flex items-center gap-3 rounded-xl border border-neutral-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-900">
                <span class="grid size-10 place-items-center rounded-lg bg-brand/10 text-brand dark:text-highlight"><flux:icon :name="$icon" variant="mini" /></span>
                <div>
                    <p class="text-xl font-bold text-zinc-900 dark:text-white">{{ $value }}</p>
                    <p class="text-xs text-zinc-500">{{ $label }}</p>
                </div>
            </div>
        @endforeach
    </div>

    @if (count($tree) === 0)
        <p class="rounded-xl border border-dashed border-neutral-300 p-12 text-center text-neutral-500 dark:border-neutral-700">
            {{ $view === 'departments' ? __('No departments yet. Add departments and set their parent to build the structure.') : __('Nothing to chart yet. Add employees and assign managers.') }}
        </p>
    @else
        <div class="rounded-xl border border-neutral-200 bg-neutral-50/60 dark:border-neutral-700 dark:bg-neutral-950/40">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-neutral-200 px-4 py-3 dark:border-neutral-700">
                <flux:input x-model.debounce.200ms="q" icon="magnifying-glass" size="sm" class="max-w-xs"
                    :placeholder="$view === 'departments' ? __('Find a department') : __('Find a person')" />
                <div class="flex items-center gap-1">
                    <flux:button size="sm" variant="ghost" icon="minus" @click="zoom = Math.max(0.4, +(zoom - 0.1).toFixed(1))" :aria-label="__('Zoom out')" />
                    <button type="button" class="w-14 text-center text-xs text-zinc-500" @click="zoom = 1" x-text="Math.round(zoom * 100) + '%'"></button>
                    <flux:button size="sm" variant="ghost" icon="plus" @click="zoom = Math.min(1.5, +(zoom + 0.1).toFixed(1))" :aria-label="__('Zoom in')" />
                </div>
            </div>

            <div class="overflow-auto p-6" style="max-height: 75vh">
                <div class="orgchart mx-auto w-max origin-top transition-transform" :style="`transform: scale(${zoom})`" data-test="organogram">
                    <ul>
                        @foreach ($tree as $node)
                            @include('livewire.organization.node', ['node' => $node, 'childKey' => $childKey, 'depth' => 0, 'canManage' => $canManage])
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <flux:modal name="reassign-manager" class="max-w-sm">
        <form wire:submit="saveManager" class="flex flex-col gap-4">
            <flux:heading size="lg">{{ __('Change manager') }}</flux:heading>
            <flux:text size="sm">{{ __('Who does :name report to?', ['name' => $reassigningName]) }}</flux:text>
            <flux:select wire:model="newManagerId" :label="__('Manager')">
                <flux:select.option value="">{{ __('No manager (top level)') }}</flux:select.option>
                @foreach ($employees as $emp)
                    <flux:select.option value="{{ $emp->id }}">{{ $emp->first_name }} {{ $emp->last_name }}</flux:select.option>
                @endforeach
            </flux:select>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button type="button" variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
