@props([
    'token',
])

<div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
    <a
        href="{{ route('portal.estimates.pdf.view', ['token' => $token]) }}"
        target="_blank"
        rel="noopener"
        class="inline-flex min-h-10 items-center gap-1.5 font-semibold text-[#0099cc] hover:text-[#007aa3]"
    >
        <span aria-hidden="true">↗</span>
        View PDF
    </a>
    <span class="hidden text-slate-300 sm:inline" aria-hidden="true">|</span>
    <a
        href="{{ route('portal.estimates.pdf.download', ['token' => $token]) }}"
        class="inline-flex min-h-10 items-center gap-1.5 font-semibold text-slate-700 hover:text-slate-950"
    >
        <span aria-hidden="true">↓</span>
        Download PDF
    </a>
</div>
