@php
    $customerRecord = $customer ?? $result;
    $customerTag = trim((string) ($customerRecord->customer_type ?: 'Retail'));
    $hideDefaultRetail = ($intakeMode ?? false) && $customerTag === 'Retail';
@endphp

@if (! $hideDefaultRetail)
    <span @class([
        'ops-state-pill shrink-0',
        'ops-state-pill--customer-tag' => ($intakeMode ?? false),
    ])>{{ $customerTag }}</span>
@endif
