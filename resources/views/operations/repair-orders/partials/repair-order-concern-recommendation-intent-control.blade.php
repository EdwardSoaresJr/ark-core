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
    <div class="flex items-center gap-1">
        @if ($presentation->staffLabel())
            <span class="ops-intent-label {{ $presentation->labelClass() }}">{{ $presentation->staffLabel() }}</span>
        @else
            <span class="ops-intent-label ops-priority-label--unset">No priority</span>
        @endif
        @if ($semantic)
            <span class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">{{ $semantic->staffLabel() }}</span>
        @endif
    </div>
@else
    <div class="flex flex-wrap items-center gap-1">
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
            <fieldset class="ops-priority-choices">
                <legend class="sr-only">Priority for {{ $concern->summary }}</legend>
                @foreach (RecommendationPriority::cases() as $priority)
                    @php $option = RecommendationPresentation::priority($priority); @endphp
                    <label class="ops-priority-choice {{ $posture === $priority->value ? $option->labelClass() : '' }}" title="{{ $priority->helpText() }}">
                        <input
                            type="radio"
                            name="recommendation_posture"
                            value="{{ $priority->value }}"
                            @checked($posture === $priority->value)
                            onchange="preserveRepairOrderConcernScroll({{ $concern->id }}); this.form.requestSubmit()"
                        >
                        <span>{{ $priority->staffLabel() }}</span>
                    </label>
                @endforeach
                @php $info = RecommendationPresentation::informationOnly(); @endphp
                <label class="ops-priority-choice {{ $posture === $info->key() ? $info->labelClass() : '' }}" title="{{ $info->helpText() }}">
                    <input
                        type="radio"
                        name="recommendation_posture"
                        value="information_only"
                        @checked($posture === 'information_only')
                        onchange="preserveRepairOrderConcernScroll({{ $concern->id }}); this.form.requestSubmit()"
                    >
                    <span>Information Only</span>
                </label>
            </fieldset>
        </form>
        @if ($semantic)
            <span class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">{{ $semantic->staffLabel() }}</span>
        @endif
        @if ($authorViaModal)
            <button
                type="button"
                class="ops-priority-work-type"
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
                <label class="sr-only" for="concern-work-type-{{ $concern->id }}">Work type for {{ $concern->summary }}</label>
                <select
                    id="concern-work-type-{{ $concern->id }}"
                    name="recommendation_intent"
                    class="ops-disposition-select max-w-[9rem] text-[11px]"
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
