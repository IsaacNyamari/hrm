<div class="flex flex-col gap-5">
    <h1 class="text-xl font-semibold text-neutral-900 dark:text-neutral-100">{{ __('Reports') }}</h1>

    @if (count($reports) === 0)
        <p class="rounded-xl border border-dashed border-neutral-300 p-8 text-center text-neutral-500 dark:border-neutral-700">
            {{ __('You do not have access to any reports.') }}
        </p>
    @else
        <div class="flex flex-wrap items-end gap-3 rounded-xl border border-neutral-200 p-4 dark:border-neutral-800">
            <label class="text-sm">
                <span class="text-neutral-600 dark:text-neutral-400">{{ __('Report') }}</span>
                <select wire:model.live="report" class="mt-1 w-72 rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900">
                    <option value="">{{ __('Select…') }}</option>
                    @foreach ($reports as $key => $r)
                        <option value="{{ $key }}">{{ $r->category() }} — {{ $r->name() }}</option>
                    @endforeach
                </select>
            </label>

            @if ($selected)
                @foreach ($selected->filters() as $filter)
                    <label class="text-sm">
                        <span class="text-neutral-600 dark:text-neutral-400">{{ $filter['label'] }}</span>
                        <input type="{{ $filter['type'] === 'date' ? 'date' : ($filter['type'] === 'number' ? 'number' : 'text') }}"
                            wire:model.live.debounce.500ms="filters.{{ $filter['key'] }}"
                            class="mt-1 w-40 rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900" />
                    </label>
                @endforeach

                <div class="flex gap-2">
                    <a href="{{ route('reports.export', $selected->key()) }}?format=xlsx&{{ http_build_query($filters) }}"
                       class="rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700">{{ __('Excel') }}</a>
                    <a href="{{ route('reports.export', $selected->key()) }}?format=csv&{{ http_build_query($filters) }}"
                       class="rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700">{{ __('CSV') }}</a>
                    <a href="{{ route('reports.print', $selected->key()) }}?{{ http_build_query($filters) }}" target="_blank"
                       class="rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700">{{ __('Print') }}</a>
                </div>
            @endif
        </div>

        @if ($error)
            <p class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-950 dark:text-red-300">{{ $error }}</p>
        @endif

        @if ($result)
            @if (! empty($result['summary']))
                <div class="grid gap-3 sm:grid-cols-3">
                    @foreach ($result['summary'] as $label => $value)
                        <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-800">
                            <p class="text-xs uppercase text-neutral-500">{{ $label }}</p>
                            <p class="mt-1 text-lg font-semibold text-neutral-900 dark:text-neutral-100">{{ $value }}</p>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-neutral-800">
                <table class="w-full text-sm">
                    <thead class="bg-neutral-50 text-left text-xs uppercase text-neutral-500 dark:bg-neutral-900">
                        <tr>
                            @foreach ($result['columns'] as $column)
                                <th class="px-3 py-2">{{ $column }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($result['rows'] as $row)
                            <tr class="border-t border-neutral-100 dark:border-neutral-800">
                                @foreach ($row as $cell)
                                    <td class="px-3 py-1.5">{{ $cell }}</td>
                                @endforeach
                            </tr>
                        @empty
                            <tr><td colspan="{{ count($result['columns']) }}" class="px-3 py-3 text-neutral-500">{{ __('No data for these filters.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p class="text-xs text-neutral-400">{{ __('Generated') }} {{ $result['generated_at'] }}</p>
        @endif
    @endif
</div>
