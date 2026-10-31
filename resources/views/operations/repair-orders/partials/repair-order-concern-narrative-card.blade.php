@php
    $narrativeRows = [
        'customer_states' => [
            'label' => 'Customer states',
            'value' => $concern->customer_states,
        ],
        'verified_findings' => [
            'label' => 'Verified findings',
            'value' => $concern->verified_findings,
        ],
        'dtcs_summary' => [
            'label' => 'DTCs',
            'value' => $concern->dtcs_summary,
        ],
        'recommendation' => [
            'label' => 'Recommendation',
            'value' => $concern->recommendation,
        ],
    ];
    $hasNarrativeBody = collect($narrativeRows)->filter(fn ($row) => filled($row['value']))->isNotEmpty();
    $dragonReady = class_exists(\Ark\Saas\Dragon\ServiceAdvisor\ServiceAdvisorMode::class)
        && \Illuminate\Support\Facades\Route::has('operations.repair-orders.concerns.dragon-service-advisor');

    $activeDragonApp = null;
    if (
        class_exists(\Ark\Saas\Dragon\ServiceAdvisor\DragonServiceAdvisorApplication::class)
        && \App\Ark\Runtime\Database\SchemaPresence::hasTable('dragon_service_advisor_applications')
    ) {
        $activeDragonApp = \Ark\Saas\Dragon\ServiceAdvisor\DragonServiceAdvisorApplication::query()
            ->where('concern_id', $concern->id)
            ->whereNotNull('applied_at')
            ->whereNull('reverted_at')
            ->latest('applied_at')
            ->first();
    }
@endphp

<div id="concern-narrative-{{ $concern->id }}" class="card-body border-bottom" data-concern-narrative>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
        <h4 class="subheader mb-0">Narrative</h4>
        @unless ($isTerminal)
            <div class="btn-list">
                @if ($dragonReady && ($hasNarrativeBody || filled($concern->summary)))
                    <button
                        type="button"
                        class="btn btn-sm btn-ghost-secondary"
                        @click="window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', { detail: { task: 'review-estimate-notes', context: { concernId: {{ $concern->id }} }, invokeEl: $event.currentTarget } }))"
                    >
                        Review this concern
                    </button>
                    <button
                        type="button"
                        class="btn btn-sm btn-ghost-secondary"
                        @click="window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', { detail: { task: 'dragon-service-advisor', context: { concernId: {{ $concern->id }} }, invokeEl: $event.currentTarget } }))"
                    >
                        Rewrite notes
                    </button>
                @endif
                <button
                    type="button"
                    class="btn btn-sm"
                    @click="window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', { detail: { task: 'concern-narrative', context: { concernId: {{ $concern->id }} }, invokeEl: $event.currentTarget } }))"
                >
                    {{ $hasNarrativeBody || filled($concern->summary) ? 'Edit' : 'Add' }}
                </button>
            </div>
        @endunless
    </div>

    <div class="d-flex align-items-start justify-content-between gap-2">
        <p class="fw-medium mb-2">{!! \App\Ark\Operations\RepairOrders\RepairOrderMention::html((string) $concern->summary, $priorVisitMentions['href_by_number'] ?? []) !!}</p>
        @unless ($isTerminal)
            @if ($dragonReady && filled($concern->summary))
                <button
                    type="button"
                    class="btn btn-sm btn-link px-0"
                    @click="window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', { detail: { task: 'dragon-service-advisor', context: { concernId: {{ $concern->id }}, field: 'summary' }, invokeEl: $event.currentTarget } }))"
                >
                    Rewrite
                </button>
            @endif
        @endunless
    </div>

    @if ($hasNarrativeBody)
        <div class="row g-3">
            @foreach ($narrativeRows as $fieldKey => $row)
                @if (filled($row['value']))
                    <div class="col-md-6">
                        <div class="d-flex align-items-center justify-content-between gap-2">
                            <div class="subheader">{{ $row['label'] }}</div>
                            @if ($dragonReady && ! $isTerminal)
                                <button
                                    type="button"
                                    class="btn btn-sm btn-link px-0"
                                    @click="window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', { detail: { task: 'dragon-service-advisor', context: { concernId: {{ $concern->id }}, field: @js($fieldKey) }, invokeEl: $event.currentTarget } }))"
                                >
                                    Rewrite
                                </button>
                            @endif
                        </div>
                        <p class="mb-0">{{ $row['value'] }}</p>
                    </div>
                @endif
            @endforeach
        </div>
    @else
        <p class="text-secondary mb-0">No customer story or findings recorded yet.</p>
    @endif

    @include('operations.repair-orders.partials.finding-from-verified-findings-nudge', [
        'repairOrder' => $repairOrder,
        'concern' => $concern,
        'ui' => 'tabler',
    ])

    @if ($activeDragonApp && ! $isTerminal)
        <div
            class="alert alert-info mt-3 mb-0"
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
            <p class="mb-1">Dragon rewrite applied to {{ $activeDragonApp->field->label() }}.</p>
            <button type="button" class="btn btn-sm btn-link px-0" @click="revert()">
                Revert Dragon Rewrite
            </button>
        </div>
    @endif
</div>
