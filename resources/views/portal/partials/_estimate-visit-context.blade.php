@php
    $visit = $snapshot['customer_visit'] ?? null;
    $visitReason = trim((string) (
        (is_array($visit) ? ($visit['concern'] ?? null) : null)
            ?? $snapshot['intake']['visit_reason']
            ?? $repairOrder->visit_reason
            ?? ''
    ));
    $preferredVisit = trim((string) (is_array($visit) ? ($visit['preferred_visit'] ?? '') : ''));
@endphp

@if ($visitReason !== '' || $preferredVisit !== '')
    <section class="rounded-xl border border-slate-200/90 bg-slate-50/80 px-4 py-4 sm:px-5">
        <h2 class="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">Reason for Visit</h2>
        @if ($visitReason !== '')
            <p class="mt-2 text-xs font-semibold uppercase tracking-[0.08em] text-slate-500">Customer Concern</p>
            <p class="mt-1 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $visitReason }}</p>
        @endif
        @if ($preferredVisit !== '')
            <p class="mt-3 text-xs text-slate-500">Preferred visit · {{ $preferredVisit }}</p>
        @endif
    </section>
@endif
