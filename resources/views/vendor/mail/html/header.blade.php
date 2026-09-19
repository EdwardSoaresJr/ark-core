@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if (trim($slot) === 'Laravel')
<img src="{{ \App\Support\Branding\Branding::emailLogo() }}" class="logo" alt="ARK-SMS">
@else
{!! $slot !!}
@endif
</a>
</td>
</tr>
