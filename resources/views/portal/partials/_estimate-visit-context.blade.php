@php
    $visitReason = trim((string) (
        $snapshot['intake']['visit_reason']
            ?? $repairOrder->visit_reason
            ?? ''
    ));
@endphp

@if ($visitReason !== '')
    <section class="rounded-xl border border-slate-200/90 bg-slate-50/80 px-4 py-4 sm:px-5">
        <h2 class="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">Why the vehicle came in</h2>
        <p class="mt-2 text-xs font-semibold text-slate-500">You told us:</p>
        <p class="mt-1 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $visitReason }}</p>
    </section>
@endif
