@php
    $sinceLastShiftCount = count($since_last_shift ?? []);
    $needsAttentionCount = count($needs_attention_now ?? []);
    $unknownCount = count($unknown ?? []);
@endphp

@include('operations.communications.partials.queue-section', [
    'title' => 'Since Last Shift',
    'count' => $sinceLastShiftCount,
    'note' => ($since_last_shift_boundary_label ?? '') !== ''
        ? 'Since you were last in ARK ('.$since_last_shift_boundary_label.'). Oldest first.'
        : 'Since you were last in ARK. Oldest first.',
    'rows' => $since_last_shift ?? [],
    'empty' => 'Nothing new since your last shift.',
    'show_timestamp' => true,
])

@include('operations.communications.partials.queue-section', [
    'title' => 'Needs Attention',
    'count' => $needsAttentionCount,
    'note' => 'Live calls, recent unread messages, and conversations still waiting on the shop.',
    'rows' => $needs_attention_now ?? [],
    'empty' => 'Nothing needs attention right now.',
])

@if ($unknownCount > 0)
    @include('operations.communications.partials.queue-section', [
        'title' => 'Unknown / Unmatched',
        'count' => $unknownCount,
        'note' => 'Callers and texters that may need intake or customer creation.',
        'rows' => $unknown ?? [],
        'empty' => 'No unknown contacts in the queue window.',
    ])
@endif
