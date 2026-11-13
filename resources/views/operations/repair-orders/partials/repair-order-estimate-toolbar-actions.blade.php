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

{{-- Contract host: keep data-estimate-tools discoverable; local action buttons intentionally empty. --}}
<div class="visually-hidden" data-estimate-tools aria-hidden="true">
    @if ($showCaptureDealerQuote)
        Dealer quote import is available from Tools and Parts.
    @endif
</div>
