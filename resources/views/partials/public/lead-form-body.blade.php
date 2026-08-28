@php
    $phoneTel = preg_replace('/\D+/', '', (string) ($shop->phone ?? '')) ?: '7194136227';
    $phoneDisplay = \App\Ark\Operations\PhoneNumber::display($shop->phone) ?: '(719) 413-6227';
    $smsHref = 'sms:'.$phoneTel;
    $staged = (bool) ($staged ?? false);
@endphp

@if (isset($errors) && $errors->any())
    <div class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2.5 text-sm text-rose-900" role="alert">
        <ul class="list-disc space-y-1 pl-4">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@include('partials.public.lead-form-fields', [
    'prefilledConcern' => $prefilledConcern ?? null,
    'submitLabel' => $submitLabel ?? \App\Ark\Operations\Leads\Public\PublicLeadFormCopy::SUBMIT_LABEL,
    'surfaceContext' => $surfaceContext ?? null,
    'phoneVerificationRequired' => $phoneVerificationRequired ?? false,
    'formRenderedAt' => $formRenderedAt ?? now()->timestamp,
    'staged' => $staged,
    'autofocusConcern' => $autofocusConcern ?? null,
    'appointmentRequest' => $appointmentRequest ?? false,
    'requestAvailability' => $requestAvailability ?? null,
    'marketingBook' => $marketingBook ?? false,
])

@if (filled($responseTimeHint ?? null))
    <p class="mt-4 text-sm text-slate-600">
        <span class="font-semibold text-slate-800">Response time:</span>
        {{ $responseTimeHint }}
    </p>
@endif

<p class="mt-4 text-sm text-slate-600 {{ $staged ? 'text-center sm:text-left' : 'text-center' }}">
    Prefer to talk now?
    <a href="tel:{{ $phoneTel }}" data-public-surface-call class="font-semibold text-[#0099cc] hover:text-[#0088b8]">Call</a>
    or
    <a href="{{ $smsHref }}" data-public-surface-text class="font-semibold text-[#0099cc] hover:text-[#0088b8]">text</a>
    {{ $phoneDisplay }}
    @if ($appointmentRequest ?? false)
        to confirm a time sooner.
    @endif
</p>
