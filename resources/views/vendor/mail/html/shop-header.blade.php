@props(['url', 'shopName', 'logoUrl' => null])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block; text-decoration: none;">
@if (filled($logoUrl))
<img src="{{ $logoUrl }}" class="logo" alt="{{ $shopName }}" style="max-height: 75px; max-width: 240px; width: auto; height: auto;">
@else
<span style="font-size: 19px; font-weight: bold; color: #3d4852;">{{ $shopName }}</span>
@endif
</a>
</td>
</tr>
