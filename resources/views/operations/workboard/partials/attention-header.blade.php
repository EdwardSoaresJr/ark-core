@php
    /** @var \App\Ark\Operations\Workboard\WorkboardTriageAttentionHeader $header */
@endphp

<div id="ops-workboard-attention-strip" class="ops-workboard-attention-strip" aria-label="Workboard attention counts">
    @include('operations.workboard.partials.attention-metric', [
        'label' => 'Needs Attention',
        'count' => $header->needsAttention,
        'url' => $header->needsAttentionUrl,
        'tone' => 'alert',
    ])
    @include('operations.workboard.partials.attention-metric', [
        'label' => 'Customer Waiting',
        'count' => $header->customerWaiting,
        'url' => $header->customerWaitingUrl,
        'tone' => 'warn',
    ])
    @include('operations.workboard.partials.attention-metric', [
        'label' => 'Unassigned',
        'count' => $header->unassigned,
        'url' => $header->unassignedUrl,
        'tone' => 'warn',
    ])
    @include('operations.workboard.partials.attention-metric', [
        'label' => 'Overdue Pickup',
        'count' => $header->overduePickup,
        'url' => $header->overduePickupUrl,
        'tone' => 'alert',
    ])
</div>
