<tr>
<td>
<table class="footer" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="content-cell" align="center">
{{ Illuminate\Mail\Markdown::parse($slot) }}
@if (isset($tenantBrand) && filled($tenantBrand->email_footer))
<br>
{{ Illuminate\Mail\Markdown::parse($tenantBrand->email_footer) }}
@endif
</td>
</tr>
</table>
</td>
</tr>
