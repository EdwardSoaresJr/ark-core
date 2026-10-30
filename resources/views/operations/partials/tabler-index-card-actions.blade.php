@php
    /** @var list<array{href: ?string, key: string, label: string}> $indexActions */
    $actionIcons = [
        'vehicle' => 'car',
        'documents' => 'file-description',
        'customer' => 'user',
        'ro' => 'file-text',
    ];
@endphp

<div class="btn-list flex-nowrap" role="group" aria-label="Open">
    @foreach ($indexActions as $action)
        @if ($action['href'] !== null)
            <a
                href="{{ $action['href'] }}"
                class="btn btn-icon btn-ghost-secondary"
                title="{{ $action['label'] }}"
                aria-label="{{ $action['label'] }}"
                data-bs-toggle="tooltip"
            >
                <x-tabler.icon :name="$actionIcons[$action['key']]" />
            </a>
        @else
            <span class="btn btn-icon btn-ghost-secondary disabled" aria-hidden="true">
                <x-tabler.icon :name="$actionIcons[$action['key']]" />
            </span>
        @endif
    @endforeach
</div>
