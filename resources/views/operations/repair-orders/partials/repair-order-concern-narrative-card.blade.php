@php
    use App\Ark\Dragon\ServiceAdvisor\DragonServiceAdvisorApplication;
    use App\Ark\Dragon\ServiceAdvisor\ServiceAdvisorField;

    $narrativeRows = [
        ServiceAdvisorField::CustomerStates->value => [
            'label' => ServiceAdvisorField::CustomerStates->label(),
            'value' => $concern->customer_states,
        ],
        ServiceAdvisorField::VerifiedFindings->value => [
            'label' => ServiceAdvisorField::VerifiedFindings->label(),
            'value' => $concern->verified_findings,
        ],
        ServiceAdvisorField::DtcsSummary->value => [
            'label' => ServiceAdvisorField::DtcsSummary->label(),
            'value' => $concern->dtcs_summary,
        ],
        ServiceAdvisorField::Recommendation->value => [
            'label' => ServiceAdvisorField::Recommendation->label(),
            'value' => $concern->recommendation,
        ],
    ];
    $hasNarrativeBody = collect($narrativeRows)->filter(fn ($row) => filled($row['value']))->isNotEmpty();

    $activeDragonApp = DragonServiceAdvisorApplication::query()
        ->where('concern_id', $concern->id)
        ->whereNotNull('applied_at')
        ->whereNull('reverted_at')
        ->latest('applied_at')
        ->first();
@endphp

<div id="concern-narrative-{{ $concern->id }}" class="ops-builder-present-card">
    <div class="ops-builder-present-card__header">
        <h3 class="ops-builder-present-card__title">Narrative</h3>
        @unless ($isTerminal)
            <div class="flex items-center gap-2">
                @if ($hasNarrativeBody || filled($concern->summary))
                    <button
                        type="button"
                        class="ops-builder-present-action"
                        @click="window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', { detail: { task: 'review-estimate-notes', context: { concernId: {{ $concern->id }} }, invokeEl: $event.currentTarget } }))"
                    >
                        Review this concern
                    </button>
                    <button
                        type="button"
                        class="ops-builder-present-action"
                        @click="window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', { detail: { task: 'dragon-service-advisor', context: { concernId: {{ $concern->id }} }, invokeEl: $event.currentTarget } }))"
                    >
                        Rewrite notes
                    </button>
                @endif
                <button
                    type="button"
                    class="ops-builder-present-action"
                    @click="window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', { detail: { task: 'concern-narrative', context: { concernId: {{ $concern->id }} }, invokeEl: $event.currentTarget } }))"
                >
                    {{ $hasNarrativeBody || filled($concern->summary) ? 'Edit' : 'Add' }}
                </button>
            </div>
        @endunless
    </div>

    <div class="ops-builder-present-card__body">
        <div class="flex items-start justify-between gap-2">
            <p class="ops-builder-present-card__lead">{!! \App\Ark\Operations\RepairOrders\RepairOrderMention::html((string) $concern->summary, $priorVisitMentions['href_by_number'] ?? []) !!}</p>
            @unless ($isTerminal)
                @if (filled($concern->summary))
                    <button
                        type="button"
                        class="shrink-0 text-[10px] font-semibold uppercase tracking-wide text-slate-500 hover:text-slate-800"
                        @click="window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', { detail: { task: 'dragon-service-advisor', context: { concernId: {{ $concern->id }}, field: 'summary' }, invokeEl: $event.currentTarget } }))"
                    >
                        Rewrite
                    </button>
                @endif
            @endunless
        </div>

        @if ($hasNarrativeBody)
            <div class="ops-builder-present-card__grid">
                @foreach ($narrativeRows as $fieldKey => $row)
                    @if (filled($row['value']))
                        <div>
                            <div class="flex items-center justify-between gap-2">
                                <p class="ops-builder-present-card__label">{{ $row['label'] }}</p>
                                @unless ($isTerminal)
                                    <button
                                        type="button"
                                        class="text-[10px] font-semibold uppercase tracking-wide text-slate-500 hover:text-slate-800"
                                        @click="window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', { detail: { task: 'dragon-service-advisor', context: { concernId: {{ $concern->id }}, field: @js($fieldKey) }, invokeEl: $event.currentTarget } }))"
                                    >
                                        Rewrite
                                    </button>
                                @endunless
                            </div>
                            <p class="ops-builder-present-card__copy">{{ $row['value'] }}</p>
                        </div>
                    @endif
                @endforeach
            </div>
        @else
            <p class="ops-builder-present-card__empty">No customer story or findings recorded yet.</p>
        @endif

        @if ($activeDragonApp && ! $isTerminal)
            <div
                class="mt-3 rounded-sm border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600"
                x-data="arkDragonServiceAdvisor({
                    revertUrlTemplate: @js('/app/repair-orders/'.$repairOrder->getRouteKey().'/dragon-service-advisor/__APP__/revert'),
                    estimateVersion: @js($estimateVersion ?? $repairOrder->estimate_version),
                    csrfToken: @js(csrf_token()),
                    lastApplication: @js([
                        'public_id' => $activeDragonApp->public_id,
                        'field' => $activeDragonApp->field->value,
                        'can_revert' => true,
                    ]),
                })"
            >
                <p class="font-medium text-slate-700">Dragon rewrite applied to {{ $activeDragonApp->field->label() }}.</p>
                <button type="button" class="mt-1 text-[11px] font-semibold text-slate-800 underline" @click="revert()">
                    Revert Dragon Rewrite
                </button>
            </div>
        @endif
    </div>
</div>
