<x-layouts::app :title="__('Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <h1 class="text-xl font-bold text-zinc-900 dark:text-white">{{ __('Hi, welcome back!') }}</h1>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Here is what is happening across your organization today.') }}</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <flux:button size="sm" icon="bell" :href="route('notifications')" wire:navigate>{{ __('Notifications') }}</flux:button>
                <flux:button size="sm" icon="chart-bar" :href="route('reports')" wire:navigate>{{ __('Reports') }}</flux:button>
                @can('employees.create')
                    <a href="{{ route('employees.index') }}" wire:navigate class="inline-flex h-8 items-center gap-1.5 rounded-lg bg-highlight px-3 text-sm font-semibold text-night shadow-sm shadow-highlight/30 transition hover:bg-cyan-400">
                        <flux:icon.user-plus variant="micro" />
                        {{ __('Add employee') }}
                    </a>
                @endcan
            </div>
        </div>

        <livewire:analytics.overview />

        <div class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-neutral-200 p-6 dark:border-neutral-700">
            <div class="text-sm text-neutral-600 dark:text-neutral-400">
                <p class="font-semibold text-neutral-800 dark:text-neutral-200">{{ __('Reports') }}</p>
                <p class="mt-1">{{ __('Headcount, attendance, leave, payroll, expenses and recruitment reports — with Excel, CSV and print export.') }}</p>
            </div>
            <flux:button variant="primary" size="sm" icon-trailing="arrow-right" :href="route('reports')" wire:navigate>{{ __('Open reports') }}</flux:button>
        </div>
    </div>
</x-layouts::app>
