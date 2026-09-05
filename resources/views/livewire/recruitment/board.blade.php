<div class="flex flex-col gap-4" wire:key="recruitment-board">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-semibold text-neutral-900 dark:text-neutral-100">{{ __('Recruitment') }}</h1>
        <select wire:model.live="vacancyUuid" class="rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900">
            @foreach ($vacancies as $v)
                <option value="{{ $v->uuid }}">{{ $v->title }} — {{ ucfirst($v->status->value) }}</option>
            @endforeach
        </select>
    </div>

    @if ($notice)
        <p class="rounded-lg bg-green-50 px-3 py-2 text-sm text-green-700 dark:bg-green-950 dark:text-green-300">{{ $notice }}</p>
    @endif

    @if ($vacancy === null)
        <p class="rounded-xl border border-dashed border-neutral-300 p-8 text-center text-neutral-500 dark:border-neutral-700">
            {{ __('No vacancies yet. Create one from the API or seed the demo data.') }}
        </p>
    @else
        <div class="grid gap-4 overflow-x-auto md:grid-cols-2 xl:grid-cols-5">
            @foreach ($columns as $stage => $apps)
                <div class="min-w-[220px] rounded-xl border border-neutral-200 p-3 dark:border-neutral-800">
                    <h2 class="mb-2 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                        {{ \Illuminate\Support\Str::headline($stage) }} <span class="text-neutral-400">({{ $apps->count() }})</span>
                    </h2>
                    <ul class="space-y-2">
                        @forelse ($apps as $app)
                            <li class="rounded-lg border border-neutral-200 p-2 text-sm dark:border-neutral-700">
                                <p class="font-medium text-neutral-800 dark:text-neutral-200">{{ $app->applicant->fullName() }}</p>
                                <p class="text-xs text-neutral-500">{{ $app->status->value }}</p>
                                <div class="mt-1 flex flex-wrap gap-1">
                                    @foreach ($vacancy->stages() as $target)
                                        @if ($target !== $stage)
                                            <button type="button"
                                                wire:click="moveStage('{{ $app->uuid }}', '{{ $target }}')"
                                                class="rounded bg-neutral-100 px-1.5 py-0.5 text-[11px] text-neutral-600 hover:bg-neutral-200 dark:bg-neutral-800 dark:text-neutral-300">
                                                → {{ \Illuminate\Support\Str::headline($target) }}
                                            </button>
                                        @endif
                                    @endforeach
                                </div>
                            </li>
                        @empty
                            <li class="text-xs text-neutral-400">{{ __('Empty') }}</li>
                        @endforelse
                    </ul>
                </div>
            @endforeach
        </div>
    @endif
</div>
