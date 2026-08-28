@include('operations.work.partials.advisor-work-items-section', [
    'groups' => $follow_ups,
    'variant' => 'follow-up',
    'empty' => 'No customer follow-ups scheduled.',
])
