@if (isset($repairOrder) && $repairOrder->lost_reason_key !== null)
    @php
        $displayTz = \App\Ark\Operations\Settings\ShopDisplayTimezone::resolve();
        $recordedBy = $repairOrder->relationLoaded('lostReasonRecordedBy')
            ? $repairOrder->lostReasonRecordedBy
            : $repairOrder->lostReasonRecordedBy()->first(['id', 'name']);
    @endphp
    <div class="alert alert-warning" role="status" data-lost-reason-record>
        <div class="subheader">Closed lost</div>
        <div class="fw-bold">{{ $repairOrder->lost_reason_key->label() }}</div>
        @if (filled($repairOrder->lost_reason_note))
            <div class="small mt-1">{{ $repairOrder->lost_reason_note }}</div>
        @endif
        <div class="small text-secondary mt-1">
            Recorded
            @if ($repairOrder->lost_reason_recorded_at)
                {{ $repairOrder->lost_reason_recorded_at->timezone($displayTz)->format('M j, Y g:i A') }}
            @endif
            @if ($recordedBy?->name)
                · {{ $recordedBy->name }}
            @endif
        </div>
    </div>
@endif
