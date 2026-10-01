@props([
    'rows' => [],
])

@if ($rows === [])
    <p class="px-3 py-3 text-sm text-slate-600">No recent communications in the queue window.</p>
@else
    <ul class="divide-y divide-slate-100">
        @foreach ($rows as $row)
            @include('operations.communications.partials.queue-row', [
                'row' => $row,
                'show_timestamp' => true,
            ])
        @endforeach
    </ul>
@endif
