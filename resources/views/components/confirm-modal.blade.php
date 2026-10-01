@props(['name', 'heading', 'text' => null, 'confirmLabel' => null, 'confirmWire' => null, 'form' => null, 'variant' => 'danger'])

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
            <flux:modal.close>
                @if ($form)
                    <flux:button type="submit" form="{{ $form }}" :variant="$variant">{{ $confirmLabel ?? __('Confirm') }}</flux:button>
                @else
                    <flux:button :variant="$variant" wire:click="{{ $confirmWire }}">{{ $confirmLabel ?? __('Confirm') }}</flux:button>
                @endif
            </flux:modal.close>
        </div>
    </div>
</flux:modal>
