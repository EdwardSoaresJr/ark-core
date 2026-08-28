@php
    /** @var array<string, mixed> $workspace */
    $listTitle = 'History';
    $listDescription = 'Past calls and conversations — last 30 days shown, search reaches everything';
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
        :filters="$workspace['filters'] ?? null"
        :paginator="$workspace['paginator'] ?? null"
    />
</x-operations.app>
