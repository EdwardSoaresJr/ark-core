@php
    use App\Ark\Operations\RepairOrders\RecommendationIntent;
    use App\Ark\Operations\RepairOrders\RecommendationPresentation;
    use App\Ark\Operations\RepairOrders\RecommendationPriority;

    $presentation = $concern->presentation();
    $semantic = $concern->recommendationIntent();
    $authorViaModal = (bool) ($authorViaModal ?? false);
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
    <div class="d-flex flex-wrap align-items-center gap-2">
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
                        <span class="text-{{ $currentPriority->tablerColor() }} fw-medium">{{ $currentPriority->staffLabel() }}</span>
                    @else
                        <span class="text-secondary">Set priority</span>
                    @endif
                </button>
                <div class="dropdown-menu">
                    @foreach ($priorityOptions as $option)
                        <button
                            type="button"
                            @class(['dropdown-item', 'active' => $posture === $option->key()])
                            title="{{ $option->helpText() }}"
                            data-priority-option="{{ $option->key() }}"
                            @click="preserveRepairOrderConcernScroll({{ $concern->id }}); $el.form.elements.recommendation_posture.value = @js($option->key()); $el.form.requestSubmit()"
                        >
                            <span class="status-dot status-{{ $option->tablerColor() }} me-2"></span>
                            <span class="text-{{ $option->tablerColor() }}">{{ $option->staffLabel() }}</span>
                        </button>
                    @endforeach
                </div>
            </div>
        </form>
        @if ($semantic)
            <span class="subheader">{{ $semantic->staffLabel() }}</span>
        @endif
        @if ($authorViaModal)
            <button
                type="button"
                class="btn btn-sm btn-ghost-secondary"
                title="Work type"
                @click="window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', { detail: { task: 'concern-intent', context: { concernId: {{ $concern->id }} }, invokeEl: $event.currentTarget } }))"
            >
                Work type
            </button>
        @else
            <form
                method="POST"
                action="{{ route('operations.repair-orders.concerns.recommendation-intent', [$repairOrder, $concern]) }}"
                data-refresh-scope="worksheet"
                data-continuity-focus="#concern-{{ $concern->id }} select[name='recommendation_intent']"
                @submit.prevent="submitWorksheetForm($event)"
            >
                @csrf
                @method('PATCH')
                <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
                <label class="visually-hidden" for="concern-work-type-{{ $concern->id }}">Work type for {{ $concern->summary }}</label>
                <select
                    id="concern-work-type-{{ $concern->id }}"
                    name="recommendation_intent"
                    class="form-select form-select-sm"
                    style="width: auto; max-width: 9rem"
                    onchange="preserveRepairOrderConcernScroll({{ $concern->id }}); this.form.requestSubmit()"
                >
                    <option value="" @selected($semantic === null)>Work type</option>
                    @foreach (RecommendationIntent::cases() as $option)
                        <option value="{{ $option->value }}" @selected($semantic === $option)>{{ $option->staffLabel() }}</option>
                    @endforeach
                </select>
            </form>
        @endif
        <x-operations.help-tip
            :items="RecommendationPresentation::advisorHelpOverviewItems()"
            title="Priority"
            label="Priority help"
            class="ops-help-tip--above"
        />
    </div>
@endif
