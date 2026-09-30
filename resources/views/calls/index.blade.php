<x-layouts::app :title="__('Meetings')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <livewire:calls.room />
    </div>

    @push('scripts')
        @vite('resources/js/calls.js')
    @endpush
</x-layouts::app>
