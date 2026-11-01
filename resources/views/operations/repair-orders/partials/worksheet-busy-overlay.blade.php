<div
    x-show="worksheetSaving"
    x-cloak
    class="fixed-top h-100 bg-dark bg-opacity-25"
    data-worksheet-busy-overlay
    aria-live="polite"
    aria-busy="true"
>
    {{-- Bootstrap display utilities override x-show, so the centering lives on this inner wrapper. --}}
    <div class="d-flex align-items-center justify-content-center h-100">
        <div class="card card-sm shadow-lg">
            <div class="card-body d-flex flex-column align-items-center gap-3 px-4 py-3">
                <span class="spinner-border text-primary" aria-hidden="true"></span>
                <p class="fw-bold small text-center mb-0" x-text="worksheetSavingLabel || 'Saving…'"></p>
            </div>
        </div>
    </div>
</div>
