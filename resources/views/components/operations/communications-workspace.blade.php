@props([
    'section',
    'listItems' => [],
    'listCount' => 0,
    'selected' => null,
    'thread' => null,
    'context' => null,
    'listTitle' => 'Queue',
    'listDescription' => null,
    'filters' => null,
    'paginator' => null,
    'turnFilter' => null,
    'turnCounts' => null,
    'listFilter' => null,
    'filterCounts' => null,
    'ownerFilter' => 'everyone',
    'ownerCounts' => null,
    'listShown' => null,
    'listTotal' => null,
    'listTruncated' => false,
    'pollSignature' => null,
    'platformBacked' => false,
])

@php
    $isInbox = $section === 'inbox';
@endphp

<section
    @class(['ops-comms-workspace', 'ops-comms-workspace--inbox' => $isInbox])
    @if ($isInbox)
        x-data="{ threadOpen: {{ $selected ? 'true' : 'false' }}, contextOpen: false }"
        :class="{ 'is-thread-open': threadOpen, 'is-context-open': contextOpen }"
    @endif
>
    @unless ($isInbox)
        <x-operations.queue-page-header
            id="ops-comms-workspace"
            title="Communications"
            :description="$listDescription"
            :count="$listCount > 0 ? $listCount : null"
            :show-back="false"
        />
    @endunless

    @if ($isInbox)
        <div class="ops-comms-inbox__chrome">
    @endif
    @include('operations.communications.workspace.partials.section-nav', [
        'section' => $section,
        'listFilter' => $listFilter,
        'filterCounts' => $filterCounts,
        'ownerFilter' => $ownerFilter,
        'turnFilter' => $turnFilter,
        'turnCounts' => $turnCounts,
    ])
    @if ($isInbox)
            <div class="ops-comms-inbox__chrome-tools">
                <button type="button" class="ops-comms-inbox__search-button" data-ops-global-search-open>Search conversations</button>
                <button type="button" class="ops-comms-inbox__compose" data-ops-global-search-open>Compose</button>
            </div>
        </div>
    @endif

    <div
        id="ops-comms-workspace-live"
        @class([
            'ops-comms-workspace__grid',
            'ops-comms-workspace__grid--inbox' => $isInbox,
        ])
        data-fragment-url="{{ route('operations.communications.workspace.fragment', array_merge(request()->query(), ['section' => $section])) }}"
        data-poll-signature="{{ $pollSignature ?? '' }}"
        data-section="{{ $section }}"
    >
        <aside id="ops-comms-workspace-list" class="ops-comms-workspace__list" aria-label="{{ $listTitle }}">
            @if (is_array($filters))
                @include('operations.communications.workspace.partials.history-filters', ['filters' => $filters])
            @endif
            @include('operations.communications.workspace.partials.list-panel', [
                'title' => $listTitle,
                'count' => $listCount,
                'items' => $listItems,
                'selected' => $selected,
                'listFilter' => $listFilter,
                'filterCounts' => $filterCounts,
                'ownerFilter' => $ownerFilter,
                'ownerCounts' => $ownerCounts,
                'listShown' => $listShown,
                'listTotal' => $listTotal,
                'listTruncated' => $listTruncated,
                'section' => $section,
                'platformBacked' => $platformBacked,
            ])
            @if ($paginator !== null)
                <div class="ops-comms-workspace__pagination">
                    {{ $paginator->withQueryString()->links() }}
                </div>
            @endif
        </aside>

        <main id="ops-comms-workspace-thread" class="ops-comms-workspace__thread" aria-label="Conversation">
            @include('operations.communications.workspace.partials.thread-panel', [
                'thread' => $thread,
                'selected' => $selected,
                'section' => $section,
                'listFilter' => $listFilter,
                'ownerFilter' => $ownerFilter,
            ])
        </main>

        <aside id="ops-comms-workspace-context" class="ops-comms-workspace__context" aria-label="Customer and work">
            @include('operations.communications.workspace.partials.context-panel', [
                'context' => $context,
                'selected' => $selected,
                'section' => $section,
            ])
        </aside>
    </div>
</section>
