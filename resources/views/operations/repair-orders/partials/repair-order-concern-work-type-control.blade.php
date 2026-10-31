@php
    use App\Ark\Operations\RepairOrders\RecommendationIntent;
    use App\Ark\Operations\RepairOrders\RecommendationPresentation;

    $semantic = $concern->recommendationIntent();
    $authorViaModal = (bool) ($authorViaModal ?? false);
@endphp

<div class="d-flex align-items-center gap-2">
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
