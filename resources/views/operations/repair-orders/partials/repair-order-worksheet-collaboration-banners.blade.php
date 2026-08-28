<div x-show="presenceMessage" x-cloak class="border border-sky-300 bg-sky-50 px-3 py-2 text-sm font-semibold text-sky-950">
    <p x-text="presenceMessage"></p>
</div>

<div x-show="versionDriftNotice" x-cloak class="border border-amber-300 bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-900">
    <div class="flex items-start justify-between gap-3">
        <p x-text="versionDriftNotice"></p>
        <button type="button" class="text-xs font-bold uppercase tracking-[0.08em] text-amber-800 hover:text-amber-950" @click="window.location.reload()">Refresh</button>
    </div>
</div>

<div x-show="staleNotice" x-cloak class="border border-amber-300 bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-900">
    <div class="flex items-start justify-between gap-3">
        <p x-text="staleNotice"></p>
        <button type="button" class="text-xs font-bold uppercase tracking-[0.08em] text-amber-800 hover:text-amber-950" @click="clearStaleNotice()">Dismiss</button>
    </div>
</div>
