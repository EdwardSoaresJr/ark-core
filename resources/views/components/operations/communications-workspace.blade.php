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
    $threadOpen = request()->filled('conversation')
        || request()->filled('lead')
        || request()->filled('call')
        || request()->filled('platform_conversation');
@endphp

<div
    class="d-flex flex-column flex-fill ark-split"
    data-comms-workspace
    x-data="{ threadOpen: {{ $threadOpen ? 'true' : 'false' }}, contextOpen: false }"
>
    <x-tabler.page
        layout="split"
        title="Communications"
        :title-id="$isInbox ? null : 'ops-comms-workspace'"
        :title-count="$isInbox ? null : $listCount"
        :subtitle="$isInbox ? null : $listDescription"
        actions-label="Communications commands"
    >
        @if ($isInbox)
            <x-slot:actions>
                <x-tabler.page-action icon="search" label="Search conversations" data-ops-global-search-open />
                <x-tabler.page-action icon="message" label="Compose" data-ops-global-search-open />
            </x-slot:actions>
        @endif

        {{ $slot }}

        @include('operations.communications.workspace.partials.section-nav', [
            'section' => $section,
            'listFilter' => $listFilter,
            'filterCounts' => $filterCounts,
            'ownerFilter' => $ownerFilter,
            'turnFilter' => $turnFilter,
            'turnCounts' => $turnCounts,
        ])

        <div
            id="ops-comms-workspace-live"
            class="row g-2 g-lg-3"
            data-fragment-url="{{ route('operations.communications.workspace.fragment', array_merge(request()->query(), ['section' => $section])) }}"
            data-poll-signature="{{ $pollSignature ?? '' }}"
            data-section="{{ $section }}"
        >
            <aside
                id="ops-comms-workspace-list"
                class="col-12 col-lg-4 col-xl-3 flex-column"
                :class="threadOpen ? 'd-none d-lg-flex' : 'd-flex'"
                aria-label="{{ $listTitle }}"
            >
                <div class="card flex-fill overflow-hidden">
                    @if (is_array($filters))
                        @include('operations.communications.workspace.partials.history-filters', ['filters' => $filters])
                    @endif
                    <div id="ops-comms-workspace-list-body" class="d-flex flex-column flex-fill overflow-hidden">
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
                    </div>
                    @if ($paginator !== null && $paginator->hasPages())
                        <div class="card-footer py-2 d-flex justify-content-center">
                            {{ $paginator->withQueryString()->links('pagination::simple-bootstrap-5') }}
                        </div>
                    @endif
                </div>
            </aside>

            <section
                id="ops-comms-workspace-thread"
                class="col-12 col-lg flex-column"
                :class="! threadOpen ? 'd-none d-lg-flex' : (contextOpen ? 'd-none d-xl-flex' : 'd-flex')"
                aria-label="Conversation"
            >
                @include('operations.communications.workspace.partials.thread-panel', [
                    'thread' => $thread,
                    'selected' => $selected,
                    'section' => $section,
                    'listFilter' => $listFilter,
                    'ownerFilter' => $ownerFilter,
                ])
            </section>

            <aside
                id="ops-comms-workspace-context"
                class="col-12 col-xl-3 flex-column"
                :class="contextOpen ? 'd-flex' : 'd-none d-xl-flex'"
                aria-label="Customer and work"
            >
                @include('operations.communications.workspace.partials.context-panel', [
                    'context' => $context,
                    'selected' => $selected,
                    'section' => $section,
                ])
            </aside>
        </div>
    </x-tabler.page>
</div>
