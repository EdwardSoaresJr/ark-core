@php
    use App\Ark\Operations\RepairOrders\RecommendationIntent;

    $fieldName = $fieldName ?? 'recommendation_intent';
    $selectedValue = $selected ?? null;
    $selected = $selectedValue instanceof RecommendationIntent
        ? $selectedValue
        : RecommendationIntent::trySemantic(is_string($selectedValue) ? $selectedValue : null);
    $inputId = $inputId ?? $fieldName;
    $selectClass = $selectClass ?? 'rounded-sm border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-950';
    $allowEmpty = $allowEmpty ?? true;
@endphp

<select id="{{ $inputId }}" name="{{ $fieldName }}" class="{{ $selectClass }}" @unless ($allowEmpty) required @endunless>
    @if ($allowEmpty)
        <option value="" @selected($selected === null)>No work type</option>
    @endif
    @foreach (RecommendationIntent::cases() as $intent)
        <option value="{{ $intent->value }}" @selected($selected === $intent)>{{ $intent->staffLabel() }}</option>
    @endforeach
</select>
