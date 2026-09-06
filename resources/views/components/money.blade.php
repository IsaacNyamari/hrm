@props(['cents' => 0, 'currency' => 'KES', 'decimals' => 2])

<span {{ $attributes }}>{{ $currency }} {{ number_format((int) $cents / 100, $decimals) }}</span>
