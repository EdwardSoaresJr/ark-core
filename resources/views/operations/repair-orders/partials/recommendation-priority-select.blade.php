@php
    use App\Ark\Operations\RepairOrders\RecommendationPresentation;
    use App\Ark\Operations\RepairOrders\RecommendationPriority;

    $fieldName = $fieldName ?? 'recommendation_posture';
    $selectedPriority = $selectedPriority ?? null;
    $informationOnly = (bool) ($informationOnly ?? false);
    $selected = $informationOnly
        ? RecommendationPresentation::INFORMATION_ONLY
        : ($selectedPriority instanceof RecommendationPriority ? $selectedPriority->value : (is_string($selectedPriority) ? $selectedPriority : ''));
    $inputId = $inputId ?? $fieldName;
    $selectClass = $selectClass ?? 'rounded-sm border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-950';
    $allowEmpty = $allowEmpty ?? true;
@endphp

<select id="{{ $inputId }}" name="{{ $fieldName }}" class="{{ $selectClass }}">
    @if ($allowEmpty)
        <option value="" @selected($selected === '' || $selected === null)>No priority</option>
    @endif
    @foreach (RecommendationPriority::cases() as $priority)
        <option value="{{ $priority->value }}" @selected($selected === $priority->value)>{{ $priority->staffLabel() }}</option>
    @endforeach
    <option value="information_only" @selected($selected === 'information_only')>Information Only</option>
</select>
