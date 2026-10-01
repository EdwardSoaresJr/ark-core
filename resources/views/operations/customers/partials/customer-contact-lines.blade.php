@php
    $customerRecord = $customer ?? $result;
    $smsCapability = filled($customerRecord->phone ?? null)
        ? \App\Ark\Operations\Messaging\PhoneSmsCapability::findByNormalizedPhone((string) $customerRecord->phone)
        : null;
@endphp

@if ($customerRecord->email)
    <p class="ops-ro-subline truncate">{{ $customerRecord->email }}</p>
@endif

@if ($customerRecord->contact_preference)
    <p class="ops-ro-subline truncate font-semibold text-sky-800">{{ $customerRecord->contact_preference->outreachLabel() }}</p>
@endif

@if ($smsCapability !== null && ! $smsCapability->sms_capable)
    <p class="ops-ro-subline truncate text-amber-800">{{ $smsCapability->blockReason() }}</p>
@endif

<p class="ops-ro-subline truncate {{ $customerRecord->display_address ? '' : 'text-slate-400' }}">
    {{ $customerRecord->display_address ?: 'No address on file' }}
</p>
