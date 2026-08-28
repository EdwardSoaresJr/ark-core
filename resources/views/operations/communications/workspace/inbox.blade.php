@php
    /** @var array<string, mixed> $workspace */
    $listFilter = $workspace['list_filter'] ?? 'needs';
    $listTitle = match ($listFilter) {
        'waiting' => 'Waiting',
        'resolved' => 'Resolved',
        'all' => 'Inbox',
        default => 'Needs attention',
    };
    $listDescription = match ($listFilter) {
        'waiting' => 'Ball is in their court — still one relationship thread',
        'resolved' => 'Closed relationships — reopen when they return',
        'all' => 'Every open relationship — shop-turn first',
        default => 'Who needs the shop right now',
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
        :list-filter="$listFilter"
        :filter-counts="$workspace['filter_counts'] ?? null"
        :turn-filter="$workspace['turn_filter'] ?? null"
        :turn-counts="$workspace['turn_counts'] ?? null"
    />
</x-operations.app>
