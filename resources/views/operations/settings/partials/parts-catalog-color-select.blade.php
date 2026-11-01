@php
    $colorName = $name ?? 'color';
    $colorValue = $value ?? '';
    $allowAutomatic = $allowAutomatic ?? false;
    $colorId = $id ?? 'settings-catalog-'.trim(preg_replace('/[^a-z0-9]+/i', '-', $colorName), '-');
@endphp
<label for="{{ $colorId }}" class="form-label">Button color</label>
<select id="{{ $colorId }}" name="{{ $colorName }}" class="form-select">
    @if ($allowAutomatic)
        <option value="" @selected($colorValue === '' || $colorValue === null)>Automatic</option>
    @endif
    @foreach (\App\Ark\Operations\Parts\PartsCatalogButtonColor::labels() as $colorKey => $colorLabel)
        <option value="{{ $colorKey }}" @selected($colorValue === $colorKey)>{{ $colorLabel }}</option>
    @endforeach
</select>
