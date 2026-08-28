@php
    $importantInformation = collect($footer['important_information'] ?? [])
        ->filter(fn ($bullet): bool => filled($bullet))
        ->values()
        ->all();
    $customerTypeTerms = is_array($footer['customer_type_terms'] ?? null)
        ? $footer['customer_type_terms']
        : null;
    $termsBullets = collect($customerTypeTerms['bullets'] ?? [])
        ->filter(fn ($bullet): bool => filled($bullet))
        ->values()
        ->all();
@endphp

@if ($importantInformation !== [] || $termsBullets !== [])
    <section class="{{ filled($class ?? null) ? $class : 'mb-5' }}">
        @if ($importantInformation !== [])
            <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">Important Information</p>
            <ul class="mt-2 space-y-1.5 text-sm leading-5 text-slate-600">
                @foreach ($importantInformation as $bullet)
                    <li class="flex gap-2">
                        <span class="mt-2 h-1 w-1 shrink-0 rounded-full bg-slate-400" aria-hidden="true"></span>
                        <span>{{ $bullet }}</span>
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($termsBullets !== [])
            <p @class([
                'text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500',
                'mt-4' => $importantInformation !== [],
            ])>{{ $customerTypeTerms['heading'] ?? 'Terms' }}</p>
            <ul class="mt-2 space-y-1.5 text-sm leading-5 text-slate-600">
                @foreach ($termsBullets as $bullet)
                    <li class="flex gap-2">
                        <span class="mt-2 h-1 w-1 shrink-0 rounded-full bg-slate-400" aria-hidden="true"></span>
                        <span>{{ $bullet }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endif
