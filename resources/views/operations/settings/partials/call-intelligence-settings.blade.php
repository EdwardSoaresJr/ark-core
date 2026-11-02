<div class="border rounded p-3 d-flex flex-column gap-3">
    <div class="border-bottom pb-2">
        <div class="subheader">Call intelligence (AI)</div>
        <p class="text-secondary small mb-0">
            Transcribes recorded calls and generates owner summaries on
            <a href="{{ route('operations.owner.call-intelligence') }}" class="fw-semibold">Call intelligence</a>.
        </p>
    </div>

    <div class="alert alert-warning small fw-semibold mb-0">
        Call transcription and AI summaries require a model provider implementation. Stock Core does not include one.
        Recordings can still be captured when call recording is enabled below.
    </div>
</div>
