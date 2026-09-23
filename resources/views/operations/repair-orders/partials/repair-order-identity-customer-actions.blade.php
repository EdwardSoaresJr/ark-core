<div class="ops-identity-actions">
    <a href="#communication-rail" class="ops-identity-action">Message</a>
    @if (! empty($scheduleFromRoHref))
        <a href="{{ $scheduleFromRoHref }}" class="ops-identity-action">Schedule Follow-up</a>
    @endif
    @if (! empty($newRoFromExistingHref))
        <a href="{{ $newRoFromExistingHref }}" class="ops-identity-action" title="Open another repair order for this customer and vehicle">New RO</a>
    @endif
</div>
