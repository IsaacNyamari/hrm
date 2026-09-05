<div class="flex flex-col gap-4">
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold text-neutral-900 dark:text-neutral-100">{{ __('Organization chart') }}</h1>
        <div class="inline-flex rounded-lg border border-neutral-300 p-0.5 text-sm dark:border-neutral-700">
            <button type="button" wire:click="$set('view', 'reporting')"
                @class(['rounded-md px-3 py-1', 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900' => $view === 'reporting'])>
                {{ __('Reporting lines') }}
            </button>
            <button type="button" wire:click="$set('view', 'departments')"
                @class(['rounded-md px-3 py-1', 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900' => $view === 'departments'])>
                {{ __('Departments') }}
            </button>
        </div>
    </div>

    @if (count($tree) === 0)
        <p class="rounded-xl border border-dashed border-neutral-300 p-12 text-center text-neutral-500 dark:border-neutral-700">
            {{ __('Nothing to chart yet. Add employees and assign managers.') }}
        </p>
    @else
        <ul class="space-y-2">
            @foreach ($tree as $node)
                @include('livewire.organization.node', ['node' => $node, 'childKey' => $childKey, 'depth' => 0])
            @endforeach
        </ul>
    @endif
</div>
