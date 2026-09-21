<div class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-700" x-data="{ open: false }">
    <button type="button" @click="open = !open" class="flex w-full items-center justify-between gap-4 text-left">
        <span class="flex items-center gap-3">
            <span class="grid size-10 place-items-center rounded-lg bg-brand/10 text-brand dark:text-highlight"><flux:icon.adjustments-horizontal variant="mini" /></span>
            <span>
                <span class="block font-semibold text-zinc-900 dark:text-white">{{ __('Pay rules') }}</span>
                <span class="block text-sm text-zinc-500">
                    {{ $salary_basis === 'attendance' ? __('Basic salary follows attendance') : __('Fixed monthly salary') }}
                    · {{ $overtime_enabled ? __('Overtime :normal× / rest days :rest×', ['normal' => $overtime_multiplier + 0, 'rest' => $rest_day_multiplier + 0]) : __('Overtime off') }}
                </span>
            </span>
        </span>
        <flux:icon.chevron-down variant="mini" class="text-zinc-400 transition" ::class="open && 'rotate-180'" />
    </button>

    <form wire:submit="save" x-show="open" x-collapse x-cloak class="mt-6 space-y-6">
        <div>
            <p class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Salary basis') }}</p>
            <div class="mt-3 grid gap-3 md:grid-cols-2">
                @foreach ([
                    'attendance' => [__('Attendance-based'), __('Basic salary is prorated by payable days: days present plus approved paid leave, out of the working days in the period. Weekends and public holidays never reduce pay.')],
                    'fixed' => [__('Fixed salary'), __('Everyone is paid their full monthly basic regardless of attendance. Overtime can still be paid.')],
                ] as $value => [$title, $text])
                    <label class="flex cursor-pointer gap-3 rounded-xl border p-4 transition {{ $salary_basis === $value ? 'border-brand bg-brand/5 ring-2 ring-brand/20' : 'border-neutral-200 dark:border-neutral-700' }}">
                        <input type="radio" wire:model.live="salary_basis" value="{{ $value }}" class="mt-1 accent-brand" @disabled(! $canManage)>
                        <span>
                            <span class="block text-sm font-semibold text-zinc-900 dark:text-white">{{ $title }}</span>
                            <span class="mt-1 block text-xs text-zinc-500">{{ $text }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
            <p class="mt-2 text-xs text-zinc-500">{{ __('Employees whose pay frequency is “hourly” are always paid their hourly rate × hours worked.') }}</p>
        </div>

        <div>
            <flux:switch wire:model.live="overtime_enabled" :label="__('Pay overtime automatically from attendance')" :disabled="! $canManage" />
            <div class="mt-4 grid gap-4 sm:grid-cols-3 {{ $overtime_enabled ? '' : 'opacity-50' }}">
                <flux:input type="number" step="0.05" min="1" max="5" wire:model="overtime_multiplier" :label="__('Overtime rate (working days)')" :description="__('× hourly rate, e.g. 1.5')" :disabled="! $canManage || ! $overtime_enabled" />
                <flux:input type="number" step="0.05" min="1" max="5" wire:model="rest_day_multiplier" :label="__('Rest day & holiday rate')" :description="__('× hourly rate, e.g. 2')" :disabled="! $canManage || ! $overtime_enabled" />
                <flux:input type="number" step="0.5" min="1" max="24" wire:model="standard_daily_hours" :label="__('Standard daily hours')" :description="__('Hourly rate = basic ÷ (working days × this)')" :disabled="! $canManage" />
            </div>
            <p class="mt-3 text-xs text-zinc-500">{{ __('Overtime on working days comes from each attendance record (time beyond the shift, after the shift\'s overtime threshold). Every hour worked on a weekend or public holiday is paid at the rest-day rate.') }}</p>
        </div>

        @if ($canManage)
            <div class="flex items-center justify-end gap-3">
                @if ($saved)
                    <span class="text-sm font-medium text-success" x-data x-init="setTimeout(() => $wire.set('saved', false), 3000)">{{ __('Saved. Re-run payroll to apply.') }}</span>
                @endif
                <flux:button type="submit" variant="primary">{{ __('Save pay rules') }}</flux:button>
            </div>
        @endif
    </form>
</div>
