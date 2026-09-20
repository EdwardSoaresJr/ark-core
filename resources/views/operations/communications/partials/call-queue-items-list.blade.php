@if ($items === [])
    <p class="ops-call-queue__empty">No calls or unread messages in the last 8 hours.</p>
@else
    <ul class="ops-call-queue__list">
        @foreach ($items as $row)
            <x-operations.queue-row :row="$row" variant="interrupt" />
        @endforeach
    </ul>
@endif
