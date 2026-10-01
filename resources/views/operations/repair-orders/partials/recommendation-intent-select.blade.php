@php
    use App\Ark\Operations\RepairOrders\RecommendationIntent;

    $fieldName = $fieldName ?? 'recommendation_intent';
    $requireSelection = $requireSelection ?? false;
    $selected = $requireSelection && ! filled($selected ?? null)
        ? null
        : RecommendationIntent::fromStored($selected ?? null);
    $useCustomerLabels = $useCustomerLabels ?? false;
    $inputId = $inputId ?? $fieldName;
    $selectClass = $selectClass ?? 'rounded-sm border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-950';
    $requiredMessage = $requiredMessage ?? 'Pick a recommendation intent for this scope.';
@endphp

<select
    id="{{ $inputId }}"
    name="{{ $fieldName }}"
    required
    class="{{ $selectClass }}"
    @if ($requireSelection)
        oninvalid="this.setCustomValidity('{{ e($requiredMessage) }}')"
        oninput="this.setCustomValidity('')"
        onchange="this.setCustomValidity('')"
    @endif
>
    @if ($requireSelection)
        <option value="" disabled @selected($selected === null)>Pick…</option>
    @endif
    @foreach (RecommendationIntent::cases() as $intent)
        <option value="{{ $intent->value }}" @selected($selected === $intent)>
            {{ $useCustomerLabels ? $intent->customerLabel() : $intent->staffLabel() }}
        </option>
    @endforeach
</select>
