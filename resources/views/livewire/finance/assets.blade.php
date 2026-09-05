<div class="flex flex-col gap-6">
    <h1 class="text-xl font-semibold text-neutral-900 dark:text-neutral-100">{{ __('Assets') }}</h1>

    <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-800">
        <h2 class="text-sm font-semibold">{{ __('Assigned to me') }}</h2>
        <ul class="mt-2 space-y-1 text-sm">
            @forelse ($mine as $assignment)
                <li class="flex justify-between">
                    <span class="text-neutral-700 dark:text-neutral-300">{{ $assignment->asset->asset_tag }} — {{ $assignment->asset->name }}</span>
                    <span class="text-xs text-neutral-500">{{ __('since') }} {{ $assignment->assigned_on->toFormattedDateString() }}</span>
                </li>
            @empty
                <li class="text-neutral-500">{{ __('Nothing assigned to you.') }}</li>
            @endforelse
        </ul>
    </div>

    @if ($canManage)
        <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-800">
            <h2 class="text-sm font-semibold">{{ __('Asset register') }}</h2>
            <table class="mt-2 w-full text-sm">
                <thead class="text-left text-xs uppercase text-neutral-500">
                    <tr><th class="py-1">{{ __('Tag') }}</th><th>{{ __('Name') }}</th><th>{{ __('Category') }}</th><th>{{ __('Status') }}</th><th>{{ __('Holder') }}</th></tr>
                </thead>
                <tbody>
                    @forelse ($register as $asset)
                        <tr class="border-t border-neutral-100 dark:border-neutral-800">
                            <td class="py-1.5 font-mono text-xs">{{ $asset->asset_tag }}</td>
                            <td>{{ $asset->name }}</td>
                            <td class="text-neutral-500">{{ $asset->category->label() }}</td>
                            <td>{{ $asset->status->label() }}</td>
                            <td class="text-neutral-500">{{ optional($asset->activeAssignments->first())->employee?->fullName() ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-2 text-neutral-500">{{ __('No assets recorded.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            <p class="mt-2 text-xs text-neutral-400">{{ __('Add assets and manage assignments through the assets API.') }}</p>
        </div>
    @endif
</div>
