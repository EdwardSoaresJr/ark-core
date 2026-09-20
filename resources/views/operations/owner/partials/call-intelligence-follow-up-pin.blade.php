@props(['row'])

<form method="POST" action="{{ $row['toggle_coaching_follow_up_url'] }}" class="inline">
    @csrf
    <button
        type="submit"
        @class([
            'rounded-sm border px-2.5 py-1.5 text-[11px] font-bold',
            'border-indigo-700 bg-indigo-700 text-white hover:bg-indigo-800' => $row['coaching_follow_up_pinned'],
            'border-slate-300 bg-white text-slate-700 hover:border-slate-400 hover:text-slate-900' => ! $row['coaching_follow_up_pinned'],
        ])
        title="{{ $row['coaching_follow_up_pinned'] ? 'Remove from pinned coaching follow-up' : 'Pin to top of coaching queue for owner follow-up' }}"
    >{{ $row['coaching_follow_up_pinned'] ? 'Unpin' : 'Pin follow-up' }}</button>
</form>
