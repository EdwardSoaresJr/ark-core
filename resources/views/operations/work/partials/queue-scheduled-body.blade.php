@include('operations.work.partials.advisor-scheduled-decisions-section', [
    'groups' => $scheduled_decisions,
    'empty' => 'No customer decisions waiting on a scheduled day.',
])
