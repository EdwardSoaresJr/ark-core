<div x-show="presenceMessage" x-cloak role="status">
    <div class="alert alert-info">
        <p class="mb-0" x-text="presenceMessage"></p>
    </div>
</div>

<div x-show="versionDriftNotice" x-cloak role="status">
    <div class="alert alert-warning d-flex align-items-start gap-3">
        <p class="flex-fill mb-0" x-text="versionDriftNotice"></p>
        <button type="button" class="btn btn-ghost-warning btn-sm" @click="reconcileAfterDraftDiscard()">Refresh</button>
    </div>
</div>

<div x-show="staleNotice" x-cloak role="status">
    <div class="alert alert-warning d-flex align-items-start gap-3">
        <p class="flex-fill mb-0" x-text="staleNotice"></p>
        <button type="button" class="btn btn-ghost-warning btn-sm" @click="clearStaleNotice()">Dismiss</button>
    </div>
</div>
