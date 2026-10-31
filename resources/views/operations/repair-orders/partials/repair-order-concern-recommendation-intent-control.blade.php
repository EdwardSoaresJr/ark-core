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
            <fieldset data-concern-priority-choices>
                <legend class="visually-hidden">Priority for {{ $concern->summary }}</legend>
                <div class="d-flex flex-wrap gap-1">
                    @foreach (RecommendationPriority::cases() as $priority)
                        @php $option = RecommendationPresentation::priority($priority); @endphp
                        <input
                            type="radio"
                            class="btn-check"
                            id="scope-priority-{{ $concern->id }}-{{ $priority->value }}"
                            name="recommendation_posture"
                            value="{{ $priority->value }}"
                            @checked($posture === $priority->value)
                            onchange="preserveRepairOrderConcernScroll({{ $concern->id }}); this.form.requestSubmit()"
                        >
                        <label class="btn btn-sm btn-outline-{{ $option->tablerColor() }}" for="scope-priority-{{ $concern->id }}-{{ $priority->value }}" title="{{ $priority->helpText() }}">{{ $priority->staffLabel() }}</label>
                    @endforeach
                    @php $info = RecommendationPresentation::informationOnly(); @endphp
                    <input
                        type="radio"
                        class="btn-check"
                        id="scope-priority-{{ $concern->id }}-information_only"
                        name="recommendation_posture"
                        value="information_only"
                        @checked($posture === 'information_only')
                        onchange="preserveRepairOrderConcernScroll({{ $concern->id }}); this.form.requestSubmit()"
                    >
                    <label class="btn btn-sm btn-outline-{{ $info->tablerColor() }}" for="scope-priority-{{ $concern->id }}-information_only" title="{{ $info->helpText() }}">Information Only</label>
                </div>
            </fieldset>
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
