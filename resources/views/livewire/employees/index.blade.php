<div class="flex flex-col gap-4">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-neutral-900 dark:text-neutral-100">{{ __('Employees') }}</h1>
            <p class="text-sm text-neutral-500">{{ $employees->total() }} {{ Str::plural('person', $employees->total()) }}</p>
        </div>
        <a href="{{ route('employees.export') }}"
           class="rounded-lg border border-neutral-300 px-3 py-2 text-sm font-medium hover:bg-neutral-50 dark:border-neutral-700 dark:hover:bg-neutral-800">
            {{ __('Export') }}
        </a>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search name, number, email…') }}"
               class="w-64 rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900" />

        <select wire:model.live="status" class="rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900">
            <option value="">{{ __('All statuses') }}</option>
            @foreach ($statuses as $case)
                <option value="{{ $case->value }}">{{ $case->label() }}</option>
            @endforeach
        </select>

        <select wire:model.live="department" class="rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900">
            <option value="">{{ __('All departments') }}</option>
            @foreach ($departments as $dept)
                <option value="{{ $dept->uuid }}">{{ $dept->name }}</option>
            @endforeach
        </select>

        @if ($search !== '' || $status !== '' || $department !== '')
            <button type="button" wire:click="clearFilters" class="text-sm text-neutral-500 underline">{{ __('Clear') }}</button>
        @endif
    </div>

    <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-neutral-800">
        <table class="w-full text-left text-sm">
            <thead class="bg-neutral-50 text-xs uppercase text-neutral-500 dark:bg-neutral-900">
                <tr>
                    <th class="px-4 py-3">{{ __('Number') }}</th>
                    <th class="px-4 py-3">{{ __('Name') }}</th>
                    <th class="px-4 py-3">{{ __('Job title') }}</th>
                    <th class="px-4 py-3">{{ __('Department') }}</th>
                    <th class="px-4 py-3">{{ __('Branch') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                @forelse ($employees as $employee)
                    <tr wire:key="emp-{{ $employee->uuid }}" class="hover:bg-neutral-50 dark:hover:bg-neutral-900">
                        <td class="px-4 py-3 font-mono text-xs">{{ $employee->employee_number }}</td>
                        <td class="px-4 py-3 font-medium text-neutral-900 dark:text-neutral-100">{{ $employee->fullName() }}</td>
                        <td class="px-4 py-3 text-neutral-500">{{ $employee->job_title ?? '—' }}</td>
                        <td class="px-4 py-3 text-neutral-500">{{ $employee->department?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-neutral-500">{{ $employee->branch?->name ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full bg-neutral-100 px-2 py-0.5 text-xs font-medium text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300">
                                {{ $employee->status->label() }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center text-neutral-500">{{ __('No employees match your filters.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $employees->links() }}</div>
</div>
