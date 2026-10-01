@php
    /** @var array<string, mixed> $workspace */
    $listTitle = 'Internal channels';
    $listDescription = 'shop coordination channels';
@endphp

<x-operations.app title="Communications">
    <x-operations.communications-workspace
        :section="$workspace['section']"
        :list-items="$workspace['list_items']"
        :list-count="$workspace['list_count']"
        :selected="$workspace['selected']"
        :thread="$workspace['thread']"
        :context="$workspace['context']"
        :list-title="$listTitle"
        :list-description="$listDescription"
    />
</x-operations.app>
