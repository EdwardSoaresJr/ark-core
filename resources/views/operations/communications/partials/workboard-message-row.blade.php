@php
    /** @var array<string, mixed> $row */
@endphp

<div class="bg-white px-2 py-1">
    @include('operations.communications.partials.queue-row', ['row' => $row, 'show_timestamp' => true])
</div>
