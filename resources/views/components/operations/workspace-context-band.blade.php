@props([
    'note' => null,
])

<div {{ $attributes->class(['card card-sm mb-3']) }} data-workspace-context-band>
    <div class="card-body d-flex flex-wrap align-items-center gap-2">
        <div class="flex-fill min-w-0">
            @if (filled($note))
                <div class="subheader">{{ $note }}</div>
            @endif

            @if (filled($title ?? null))
                <div class="fw-bold">{{ $title }}</div>
            @endif
        </div>

        @isset($actions)
            <div class="btn-list">
                {{ $actions }}
            </div>
        @endisset
    </div>
</div>
