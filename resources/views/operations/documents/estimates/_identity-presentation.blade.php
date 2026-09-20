@php
    $snapshotConcerns = collect($snapshot['concerns']);
    $approvedCount = $snapshotConcerns->where('disposition', 'approved')->count();
    $deferredCount = $snapshotConcerns->where('disposition', 'deferred')->count();
    $declinedCount = $snapshotConcerns->where('disposition', 'declined')->count();
    $nonApprovedCount = $deferredCount + $declinedCount;
    $recommendedCount = $snapshotConcerns->where('disposition', 'recommended')->count();
    $roStatus = $snapshot['repair_order']['status'] ?? null;
    $paymentStatus = $snapshot['repair_order']['payment_status'] ?? 'unpaid';

    $presentationPrimary = match (true) {
        $roStatus === 'ready_pickup' && $paymentStatus === 'unpaid' => 'Balance due at pickup',
        $roStatus === 'ready_pickup' && $paymentStatus === 'paid' => 'Ready for pickup',
        $roStatus === 'closed' => 'Delivered',
        $roStatus === 'waiting_approval' || $roStatus === 'awaiting_approval' => 'Awaiting Approval',
        $approvedCount > 0 && $nonApprovedCount > 0 => 'Partially approved',
        $approvedCount > 0 => 'Approved for repair',
        $declinedCount > 0 && $deferredCount === 0 => 'Work declined',
        $deferredCount > 0 => 'Optional work on hold',
        $recommendedCount > 0 => 'Recommendations pending',
        default => 'In shop review',
    };

    $presentationSecondary = match (true) {
        $roStatus === 'ready_pickup' && $paymentStatus === 'unpaid' => 'Payment due before release',
        $roStatus === 'ready_pickup' && $paymentStatus === 'paid' => 'Paid in full',
        $roStatus === 'waiting_approval' || $roStatus === 'awaiting_approval' => 'Review and authorize work',
        $recommendedCount > 0 && $approvedCount === 0 && $nonApprovedCount === 0 => $recommendedCount.' '.str('recommendation')->plural($recommendedCount).' to review',
        default => null,
    };
@endphp

@if (($variant ?? 'show') === 'pdf')
    <div class="box">
        <p class="eyebrow">Presentation</p>
        <h3>{{ $presentationPrimary }}</h3>
        @if (filled($presentationSecondary))
            <p class="muted">{{ $presentationSecondary }}</p>
        @endif
    </div>
@else
    <div class="border border-slate-200 bg-white px-2.5 py-2">
        <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Presentation</p>
        <p class="mt-0.5 font-semibold text-slate-950">{{ $presentationPrimary }}</p>
        @if (filled($presentationSecondary))
            <p class="mt-0.5 text-slate-600">{{ $presentationSecondary }}</p>
        @endif
    </div>
@endif
