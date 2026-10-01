<div
    x-show="worksheetSaving"
    x-cloak
    class="ops-worksheet-busy-overlay"
    aria-live="polite"
    aria-busy="true"
>
    <div class="ops-worksheet-busy-overlay__panel">
        <span class="ops-worksheet-busy-overlay__spinner ops-partstech-loader" aria-hidden="true"></span>
        <p class="ops-worksheet-busy-overlay__label" x-text="worksheetSavingLabel || 'Saving…'"></p>
    </div>
</div>
