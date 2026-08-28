@if (isset($repairOrder) && $repairOrder->lost_reason_key !== null)
    @php
        $displayTz = \App\Ark\Operations\Settings\ShopDisplayTimezone::resolve();
        $recordedBy = $repairOrder->relationLoaded('lostReasonRecordedBy')
            ? $repairOrder->lostReasonRecordedBy
            : $repairOrder->lostReasonRecordedBy()->first(['id', 'name']);
    @endphp
    <div class="border-b border-amber-200 bg-amber-50 px-3 py-2" role="status">
        <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-amber-900">Closed lost</p>
        <p class="mt-0.5 text-sm font-bold text-amber-950">{{ $repairOrder->lost_reason_key->label() }}</p>
        @if (filled($repairOrder->lost_reason_note))
            <p class="mt-1 text-xs leading-5 text-amber-950">{{ $repairOrder->lost_reason_note }}</p>
        @endif
        <p class="mt-1 text-[11px] leading-4 text-amber-900/80">
            Recorded
            @if ($repairOrder->lost_reason_recorded_at)
                {{ $repairOrder->lost_reason_recorded_at->timezone($displayTz)->format('M j, Y g:i A') }}
            @endif
            @if ($recordedBy?->name)
                · {{ $recordedBy->name }}
            @endif
        </p>
    </div>
@endif
