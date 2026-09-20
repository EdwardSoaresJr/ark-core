<div class="space-y-3 rounded-sm border border-slate-200 bg-white p-3">
    <div class="border-b border-slate-200 pb-2">
        <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Call intelligence (AI)</p>
        <p class="mt-1 text-xs leading-5 text-slate-500">
            Transcribes recorded calls and generates owner summaries on
            <a href="{{ route('operations.owner.call-intelligence') }}" class="font-semibold text-slate-800 underline">Call intelligence</a>.
        </p>
    </div>

    <div class="rounded-sm border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-900">
        Call transcription and AI summaries require a model provider implementation. Stock Core does not include one.
        Recordings can still be captured when call recording is enabled below.
    </div>
</div>
