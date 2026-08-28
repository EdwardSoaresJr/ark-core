<x-operations.conversation-context-panel
    :context="$callContext"
    :show-customer-header="false"
    :show-section-header="false"
    :show-active-vehicles="false"
    :show-conversation="false"
    open-repair-orders-label="Active Work"
    :open-repair-orders-meta="'Open repair orders across all vehicles — status, workflow posture, and next action.'"
/>

@include('operations.work.partials.advisor-work-context-panel', [
    'followUps' => $openFollowUps ?? [],
    'tasks' => $openTasks ?? [],
])

<div class="border-t border-slate-100 px-3 py-2">
    <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">All visits</p>
    <p class="mt-0.5 text-xs text-slate-500">Closed and historical repair orders live on the History tab.</p>
</div>
