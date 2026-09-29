@php($children = $node[$childKey] ?? [])
@php($isDepartment = $childKey === 'children')
<li x-data="{ open: {{ $depth < 2 ? 'true' : 'false' }} }" wire:key="org-{{ $node['uuid'] }}">
    <div class="w-52 rounded-xl border bg-white p-3 text-center shadow-sm transition dark:bg-neutral-900"
        data-name="{{ \Illuminate\Support\Str::lower($node['name']) }}"
        :class="q !== '' && $el.dataset.name.includes(q.toLowerCase()) ? 'border-highlight ring-2 ring-highlight/40' : 'border-neutral-200 dark:border-neutral-700'">
        <span @class([
            'mx-auto grid size-11 place-items-center rounded-full text-sm font-bold text-white',
            'bg-linear-to-br from-brand to-highlight' => $depth === 0,
            'bg-brand' => $depth === 1,
            'bg-brand/70' => $depth > 1,
        ])>{{ $node['initials'] }}</span>

        <p class="mt-2 truncate text-sm font-semibold text-zinc-900 dark:text-white" title="{{ $node['name'] }}">{{ $node['name'] }}</p>

        @if ($isDepartment)
            <p class="truncate text-xs text-zinc-500">{{ $node['head'] ? __('Head: :name', ['name' => $node['head']]) : __('No head assigned') }}</p>
            <p class="mt-2 inline-flex rounded-full bg-neutral-100 px-2 py-0.5 text-[11px] text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300">
                {{ trans_choice(':count person|:count people', $node['headcount']) }}
            </p>
        @else
            <p class="truncate text-xs text-zinc-500">{{ $node['job_title'] ?: '—' }}</p>
            @if (! empty($node['department']))
                <p class="mt-2 inline-flex max-w-full truncate rounded-full bg-highlight/10 px-2 py-0.5 text-[11px] text-teal-700 dark:text-highlight">{{ $node['department'] }}</p>
            @endif
        @endif

        @if (count($children) > 0)
            <button type="button" @click="open = !open"
                class="mt-2 flex w-full items-center justify-center gap-1 rounded-lg border border-neutral-200 py-1 text-[11px] font-medium text-zinc-600 hover:border-brand hover:text-brand dark:border-neutral-700 dark:text-zinc-300">
                <span x-text="open || q !== '' ? '{{ __('Hide') }}' : '{{ __('Show') }}'"></span>
                {{ $isDepartment
                    ? trans_choice(':count sub-department|:count sub-departments', count($children))
                    : trans_choice(':count direct report|:count direct reports', count($children)) }}
                @if (! $isDepartment && $node['team_size'] > count($children))
                    <span class="text-zinc-400">· {{ __(':count in team', ['count' => $node['team_size']]) }}</span>
                @endif
            </button>
        @endif

        @if (! $isDepartment && ($canManage ?? false))
            <button type="button" wire:click="startReassign('{{ $node['uuid'] }}')"
                class="mt-1 flex w-full items-center justify-center gap-1 rounded-lg py-1 text-[11px] font-medium text-zinc-400 hover:text-brand">
                {{ __('Change manager') }}
            </button>
        @endif
    </div>

    @if (count($children) > 0)
        <ul x-show="open || q !== ''" x-cloak>
            @foreach ($children as $child)
                @include('livewire.organization.node', ['node' => $child, 'childKey' => $childKey, 'depth' => $depth + 1, 'canManage' => $canManage ?? false])
            @endforeach
        </ul>
    @endif
</li>
