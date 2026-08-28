@php
    $shopName = $shop->displayName();
    $phoneDisplay = \App\Ark\Operations\PhoneNumber::display($shop->phone) ?: '(719) 413-6227';
    $phoneTel = preg_replace('/\D+/', '', (string) $shop->phone) ?: '7194136227';
    $smsHref = 'sms:'.$phoneTel;
    $formHeading = $formHeading ?? 'What\'s going on?';
    $formSubheading = $formSubheading ?? 'We\'ll take a look and reach out with the next best step.';
    $placement = $placement ?? 'rail';
    $staged = (bool) ($staged ?? false);
    $useShell = (bool) ($useShell ?? true);
    $marketingBook = (bool) ($marketingBook ?? false);
    $bodyPayload = [
        'shop' => $shop,
        'prefilledConcern' => $prefilledConcern ?? null,
        'submitLabel' => $submitLabel ?? \App\Ark\Operations\Leads\Public\PublicLeadFormCopy::SUBMIT_LABEL,
        'surfaceContext' => $surfaceContext ?? null,
        'phoneVerificationRequired' => $phoneVerificationRequired ?? false,
        'formRenderedAt' => $formRenderedAt ?? now()->timestamp,
        'responseTimeHint' => $responseTimeHint ?? null,
        'staged' => $staged,
        'autofocusConcern' => $autofocusConcern ?? null,
        'appointmentRequest' => $appointmentRequest ?? false,
        'requestAvailability' => $requestAvailability ?? null,
        'marketingBook' => $marketingBook,
    ];
@endphp

@if ($marketingBook)
    <div class="public-book-form">
        @include('partials.public.lead-form-body', $bodyPayload)
    </div>
@elseif ($useShell)
    <x-public.action-card
        :heading="$formHeading"
        :subheading="$formSubheading"
        :placement="$placement"
        :staged="$staged"
        id="tell-the-shop"
    >
        @include('partials.public.lead-form-body', $bodyPayload)
    </x-public.action-card>
@else
    <section class="public-panel public-panel--accent" id="tell-the-shop">
        @if (filled($formHeading))
            <h2 class="text-xl font-bold tracking-tight text-slate-950">{{ $formHeading }}</h2>
        @endif
        @if (filled($formSubheading))
            <p class="mt-1.5 text-sm leading-relaxed text-slate-600 sm:text-base">{{ $formSubheading }}</p>
        @endif
        @include('partials.public.lead-form-body', $bodyPayload)
    </section>
@endif
