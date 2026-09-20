@php
    /** @var array<string, mixed> $workspace */
    $listTitle = match ($workspace['section'] ?? '') {
        'attention' => 'Needs attention',
        'inbox' => 'Inbox',
        'internal' => 'Internal channels',
        default => 'Queue',
    };
    $listDescription = match ($workspace['section'] ?? '') {
        'attention' => 'What needs a reply or follow-up',
        'inbox' => 'conversations, leads, and calls',
        'internal' => 'shop coordination channels',
        default => 'communications',
    };
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
