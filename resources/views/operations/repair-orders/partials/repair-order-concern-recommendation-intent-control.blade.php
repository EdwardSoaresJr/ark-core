@php
    use App\Ark\Operations\RepairOrders\RecommendationIntent;

    $intent = $concern->recommendationIntent();
    $authorViaModal = (bool) ($authorViaModal ?? false);
@endphp

@if ($isTerminal ?? false)
    <div class="flex items-center gap-0.5">
        <span class="ops-intent-label {{ $intent->intentLabelClass() }}">{{ $intent->staffLabel() }}</span>
        <x-operations.help-tip
            :text="$intent->helpText().' Customer PDF: '.$intent->customerLabel().'.'"
            label="Recommendation status help for {{ $concern->summary }}"
        />
    </div>
@elseif ($authorViaModal)
    <button
        type="button"
        class="ops-builder-present-chip ops-intent-label {{ $intent->intentLabelClass() }}"
        title="Recommendation status"
        @click="window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', { detail: { task: 'concern-intent', context: { concernId: {{ $concern->id }} }, invokeEl: $event.currentTarget } }))"
    >
        {{ $intent->staffLabel() }}
    </button>
@else
    <div class="flex items-center gap-0.5">
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
            <label class="sr-only" for="concern-intent-{{ $concern->id }}">Recommendation status for {{ $concern->summary }}</label>
            <select
                id="concern-intent-{{ $concern->id }}"
                name="recommendation_intent"
                class="ops-disposition-select ops-intent-select {{ $intent->intentLabelClass() }} max-w-[11.5rem] text-[11px]"
                onchange="preserveRepairOrderConcernScroll({{ $concern->id }}); this.form.requestSubmit()"
            >
                @foreach (RecommendationIntent::cases() as $option)
                    <option
                        value="{{ $option->value }}"
                        title="{{ $option->helpText() }} Customer PDF: {{ $option->customerLabel() }}."
                        @selected($intent === $option)
                    >{{ $option->staffLabel() }}</option>
                @endforeach
            </select>
        </form>
        <x-operations.help-tip
            :items="RecommendationIntent::advisorHelpOverviewItems()"
            title="Recommendation status"
            label="Recommendation status help"
            class="ops-help-tip--above"
        />
    </div>
@endif
