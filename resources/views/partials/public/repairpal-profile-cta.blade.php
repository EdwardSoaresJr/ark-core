@if (filled($repairPalUrl ?? null))
    <p class="mt-4">
        <a
            href="{{ $repairPalUrl }}"
            target="_blank"
            rel="noopener noreferrer"
            class="public-link text-sm"
            data-public-surface-repairpal-profile
        >
            View our official RepairPal profile →
        </a>
    </p>
@endif
