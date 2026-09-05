<div class="flex flex-col gap-6">
    <h1 class="text-xl font-semibold text-neutral-900 dark:text-neutral-100">{{ __('Performance') }}</h1>

    @if ($notice)
        <p class="rounded-lg bg-green-50 px-3 py-2 text-sm text-green-700 dark:bg-green-950 dark:text-green-300">{{ $notice }}</p>
    @endif

    @unless ($hasEmployee)
        <p class="rounded-xl border border-dashed border-neutral-300 p-8 text-center text-neutral-500 dark:border-neutral-700">
            {{ __('Your account is not linked to an employee record. Ask your HR team to link it.') }}
        </p>
    @else
        <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-800">
            <h2 class="text-sm font-semibold">{{ __('Add a goal') }}</h2>
            <form wire:submit="createGoal" class="mt-3 grid gap-3 sm:grid-cols-[1fr_12rem_12rem_auto]">
                <input type="text" wire:model="title" placeholder="{{ __('Goal title') }}"
                    class="rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900" />
                <select wire:model="category" class="rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900">
                    @foreach ($categories as $c)
                        <option value="{{ $c->value }}">{{ $c->label() }}</option>
                    @endforeach
                </select>
                <select wire:model="cycleUuid" class="rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900">
                    <option value="">{{ __('No cycle') }}</option>
                    @foreach ($cycles as $cycle)
                        <option value="{{ $cycle->uuid }}">{{ $cycle->name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white dark:bg-white dark:text-neutral-900">
                    {{ __('Add') }}
                </button>
            </form>
            @error('title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-800">
            <h2 class="text-sm font-semibold">{{ __('My goals') }}</h2>
            <table class="mt-2 w-full text-sm">
                <thead class="text-left text-xs uppercase text-neutral-500">
                    <tr><th class="py-1">{{ __('Goal') }}</th><th>{{ __('Cycle') }}</th><th>{{ __('Category') }}</th><th>{{ __('Progress') }}</th><th>{{ __('Mgr rating') }}</th></tr>
                </thead>
                <tbody>
                    @forelse ($goals as $goal)
                        <tr class="border-t border-neutral-100 dark:border-neutral-800">
                            <td class="py-1.5">{{ $goal->title }}</td>
                            <td class="text-neutral-500">{{ $goal->cycle?->name ?? '—' }}</td>
                            <td class="text-neutral-500">{{ $goal->category->label() }}</td>
                            <td>{{ $goal->progress }}%</td>
                            <td>{{ $goal->manager_rating ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-2 text-neutral-500">{{ __('No goals yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-800">
            <h2 class="text-sm font-semibold">{{ __('My KPIs') }}</h2>
            <ul class="mt-2 space-y-1 text-sm">
                @forelse ($kpis as $kpi)
                    <li class="flex justify-between">
                        <span class="text-neutral-500">{{ $kpi->name }}</span>
                        <span class="font-medium">{{ rtrim(rtrim(number_format($kpi->current_value, 2), '0'), '.') }} / {{ rtrim(rtrim(number_format($kpi->target_value, 2), '0'), '.') }} {{ $kpi->unit }}</span>
                    </li>
                @empty
                    <li class="text-neutral-500">{{ __('No KPIs yet.') }}</li>
                @endforelse
            </ul>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-800">
                <h2 class="text-sm font-semibold">{{ __('Reviews to complete') }}</h2>
                <ul class="mt-2 space-y-2 text-sm">
                    @forelse ($toComplete as $review)
                        <li class="flex items-center justify-between">
                            <span class="text-neutral-700 dark:text-neutral-300">
                                {{ $review->reviewer_type->label() }} — {{ $review->employee->fullName() }}
                                <span class="text-neutral-500">({{ $review->cycle->name }})</span>
                            </span>
                            <span class="rounded bg-neutral-100 px-2 py-0.5 text-xs dark:bg-neutral-800">{{ $review->status->label() }}</span>
                        </li>
                    @empty
                        <li class="text-neutral-500">{{ __('Nothing assigned to you.') }}</li>
                    @endforelse
                </ul>
            </div>

            <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-800">
                <h2 class="text-sm font-semibold">{{ __('My reviews') }}</h2>
                <ul class="mt-2 space-y-2 text-sm">
                    @forelse ($aboutMe as $review)
                        <li class="flex items-center justify-between">
                            <span class="text-neutral-700 dark:text-neutral-300">
                                {{ $review->reviewer_type->label() }}
                                <span class="text-neutral-500">({{ $review->cycle->name }})</span>
                            </span>
                            <span class="font-medium">{{ $review->overall_rating ?? '—' }}</span>
                        </li>
                    @empty
                        <li class="text-neutral-500">{{ __('No reviews yet.') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>
    @endunless
</div>
