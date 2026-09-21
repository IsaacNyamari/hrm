<x-layouts::app :title="__('Attendance')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <livewire:attendance.clock />
        @can('viewAny', \App\Models\Attendance::class)
            <livewire:attendance.live-today />
            <livewire:attendance.records />
        @endcan
        @can('create', \App\Models\Attendance::class)
            <livewire:attendance.zkteco-devices />
        @endcan
    </div>
</x-layouts::app>
