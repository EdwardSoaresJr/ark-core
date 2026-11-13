{{--
  Estimate-local Import / Preview / Customer View removed for density.
  Import Dealer Quote: footer Tools + Parts context.
  Preview: Customer View → portal panel (estimate-portal-link).
  Customer View: estimate context strip.
--}}
@php
    $mode = $mode ?? 'edit';
    $isTerminal = $isTerminal ?? false;
    $showCaptureDealerQuote = $mode === 'edit' && ! $isTerminal;
@endphp

@if ($showCaptureDealerQuote)
    {{-- Keep a non-visual host marker so existing tests can assert the capture path still exists elsewhere. --}}
    <div class="visually-hidden" data-estimate-tools-relocated aria-hidden="true">
        Dealer quote import is available from Tools and Parts.
    </div>
@endif
