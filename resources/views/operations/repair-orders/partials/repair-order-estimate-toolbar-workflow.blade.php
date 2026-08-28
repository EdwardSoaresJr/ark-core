@php
    $isTerminal = $isTerminal ?? false;
    $gateWorkflowControls = $gateWorkflowControls ?? false;
    $reviewRequest = $reviewRequest ?? app(\App\Ark\Operations\Messaging\ReviewRequestProjection::class)->for($repairOrder);
@endphp

{{-- Primary tech remains for In Progress when no Repair Action owner exists yet (common on PPI / inspection visits). --}}
<div class="ops-review-toolbar-section ops-review-toolbar-section--workflow">
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
            <div class="mt-2 max-w-md space-y-1.5" data-review-request-follow-up>
                @if ($reviewRequest['already_sent'] && ($reviewRequest['history_entries'] !== [] || filled($reviewRequest['status_label'])))
                    <div class="space-y-1" data-review-request-status>
                        <p class="text-[11px] font-semibold leading-4 text-slate-900">Review Requested</p>
                        @foreach ($reviewRequest['history_entries'] as $entry)
                            <p class="text-[11px] leading-4 text-slate-700">
                                {{ $entry['channel_label'] }} · {{ $entry['when_label'] }}
                            </p>
                        @endforeach
                        @if (filled($reviewRequest['sent_by_label']))
                            <p class="text-[11px] leading-4 text-slate-600">by {{ $reviewRequest['sent_by_label'] }}</p>
                        @endif
                    </div>
                @elseif ($reviewRequest['can_send'])
                    <button
                        type="button"
                        class="text-[11px] font-semibold leading-4 text-slate-800 underline-offset-2 hover:underline"
                        data-review-request-open
                        @click="window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', { detail: { task: 'review-request', context: { closePaid: false }, invokeEl: $event.currentTarget } }))"
                    >
                        Request a Review
                    </button>
                @elseif (filled($reviewRequest['no_contact_message']))
                    <p class="text-[11px] leading-4 text-slate-600">{{ $reviewRequest['no_contact_message'] }}</p>
                @endif
            </div>
        @endcanany
    @endif
</div>
