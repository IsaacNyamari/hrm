<x-layouts::app :title="__('Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <livewire:analytics.overview />

        <div class="rounded-xl border border-neutral-200 p-6 text-sm text-neutral-600 dark:border-neutral-700 dark:text-neutral-400">
            <p class="font-medium text-neutral-800 dark:text-neutral-200">{{ __('Reports') }}</p>
            <p class="mt-1">{{ __('Headcount, attendance, leave, payroll, expenses and recruitment reports — with Excel, CSV and print export — are in the') }}
                <a href="{{ route('reports') }}" wire:navigate class="underline">{{ __('Reports') }}</a> {{ __('area.') }}
            </p>
        </div>
    </div>
</x-layouts::app>
