@props(['url'])
@php($brandLogo = isset($tenantBrand) ? $tenantBrand->logoUrl() : null)
@php($brandName = isset($tenantBrand) ? $tenantBrand->displayName() : ($slot ?? config('app.name')))
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if ($brandLogo)
<img src="{{ $brandLogo }}" class="logo" alt="{{ $brandName }}" style="max-height: 48px;">
@elseif (trim($slot) === 'Laravel')
{{ config('app.name') }}
@else
{{ $brandName }}
@endif
</a>
</td>
</tr>
