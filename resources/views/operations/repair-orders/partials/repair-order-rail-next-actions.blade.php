@php
    $customerIdentityPressure = $repairOrder->customerIdentityPressure();
    $vehicleIdentityPressure = $repairOrder->vehicleIdentityPressure();
    $customerIdentityHint = $repairOrder->customerIdentityPressureHint();
    $vehicleIdentityHint = $repairOrder->vehicleIdentityPressureHint();
    $unresolvedParts = $repairOrder->approvedPartLines()
        ->filter(fn ($line): bool => $line->hasUnresolvedProcurement())
        ->values();
    $communicationAction = $repairOrder->communicationNextAction();
    $communicationHint = $repairOrder->communicationPostureLabel();
    $idleCommunication = in_array($communicationAction, [
        'No communication action pending',
        'Wait for customer response',
        'Waiting customer response',
        'Await customer arrival',
    ], true);
    $companionCheck = app(\App\Ark\Operations\RepairOrders\EstimateCompanionCompletenessProjection::class)->for($repairOrder);
    $showCompanion = (bool) ($companionCheck['needs_attention'] ?? false);
    $hasNextActions = $customerIdentityPressure->showsChip()
        || $vehicleIdentityPressure->showsChip()
        || $unresolvedParts->isNotEmpty()
        || ! $idleCommunication
        || $showCompanion;
@endphp

@if ($hasNextActions)
    <section class="ops-review-panel" aria-label="Next actions">
        <div class="ops-review-panel-header">
            <p class="ops-eyebrow">Next actions</p>
        </div>
        <div class="divide-y divide-slate-100">
            @if ($unresolvedParts->isNotEmpty())
                <div class="ops-review-rail-posture__row ops-review-rail-posture__row--parts">
                    <p class="ops-review-rail-posture__label">Order {{ $unresolvedParts->count() }} part{{ $unresolvedParts->count() === 1 ? '' : 's' }}</p>
                    <ul class="mt-1 space-y-1">
                        @foreach ($unresolvedParts as $line)
                            <li>
                                <p class="ops-review-rail-posture__value">{{ $line->description !== '' ? $line->description : 'Part line' }}</p>
                                <p class="ops-review-rail-posture__hint">{{ \Illuminate\Support\Str::ucfirst($line->procurementPressureLabel()) }}</p>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @unless ($idleCommunication)
                <div class="ops-review-rail-posture__row">
                    <p class="ops-review-rail-posture__label">Follow up</p>
                    <p class="ops-review-rail-posture__value">{{ $communicationAction }}</p>
                    <p class="ops-review-rail-posture__hint">{{ $communicationHint }}</p>
                </div>
            @endunless

            @if ($showCompanion)
                <div
                    class="ops-review-rail-posture__row"
                    @if (\Illuminate\Support\Facades\Route::has('operations.repair-orders.companion-suggestion.dismiss'))
                        x-data="arkDismissCompanionSuggestion({
                            url: @js(route('operations.repair-orders.companion-suggestion.dismiss', $repairOrder))
                        })"
                    @endif
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="ops-review-rail-posture__label">Estimate check</p>
                            <p class="ops-review-rail-posture__value">{{ $companionCheck['headline'] }}</p>
                            <p class="ops-review-rail-posture__hint">{{ $companionCheck['advisor_detail'] }}</p>
                        </div>
                        @if (\Illuminate\Support\Facades\Route::has('operations.repair-orders.companion-suggestion.dismiss'))
                            <button
                                type="button"
                                class="shrink-0 text-[11px] font-bold text-slate-600 hover:text-slate-950 disabled:opacity-50"
                                :disabled="busy"
                                @click="dismiss()"
                            >Dismiss</button>
                        @endif
                    </div>
                </div>
            @endif

            @if ($customerIdentityPressure->showsChip())
                <div class="ops-review-rail-posture__row">
                    <p class="ops-review-rail-posture__label">Customer info</p>
                    <div class="mt-1">
                        @include('operations.repair-orders.partials.repair-order-customer-identity-pressure', ['repairOrder' => $repairOrder])
                    </div>
                    @if ($customerIdentityHint)
                        <p class="ops-review-rail-posture__hint">{{ $customerIdentityHint }}</p>
                    @endif
                </div>
            @endif

            @if ($vehicleIdentityPressure->showsChip())
                <div class="ops-review-rail-posture__row">
                    <p class="ops-review-rail-posture__label">Vehicle identity</p>
                    <div class="mt-1">
                        @include('operations.repair-orders.partials.repair-order-vehicle-identity-pressure-chip', ['repairOrder' => $repairOrder])
                    </div>
                    @if ($vehicleIdentityHint)
                        <p class="ops-review-rail-posture__hint">{{ $vehicleIdentityHint }}</p>
                    @endif
                </div>
            @endif
        </div>
    </section>
@endif
