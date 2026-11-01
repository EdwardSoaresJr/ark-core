@php
    $customerRecord = $customer ?? $result;
    $smsCapability = filled($customerRecord->phone ?? null)
        ? \App\Ark\Operations\Messaging\PhoneSmsCapability::findByNormalizedPhone((string) $customerRecord->phone)
        : null;
@endphp

@if ($customerRecord->email)
    <div class="small text-secondary text-truncate">{{ $customerRecord->email }}</div>
@endif

@if ($customerRecord->contact_preference)
    <div class="small fw-medium text-azure text-truncate">{{ $customerRecord->contact_preference->outreachLabel() }}</div>
@endif

@if ($smsCapability !== null && ! $smsCapability->sms_capable)
    <div class="small text-warning text-truncate">{{ $smsCapability->blockReason() }}</div>
@endif

<div @class(['small text-truncate', 'text-secondary' => (bool) $customerRecord->display_address, 'text-muted' => ! $customerRecord->display_address])>
    {{ $customerRecord->display_address ?: 'No address on file' }}
</div>
