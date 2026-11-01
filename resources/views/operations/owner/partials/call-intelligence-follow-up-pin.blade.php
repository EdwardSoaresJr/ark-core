@props(['row'])

<form method="POST" action="{{ $row['toggle_coaching_follow_up_url'] }}" class="d-inline">
    @csrf
    <button
        type="submit"
        @class([
            'btn btn-sm',
            'btn-indigo' => $row['coaching_follow_up_pinned'],
        ])
        title="{{ $row['coaching_follow_up_pinned'] ? 'Remove from pinned coaching follow-up' : 'Pin to top of coaching queue for owner follow-up' }}"
        data-call-intelligence-pin="{{ $row['coaching_follow_up_pinned'] ? 'pinned' : 'unpinned' }}"
    >{{ $row['coaching_follow_up_pinned'] ? 'Unpin' : 'Pin follow-up' }}</button>
</form>
