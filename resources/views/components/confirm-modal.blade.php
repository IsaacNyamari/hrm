@props(['name', 'heading', 'text' => null, 'confirmLabel' => null, 'confirmWire', 'variant' => 'danger'])

<flux:modal :name="$name" class="min-w-[22rem]">
    <div class="space-y-4">
        <flux:heading size="lg">{{ $heading }}</flux:heading>
        @if ($text)
            <flux:text>{{ $text }}</flux:text>
        @endif
        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button :variant="$variant" wire:click="{{ $confirmWire }}">{{ $confirmLabel ?? __('Confirm') }}</flux:button>
        </div>
    </div>
</flux:modal>
