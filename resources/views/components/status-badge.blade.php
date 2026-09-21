@props(['status', 'size' => 'sm'])

@php
    $label = $status instanceof \BackedEnum && method_exists($status, 'label')
        ? $status->label()
        : \Illuminate\Support\Str::headline((string) ($status instanceof \BackedEnum ? $status->value : $status));
@endphp

<flux:badge :size="$size" :color="\App\Support\StatusTone::for($status)" {{ $attributes }}>{{ $label }}</flux:badge>
