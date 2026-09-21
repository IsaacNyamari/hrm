<x-layouts::app :title="__('Payroll')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <livewire:payroll.runs />
        <livewire:payroll.pay-rules-settings />
    </div>
</x-layouts::app>
