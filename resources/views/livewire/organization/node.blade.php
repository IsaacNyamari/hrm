@php($children = $node[$childKey] ?? [])
<li>
    <div class="flex items-center gap-2 rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm dark:border-neutral-800 dark:bg-neutral-900"
         style="margin-left: {{ $depth * 1.5 }}rem">
        <span class="font-medium text-neutral-800 dark:text-neutral-100">{{ $node['name'] }}</span>
        @if (! empty($node['job_title']))
            <span class="text-neutral-500">· {{ $node['job_title'] }}</span>
        @endif
        @if (! empty($node['head']))
            <span class="text-neutral-500">· {{ __('Head') }}: {{ $node['head'] }}</span>
        @endif
        @if (! empty($node['department']))
            <span class="ml-auto rounded-full bg-neutral-100 px-2 py-0.5 text-xs text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300">{{ $node['department'] }}</span>
        @endif
    </div>

    @if (count($children) > 0)
        <ul class="mt-2 space-y-2">
            @foreach ($children as $child)
                @include('livewire.organization.node', ['node' => $child, 'childKey' => $childKey, 'depth' => $depth + 1])
            @endforeach
        </ul>
    @endif
</li>
