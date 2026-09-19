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
        'waiting' => 'Waiting on the customer — still owned work',
        'resolved' => 'Closed — reopen when they come back',
        'all' => 'Every relationship thread',
        default => 'Who needs the shop right now',
    };
@endphp

<x-operations.app title="Communications">
    @if (filled($workspace['error'] ?? null) || session('error'))
        <p class="mb-3 border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-950">
            {{ $workspace['error'] ?? session('error') }}
        </p>
    @endif
    @if (session('status'))
        <p class="mb-3 border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-900">
            {{ session('status') }}
        </p>
    @endif
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
    />
</x-operations.app>
