@php
    /** @var array<string, mixed> $row */
@endphp

@if (($row['kind'] ?? '') === 'waiting_customer_lead' || ($row['kind'] ?? '') === 'lead')
    @include('operations.communications.partials.workboard-card-lead', ['row' => $row])
@else
    @include('operations.communications.partials.workboard-card-conversation', ['row' => $row])
@endif
