@php
    $isTerminal = $isTerminal ?? false;
    $gateWorkflowControls = $gateWorkflowControls ?? false;
    $reviewRequest = $reviewRequest ?? app(\App\Ark\Operations\Messaging\ReviewRequestProjection::class)->for($repairOrder);
@endphp

{{-- Primary tech remains for In Progress when no Repair Action owner exists yet (common on PPI / inspection visits). --}}
<div class="ark-ro-command-bar__workflow" data-ro-command-workflow>
    @unless ($isTerminal)
        @include('operations.repair-orders.partials.repair-order-technician-select', [
            'repairOrder' => $repairOrder,
            'technicians' => $technicians ?? collect(),
            'estimateVersion' => $estimateVersion,
            'soloOwnerShop' => $soloOwnerShop ?? false,
            'selectId' => 'assigned-technician-toolbar',
        ])

        @canany([
            App\Ark\Runtime\Authorization\ArkCapability::RepairOrdersManage->value,
            App\Ark\Runtime\Authorization\ArkCapability::RepairOrdersLifecycle->value,
        ])
            @include('operations.repair-orders.partials.repair-order-lifecycle-select', [
                'repairOrder' => $repairOrder,
                'lifecycleOptions' => $lifecycleOptions,
                'closeVariantOptions' => $closeVariantOptions ?? [],
                'estimateVersion' => $estimateVersion,
                'balanceProjection' => $balanceProjection ?? null,
                'reviewRequest' => $reviewRequest,
            ])
        @endcanany
    @endunless

    @if ($reviewRequest['show_follow_up'])
        @canany([
            App\Ark\Runtime\Authorization\ArkCapability::RepairOrdersManage->value,
            App\Ark\Runtime\Authorization\ArkCapability::RepairOrdersLifecycle->value,
        ])
            <div class="ark-ro-command-bar__review small" data-review-request-follow-up>
                @if ($reviewRequest['already_sent'] && ($reviewRequest['history_entries'] !== [] || filled($reviewRequest['status_label'])))
                    <div data-review-request-status>
                        <div class="fw-bold">Review Requested</div>
                        @foreach ($reviewRequest['history_entries'] as $entry)
                            <div class="text-secondary">{{ $entry['channel_label'] }} · {{ $entry['when_label'] }}</div>
                        @endforeach
                        @if (filled($reviewRequest['sent_by_label']))
                            <div class="text-secondary">by {{ $reviewRequest['sent_by_label'] }}</div>
                        @endif
                    </div>
                @elseif ($reviewRequest['can_send'])
                    <button
                        type="button"
                        class="btn btn-link btn-sm px-0"
                        data-review-request-open
                        @click="window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', { detail: { task: 'review-request', context: { closePaid: false }, invokeEl: $event.currentTarget } }))"
                    >
                        Request a Review
                    </button>
                @elseif (filled($reviewRequest['no_contact_message']))
                    <div class="text-secondary">{{ $reviewRequest['no_contact_message'] }}</div>
                @endif
            </div>
        @endcanany
    @endif
</div>
