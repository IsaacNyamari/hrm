<div class="flex flex-col gap-6">
    <h1 class="text-xl font-semibold text-neutral-900 dark:text-neutral-100">{{ __('Leave') }}</h1>

    @unless ($hasEmployee)
        <p class="rounded-xl border border-dashed border-neutral-300 p-8 text-center text-neutral-500 dark:border-neutral-700">
            {{ __('Your account is not linked to an employee record.') }}
        </p>
    @else
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($balances as $b)
                <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-800">
                    <p class="text-xs uppercase text-neutral-500">{{ $b['name'] }}</p>
                    <p class="mt-1 text-2xl font-semibold text-neutral-900 dark:text-neutral-100">{{ $b['remaining'] }}</p>
                    <p class="text-xs text-neutral-500">{{ $b['used'] }} {{ __('used') }} · {{ $b['pending'] }} {{ __('pending') }}</p>
                </div>
            @endforeach
        </div>

        <form wire:submit="submit" class="grid gap-3 rounded-xl border border-neutral-200 p-4 dark:border-neutral-800 md:grid-cols-2">
            <div class="md:col-span-2">
                <h2 class="text-sm font-semibold">{{ __('Request leave') }}</h2>
            </div>

            @if ($error)
                <p class="md:col-span-2 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-950 dark:text-red-300">{{ $error }}</p>
            @endif
            @if ($success)
                <p class="md:col-span-2 rounded-lg bg-green-50 px-3 py-2 text-sm text-green-700 dark:bg-green-950 dark:text-green-300">{{ $success }}</p>
            @endif

            <label class="text-sm">
                <span class="text-neutral-600 dark:text-neutral-400">{{ __('Leave type') }}</span>
                <select wire:model="leaveTypeUuid" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900">
                    <option value="">{{ __('Select…') }}</option>
                    @foreach ($leaveTypes as $type)
                        <option value="{{ $type->uuid }}">{{ $type->name }}</option>
                    @endforeach
                </select>
                @error('leaveTypeUuid') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </label>

            <label class="text-sm">
                <span class="text-neutral-600 dark:text-neutral-400">{{ __('Reason') }}</span>
                <input type="text" wire:model="reason" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900" />
            </label>

            <label class="text-sm">
                <span class="text-neutral-600 dark:text-neutral-400">{{ __('Start date') }}</span>
                <input type="date" wire:model="startDate" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900" />
                @error('startDate') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </label>

            <label class="text-sm">
                <span class="text-neutral-600 dark:text-neutral-400">{{ __('End date') }}</span>
                <input type="date" wire:model="endDate" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900" />
                @error('endDate') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </label>

            <div class="flex items-center gap-4 text-sm md:col-span-2">
                <label class="flex items-center gap-2"><input type="checkbox" wire:model="halfDayStart"> {{ __('Half day (start)') }}</label>
                <label class="flex items-center gap-2"><input type="checkbox" wire:model="halfDayEnd"> {{ __('Half day (end)') }}</label>
            </div>

            <div class="md:col-span-2">
                <button type="submit" class="rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white dark:bg-white dark:text-neutral-900">
                    {{ __('Submit request') }}
                </button>
            </div>
        </form>

        <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-neutral-800">
            <table class="w-full text-left text-sm">
                <thead class="bg-neutral-50 text-xs uppercase text-neutral-500 dark:bg-neutral-900">
                    <tr>
                        <th class="px-4 py-3">{{ __('Type') }}</th>
                        <th class="px-4 py-3">{{ __('Dates') }}</th>
                        <th class="px-4 py-3">{{ __('Days') }}</th>
                        <th class="px-4 py-3">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                    @forelse ($requests as $r)
                        <tr wire:key="lr-{{ $r->uuid }}">
                            <td class="px-4 py-3">{{ $r->leaveType->name }}</td>
                            <td class="px-4 py-3 text-neutral-500">{{ $r->start_date->toFormattedDateString() }} – {{ $r->end_date->toFormattedDateString() }}</td>
                            <td class="px-4 py-3">{{ $r->days }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full bg-neutral-100 px-2 py-0.5 text-xs text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300">{{ $r->status->label() }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-10 text-center text-neutral-500">{{ __('No leave requests yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endunless
</div>
