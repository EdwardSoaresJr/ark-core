@php
    $colorName = $name ?? 'color';
    $colorValue = $value ?? '';
    $allowAutomatic = $allowAutomatic ?? false;
@endphp
<label class="block">
    <span class="{{ $labelClass ?? 'text-xs font-medium text-slate-500' }}">Button color</span>
    <select
        name="{{ $colorName }}"
        class="mt-1 h-9 w-full border-slate-300 text-sm text-slate-800 {{ $selectClass ?? '' }}"
    >
        @if ($allowAutomatic)
            <option value="" @selected($colorValue === '' || $colorValue === null)>Automatic</option>
        @endif
        @foreach (\App\Ark\Operations\Parts\PartsCatalogButtonColor::labels() as $colorKey => $colorLabel)
            <option value="{{ $colorKey }}" @selected($colorValue === $colorKey)>{{ $colorLabel }}</option>
        @endforeach
    </select>
</label>
