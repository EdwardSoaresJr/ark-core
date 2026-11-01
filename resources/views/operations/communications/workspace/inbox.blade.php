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
        'waiting' => 'Waiting on the customer - still owned work',
        'resolved' => 'Closed - reopen when they come back',
        'all' => 'Every relationship thread',
        default => 'Who needs the shop right now',
    };
@endphp

<x-operations.app title="Communications" ui="tabler">
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
        :list-shown="$workspace['list_shown'] ?? null"
        :list-total="$workspace['list_total'] ?? null"
        :list-truncated="$workspace['list_truncated'] ?? false"
        :turn-filter="$workspace['turn_filter'] ?? null"
        :turn-counts="$workspace['turn_counts'] ?? null"
        :poll-signature="$workspace['poll_signature'] ?? ''"
        :platform-backed="(bool) ($workspace['platform_backed'] ?? false)"
        :owner-filter="$workspace['owner_filter'] ?? 'everyone'"
        :owner-counts="$workspace['owner_counts'] ?? null"
    >
        @if (filled($workspace['error'] ?? null) || session('error'))
            <div class="alert alert-warning flex-shrink-0 mb-2" role="alert">
                {{ $workspace['error'] ?? session('error') }}
            </div>
        @endif
        @if (session('status'))
            <div class="alert alert-success flex-shrink-0 mb-2" role="status">
                {{ session('status') }}
            </div>
        @endif
    </x-operations.communications-workspace>
</x-operations.app>
