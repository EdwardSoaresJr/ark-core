@php
    $modeName = $name ?? 'mode';
    $modeValue = $value ?? \App\Ark\Operations\Parts\PartsCatalogLinks::MODE_LINK;
    $modeId = $id ?? 'settings-catalog-'.trim(preg_replace('/[^a-z0-9]+/i', '-', $modeName), '-');
@endphp
<label for="{{ $modeId }}" class="form-label">Type</label>
<select id="{{ $modeId }}" name="{{ $modeName }}" class="form-select">
    <option value="{{ \App\Ark\Operations\Parts\PartsCatalogLinks::MODE_LINK }}" @selected($modeValue === \App\Ark\Operations\Parts\PartsCatalogLinks::MODE_LINK)>Link (new tab)</option>
    <option value="{{ \App\Ark\Operations\Parts\PartsCatalogLinks::MODE_CATALOG }}" @selected($modeValue === \App\Ark\Operations\Parts\PartsCatalogLinks::MODE_CATALOG)>Catalog</option>
</select>
