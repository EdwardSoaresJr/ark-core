@php
    $modeName = $name ?? 'mode';
    $modeValue = $value ?? \App\Ark\Operations\Parts\PartsCatalogLinks::MODE_LINK;
@endphp
<label class="block">
    <span class="{{ $labelClass ?? 'text-xs font-medium text-slate-500' }}">Type</span>
    <select
        name="{{ $modeName }}"
        class="mt-1 h-9 w-full border-slate-300 text-sm text-slate-800 {{ $selectClass ?? '' }}"
    >
        <option value="{{ \App\Ark\Operations\Parts\PartsCatalogLinks::MODE_LINK }}" @selected($modeValue === \App\Ark\Operations\Parts\PartsCatalogLinks::MODE_LINK)>Link (new tab)</option>
        <option value="{{ \App\Ark\Operations\Parts\PartsCatalogLinks::MODE_CATALOG }}" @selected($modeValue === \App\Ark\Operations\Parts\PartsCatalogLinks::MODE_CATALOG)>Catalog</option>
    </select>
</label>
