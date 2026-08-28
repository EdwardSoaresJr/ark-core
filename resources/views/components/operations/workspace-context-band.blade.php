@props([
    'note' => null,
])

<div class="ops-workspace-context-band">
    <div class="min-w-0 flex-1">
        @if (filled($note))
            <p class="ops-workspace-context-band__note">{{ $note }}</p>
        @endif

        @if (filled($title ?? null))
            <p class="ops-workspace-context-band__title">{{ $title }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="ops-workspace-context-band__actions">
            {{ $actions }}
        </div>
    @endisset
</div>
