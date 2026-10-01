@if ($line->type->isLabor() && filled($line->labor_category_name))
    @php
        $laborCategoryTone = match ($line->labor_category_key) {
            'courtesy' => 'ops-labor-category--courtesy',
            'comeback' => 'ops-labor-category--comeback',
            'internal' => 'ops-labor-category--internal',
            default => 'ops-labor-category--default',
        };
    @endphp
    <span class="ops-labor-category {{ $laborCategoryTone }}">{{ $line->labor_category_name }}</span>
@endif
