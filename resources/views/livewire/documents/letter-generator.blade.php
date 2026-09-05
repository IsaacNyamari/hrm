<div class="flex flex-col gap-4">
    <h1 class="text-xl font-semibold text-neutral-900 dark:text-neutral-100">{{ __('Generate HR letter') }}</h1>

    @if ($notice)
        <p class="rounded-lg bg-green-50 px-3 py-2 text-sm text-green-700 dark:bg-green-950 dark:text-green-300">{{ $notice }}</p>
    @endif

    <div class="grid gap-3 sm:grid-cols-2">
        <label class="text-sm">
            <span class="text-neutral-600 dark:text-neutral-400">{{ __('Template') }}</span>
            <select wire:model.live="templateUuid" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900">
                <option value="">{{ __('Select…') }}</option>
                @foreach ($templates as $t)
                    <option value="{{ $t->uuid }}">{{ $t->name }}</option>
                @endforeach
            </select>
        </label>

        <label class="text-sm">
            <span class="text-neutral-600 dark:text-neutral-400">{{ __('Employee') }}</span>
            <select wire:model.live="employeeUuid" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900">
                <option value="">{{ __('Select…') }}</option>
                @foreach ($employees as $e)
                    <option value="{{ $e->uuid }}">{{ $e->fullName() }}</option>
                @endforeach
            </select>
        </label>
    </div>

    @if ($previewBody !== '')
        <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-800">
            <p class="text-sm font-semibold">{{ $previewSubject }}</p>
            <p class="mt-2 whitespace-pre-wrap text-sm text-neutral-700 dark:text-neutral-300">{{ $previewBody }}</p>
        </div>

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" wire:model="saveToDocuments"> {{ __('File a copy to the employee\'s documents') }}
        </label>

        <div>
            <button wire:click="generate" class="rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white dark:bg-white dark:text-neutral-900">
                {{ __('Generate letter') }}
            </button>
        </div>
    @endif
</div>
