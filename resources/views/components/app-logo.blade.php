@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand :name="config('app.name', 'Laravel')" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-lg bg-linear-to-br from-brand to-highlight shadow-sm">
            <x-app-logo-icon class="size-6 text-white" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand :name="config('app.name', 'Laravel')" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-lg bg-linear-to-br from-brand to-highlight shadow-sm">
            <x-app-logo-icon class="size-6 text-white" />
        </x-slot>
    </flux:brand>
@endif
