@php
    use Illuminate\Support\Str;

    $customerNotes = trim($repairOrder->customer->notes ?? '');
    $notesPreview = Str::of($customerNotes)
        ->replaceMatches('/\s+/u', ' ')
        ->trim()
        ->limit(140)
        ->toString();
@endphp

<div
    class="ops-ro-customer-notes-band border-b border-slate-200 bg-amber-50/70"
    x-data="{ open: false }"
>
    <button
        type="button"
        class="ops-ro-customer-notes-band__toggle flex w-full items-center gap-3 px-2.5 py-2 text-left hover:bg-amber-50"
        @click="open = ! open"
        :aria-expanded="open"
    >
        <span class="shrink-0 text-[10px] font-bold uppercase tracking-[0.08em] text-amber-900/80">Customer notes</span>
        <span
            class="ops-ro-customer-notes-band__preview min-w-0 flex-1 text-xs font-medium leading-4 text-amber-950/90"
            x-show="! open"
        >{{ $notesPreview }}</span>
        <span class="shrink-0 text-[11px] font-semibold text-amber-900 underline-offset-2 hover:underline" x-text="open ? 'Hide notes' : 'Show notes'"></span>
    </button>
    <div x-show="open" x-cloak class="border-t border-amber-200/70 px-2.5 pb-2.5 pt-2">
        <p class="whitespace-pre-line text-xs leading-4 text-amber-950">{{ $customerNotes }}</p>
    </div>
</div>
