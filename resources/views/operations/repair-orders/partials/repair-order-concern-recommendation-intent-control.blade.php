@php
    use App\Ark\Operations\RepairOrders\RecommendationPresentation;
    use App\Ark\Operations\RepairOrders\RecommendationPriority;

    $presentation = $concern->presentation();
    $semantic = $concern->recommendationIntent();
    $posture = $concern->isInformationOnly()
        ? RecommendationPresentation::INFORMATION_ONLY
        : $concern->recommendationPriority()?->value;
    $priorityOptions = collect(RecommendationPriority::cases())
        ->map(fn (RecommendationPriority $priority): RecommendationPresentation => RecommendationPresentation::priority($priority))
        ->push(RecommendationPresentation::informationOnly());
    $currentPriority = $priorityOptions->first(fn (RecommendationPresentation $option): bool => $option->key() === $posture);
@endphp

@if ($isTerminal ?? false)
    <div class="d-flex align-items-center gap-1">
        @if ($presentation->staffLabel())
            <span class="badge bg-{{ $presentation->tablerColor() }}-lt">{{ $presentation->staffLabel() }}</span>
        @else
            <span class="badge bg-secondary-lt">No priority</span>
        @endif
        @if ($semantic)
            <span class="subheader">{{ $semantic->staffLabel() }}</span>
        @endif
    </div>
@else
    <form
        method="POST"
        action="{{ route('operations.repair-orders.concerns.recommendation-intent', [$repairOrder, $concern]) }}"
        data-refresh-scope="worksheet"
        data-continuity-focus="#concern-{{ $concern->id }}"
        @submit.prevent="submitWorksheetForm($event)"
    >
        @csrf
        @method('PATCH')
        <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
        <input type="hidden" name="recommendation_posture" value="{{ $posture }}">
        <div class="dropdown" data-concern-priority-choices>
            <button
                type="button"
                class="btn btn-sm dropdown-toggle"
                data-bs-toggle="dropdown"
                aria-expanded="false"
                aria-label="Priority for {{ $concern->summary }}: {{ $currentPriority?->staffLabel() ?? 'not set' }}"
                data-priority-current="{{ $posture ?? '' }}"
            >
                @if ($currentPriority)
                    <span class="status-dot status-{{ $currentPriority->tablerColor() }} me-2"></span>
                    <span class="fw-medium">{{ $currentPriority->staffLabel() }}</span>
                @else
                    <span class="text-secondary">Set priority</span>
                @endif
            </button>
            <div class="dropdown-menu">
                <h6 class="dropdown-header">Priority</h6>
                @foreach ($priorityOptions as $option)
                    @php $isCurrent = $posture === $option->key(); @endphp
                    <button
                        type="button"
                        class="dropdown-item"
                        title="{{ $option->helpText() }}"
                        data-priority-option="{{ $option->key() }}"
                        @if ($isCurrent)
                            aria-current="true"
                        @else
                            @click="preserveRepairOrderConcernScroll({{ $concern->id }}); $el.form.elements.recommendation_posture.value = @js($option->key()); $el.form.requestSubmit()"
                        @endif
                    >
                        <span class="status-dot status-{{ $option->tablerColor() }} me-2"></span>
                        <span class="me-3">{{ $option->staffLabel() }}</span>
                        @if ($isCurrent)
                            <x-tabler.icon name="check" class="ms-auto text-primary" />
                        @endif
                    </button>
                @endforeach
            </div>
        </div>
    </form>
@endif
