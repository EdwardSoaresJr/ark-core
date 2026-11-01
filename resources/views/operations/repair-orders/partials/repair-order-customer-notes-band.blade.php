@php
    use Illuminate\Support\Str;

    $customerNotes = trim($repairOrder->customer->notes ?? '');
    $notesPreview = Str::of($customerNotes)
        ->replaceMatches('/\s+/u', ' ')
        ->trim()
        ->limit(140)
        ->toString();
@endphp

<div class="card-footer p-0" x-data="{ open: false }" data-customer-notes-band>
    <button
        type="button"
        class="btn btn-ghost-warning w-100 justify-content-start gap-3 rounded-0 text-start"
        @click="open = ! open"
        :aria-expanded="open"
        aria-controls="ro-customer-notes"
    >
        <span class="subheader text-warning flex-shrink-0">Customer notes</span>
        <span class="small text-truncate flex-fill text-reset" x-show="! open">{{ $notesPreview }}</span>
        <span class="small flex-shrink-0 ms-auto" x-text="open ? 'Hide notes' : 'Show notes'">Show notes</span>
    </button>
    <div id="ro-customer-notes" x-show="open" x-cloak class="px-3 pb-3 pt-2 border-top">
        <p class="small mb-0" style="white-space: pre-line">{{ $customerNotes }}</p>
    </div>
</div>
