{{-- The conversation context panel is shared with the communications screens and stays legacy until they migrate. --}}
<div class="ark-legacy bg-white text-dark">
    <x-operations.conversation-context-panel
        :context="$callContext"
        :show-customer-header="false"
        :show-section-header="false"
        :show-active-vehicles="false"
        :show-conversation="false"
        open-repair-orders-label="Active Work"
        :open-repair-orders-meta="'Open repair orders across all vehicles - status, workflow posture, and next action.'"
    />
</div>

@if (($openFollowUps ?? []) !== [] || ($openTasks ?? []) !== [])
    <div class="border-top">
        @include('operations.work.partials.advisor-work-context-panel', [
            'followUps' => $openFollowUps ?? [],
            'tasks' => $openTasks ?? [],
        ])
    </div>
@endif

<div class="card-body border-top py-2">
    <div class="subheader">All visits</div>
    <div class="text-secondary small">Closed and historical repair orders live on the History tab.</div>
</div>
